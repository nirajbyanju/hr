<?php

namespace App\Modules\Tasks\Services;

use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Modules\Tasks\Repositories\TaskRepository;
use Illuminate\Support\Facades\DB;

class TaskService
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly TaskAssignmentService $assignmentService,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public function createTask(array $payload, ?int $creatorEmployeeId, User $actor): Task
    {
        return DB::transaction(function () use ($payload, $creatorEmployeeId, $actor): Task {
            $draftStatus = TaskStatus::query()->where('code', 'draft')->firstOrFail();
            $employeeIds = array_values(array_unique(array_map('intval', $payload['employee_ids'] ?? [])));
            $ownerEmployeeId = isset($payload['owner_employee_id']) ? (int) $payload['owner_employee_id'] : null;
            if ($ownerEmployeeId === null && $employeeIds !== []) {
                $ownerEmployeeId = $employeeIds[0];
            }

            $task = $this->taskRepository->create([
                'project_id' => (int) $payload['project_id'],
                'category_id' => $payload['category_id'] ?? null,
                'status_id' => $draftStatus->id,
                'priority_id' => (int) $payload['priority_id'],
                'created_by_employee_id' => $creatorEmployeeId,
                'owner_employee_id' => $ownerEmployeeId,
                'parent_task_id' => $payload['parent_task_id'] ?? null,
                'visibility' => $payload['visibility'] ?? 'public',
                'is_team_task' => count($employeeIds) > 1,
                'title' => $payload['title'],
                'description' => $payload['description'] ?? null,
                'start_date' => $payload['start_date'] ?? null,
                'due_date' => $payload['due_date'] ?? null,
                'progress_percent' => 0,
                'estimated_hours' => $payload['estimated_hours'] ?? null,
                'actual_hours' => $payload['actual_hours'] ?? null,
                'created_by' => $actor->id,
            ]);

            if (! empty($payload['tag_ids'])) {
                $task->tags()->sync(array_map('intval', $payload['tag_ids']));
            }

            if ($employeeIds !== []) {
                $this->assignmentService->assign($task, $employeeIds, $ownerEmployeeId, $actor);
            }

            return $task->fresh() ?? $task;
        });
    }

    /** @param array<string, mixed> $payload */
    public function updateTask(Task $task, array $payload, User $actor): Task
    {
        return DB::transaction(function () use ($task, $payload, $actor): Task {
            $this->taskRepository->update($task, [
                'project_id' => (int) $payload['project_id'],
                'category_id' => $payload['category_id'] ?? null,
                'priority_id' => (int) $payload['priority_id'],
                'parent_task_id' => $payload['parent_task_id'] ?? null,
                'visibility' => $payload['visibility'] ?? $task->visibility,
                'title' => $payload['title'],
                'description' => $payload['description'] ?? null,
                'start_date' => $payload['start_date'] ?? null,
                'due_date' => $payload['due_date'] ?? null,
                'estimated_hours' => $payload['estimated_hours'] ?? null,
                'actual_hours' => $payload['actual_hours'] ?? null,
                'updated_by' => $actor->id,
            ]);

            if (array_key_exists('tag_ids', $payload)) {
                $task->tags()->sync(array_map('intval', $payload['tag_ids'] ?? []));
            }

            return $task->fresh() ?? $task;
        });
    }

    public function deleteTask(Task $task, User $actor): void
    {
        DB::transaction(function () use ($task, $actor): void {
            $task->update(['deleted_by' => $actor->id]);
            $task->comments()->delete();
            $this->taskRepository->delete($task);
        });
    }

    public function addComment(Task $task, ?int $employeeId, string $comment): void
    {
        DB::transaction(function () use ($task, $employeeId, $comment): void {
            $this->taskRepository->addComment((int) $task->id, $employeeId, $comment);
        });
    }
}
