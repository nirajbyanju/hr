<?php

namespace App\Modules\Tasks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TaskAssignment;
use App\Modules\Tasks\Repositories\TaskAssignmentRepository;
use App\Modules\Tasks\Services\TaskWorkflowService;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TaskKanbanController extends Controller
{
    /**
     * Kanban columns per the spec: Backlog/Assigned/In Progress/Hold/Review/Completed.
     * The underlying workflow has finer-grained statuses (11 total); each column groups the
     * ones that visually belong together, but a card can only be dropped on a column whose
     * canonical status is reachable in one legal transition from the card's current status —
     * dragging never bypasses the same permission matrix the status buttons enforce.
     *
     * @var array<string, array{label: string, statuses: array<int, string>, drop_target: ?string, accent: string}>
     */
    private const COLUMNS = [
        'backlog' => ['label' => 'Backlog', 'statuses' => ['draft'], 'drop_target' => null, 'accent' => '#6c757d'],
        'assigned' => ['label' => 'Assigned', 'statuses' => ['assigned', 'accepted', 'rejected'], 'drop_target' => null, 'accent' => '#0d6efd'],
        'in_progress' => ['label' => 'In Progress', 'statuses' => ['in_progress', 'changes_requested'], 'drop_target' => 'in_progress', 'accent' => '#0dcaf0'],
        'hold' => ['label' => 'Hold', 'statuses' => ['on_hold'], 'drop_target' => 'on_hold', 'accent' => '#ffc107'],
        'review' => ['label' => 'Review', 'statuses' => ['under_review', 'approved'], 'drop_target' => 'under_review', 'accent' => '#6f42c1'],
        'completed' => ['label' => 'Completed', 'statuses' => ['completed', 'closed'], 'drop_target' => 'completed', 'accent' => '#198754'],
    ];

    public function __construct(
        private readonly TaskAssignmentRepository $taskAssignmentRepository,
        private readonly TaskWorkflowService $taskWorkflowService,
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $canViewAll = $user?->hasAnyPermission(['task.delete', 'task.assign', 'task.assign-team']) ?? false;
        $employeeId = (int) ($user?->employee?->id ?? 0);

        $assignments = TaskAssignment::query()
            ->with([
                'task:id,title,priority_id,due_date',
                'task.priority:id,name,color',
                'employee:id,employee_code,first_name,last_name',
                'status:id,code,name,color,sort_order',
                'latestStatusHistory',
            ])
            ->where('is_active', true)
            ->when(! $canViewAll, fn ($q) => $q->where('employee_id', $employeeId))
            ->get();

        foreach ($assignments as $assignment) {
            $enteredAt = $assignment->latestStatusHistory?->changed_at
                ?? $assignment->assigned_at
                ?? $assignment->created_at
                ?? now();

            $assignment->stage_entered_at = $enteredAt;
            $assignment->stage_duration_label = $this->formatDuration($enteredAt);
            $assignment->stage_aging_level = $this->agingLevel($enteredAt);
        }

        $columns = [];
        foreach (self::COLUMNS as $key => $column) {
            $columns[$key] = [
                'label' => $column['label'],
                'drop_target' => $column['drop_target'],
                'accent' => $column['accent'],
                'is_terminal' => in_array($key, ['completed', 'backlog'], true),
                'cards' => $assignments
                    ->filter(fn (TaskAssignment $a) => in_array($a->status?->code, $column['statuses'], true))
                    ->sortBy(fn (TaskAssignment $a) => $a->stage_entered_at)
                    ->values(),
            ];
        }

        return view('hr.tasks.kanban', ['columns' => $columns]);
    }

    /**
     * Compact "time in stage" label: "Just now", "42m", "3h 15m", "2d 4h".
     */
    private function formatDuration(CarbonInterface $since): string
    {
        $seconds = max(0, $since->diffInSeconds(now()));

        if ($seconds < 60) {
            return 'Just now';
        }

        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($days > 0) {
            return $hours > 0 ? "{$days}d {$hours}h" : "{$days}d";
        }

        if ($hours > 0) {
            return $minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours}h";
        }

        return "{$minutes}m";
    }

    /**
     * @return 'fresh'|'warning'|'stale'
     */
    private function agingLevel(CarbonInterface $since): string
    {
        $hours = $since->diffInHours(now());

        return match (true) {
            $hours < 24 => 'fresh',
            $hours < 72 => 'warning',
            default => 'stale',
        };
    }

    public function move(Request $request, TaskAssignment $assignment): JsonResponse
    {
        $validated = $request->validate([
            'column' => 'required|string',
            'reason' => 'nullable|string|max:2000',
        ]);

        $column = self::COLUMNS[$validated['column']] ?? null;
        $targetStatus = $column['drop_target'] ?? null;

        if ($targetStatus === null) {
            return response()->json(['message' => 'This column cannot be a drop target.'], 422);
        }

        $actor = $request->user();
        $path = $this->taskWorkflowService->pathToStatus($assignment, $targetStatus, $actor);

        if ($path === null) {
            return response()->json(['message' => 'This move is not allowed from the task\'s current status.'], 422);
        }

        if ($path === []) {
            return response()->json(['message' => 'No change.']);
        }

        $reason = trim((string) ($validated['reason'] ?? ''));

        // Some steps (hold, reopen, review_reject) demand a reason. Tell the board so it can ask
        // for one and retry, rather than failing the drag with a validation error the user can't act on.
        if ($reason === '') {
            foreach ($path as $action) {
                if ($this->taskWorkflowService->requiresReason($action)) {
                    return response()->json([
                        'message' => 'A reason is required for this move.',
                        'reason_required' => true,
                    ], 422);
                }
            }
        }

        try {
            $this->taskWorkflowService->transitionAlongPath($assignment, $path, $actor, ['reason' => $reason !== '' ? $reason : null]);
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first() ?? 'This move is not allowed.'], 422);
        }

        return response()->json([
            'message' => 'Moved successfully.',
            'entered_at' => now()->toIso8601String(),
            'stage_duration_label' => 'Just now',
            'stage_aging_level' => 'fresh',
        ]);
    }
}
