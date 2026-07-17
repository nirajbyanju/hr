<?php

namespace App\Modules\Tasks\Repositories;

use App\Models\TaskTransferRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TaskTransferRepository
{
    private const EAGER = [
        'task:id,title',
        'fromEmployee:id,employee_code,first_name,last_name',
        'toEmployee:id,employee_code,first_name,last_name',
        'requestedBy:id,name',
        'decidedBy:id,name',
    ];

    /** All transfer requests, for the Admin transfer log. */
    public function paginateAll(int $perPage = 20): LengthAwarePaginator
    {
        return TaskTransferRequest::query()
            ->with(self::EAGER)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @return Collection<int, TaskTransferRequest> */
    public function pendingForEmployee(int $employeeId): Collection
    {
        return TaskTransferRequest::query()
            ->with(self::EAGER)
            ->where('to_employee_id', $employeeId)
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->get();
    }
}
