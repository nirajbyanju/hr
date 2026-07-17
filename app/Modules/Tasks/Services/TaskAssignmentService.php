<?php

namespace App\Modules\Tasks\Services;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TaskAssignmentService
{
    public function __construct(
        private readonly TaskWorkflowService $workflowService,
        private readonly TaskActivityLogger $activityLogger,
    ) {
    }

    /**
     * @param array<int, int> $employeeIds
     * @return Collection<int, TaskAssignment>
     */
    public function assign(Task $task, array $employeeIds, ?int $ownerEmployeeId, User $actor): Collection
    {
        return DB::transaction(function () use ($task, $employeeIds, $ownerEmployeeId, $actor): Collection {
            $assignedStatus = TaskStatus::query()->where('code', 'assigned')->firstOrFail();
            $existingActiveIds = $task->activeAssignments()->pluck('employee_id')->all();
            $created = new Collection();

            foreach (array_unique($employeeIds) as $employeeId) {
                if (in_array($employeeId, $existingActiveIds, true)) {
                    continue;
                }

                $assignment = $task->assignments()->create([
                    'employee_id' => $employeeId,
                    'status_id' => $assignedStatus->id,
                    'is_owner' => $employeeId === $ownerEmployeeId,
                    'assigned_by' => $actor->id,
                    'assigned_at' => now(),
                    'is_active' => true,
                    'created_by' => $actor->id,
                ]);

                $task->statusHistory()->create([
                    'task_assignment_id' => $assignment->id,
                    'from_status_id' => null,
                    'to_status_id' => $assignedStatus->id,
                    'changed_by' => $actor->id,
                    'reason' => null,
                    'changed_at' => now(),
                ]);

                $this->activityLogger->log(
                    $task,
                    'assigned',
                    sprintf('%s assigned the task to %s', $actor->name, $assignment->employee?->fullName() ?: 'an employee'),
                    $actor,
                    $assignment,
                );

                $created->push($assignment);
            }

            if ($ownerEmployeeId !== null && in_array($ownerEmployeeId, $existingActiveIds, true)) {
                $task->activeAssignments()->update(['is_owner' => false]);
                $task->activeAssignments()->where('employee_id', $ownerEmployeeId)->update(['is_owner' => true]);
            }

            $task->update(['is_team_task' => $task->activeAssignments()->count() > 1]);
            $this->workflowService->recomputeTaskStatus($task);

            return $created;
        });
    }

    public function remove(TaskAssignment $assignment, User $actor): void
    {
        DB::transaction(function () use ($assignment, $actor): void {
            $task = $assignment->task;
            $assignment->update(['is_active' => false, 'updated_by' => $actor->id]);
            $assignment->delete();

            $this->activityLogger->log(
                $task,
                'unassigned',
                sprintf('%s removed %s from the task', $actor->name, $assignment->employee?->fullName() ?: 'an employee'),
                $actor,
                $assignment,
            );

            $task->update(['is_team_task' => $task->activeAssignments()->count() > 1]);
            $this->workflowService->recomputeTaskStatus($task);
        });
    }
}
