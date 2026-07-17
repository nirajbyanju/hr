<?php

namespace App\Modules\Tasks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Modules\Tasks\Http\Requests\AddTaskCommentRequest;
use App\Modules\Tasks\Http\Requests\StoreTaskRequest;
use App\Modules\Tasks\Http\Requests\UpdateTaskRequest;
use App\Modules\Tasks\Repositories\TaskAssignmentRepository;
use App\Modules\Tasks\Repositories\TaskRepository;
use App\Modules\Tasks\Services\TaskCommentService;
use App\Modules\Tasks\Services\TaskService;
use App\Modules\Tasks\Services\TaskWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly TaskService $taskService,
        private readonly TaskCommentService $taskCommentService,
        private readonly TaskWorkflowService $taskWorkflowService,
        private readonly TaskAssignmentRepository $taskAssignmentRepository,
    ) {
    }

    public function myDashboard(Request $request): View
    {
        $employeeId = (int) ($request->user()?->employee?->id ?? 0);
        $assignments = $employeeId > 0 ? $this->taskAssignmentRepository->myActiveAssignments($employeeId) : collect();
        $today = now()->toDateString();

        $buckets = [
            'assigned_today' => $assignments->filter(fn ($a) => optional($a->assigned_at)->toDateString() === $today),
            'pending' => $assignments->filter(fn ($a) => in_array($a->status?->code, ['assigned'], true)),
            'in_progress' => $assignments->filter(fn ($a) => in_array($a->status?->code, ['accepted', 'in_progress'], true)),
            'on_hold' => $assignments->filter(fn ($a) => $a->status?->code === 'on_hold'),
            'review' => $assignments->filter(fn ($a) => in_array($a->status?->code, ['under_review', 'changes_requested', 'approved'], true)),
            'completed' => $assignments->filter(fn ($a) => in_array($a->status?->code, ['completed', 'closed'], true)),
            'overdue' => $assignments->filter(fn ($a) => $a->task?->due_date && $a->task->due_date < $today && ! in_array($a->status?->code, ['completed', 'closed'], true)),
        ];

        return view('hr.tasks.my-dashboard', [
            'buckets' => $buckets,
            'upcomingDeadlines' => $assignments->filter(fn ($a) => $a->task?->due_date && $a->task->due_date >= $today)->sortBy(fn ($a) => $a->task->due_date)->take(5),
            'todaysTasks' => $assignments->filter(fn ($a) => $a->task?->due_date === $today),
            'highPriority' => $assignments->filter(fn ($a) => in_array($a->task?->priority?->code, ['critical', 'high'], true)),
            'recentlyUpdated' => $assignments->sortByDesc(fn ($a) => $a->updated_at)->take(5),
        ]);
    }

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'project_id' => (int) $request->input('project_id', 0),
            'category_id' => (int) $request->input('category_id', 0),
            'priority_id' => (int) $request->input('priority_id', 0),
            'status_id' => (int) $request->input('status_id', 0),
            'tag_id' => (int) $request->input('tag_id', 0),
            'assigned_to_employee_id' => (int) $request->input('assigned_to_employee_id', 0),
            'due_from' => (string) $request->input('due_from', ''),
            'due_to' => (string) $request->input('due_to', ''),
            'per_page' => max(10, min(100, (int) $request->input('per_page', 20))),
        ];

        return view('hr.tasks.index', [
            'tasks' => $this->taskRepository->paginate($filters, $request->user()),
            'projects' => $this->taskRepository->listProjects(),
            'employees' => $this->taskRepository->listActiveEmployees(),
            'categories' => $this->taskRepository->listCategories(),
            'priorities' => $this->taskRepository->listPriorities(),
            'statuses' => $this->taskRepository->listStatuses(),
            'tags' => $this->taskRepository->listTags(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('hr.tasks.form', [
            'mode' => 'create',
            'projects' => $this->taskRepository->listProjects(),
            'employees' => $this->taskRepository->listActiveEmployees(),
            'categories' => $this->taskRepository->listCategories(),
            'priorities' => $this->taskRepository->listPriorities(),
            'tags' => $this->taskRepository->listTags(),
            'parentOptions' => $this->taskRepository->listAvailableParents(),
        ]);
    }

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $task = $this->taskService->createTask($request->validated(), $request->user()?->employee?->id, $request->user());

        return redirect()->route('tasks.show', $task)->with('success', __('Task created successfully.'));
    }

    public function show(Task $task): View
    {
        abort_if(! $this->taskRepository->canAccess($task, request()->user()), 403);

        $task = $this->taskRepository->withFullDetails($task);
        $actor = request()->user();
        $myAssignment = $actor
            ? $task->assignments->first(fn ($a) => (int) $a->employee_id === (int) ($actor->employee?->id ?? 0))
            : null;

        return view('hr.tasks.show', [
            'task' => $task,
            'myAssignment' => $myAssignment,
            'myAvailableActions' => $myAssignment && $actor ? $this->taskWorkflowService->availableActions($myAssignment, $actor) : [],
            'isWatching' => $actor?->employee
                ? $task->watchers->contains(fn ($w) => (int) $w->employee_id === (int) $actor->employee->id)
                : false,
            'employees' => $this->taskRepository->listActiveEmployees(),
            'taskOptions' => $this->taskRepository->listAvailableParents($task->id),
        ]);
    }

    public function edit(Task $task): View
    {
        abort_if(! $this->taskRepository->canAccess($task, request()->user()), 403);

        return view('hr.tasks.form', [
            'mode' => 'edit',
            'task' => $task->load('tags:id'),
            'projects' => $this->taskRepository->listProjects(),
            'employees' => $this->taskRepository->listActiveEmployees(),
            'categories' => $this->taskRepository->listCategories(),
            'priorities' => $this->taskRepository->listPriorities(),
            'tags' => $this->taskRepository->listTags(),
            'parentOptions' => $this->taskRepository->listAvailableParents($task->id),
        ]);
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        abort_if(! $this->taskRepository->canAccess($task, $request->user()), 403);

        $this->taskService->updateTask($task, $request->validated(), $request->user());

        return redirect()->route('tasks.show', $task)->with('success', __('Task updated successfully.'));
    }

    public function destroy(Task $task): RedirectResponse
    {
        abort_if(! $this->taskRepository->canAccess($task, request()->user()), 403);

        $this->taskService->deleteTask($task, request()->user());

        return redirect()->route('tasks.index')->with('success', __('Task deleted successfully.'));
    }

    public function addComment(AddTaskCommentRequest $request, Task $task): RedirectResponse
    {
        abort_if(! $this->taskRepository->canAccess($task, $request->user()), 403);

        $validated = $request->validated();
        $this->taskCommentService->create(
            $task,
            $request->user()?->employee?->id,
            (string) $validated['comment'],
            $validated['parent_comment_id'] ?? null,
            $validated['mention_employee_ids'] ?? [],
            $request->user(),
        );

        return redirect()->route('tasks.show', $task)->with('success', __('Task comment added successfully.'));
    }
}
