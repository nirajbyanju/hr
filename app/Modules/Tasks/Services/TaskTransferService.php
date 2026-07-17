<?php

namespace App\Modules\Tasks\Services;

use App\Models\TaskAssignment;
use App\Models\TaskStatus;
use App\Models\TaskTransferRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * User-initiated transfer with accept/reject, distinct from TaskWorkflowService::reassignDirect()
 * (the immediate, no-approval Admin/Super Admin reassignment).
 */
class TaskTransferService
{
    public function __construct(private readonly TaskActivityLogger $activityLogger)
    {
    }

    public function request(TaskAssignment $assignment, int $toEmployeeId, string $reason, User $actor): TaskTransferRequest
    {
        $isSelf = (int) ($actor->employee?->id ?? 0) === (int) $assignment->employee_id;
        $isAdminTier = $actor->hasAnyPermission(['task.delete', 'task.assign', 'task.assign-team']);

        if (! $isSelf && ! $isAdminTier) {
            throw ValidationException::withMessages(['employee_id' => 'You are not allowed to request a transfer for this assignment.']);
        }

        if ($toEmployeeId === (int) $assignment->employee_id) {
            throw ValidationException::withMessages(['employee_id' => 'Choose a different employee to transfer this task to.']);
        }

        return DB::transaction(function () use ($assignment, $toEmployeeId, $reason, $actor): TaskTransferRequest {
            $transfer = TaskTransferRequest::query()->create([
                'task_id' => $assignment->task_id,
                'task_assignment_id' => $assignment->id,
                'from_employee_id' => $assignment->employee_id,
                'to_employee_id' => $toEmployeeId,
                'requested_by' => $actor->id,
                'reason' => $reason,
                'status' => 'pending',
                'created_by' => $actor->id,
            ]);

            $this->activityLogger->log(
                $assignment->task,
                'transfer_requested',
                sprintf('%s requested to transfer this task to another employee', $actor->name),
                $actor,
                $transfer,
            );

            return $transfer;
        });
    }

    public function decide(TaskTransferRequest $transfer, bool $accept, User $actor, ?string $note = null): TaskTransferRequest
    {
        if (! $transfer->isPending()) {
            throw ValidationException::withMessages(['status' => 'This transfer request has already been decided.']);
        }

        $isTarget = (int) ($actor->employee?->id ?? 0) === (int) $transfer->to_employee_id;
        $isAdminTier = $actor->hasAnyPermission(['task.transfer-approve', 'task.delete', 'task.assign-team']);

        if (! $isTarget && ! $isAdminTier) {
            throw ValidationException::withMessages(['status' => 'You are not allowed to decide this transfer request.']);
        }

        return DB::transaction(function () use ($transfer, $accept, $actor, $note): TaskTransferRequest {
            $transfer->update([
                'status' => $accept ? 'accepted' : 'rejected',
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_note' => $note,
                'updated_by' => $actor->id,
            ]);

            if ($accept) {
                $oldAssignment = $transfer->assignment;
                $task = $transfer->task;
                $assignedStatus = TaskStatus::query()->where('code', 'assigned')->firstOrFail();

                $oldAssignment->update(['is_active' => false, 'updated_by' => $actor->id]);

                $newAssignment = $task->assignments()->create([
                    'employee_id' => $transfer->to_employee_id,
                    'status_id' => $assignedStatus->id,
                    'is_owner' => $oldAssignment->is_owner,
                    'assigned_by' => $actor->id,
                    'assigned_at' => now(),
                    'is_active' => true,
                    'created_by' => $actor->id,
                ]);

                $task->statusHistory()->create([
                    'task_assignment_id' => $newAssignment->id,
                    'from_status_id' => $oldAssignment->status_id,
                    'to_status_id' => $assignedStatus->id,
                    'changed_by' => $actor->id,
                    'reason' => 'Transfer accepted',
                    'changed_at' => now(),
                ]);

                $this->activityLogger->log(
                    $task,
                    'transfer_accepted',
                    sprintf('%s accepted the task transfer from %s', $transfer->toEmployee?->fullName() ?: 'employee', $transfer->fromEmployee?->fullName() ?: 'previous assignee'),
                    $actor,
                    $transfer,
                );
            } else {
                $this->activityLogger->log(
                    $transfer->task,
                    'transfer_rejected',
                    sprintf('%s rejected the task transfer; task remains with %s', $transfer->toEmployee?->fullName() ?: 'employee', $transfer->fromEmployee?->fullName() ?: 'current owner'),
                    $actor,
                    $transfer,
                );
            }

            return $transfer->fresh() ?? $transfer;
        });
    }
}
