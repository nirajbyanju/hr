<?php

namespace App\Modules\Tasks\Repositories;

use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskComment;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\TaskTag;
use App\Models\TaskWatcher;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class TaskRepository
{
    private const EAGER = [
        'project:id,name,project_code',
        'category:id,name,color',
        'status:id,code,name,color,sort_order',
        'priority:id,code,name,color,level',
        'creator:id,employee_code,first_name,last_name',
        'owner:id,employee_code,first_name,last_name',
        'assignments.employee:id,employee_code,first_name,last_name',
        'assignments.status:id,code,name,color',
        'tags:id,name,color',
    ];

    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters, ?User $user = null): LengthAwarePaginator
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $projectId = (int) ($filters['project_id'] ?? 0);
        $categoryId = (int) ($filters['category_id'] ?? 0);
        $priorityId = (int) ($filters['priority_id'] ?? 0);
        $statusId = (int) ($filters['status_id'] ?? 0);
        $tagId = (int) ($filters['tag_id'] ?? 0);
        $assigneeId = (int) ($filters['assigned_to_employee_id'] ?? 0);
        $dueFrom = (string) ($filters['due_from'] ?? '');
        $dueTo = (string) ($filters['due_to'] ?? '');
        $perPage = max(10, min(100, (int) ($filters['per_page'] ?? 20)));

        return $this->baseQuery($user)
            ->when($q !== '', fn (Builder $query) => $query->where(function (Builder $inner) use ($q): void {
                $inner->where('title', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhere('id', 'like', "%{$q}%");
            }))
            ->when($projectId > 0, fn (Builder $query) => $query->where('project_id', $projectId))
            ->when($categoryId > 0, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->when($priorityId > 0, fn (Builder $query) => $query->where('priority_id', $priorityId))
            ->when($statusId > 0, fn (Builder $query) => $query->where('status_id', $statusId))
            ->when($tagId > 0, fn (Builder $query) => $query->whereHas('tags', fn (Builder $t) => $t->where('task_tags.id', $tagId)))
            ->when($assigneeId > 0, fn (Builder $query) => $query->whereHas(
                'assignments',
                fn (Builder $a) => $a->where('is_active', true)->where('employee_id', $assigneeId)
            ))
            ->when($dueFrom !== '', fn (Builder $query) => $query->whereDate('due_date', '>=', $dueFrom))
            ->when($dueTo !== '', fn (Builder $query) => $query->whereDate('due_date', '<=', $dueTo))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function canAccess(Task $task, ?User $user): bool
    {
        if ($this->canViewAll($user)) {
            return true;
        }

        $employeeId = (int) ($user?->employee?->id ?? 0);
        if ($employeeId <= 0) {
            return false;
        }

        $subordinateIds = $user?->employee?->subordinates()->pluck('id')->map(fn ($id) => (int) $id)->all() ?? [];
        $task->loadMissing(['project', 'assignments' => fn ($q) => $q->where('is_active', true)]);
        $assignedEmployeeIds = $task->assignments->pluck('employee_id')->map(fn ($id) => (int) $id)->all();

        return in_array($employeeId, array_merge($assignedEmployeeIds, $subordinateIds), true)
            || (int) $task->owner_employee_id === $employeeId
            || (int) $task->created_by_employee_id === $employeeId
            || $task->project?->manager_employee_id === $employeeId
            || ($task->project !== null && $task->project->members()->where('employees.id', $employeeId)->exists())
            || TaskWatcher::query()->where('task_id', $task->id)->where('employee_id', $employeeId)->exists();
    }

    public function create(array $attributes): Task
    {
        return Task::query()->create($attributes);
    }

    public function update(Task $task, array $attributes): void
    {
        $task->update($attributes);
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }

    /** @return Collection<int, Project> */
    public function listProjects(): Collection
    {
        return Project::query()->orderBy('name')->get(['id', 'name', 'project_code']);
    }

    /** @return Collection<int, Employee> */
    public function listActiveEmployees(): Collection
    {
        return Employee::query()
            ->where('employment_status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'employee_code', 'first_name', 'last_name']);
    }

    /** @return Collection<int, TaskCategory> */
    public function listCategories(): Collection
    {
        return TaskCategory::query()->where('is_active', true)->orderBy('name')->get();
    }

    /** @return Collection<int, TaskPriority> */
    public function listPriorities(): Collection
    {
        return TaskPriority::query()->where('is_active', true)->orderBy('sort_order')->get();
    }

    /** @return Collection<int, TaskStatus> */
    public function listStatuses(): Collection
    {
        return TaskStatus::query()->where('is_active', true)->orderBy('sort_order')->get();
    }

    /** @return Collection<int, TaskTag> */
    public function listTags(): Collection
    {
        return TaskTag::query()->where('is_active', true)->orderBy('name')->get();
    }

    /** @return Collection<int, Task> */
    public function listAvailableParents(?int $excludingTaskId = null): Collection
    {
        return Task::query()
            ->when($excludingTaskId !== null, fn (Builder $q) => $q->where('id', '!=', $excludingTaskId))
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id', 'title']);
    }

    public function withFullDetails(Task $task): Task
    {
        return $task->load([
            'project:id,name,project_code',
            'category:id,name,color',
            'status:id,code,name,color,sort_order,is_terminal',
            'priority:id,code,name,color,level',
            'creator:id,employee_code,first_name,last_name',
            'owner:id,employee_code,first_name,last_name',
            'parentTask:id,title',
            'childTasks:id,title,parent_task_id,status_id',
            'childTasks.status:id,name,color',
            'assignments' => fn ($q) => $q->where('is_active', true),
            'assignments.employee:id,employee_code,first_name,last_name',
            'assignments.status:id,code,name,color,sort_order',
            'checklists.items',
            'attachments.uploadedBy:id,name',
            'comments' => fn ($q) => $q->whereNull('parent_comment_id')->orderBy('created_at'),
            'comments.employee:id,employee_code,first_name,last_name',
            'comments.replies.employee:id,employee_code,first_name,last_name',
            'comments.attachments',
            'comments.mentions.employee:id,employee_code,first_name,last_name',
            'dependencies.dependsOnTask:id,title,status_id',
            'dependencies.dependsOnTask.status:id,name,color',
            'dependents.task:id,title,status_id',
            'tags:id,name,color',
            'watchers.employee:id,employee_code,first_name,last_name',
            'reviews' => fn ($q) => $q->orderByDesc('submitted_at'),
            'reviews.submittedBy:id,name',
            'reviews.reviewedBy:id,name',
            'transferRequests' => fn ($q) => $q->orderByDesc('created_at'),
            'transferRequests.fromEmployee:id,employee_code,first_name,last_name',
            'transferRequests.toEmployee:id,employee_code,first_name,last_name',
            'activityLogs.causer:id,name',
        ]);
    }

    public function addComment(int $taskId, ?int $employeeId, string $comment): TaskComment
    {
        return TaskComment::query()->create([
            'task_id' => $taskId,
            'employee_id' => $employeeId,
            'comment' => $comment,
        ]);
    }

    private function baseQuery(?User $user): Builder
    {
        return Task::query()
            ->with(self::EAGER)
            ->when(! $this->canViewAll($user), fn (Builder $query) => $this->scopeToUser($query, $user));
    }

    private function canViewAll(?User $user): bool
    {
        return $user?->hasAnyPermission(['task.delete', 'task.assign', 'task.assign-team']) ?? false;
    }

    private function scopeToUser(Builder $query, ?User $user): void
    {
        $employeeId = (int) ($user?->employee?->id ?? 0);
        if ($employeeId <= 0) {
            $query->whereRaw('1 = 0');
            return;
        }

        $subordinateIds = $user?->employee?->subordinates()->pluck('id')->map(fn ($id) => (int) $id)->all() ?? [];
        $visibleEmployeeIds = array_values(array_unique(array_merge([$employeeId], $subordinateIds)));

        $query->where(function (Builder $inner) use ($employeeId, $visibleEmployeeIds): void {
            $inner->whereHas('assignments', fn (Builder $a) => $a->where('is_active', true)->whereIn('employee_id', $visibleEmployeeIds))
                ->orWhere('owner_employee_id', $employeeId)
                ->orWhere('created_by_employee_id', $employeeId)
                ->orWhereHas('project', fn (Builder $projectQuery) => $projectQuery
                    ->where('manager_employee_id', $employeeId)
                    ->orWhereHas('members', fn (Builder $memberQuery) => $memberQuery->where('employees.id', $employeeId)))
                ->orWhereHas('watchers', fn (Builder $w) => $w->where('employee_id', $employeeId));
        });
    }
}
