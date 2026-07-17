<?php

namespace App\Modules\Tasks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TaskAssignment;
use App\Modules\Tasks\Repositories\TaskAssignmentRepository;
use App\Modules\Tasks\Services\TaskWorkflowService;
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
     * @var array<string, array{label: string, statuses: array<int, string>, drop_target: ?string}>
     */
    private const COLUMNS = [
        'backlog' => ['label' => 'Backlog', 'statuses' => ['draft'], 'drop_target' => null],
        'assigned' => ['label' => 'Assigned', 'statuses' => ['assigned', 'accepted', 'rejected'], 'drop_target' => null],
        'in_progress' => ['label' => 'In Progress', 'statuses' => ['in_progress', 'changes_requested'], 'drop_target' => 'in_progress'],
        'hold' => ['label' => 'Hold', 'statuses' => ['on_hold'], 'drop_target' => 'on_hold'],
        'review' => ['label' => 'Review', 'statuses' => ['under_review', 'approved'], 'drop_target' => 'under_review'],
        'completed' => ['label' => 'Completed', 'statuses' => ['completed', 'closed'], 'drop_target' => 'completed'],
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
            ->with(['task:id,title,priority_id,due_date', 'task.priority:id,name,color', 'employee:id,employee_code,first_name,last_name', 'status:id,code,name,color,sort_order'])
            ->where('is_active', true)
            ->when(! $canViewAll, fn ($q) => $q->where('employee_id', $employeeId))
            ->get();

        $columns = [];
        foreach (self::COLUMNS as $key => $column) {
            $columns[$key] = [
                'label' => $column['label'],
                'drop_target' => $column['drop_target'],
                'cards' => $assignments->filter(fn (TaskAssignment $a) => in_array($a->status?->code, $column['statuses'], true))->values(),
            ];
        }

        return view('hr.tasks.kanban', ['columns' => $columns]);
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

        return response()->json(['message' => 'Moved successfully.']);
    }
}
