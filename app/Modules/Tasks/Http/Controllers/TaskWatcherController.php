<?php

namespace App\Modules\Tasks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Modules\Tasks\Repositories\TaskRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskWatcherController extends Controller
{
    public function __construct(private readonly TaskRepository $taskRepository)
    {
    }

    public function store(Request $request, Task $task): RedirectResponse
    {
        abort_if(! $this->taskRepository->canAccess($task, $request->user()), 403);

        $employeeId = $request->user()?->employee?->id;
        abort_unless($employeeId, 403);

        $task->watchers()->firstOrCreate(
            ['employee_id' => $employeeId],
            ['created_by' => $request->user()->id],
        );

        return redirect()->route('tasks.show', $task)->with('success', __('You are now watching this task.'));
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        $employeeId = $request->user()?->employee?->id;
        abort_unless($employeeId, 403);

        $task->watchers()->where('employee_id', $employeeId)->delete();

        return redirect()->route('tasks.show', $task)->with('success', __('You stopped watching this task.'));
    }
}
