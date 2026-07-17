<?php

namespace App\Modules\Tasks\Services;

use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskDependencyService
{
    public function __construct(private readonly TaskActivityLogger $activityLogger)
    {
    }

    public function addDependency(Task $task, int $dependsOnTaskId, User $actor): TaskDependency
    {
        if ($dependsOnTaskId === $task->id) {
            throw ValidationException::withMessages(['depends_on_task_id' => 'A task cannot depend on itself.']);
        }

        if ($this->createsCycle($task->id, $dependsOnTaskId)) {
            throw ValidationException::withMessages(['depends_on_task_id' => 'This dependency would create a circular chain of blocked tasks.']);
        }

        return DB::transaction(function () use ($task, $dependsOnTaskId, $actor): TaskDependency {
            $dependency = $task->dependencies()->firstOrCreate(
                ['depends_on_task_id' => $dependsOnTaskId],
                ['created_by' => $actor->id],
            );

            $this->activityLogger->log(
                $task,
                'dependency_added',
                sprintf('%s added a dependency on task #%d', $actor->name, $dependsOnTaskId),
                $actor,
                $dependency,
            );

            return $dependency;
        });
    }

    public function removeDependency(TaskDependency $dependency, User $actor): void
    {
        DB::transaction(function () use ($dependency, $actor): void {
            $task = $dependency->task;
            $dependency->update(['deleted_by' => $actor->id]);
            $dependency->delete();

            $this->activityLogger->log(
                $task,
                'dependency_removed',
                sprintf('%s removed a task dependency', $actor->name),
                $actor,
                null,
            );
        });
    }

    /** Walks the dependency graph starting from $dependsOnTaskId to see if it would eventually loop back to $taskId. */
    private function createsCycle(int $taskId, int $dependsOnTaskId, array $visited = []): bool
    {
        if ($dependsOnTaskId === $taskId) {
            return true;
        }

        if (in_array($dependsOnTaskId, $visited, true)) {
            return false;
        }

        $visited[] = $dependsOnTaskId;

        $nextIds = TaskDependency::query()
            ->where('task_id', $dependsOnTaskId)
            ->pluck('depends_on_task_id');

        foreach ($nextIds as $nextId) {
            if ($this->createsCycle($taskId, (int) $nextId, $visited)) {
                return true;
            }
        }

        return false;
    }
}
