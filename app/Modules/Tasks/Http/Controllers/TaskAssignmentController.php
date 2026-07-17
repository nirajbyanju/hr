<?php

namespace App\Modules\Tasks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Modules\Tasks\Http\Requests\AssignTeamMembersRequest;
use App\Modules\Tasks\Http\Requests\TransitionTaskAssignmentRequest;
use App\Modules\Tasks\Repositories\TaskAssignmentRepository;
use App\Modules\Tasks\Repositories\TaskRepository;
use App\Modules\Tasks\Services\TaskAssignmentService;
use App\Modules\Tasks\Services\TaskWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskAssignmentController extends Controller
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly TaskAssignmentRepository $taskAssignmentRepository,
        private readonly TaskAssignmentService $taskAssignmentService,
        private readonly TaskWorkflowService $taskWorkflowService,
    ) {
    }

    public function store(AssignTeamMembersRequest $request, Task $task): RedirectResponse
    {
        abort_unless($request->user()?->hasAnyPermission(['task.assign', 'task.assign-team']), 403);

        $validated = $request->validated();
        $this->taskAssignmentService->assign($task, $validated['employee_ids'], $validated['owner_employee_id'] ?? null, $request->user());

        return redirect()->route('tasks.show', $task)->with('success', __('Team members assigned successfully.'));
    }

    public function transition(TransitionTaskAssignmentRequest $request, TaskAssignment $assignment): RedirectResponse
    {
        abort_if(! $this->taskAssignmentRepository->canAct($assignment, $request->user()), 403);

        $validated = $request->validated();
        $this->taskWorkflowService->transitionAssignment($assignment, (string) $validated['action'], $request->user(), $validated);

        return redirect()->route('tasks.show', $assignment->task_id)->with('success', __('Task status updated successfully.'));
    }

    public function destroy(Request $request, TaskAssignment $assignment): RedirectResponse
    {
        abort_unless($request->user()?->hasAnyPermission(['task.delete', 'task.assign', 'task.assign-team']), 403);

        $taskId = $assignment->task_id;
        $this->taskAssignmentService->remove($assignment, $request->user());

        return redirect()->route('tasks.show', $taskId)->with('success', __('Assignment removed successfully.'));
    }
}
