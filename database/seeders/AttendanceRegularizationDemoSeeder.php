<?php

namespace Database\Seeders;

use App\Models\AttendanceLog;
use App\Models\AttendanceRegularization;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Populates the current tenant with pending attendance regularization requests
 * so the Attendance Regularizations page renders like the reference design.
 *
 * Run inside a tenant context:
 *   $company->run(fn () => (new AttendanceRegularizationDemoSeeder())->run());
 */
class AttendanceRegularizationDemoSeeder extends Seeder
{
    private const TARGET = 90;

    /** @var array<int, string> */
    private array $reasons = [
        'System error during clock in/out, actual time was different',
        'Was at client site for meeting, could not access office attendance system',
        'Biometric device malfunction, forgot to punch in/out',
        'Network outage prevented clock-in at the correct time',
        'Attended an off-site training and could not mark attendance',
    ];

    public function run(): void
    {
        if (AttendanceRegularization::query()->exists()) {
            $this->command?->info('Regularization requests already present — skipping.');

            return;
        }

        $employees = Employee::query()->get(['id']);
        if ($employees->isEmpty()) {
            $this->command?->warn('No employees to attach regularization requests to.');

            return;
        }

        $reviewer = User::query()->value('id');
        $created = 0;

        foreach ($employees as $employee) {
            $days = AttendanceLog::query()
                ->where('employee_id', $employee->id)
                ->orderByDesc('attendance_date')
                ->get(['attendance_date', 'check_in_at', 'check_out_at'])
                ->groupBy(fn (AttendanceLog $l): string => Carbon::parse($l->attendance_date)->toDateString());

            $perEmployee = (int) ceil(self::TARGET / max(1, $employees->count()));

            foreach ($days->take($perEmployee) as $date => $logs) {
                if ($created >= self::TARGET) {
                    break 2;
                }

                $in = $logs->pluck('check_in_at')->filter()->min();
                $out = $logs->pluck('check_out_at')->filter()->max();

                $requestedIn = $in ? Carbon::parse($in)->subMinutes(10 + ($created % 5) * 5) : Carbon::parse($date . ' 09:00');
                $requestedOut = $out ? Carbon::parse($out)->addMinutes(15 + ($created % 4) * 10) : Carbon::parse($date . ' 18:00');

                AttendanceRegularization::create([
                    'employee_id' => $employee->id,
                    'attendance_log_id' => $logs->first()->id ?? null,
                    'attendance_date' => $date,
                    'original_check_in_at' => $in ? Carbon::parse($in) : null,
                    'original_check_out_at' => $out ? Carbon::parse($out) : null,
                    'requested_check_in_at' => $requestedIn,
                    'requested_check_out_at' => $requestedOut,
                    'reason' => $this->reasons[$created % count($this->reasons)],
                    'status' => 'pending',
                    'requested_by' => $reviewer,
                ]);

                $created++;
            }
        }

        $this->command?->info($created . ' pending regularization requests are ready.');
    }
}
