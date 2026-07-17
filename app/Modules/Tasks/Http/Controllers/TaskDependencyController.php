<?php

namespace App\Modules\Tasks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Modules\Tasks\Http\Requests\StoreTaskDependencyRequest;
use App\Modules\Tasks\Repositories\TaskRepository;
use App\Modules\Tasks\Services\TaskDependencyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskDependencyController extends Controller
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly TaskDependencyService $taskDependencyService,
    ) {
    }

    public function store(StoreTaskDependencyRequest $request, Task $task): RedirectResponse
    {
        abort_if(! $this->taskRepository->canAccess($task, $request->user()), 403);

        $this->taskDependencyService->addDependency($task, (int) $request->validated()['depends_on_task_id'], $request->user());

        return redirect()->route('tasks.show', $task)->with('success', __('Dependency added successfully.'));
    }

    public function destroy(Request $request, TaskDependency $dependency): RedirectResponse
    {
        $dependency->loadMissing('task');
        abort_if(! $this->taskRepository->canAccess($dependency->task, $request->user()), 403);

        $taskId = $dependency->task_id;
        $this->taskDependencyService->removeDependency($dependency, $request->user());

        return redirect()->route('tasks.show', $taskId)->with('success', __('Dependency removed successfully.'));
    }
}
