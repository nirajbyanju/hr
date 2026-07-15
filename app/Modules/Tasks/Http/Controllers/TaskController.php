<?php

namespace App\Modules\Tasks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Modules\Tasks\Http\Requests\AddTaskCommentRequest;
use App\Modules\Tasks\Http\Requests\StoreTaskRequest;
use App\Modules\Tasks\Http\Requests\UpdateTaskRequest;
use App\Modules\Tasks\Http\Requests\UpdateTaskStatusRequest;
use App\Modules\Tasks\Repositories\TaskRepository;
use App\Modules\Tasks\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly TaskService $taskService,
    ) {
    }

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'project_id' => (int) $request->input('project_id', 0),
            'status' => (string) $request->input('status', ''),
            'assigned_to_employee_id' => (int) $request->input('assigned_to_employee_id', 0),
            'per_page' => max(10, min(100, (int) $request->input('per_page', 20))),
        ];

        return view('hr.tasks.index', [
            'tasks' => $this->taskRepository->paginate($filters, $request->user()),
            'projects' => $this->taskRepository->listProjects(),
            'employees' => $this->taskRepository->listActiveEmployees(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('hr.tasks.form', [
            'mode' => 'create',
            'projects' => $this->taskRepository->listProjects(),
            'employees' => $this->taskRepository->listActiveEmployees(),
        ]);
    }

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $this->taskService->createTask($request->validated(), $request->user()?->employee?->id);

        return redirect()->route('tasks.index')->with('success', __('Task created successfully.'));
    }

    public function show(Task $task): View
    {
        abort_if(! $this->taskRepository->canAccess($task, request()->user()), 403);

        return view('hr.tasks.show', [
            'task' => $this->taskRepository->withComments($task),
        ]);
    }

    public function edit(Task $task): View
    {
        abort_if(! $this->taskRepository->canAccess($task, request()->user()), 403);

        return view('hr.tasks.form', [
            'mode' => 'edit',
            'task' => $task,
            'projects' => $this->taskRepository->listProjects(),
            'employees' => $this->taskRepository->listActiveEmployees(),
        ]);
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        abort_if(! $this->taskRepository->canAccess($task, $request->user()), 403);

        $this->taskService->updateTask($task, $request->validated());

        return redirect()->route('tasks.index')->with('success', __('Task updated successfully.'));
    }

    public function destroy(Task $task): RedirectResponse
    {
        abort_if(! $this->taskRepository->canAccess($task, request()->user()), 403);

        $this->taskService->deleteTask($task);

        return redirect()->route('tasks.index')->with('success', __('Task deleted successfully.'));
    }

    public function updateStatus(UpdateTaskStatusRequest $request, Task $task): RedirectResponse
    {
        abort_if(! $this->taskRepository->canAccess($task, $request->user()), 403);

        $validated = $request->validated();
        $this->taskService->updateStatus($task, (string) $validated['status'], (int) ($validated['progress_percent'] ?? (int) $task->progress_percent));

        return redirect()->route('tasks.show', $task)->with('success', __('Task status updated successfully.'));
    }

    public function addComment(AddTaskCommentRequest $request, Task $task): RedirectResponse
    {
        abort_if(! $this->taskRepository->canAccess($task, $request->user()), 403);

        $this->taskService->addComment($task, $request->user()?->employee?->id, (string) $request->validated()['comment']);

        return redirect()->route('tasks.show', $task)->with('success', __('Task comment added successfully.'));
    }
}
