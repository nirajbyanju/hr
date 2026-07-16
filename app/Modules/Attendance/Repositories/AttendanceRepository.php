<?php

namespace App\Modules\Attendance\Repositories;

use App\Models\AttendanceLog;
use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
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
        $paginator = AttendanceLog::query()
            ->join('employees', 'employees.id', '=', 'attendance_logs.employee_id')
            ->selectRaw("
                attendance_logs.employee_id,
                attendance_logs.attendance_date,
                MIN(attendance_logs.check_in_at) as first_check_in_at,
                MAX(attendance_logs.check_out_at) as last_check_out_at,
                COUNT(attendance_logs.id) as total_entries,
                employees.employee_code,
                employees.first_name,
                employees.last_name
            ")
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

        $workedMinutes = $this->workedMinutesByAttendance($paginator->getCollection());
        $paginator->setCollection($paginator->getCollection()->map(function (object $row) use ($workedMinutes): object {
            $key = $this->attendanceKey((int) $row->employee_id, (string) $row->attendance_date);
            $row->worked_minutes = $workedMinutes[$key] ?? 0;

            return $row;
        }));

        return $paginator;
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
     * Total only completed check-in/check-out pairs for each attendance day.
     * Breaks between pairs are intentionally excluded.
     *
     * @param SupportCollection<int, object> $attendanceRows
     * @return array<string, int>
     */
    private function workedMinutesByAttendance(SupportCollection $attendanceRows): array
    {
        if ($attendanceRows->isEmpty()) {
            return [];
        }

        $keys = $attendanceRows
            ->mapWithKeys(fn (object $row): array => [$this->attendanceKey((int) $row->employee_id, (string) $row->attendance_date) => true]);

        $logs = AttendanceLog::query()
            ->select(['employee_id', 'attendance_date', 'check_in_at', 'check_out_at'])
            ->whereIn('employee_id', $attendanceRows->pluck('employee_id')->unique()->all())
            ->whereIn('attendance_date', $attendanceRows->pluck('attendance_date')->unique()->all())
            ->orderByRaw('COALESCE(check_in_at, check_out_at)')
            ->get()
            ->filter(fn (AttendanceLog $log): bool => isset($keys[$this->attendanceKey((int) $log->employee_id, (string) $log->attendance_date)]));

        return $logs
            ->groupBy(fn (AttendanceLog $log): string => $this->attendanceKey((int) $log->employee_id, (string) $log->attendance_date))
            ->map(fn (Collection $dayLogs): int => $this->sumCompletedSessions($dayLogs))
            ->all();
    }

    /**
     * @param Collection<int, AttendanceLog> $logs
     */
    private function sumCompletedSessions(Collection $logs): int
    {
        $openCheckIn = null;
        $minutes = 0;

        foreach ($logs as $log) {
            if ($log->check_in_at) {
                $openCheckIn ??= Carbon::parse($log->check_in_at);
                continue;
            }

            if ($log->check_out_at && $openCheckIn !== null) {
                $checkOut = Carbon::parse($log->check_out_at);
                $minutes += max(0, $openCheckIn->diffInMinutes($checkOut, false));
                $openCheckIn = null;
            }
        }

        return $minutes;
    }

    private function attendanceKey(int $employeeId, mixed $attendanceDate): string
    {
        return $employeeId . '|' . Carbon::parse($attendanceDate)->toDateString();
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
     * Count the check-in and check-out entries an employee already has for a given day.
     *
     * @return array{checkins: int, checkouts: int}
     */
    public function dayEntryCounts(int $employeeId, string $attendanceDate): array
    {
        $row = AttendanceLog::query()
            ->where('employee_id', $employeeId)
            ->whereDate('attendance_date', $attendanceDate)
            ->selectRaw('
                COUNT(CASE WHEN check_in_at IS NOT NULL THEN 1 END) as checkins,
                COUNT(CASE WHEN check_out_at IS NOT NULL THEN 1 END) as checkouts
            ')
            ->first();

        return [
            'checkins' => (int) ($row->checkins ?? 0),
            'checkouts' => (int) ($row->checkouts ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): AttendanceLog
    {
        return AttendanceLog::query()->create($attributes);
    }
}
