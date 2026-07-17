<?php

namespace App\Modules\Tasks\Services;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskDependency;
use App\Models\TaskReview;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single code path enforcing the task status permission matrix, reused by the status
 * form, the Kanban drag handler, transfer acceptance, review decisions, and reopen — so a
 * request can never reach an illegal state no matter which UI triggered it.
 */
class TaskWorkflowService
{
    /**
     * action => [from status codes, to status code, required permission slug (null = self-or-admin-tier), reason_required]
     *
     * @var array<string, array{from: array<int, string>, to: string, permission: ?string, reason_required: bool, owner_only: bool}>
     */
    private const TRANSITIONS = [
        'accept' => ['from' => ['assigned'], 'to' => 'accepted', 'permission' => null, 'reason_required' => false, 'owner_only' => false],
        'reject' => ['from' => ['assigned'], 'to' => 'rejected', 'permission' => null, 'reason_required' => false, 'owner_only' => false],
        'start' => ['from' => ['accepted'], 'to' => 'in_progress', 'permission' => null, 'reason_required' => false, 'owner_only' => false],
        'hold' => ['from' => ['in_progress'], 'to' => 'on_hold', 'permission' => null, 'reason_required' => true, 'owner_only' => false],
        'resume' => ['from' => ['on_hold', 'changes_requested'], 'to' => 'in_progress', 'permission' => null, 'reason_required' => false, 'owner_only' => false],
        'submit_review' => ['from' => ['in_progress', 'on_hold'], 'to' => 'under_review', 'permission' => null, 'reason_required' => false, 'owner_only' => false],
        'review_approve' => ['from' => ['under_review'], 'to' => 'approved', 'permission' => 'task.review-approve', 'reason_required' => false, 'owner_only' => false],
        'review_reject' => ['from' => ['under_review'], 'to' => 'changes_requested', 'permission' => 'task.review-reject', 'reason_required' => true, 'owner_only' => false],
        'complete' => ['from' => ['approved'], 'to' => 'completed', 'permission' => null, 'reason_required' => false, 'owner_only' => true],
        'close' => ['from' => ['completed'], 'to' => 'closed', 'permission' => 'task.close', 'reason_required' => false, 'owner_only' => false],
        'reopen' => ['from' => ['completed', 'closed'], 'to' => 'in_progress', 'permission' => 'task.reopen', 'reason_required' => true, 'owner_only' => false],
    ];

    /** Permission slugs that indicate Admin/Super Admin-tier (or delegated team-lead) authority to act on any assignment. */
    private const ADMIN_TIER_PERMISSIONS = ['task.delete', 'task.assign', 'task.assign-team'];

    public function __construct(private readonly TaskActivityLogger $activityLogger)
    {
    }

    public function availableActions(TaskAssignment $assignment, User $actor): array
    {
        return array_values(array_filter(
            array_keys(self::TRANSITIONS),
            fn (string $action) => $this->canPerform($action, $assignment, $actor)
        ));
    }

    public function requiresReason(string $action): bool
    {
        return self::TRANSITIONS[$action]['reason_required'] ?? false;
    }

    /**
     * Used by the Kanban board. A board column groups several workflow statuses, so dragging a
     * card between two columns often spans more than one transition — "Assigned" to "In Progress"
     * is accept + start, "Review" to "Completed" is review_approve + complete. This walks the
     * transition graph for the shortest chain of individually-permitted actions that reaches
     * $targetStatusCode, so the board stays usable without ever loosening the permission matrix:
     * every step still has to pass canPerform(), and each one is re-checked again on execution.
     *
     * @return array<int, string>|null Ordered action names ([] when already there, null when unreachable).
     */
    public function pathToStatus(TaskAssignment $assignment, string $targetStatusCode, User $actor, int $maxSteps = 3): ?array
    {
        $assignment->loadMissing('status');
        $startCode = (string) ($assignment->status?->code ?? '');

        if ($startCode === $targetStatusCode) {
            return [];
        }

        /** @var array<int, array{0: string, 1: array<int, string>}> $queue */
        $queue = [[$startCode, []]];
        $visited = [$startCode => true];

        while ($queue !== []) {
            [$code, $path] = array_shift($queue);

            if (count($path) >= $maxSteps) {
                continue;
            }

            foreach (self::TRANSITIONS as $action => $rule) {
                if (! in_array($code, $rule['from'], true)) {
                    continue;
                }

                if (! $this->canPerformFrom($code, $action, $assignment, $actor)) {
                    continue;
                }

                $nextPath = [...$path, $action];

                if ($rule['to'] === $targetStatusCode) {
                    return $nextPath;
                }

                if (isset($visited[$rule['to']])) {
                    continue;
                }

                $visited[$rule['to']] = true;
                $queue[] = [$rule['to'], $nextPath];
            }
        }

        return null;
    }

    /**
     * Applies a chain from pathToStatus() atomically, so a multi-step drag can never leave the
     * assignment stranded halfway if a later step's guard rejects it.
     *
     * @param array<int, string> $path
     * @param array{reason?: ?string} $payload
     */
    public function transitionAlongPath(TaskAssignment $assignment, array $path, User $actor, array $payload = []): TaskAssignment
    {
        return DB::transaction(function () use ($assignment, $path, $actor, $payload): TaskAssignment {
            $current = $assignment;

            foreach ($path as $action) {
                $stepPayload = $payload;
                if (! $this->requiresReason($action)) {
                    unset($stepPayload['reason']);
                }

                $current = $this->transitionAssignment(
                    $current->fresh(['status', 'task', 'employee']) ?? $current,
                    $action,
                    $actor,
                    $stepPayload,
                );
            }

            return $current;
        });
    }

    public function canPerform(string $action, TaskAssignment $assignment, User $actor): bool
    {
        $assignment->loadMissing('status');

        return $this->canPerformFrom((string) ($assignment->status?->code ?? ''), $action, $assignment, $actor);
    }

    private function canPerformFrom(string $fromStatusCode, string $action, TaskAssignment $assignment, User $actor): bool
    {
        $rule = self::TRANSITIONS[$action] ?? null;
        if ($rule === null) {
            return false;
        }

        if (! in_array($fromStatusCode, $rule['from'], true)) {
            return false;
        }

        if ($rule['owner_only']) {
            $isOwnerActor = $assignment->is_owner && $this->isSelfAssignee($assignment, $actor);

            return $isOwnerActor || $this->isAdminTier($actor);
        }

        if ($rule['permission'] !== null) {
            return $actor->hasPermission($rule['permission']) || $this->isAdminTier($actor);
        }

        return $this->isSelfAssignee($assignment, $actor) || $this->isAdminTier($actor);
    }

    /** @param array{reason?: ?string, progress_percent?: ?int} $payload */
    public function transitionAssignment(TaskAssignment $assignment, string $action, User $actor, array $payload = []): TaskAssignment
    {
        $rule = self::TRANSITIONS[$action] ?? null;
        if ($rule === null) {
            throw ValidationException::withMessages(['action' => "Unknown task action \"{$action}\"."]);
        }

        $assignment->loadMissing(['status', 'task']);
        $reason = isset($payload['reason']) ? trim((string) $payload['reason']) : null;

        if (! $this->canPerform($action, $assignment, $actor)) {
            throw ValidationException::withMessages(['action' => 'You are not allowed to perform this action on this task.']);
        }

        if ($rule['reason_required'] && $reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required for this action.']);
        }

        if ($action === 'complete' && $this->hasBlockingDependencies($assignment->task)) {
            throw ValidationException::withMessages(['action' => 'This task cannot be completed while it has incomplete blocking dependencies.']);
        }

        return DB::transaction(function () use ($assignment, $action, $actor, $rule, $reason, $payload): TaskAssignment {
            $toStatus = TaskStatus::query()->where('code', $rule['to'])->firstOrFail();
            $fromStatusId = $assignment->status_id;
            $fromStatusName = $assignment->status?->name ?? 'n/a';

            $updates = ['status_id' => $toStatus->id, 'updated_by' => $actor->id];

            if ($action === 'accept') {
                $updates['accepted_at'] = now();
            } elseif ($action === 'start') {
                $updates['started_at'] = now();
            } elseif ($action === 'complete') {
                $updates['completed_at'] = now();
                $updates['progress_percent'] = 100;
            } elseif ($action === 'reopen') {
                $updates['completed_at'] = null;
            }

            if (array_key_exists('progress_percent', $payload) && $payload['progress_percent'] !== null) {
                $updates['progress_percent'] = max(0, min(100, (int) $payload['progress_percent']));
            }

            $assignment->update($updates);

            $assignment->task->statusHistory()->create([
                'task_assignment_id' => $assignment->id,
                'from_status_id' => $fromStatusId,
                'to_status_id' => $toStatus->id,
                'changed_by' => $actor->id,
                'reason' => $reason !== '' ? $reason : null,
                'changed_at' => now(),
            ]);

            if ($action === 'submit_review') {
                TaskReview::query()->create([
                    'task_id' => $assignment->task_id,
                    'task_assignment_id' => $assignment->id,
                    'submitted_by' => $actor->id,
                    'submitted_at' => now(),
                ]);
            } elseif (in_array($action, ['review_approve', 'review_reject'], true)) {
                $review = TaskReview::query()
                    ->where('task_id', $assignment->task_id)
                    ->whereNull('outcome')
                    ->latest('submitted_at')
                    ->first();

                $review?->update([
                    'reviewed_by' => $actor->id,
                    'reviewed_at' => now(),
                    'outcome' => $action === 'review_approve' ? 'approved' : 'changes_requested',
                    'review_notes' => $reason !== '' ? $reason : null,
                ]);
            }

            $this->activityLogger->log(
                $assignment->task,
                'status_changed',
                sprintf(
                    '%s changed %s\'s status from %s to %s%s',
                    $actor->name,
                    $assignment->employee?->fullName() ?: 'assignee',
                    $fromStatusName,
                    $toStatus->name,
                    $reason !== null && $reason !== '' ? " ({$reason})" : ''
                ),
                $actor,
                $assignment,
            );

            $this->recomputeTaskStatus($assignment->task);

            return $assignment->fresh(['status', 'employee']) ?? $assignment;
        });
    }

    /**
     * Admin/Super Admin (or a role holding task.assign-team) reassigning a task directly,
     * bypassing the user-initiated accept/reject transfer-request loop entirely.
     */
    public function reassignDirect(Task $task, TaskAssignment $assignment, int $newEmployeeId, User $actor): TaskAssignment
    {
        if (! $this->isAdminTier($actor)) {
            throw ValidationException::withMessages(['employee_id' => 'You are not allowed to reassign this task directly.']);
        }

        return DB::transaction(function () use ($task, $assignment, $newEmployeeId, $actor): TaskAssignment {
            $assignedStatus = TaskStatus::query()->where('code', 'assigned')->firstOrFail();

            $assignment->update(['is_active' => false, 'updated_by' => $actor->id]);

            $newAssignment = $task->assignments()->create([
                'employee_id' => $newEmployeeId,
                'status_id' => $assignedStatus->id,
                'is_owner' => $assignment->is_owner,
                'assigned_by' => $actor->id,
                'assigned_at' => now(),
                'is_active' => true,
                'created_by' => $actor->id,
            ]);

            $task->statusHistory()->create([
                'task_assignment_id' => $newAssignment->id,
                'from_status_id' => $assignment->status_id,
                'to_status_id' => $assignedStatus->id,
                'changed_by' => $actor->id,
                'reason' => 'Reassigned directly by ' . $actor->name,
                'changed_at' => now(),
            ]);

            $this->activityLogger->log(
                $task,
                'reassigned',
                sprintf('%s reassigned the task from %s to %s', $actor->name, $assignment->employee?->fullName() ?: 'previous assignee', $newAssignment->employee?->fullName() ?: 'new assignee'),
                $actor,
                $newAssignment,
            );

            $this->recomputeTaskStatus($task);

            return $newAssignment;
        });
    }

    public function recomputeTaskStatus(Task $task): void
    {
        $activeAssignments = $task->activeAssignments()->with('status')->get();
        $nonRejected = $activeAssignments->filter(fn (TaskAssignment $a) => $a->status?->code !== 'rejected');

        $newStatusCode = match (true) {
            $activeAssignments->isEmpty() => 'draft',
            $nonRejected->isEmpty() => 'assigned',
            default => $nonRejected->sortBy(fn (TaskAssignment $a) => $a->status?->sort_order ?? 0)->first()->status?->code ?? 'assigned',
        };

        $newStatus = TaskStatus::query()->where('code', $newStatusCode)->first();
        if ($newStatus !== null && $newStatus->id !== $task->status_id) {
            $task->update(['status_id' => $newStatus->id]);
        }

        $this->recomputeTaskProgress($task);
    }

    /** Task-level progress auto-calculates from the shared checklist; per-assignee progress stays manual. */
    public function recomputeTaskProgress(Task $task): void
    {
        $items = $task->checklists()->with('items')->get()->flatMap->items;
        if ($items->isEmpty()) {
            return;
        }

        $percent = (int) round(($items->where('is_checked', true)->count() / $items->count()) * 100);
        if ($percent !== (int) $task->progress_percent) {
            $task->update(['progress_percent' => $percent]);
        }
    }

    public function hasBlockingDependencies(Task $task): bool
    {
        return TaskDependency::query()
            ->where('task_id', $task->id)
            ->whereHas('dependsOnTask.status', fn ($q) => $q->whereNotIn('code', ['completed', 'closed']))
            ->exists();
    }

    private function isSelfAssignee(TaskAssignment $assignment, User $actor): bool
    {
        return $actor->hasPermission('task.advance-status')
            && (int) ($actor->employee?->id ?? 0) === (int) $assignment->employee_id;
    }

    private function isAdminTier(User $actor): bool
    {
        return $actor->hasAnyPermission(self::ADMIN_TIER_PERMISSIONS);
    }
}
