<?php

namespace App\Modules\Tasks\Services;

use App\Models\Task;
use App\Modules\Tasks\Repositories\TaskRepository;
use Illuminate\Support\Facades\DB;

class TaskService
{
    public function __construct(private readonly TaskRepository $taskRepository)
    {
    }

    /** @param array<string, mixed> $payload */
    public function createTask(array $payload, ?int $creatorEmployeeId): Task
    {
        return DB::transaction(function () use ($payload, $creatorEmployeeId): Task {
            return $this->taskRepository->create([
                'project_id' => (int) $payload['project_id'],
                'created_by_employee_id' => $creatorEmployeeId,
                'assigned_to_employee_id' => $payload['assigned_to_employee_id'] ?? null,
                'title' => $payload['title'],
                'description' => $payload['description'] ?? null,
                'priority' => $payload['priority'],
                'status' => $payload['status'],
                'start_date' => $payload['start_date'] ?? null,
                'due_date' => $payload['due_date'] ?? null,
                'progress_percent' => (int) ($payload['progress_percent'] ?? 0),
                'estimated_hours' => $payload['estimated_hours'] ?? null,
                'actual_hours' => $payload['actual_hours'] ?? null,
            ]);
        });
    }

    /** @param array<string, mixed> $payload */
    public function updateTask(Task $task, array $payload): Task
    {
        return DB::transaction(function () use ($task, $payload): Task {
            $this->taskRepository->update($task, [
                'project_id' => (int) $payload['project_id'],
                'assigned_to_employee_id' => $payload['assigned_to_employee_id'] ?? null,
                'title' => $payload['title'],
                'description' => $payload['description'] ?? null,
                'priority' => $payload['priority'],
                'status' => $payload['status'],
                'start_date' => $payload['start_date'] ?? null,
                'due_date' => $payload['due_date'] ?? null,
                'progress_percent' => (int) ($payload['progress_percent'] ?? 0),
                'estimated_hours' => $payload['estimated_hours'] ?? null,
                'actual_hours' => $payload['actual_hours'] ?? null,
            ]);

            return $task->fresh() ?? $task;
        });
    }

    public function deleteTask(Task $task): void
    {
        DB::transaction(function () use ($task): void {
            $task->comments()->delete();
            $this->taskRepository->delete($task);
        });
    }

    public function updateStatus(Task $task, string $status, int $progressPercent): Task
    {
        return DB::transaction(function () use ($task, $status, $progressPercent): Task {
            $this->taskRepository->update($task, [
                'status' => $status,
                'progress_percent' => $progressPercent,
            ]);

            return $task->fresh() ?? $task;
        });
    }

    public function addComment(Task $task, ?int $employeeId, string $comment): void
    {
        DB::transaction(function () use ($task, $employeeId, $comment): void {
            $this->taskRepository->addComment((int) $task->id, $employeeId, $comment);
        });
    }
}
