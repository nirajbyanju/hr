<?php

namespace App\Modules\Tasks\Repositories;

use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class TaskAssignmentRepository
{
    public function canAct(TaskAssignment $assignment, ?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $employeeId = (int) ($user->employee?->id ?? 0);
        if ($employeeId > 0 && $employeeId === (int) $assignment->employee_id) {
            return true;
        }

        return $user->hasAnyPermission(['task.delete', 'task.assign', 'task.assign-team']);
    }

    /** @return Collection<int, TaskAssignment> */
    public function myActiveAssignments(int $employeeId): Collection
    {
        return TaskAssignment::query()
            ->with(['task:id,title,due_date,priority_id', 'task.priority:id,name,color', 'status:id,code,name,color'])
            ->where('employee_id', $employeeId)
            ->where('is_active', true)
            ->get();
    }
}
