<?php

namespace App\Modules\Attendance\Services;

use App\Models\AttendanceLog;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Builds the monthly attendance grid: one row per employee, one cell per day of
 * the month, each cell a single derived state plus optional sub-markers.
 *
 * The stored data is sparse — attendance_logs only ever records "present"
 * check-in/out events — so every other state (absent, on-leave, holiday, day
 * off, future, not-added) and every sub-marker (late, early, overtime, half) is
 * derived here from the logs, the holiday and leave tables, the weekend setting
 * and the configurable work window.
 */
class AttendanceCalendarService
{
    /** Cell states, most-significant first — this is also the precedence order. */
    public const STATE_FUTURE = 'future';
    public const STATE_HOLIDAY = 'holiday';
    public const STATE_DAY_OFF = 'day_off';
    public const STATE_ON_LEAVE = 'on_leave';
    public const STATE_HALF_DAY = 'half_day';
    public const STATE_PRESENT = 'present';
    public const STATE_NOT_ADDED = 'not_added';
    public const STATE_ABSENT = 'absent';
    public const STATE_BLANK = 'blank';

    /**
     * @param  Collection<int, \App\Models\Employee>  $employees
     * @return array{
     *     year:int, month:int, days:array<int, array{day:int,weekday:string,weekend:bool}>,
     *     rows:array<int, array{employee:\App\Models\Employee, cells:array<int, array>, total:array{present:float,working:int}}>
     * }
     */
    public function buildMonthlyGrid(Collection $employees, int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $end = $start->endOfMonth();
        $today = CarbonImmutable::today();
        $daysInMonth = $start->daysInMonth;

        $companyWindow = $this->workWindow();
        $weekendIndexes = $this->weekendDayIndexes();
        $employeeIds = $employees->pluck('id')->all();

        $holidays = $this->holidayDates($start, $end);
        $leaves = $this->leavesByEmployee($employeeIds, $start, $end);
        $workedByKey = $this->workedMinutesByKey($employeeIds, $start, $end);
        $firstInByKey = $this->firstCheckInByKey($employeeIds, $start, $end);
        $lastOutByKey = $this->lastCheckOutByKey($employeeIds, $start, $end);

        // Column headers (day number + short weekday + weekend flag).
        $days = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = $start->addDays($d - 1);
            $days[$d] = [
                'day' => $d,
                'weekday' => $date->isoFormat('ddd'),
                'weekend' => in_array((int) $date->dayOfWeek, $weekendIndexes, true),
            ];
        }

        $rows = [];
        foreach ($employees as $employee) {
            $joined = $employee->date_of_joining
                ? CarbonImmutable::parse($employee->date_of_joining)->startOfDay()
                : null;
            $left = $employee->termination_date
                ? CarbonImmutable::parse($employee->termination_date)->startOfDay()
                : null;

            // An employee on their own shift / policy is measured against it;
            // everyone else against the company-wide window from Settings.
            $window = $this->workWindowFor($employee, $companyWindow);

            $cells = [];
            $presentDays = 0.0;
            $workingDays = 0;

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $date = $start->addDays($d - 1);
                $iso = $date->toDateString();
                $isWeekend = in_array((int) $date->dayOfWeek, $weekendIndexes, true);
                $isHoliday = isset($holidays[$iso]);
                $isWorkingDay = ! $isWeekend && ! $isHoliday;

                if ($isWorkingDay) {
                    $workingDays++;
                }

                $cells[$d] = $this->cellFor(
                    $employee->id,
                    $date,
                    $iso,
                    $today,
                    $joined,
                    $left,
                    $isWeekend,
                    $isHoliday,
                    $leaves,
                    $workedByKey,
                    $firstInByKey,
                    $lastOutByKey,
                    $window,
                    $presentDays,
                );
            }

            $rows[] = [
                'employee' => $employee,
                'cells' => $cells,
                'total' => ['present' => $presentDays, 'working' => $workingDays],
            ];
        }

        return ['year' => $year, 'month' => $month, 'days' => $days, 'rows' => $rows];
    }

    /**
     * Resolve a single day's cell and, for present/half days, add to the running
     * present-day total (full = 1, half = 0.5).
     *
     * @param  array{start:int,end:int,standard:int,half:int,grace:int,early_grace:int,night:bool}  $window
     */
    private function cellFor(
        int $employeeId,
        CarbonImmutable $date,
        string $iso,
        CarbonImmutable $today,
        ?CarbonImmutable $joined,
        ?CarbonImmutable $left,
        bool $isWeekend,
        bool $isHoliday,
        array $leaves,
        array $workedByKey,
        array $firstInByKey,
        array $lastOutByKey,
        array $window,
        float &$presentDays,
    ): array {
        // Outside employment window — nothing to show.
        if (($joined !== null && $date->lt($joined)) || ($left !== null && $date->gt($left))) {
            return $this->cell(self::STATE_BLANK);
        }

        if ($date->gt($today)) {
            return $this->cell(self::STATE_FUTURE);
        }

        if ($isHoliday) {
            return $this->cell(self::STATE_HOLIDAY);
        }

        if ($isWeekend) {
            return $this->cell(self::STATE_DAY_OFF);
        }

        $leave = $this->leaveFor($leaves, $employeeId, $date);
        if ($leave !== null) {
            if ($leave['half']) {
                $presentDays += 0.5;

                return $this->cell(self::STATE_HALF_DAY, ['leave'], __('Half-day leave'));
            }

            return $this->cell(self::STATE_ON_LEAVE, [], __('On leave'));
        }

        $key = $employeeId . '|' . $iso;
        $hasLog = array_key_exists($key, $firstInByKey) || array_key_exists($key, $lastOutByKey);

        if ($hasLog) {
            $worked = $workedByKey[$key] ?? 0;
            $subs = [];

            $firstIn = $firstInByKey[$key] ?? null;
            $lastOut = $lastOutByKey[$key] ?? null;

            if ($firstIn !== null && $this->minutesOfDay($firstIn) > $window['start'] + $window['grace']) {
                $subs[] = 'late';
            }
            // A night shift ends on the following calendar day, so comparing a
            // clock-out's minute-of-day against the shift end would mark every
            // one of them early. Those are judged on hours worked instead.
            if (! $window['night']
                && $lastOut !== null
                && $this->minutesOfDay($lastOut) < $window['end'] - $window['early_grace']) {
                $subs[] = 'early';
            }
            if ($worked > $window['standard']) {
                $subs[] = 'overtime';
            }

            // A short-but-nonzero day reads as half; anything else is a full present day.
            if ($worked > 0 && $worked < $window['half']) {
                $presentDays += 0.5;

                return $this->cell(self::STATE_HALF_DAY, $subs, __('Half day'));
            }

            $presentDays += 1.0;

            return $this->cell(self::STATE_PRESENT, $subs, __('Present'));
        }

        // A past working day with no record: distinct from a confirmed absence.
        return $this->cell(self::STATE_NOT_ADDED, [], __('Attendance not added'));
    }

    /**
     * @param  array<int, string>  $subs
     * @return array{state:string, subs:array<int,string>, title:string}
     */
    private function cell(string $state, array $subs = [], ?string $title = null): array
    {
        return ['state' => $state, 'subs' => $subs, 'title' => $title ?? ''];
    }

    /**
     * @param  array<int, array<int, array{start:CarbonImmutable,end:CarbonImmutable,half:bool}>>  $leaves
     * @return array{half:bool}|null
     */
    private function leaveFor(array $leaves, int $employeeId, CarbonImmutable $date): ?array
    {
        foreach ($leaves[$employeeId] ?? [] as $leave) {
            if ($date->betweenIncluded($leave['start'], $leave['end'])) {
                return ['half' => $leave['half'] && $leave['start']->isSameDay($leave['end'])];
            }
        }

        return null;
    }

    /** @return array<string, true> holiday dates keyed 'Y-m-d' */
    private function holidayDates(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return Holiday::query()
            ->whereBetween('holiday_date', [$start->toDateString(), $end->toDateString()])
            ->pluck('holiday_date')
            ->mapWithKeys(fn ($d): array => [Carbon::parse($d)->toDateString() => true])
            ->all();
    }

    /**
     * @param  array<int, int>  $employeeIds
     * @return array<int, array<int, array{start:CarbonImmutable,end:CarbonImmutable,half:bool}>>
     */
    private function leavesByEmployee(array $employeeIds, CarbonImmutable $start, CarbonImmutable $end): array
    {
        if ($employeeIds === []) {
            return [];
        }

        return LeaveApplication::query()
            ->where('status', 'approved')
            ->whereIn('employee_id', $employeeIds)
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->get(['employee_id', 'start_date', 'end_date', 'is_half_day'])
            ->groupBy('employee_id')
            ->map(fn (Collection $rows): array => $rows->map(fn ($r): array => [
                'start' => CarbonImmutable::parse($r->start_date)->startOfDay(),
                'end' => CarbonImmutable::parse($r->end_date)->startOfDay(),
                'half' => (bool) $r->is_half_day,
            ])->all())
            ->all();
    }

    /**
     * Worked minutes per employee-day, pairing check-ins with check-outs the same
     * way AttendanceRepository does.
     *
     * @param  array<int, int>  $employeeIds
     * @return array<string, int>
     */
    private function workedMinutesByKey(array $employeeIds, CarbonImmutable $start, CarbonImmutable $end): array
    {
        if ($employeeIds === []) {
            return [];
        }

        return AttendanceLog::query()
            ->select(['employee_id', 'attendance_date', 'check_in_at', 'check_out_at'])
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->orderByRaw('COALESCE(check_in_at, check_out_at)')
            ->get()
            ->groupBy(fn (AttendanceLog $log): string => $log->employee_id . '|' . Carbon::parse($log->attendance_date)->toDateString())
            ->map(fn (Collection $logs): int => $this->sumCompletedSessions($logs))
            ->all();
    }

    /** @param array<int,int> $employeeIds @return array<string, Carbon> */
    private function firstCheckInByKey(array $employeeIds, CarbonImmutable $start, CarbonImmutable $end): array
    {
        return $this->extremeTimeByKey($employeeIds, $start, $end, 'check_in_at', true);
    }

    /** @param array<int,int> $employeeIds @return array<string, Carbon> */
    private function lastCheckOutByKey(array $employeeIds, CarbonImmutable $start, CarbonImmutable $end): array
    {
        return $this->extremeTimeByKey($employeeIds, $start, $end, 'check_out_at', false);
    }

    /**
     * Earliest check-in (or latest check-out) per employee-day.
     *
     * @param  array<int, int>  $employeeIds
     * @return array<string, Carbon>
     */
    private function extremeTimeByKey(array $employeeIds, CarbonImmutable $start, CarbonImmutable $end, string $column, bool $earliest): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $result = [];
        AttendanceLog::query()
            ->select(['employee_id', 'attendance_date', $column])
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull($column)
            ->get()
            ->each(function (AttendanceLog $log) use (&$result, $column, $earliest): void {
                $key = $log->employee_id . '|' . Carbon::parse($log->attendance_date)->toDateString();
                $time = Carbon::parse($log->{$column});

                if (! isset($result[$key])) {
                    $result[$key] = $time;

                    return;
                }

                $result[$key] = ($earliest ? $time->lt($result[$key]) : $time->gt($result[$key]))
                    ? $time
                    : $result[$key];
            });

        return $result;
    }

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
                $minutes += max(0, $openCheckIn->diffInMinutes(Carbon::parse($log->check_out_at), false));
                $openCheckIn = null;
            }
        }

        return $minutes;
    }

    private function minutesOfDay(Carbon $time): int
    {
        return $time->hour * 60 + $time->minute;
    }

    /** @return array{start:int,end:int,standard:int,half:int,grace:int,early_grace:int,night:bool} minutes */
    private function workWindow(): array
    {
        return [
            'start' => $this->timeToMinutes((string) SystemSetting::getValue('work_start_time', '09:00')),
            'end' => $this->timeToMinutes((string) SystemSetting::getValue('work_end_time', '17:00')),
            'standard' => (int) round(((float) SystemSetting::getValue('standard_work_hours', 8)) * 60),
            'half' => (int) round(((float) SystemSetting::getValue('half_day_hours', 4)) * 60),
            'grace' => (int) SystemSetting::getValue('late_grace_minutes', 15),
            'early_grace' => 0,
            'night' => false,
        ];
    }

    /**
     * The company window, overridden by whatever the employee has been assigned.
     *
     * Shift decides the hours (when the day starts and ends, and how long a full
     * day is); policy decides the tolerances (how late is late, how early is
     * early). Either may be absent, and each falls back independently — an
     * employee can be on a night shift under the default grace, or on the
     * standard hours under a stricter policy.
     *
     * @param  array{start:int,end:int,standard:int,half:int,grace:int,early_grace:int,night:bool}  $companyWindow
     * @return array{start:int,end:int,standard:int,half:int,grace:int,early_grace:int,night:bool}
     */
    private function workWindowFor(\App\Models\Employee $employee, array $companyWindow): array
    {
        $window = $companyWindow;
        $shift = $employee->shift;

        if ($shift !== null) {
            $window['start'] = $this->timeToMinutes((string) $shift->start_time);
            $window['end'] = $this->timeToMinutes((string) $shift->end_time);
            $window['standard'] = (int) round($shift->workingHours() * 60);
            $window['night'] = (bool) $shift->is_night_shift || $window['end'] <= $window['start'];

            // The shift's own grace applies unless a policy overrides it below.
            if ((int) $shift->grace_period_minutes > 0) {
                $window['grace'] = (int) $shift->grace_period_minutes;
            }
        }

        $policy = $employee->attendancePolicy;

        if ($policy !== null) {
            $window['grace'] = (int) $policy->late_arrival_grace_minutes;
            $window['early_grace'] = (int) $policy->early_departure_grace_minutes;
        }

        return $window;
    }

    private function timeToMinutes(string $time): int
    {
        [$h, $m] = array_pad(array_map('intval', explode(':', $time)), 2, 0);

        return $h * 60 + $m;
    }

    /** @return array<int, int> weekday indexes (0=Sun … 6=Sat) */
    private function weekendDayIndexes(): array
    {
        $configured = (string) SystemSetting::getValue('weekend_days', 'sat,sun');
        $map = ['sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6];

        $indexes = [];
        foreach (explode(',', $configured) as $token) {
            $token = strtolower(trim($token));
            if (array_key_exists($token, $map)) {
                $indexes[] = $map[$token];
            }
        }

        $indexes = array_values(array_unique($indexes));

        return $indexes === [] ? [0, 6] : $indexes;
    }
}
