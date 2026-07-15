<?php

namespace App\Modules\Attendance\Repositories;

use App\Models\AttendanceLog;
use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class AttendanceRepository
{
    /**
     * @param array<string, mixed> $filters
     * @param array<int, int>|null $employeeIds
     */
    public function paginateSummary(array $filters, ?array $employeeIds = null): LengthAwarePaginator
    {
            $fromDate = (string) ($filters['from_date'] ?? '');
            $toDate = (string) ($filters['to_date'] ?? '');
        $employeeId = (int) ($filters['employee_id'] ?? 0);
        $perPage = max(10, min(100, (int) ($filters['per_page'] ?? 20)));

        return AttendanceLog::query()
            ->join('employees', 'employees.id', '=', 'attendance_logs.employee_id')
            ->selectRaw('
                attendance_logs.employee_id,
                attendance_logs.attendance_date,
                MIN(attendance_logs.check_in_at) as first_check_in_at,
                MAX(attendance_logs.check_out_at) as last_check_out_at,
                TIMESTAMPDIFF(MINUTE, MIN(attendance_logs.check_in_at), MAX(attendance_logs.check_out_at)) as stay_minutes,
                COUNT(attendance_logs.id) as total_entries,
                employees.employee_code,
                employees.first_name,
                employees.last_name
            ')
            ->when($employeeIds !== null, fn ($query) => $query->whereIn('attendance_logs.employee_id', $employeeIds))
            ->when($employeeId > 0, fn ($query) => $query->where('attendance_logs.employee_id', $employeeId))
            ->when($fromDate !== '', fn ($query) => $query->whereDate('attendance_logs.attendance_date', '>=', $fromDate))
            ->when($toDate !== '', fn ($query) => $query->whereDate('attendance_logs.attendance_date', '<=', $toDate))
            ->groupBy([
                'attendance_logs.employee_id',
                'attendance_logs.attendance_date',
                'employees.employee_code',
                'employees.first_name',
                'employees.last_name',
            ])
            ->orderByDesc('attendance_logs.attendance_date')
            ->orderBy('employees.first_name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<int, int>|null $employeeIds
     * @return SupportCollection<int, object>
     */
    public function listSummaryForExport(array $filters, ?array $employeeIds = null): SupportCollection
    {
        $fromDate = (string) ($filters['from_date'] ?? '');
        $toDate = (string) ($filters['to_date'] ?? '');
         $employeeId = (int) ($filters['employee_id'] ?? 0);
        // Retrieve a summary of attendance logs for export, applying the same filters as the paginated version but without pagination, and returning a collection of results.
        return AttendanceLog::query()
            ->join('employees', 'employees.id', '=', 'attendance_logs.employee_id')
            ->selectRaw('
                attendance_logs.employee_id,
                attendance_logs.attendance_date,
                MIN(attendance_logs.check_in_at) as first_check_in_at,
                MAX(attendance_logs.check_out_at) as last_check_out_at,
                COUNT(attendance_logs.id) as total_entries,
                employees.employee_code,
                employees.first_name,
                employees.last_name
            ')
            ->when($employeeIds !== null, fn ($query) => $query->whereIn('attendance_logs.employee_id', $employeeIds))
              ->when($employeeId > 0, fn ($query) => $query->where('attendance_logs.employee_id', $employeeId))
              ->when($fromDate !== '', fn ($query) => $query->whereDate('attendance_logs.attendance_date', '>=', $fromDate))
            ->when($toDate !== '', fn ($query) => $query->whereDate('attendance_logs.attendance_date', '<=', $toDate))
            ->groupBy([
                'attendance_logs.employee_id',
                'attendance_logs.attendance_date',
                'employees.employee_code',
                'employees.first_name',
                'employees.last_name',
            ])
            ->orderByDesc('attendance_logs.attendance_date')
            ->orderBy('employees.first_name')
            ->get();
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<int, int>|null $employeeIds
     * @return SupportCollection<int, object>
     */
    public function listRawLogsForExport(array $filters, ?array $employeeIds = null): SupportCollection
    {
        $fromDate = (string) ($filters['from_date'] ?? '');
        $toDate = (string) ($filters['to_date'] ?? '');
        $employeeId = (int) ($filters['employee_id'] ?? 0);

        return AttendanceLog::query()
            ->join('employees', 'employees.id', '=', 'attendance_logs.employee_id')
            ->select([
                'attendance_logs.id',
                'attendance_logs.employee_id',
                'attendance_logs.attendance_date',
                'attendance_logs.check_in_at',
                'attendance_logs.check_out_at',
                'attendance_logs.status',
                'attendance_logs.source',
                'attendance_logs.remarks',
                'attendance_logs.created_at',
                'employees.employee_code',
                'employees.first_name',
                'employees.last_name',
            ])
            ->when($employeeIds !== null, fn ($query) => $query->whereIn('attendance_logs.employee_id', $employeeIds))
            ->when($employeeId > 0, fn ($query) => $query->where('attendance_logs.employee_id', $employeeId))
            ->when($fromDate !== '', fn ($query) => $query->whereDate('attendance_logs.attendance_date', '>=', $fromDate))
            ->when($toDate !== '', fn ($query) => $query->whereDate('attendance_logs.attendance_date', '<=', $toDate))
            ->orderByDesc('attendance_logs.attendance_date')
            ->orderBy('employees.first_name')
            ->orderBy('attendance_logs.created_at')
            ->get();
    }

    /**
     * @param array<int, int>|null $employeeIds
     * @return Collection<int, Employee>
     */
    public function listEmployeesForScope(?array $employeeIds = null): Collection
    {
         return Employee::query()
                ->select(['id', 'employee_code', 'first_name', 'last_name'])
                ->when($employeeIds !== null, fn ($query) => $query->whereIn('id', $employeeIds))
                ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): AttendanceLog
    {
        return AttendanceLog::query()->create($attributes);
    }
}
