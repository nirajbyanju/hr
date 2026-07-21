<?php

namespace Database\Seeders;

use App\Models\AttendanceLog;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\SalaryGrade;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Populates the current tenant with demo employees and a month of varied
 * attendance so the Attendance Records grid renders like the reference design:
 * a mix of present / late / early / half-day / absent days, a couple of
 * approved leaves and one mid-month holiday.
 *
 * Run inside a tenant context, e.g.
 *   $company->run(fn () => (new AttendanceDemoSeeder())->run());
 *
 * Idempotent-ish: it only seeds employees when the tenant has fewer than the
 * demo set, and skips a day it has already generated for an employee.
 */
class AttendanceDemoSeeder extends Seeder
{
    private const CODE_PREFIX = 'ATT-';

    /** @var array<int, array{name:string, gender:string, dept:string, title:string, profile:string}> */
    private array $people = [
        ['name' => 'Rowland Murphy',    'gender' => 'male',   'dept' => 'ADMIN', 'title' => 'System Administrator', 'profile' => 'ontime'],
        ['name' => 'Antwon Schaefer',   'gender' => 'male',   'dept' => 'IT',    'title' => 'Software Developer',   'profile' => 'late'],
        ['name' => 'Amos Hills',        'gender' => 'male',   'dept' => 'HR',    'title' => 'HR Manager',           'profile' => 'ontime'],
        ['name' => 'Caitlyn Hermiston', 'gender' => 'female', 'dept' => 'IT',    'title' => 'IT Manager',           'profile' => 'early'],
        ['name' => 'Juana Stehr',       'gender' => 'female', 'dept' => 'IT',    'title' => 'IT Manager',           'profile' => 'mixed'],
        ['name' => 'Rae McGlynn',       'gender' => 'female', 'dept' => 'IT',    'title' => 'IT Manager',           'profile' => 'leave'],
        ['name' => 'Helena Murray',     'gender' => 'female', 'dept' => 'FIN',   'title' => 'Financial Analyst',    'profile' => 'ontime'],
        ['name' => 'Antonetta Gleason', 'gender' => 'female', 'dept' => 'HR',    'title' => 'HR Manager',           'profile' => 'mixed'],
        ['name' => 'Cecelia Harvey',    'gender' => 'female', 'dept' => 'IT',    'title' => 'IT Manager',           'profile' => 'ontime'],
        ['name' => 'Hyman Bradtke',     'gender' => 'male',   'dept' => 'HR',    'title' => 'Recruiter',            'profile' => 'absentee'],
    ];

    public function run(): void
    {
        $grade = SalaryGrade::query()->first();

        $employees = [];
        foreach ($this->people as $i => $person) {
            $employees[] = $this->makeEmployee($i, $person, $grade);
        }

        $month = CarbonImmutable::now()->startOfMonth();
        $this->seedHoliday($month);
        $this->seedAttendance($employees, $month);

        $this->command?->info(count($employees) . ' demo employees and attendance for ' . $month->format('F Y') . ' are ready.');
    }

    private function makeEmployee(int $index, array $person, ?SalaryGrade $grade): Employee
    {
        $code = self::CODE_PREFIX . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
        [$first, $last] = array_pad(explode(' ', $person['name'], 2), 2, '');

        $department = Department::query()->where('code', $person['dept'])->first()
            ?? Department::query()->first();
        $designation = Designation::query()->where('name', $person['title'])->first()
            ?? Designation::query()->where('department_id', $department?->id)->first()
            ?? Designation::query()->first();

        return Employee::query()->updateOrCreate(
            ['employee_code' => $code],
            [
                'first_name' => $first,
                'last_name' => $last,
                'gender' => $person['gender'],
                'work_email' => strtolower($first . '.' . $last) . '@demo.local',
                'date_of_joining' => CarbonImmutable::now()->subMonths(10)->toDateString(),
                'employment_type' => 'full_time',
                'employment_status' => 'active',
                'department_id' => $department?->id,
                'designation_id' => $designation?->id,
                'salary_grade_id' => $grade?->id,
            ]
        );
    }

    private function seedHoliday(CarbonImmutable $month): void
    {
        // A single mid-month holiday on a weekday, so a whole column reads as holiday.
        $date = $month->addDays(9);
        while (in_array((int) $date->dayOfWeek, [0, 6], true)) {
            $date = $date->addDay();
        }

        Holiday::query()->firstOrCreate(
            ['title' => 'Company Foundation Day', 'holiday_date' => $date->toDateString()],
            ['holiday_type' => 'company']
        );
    }

    /**
     * @param  array<int, Employee>  $employees
     */
    private function seedAttendance(array $employees, CarbonImmutable $month): void
    {
        $today = CarbonImmutable::today();
        $end = $month->endOfMonth();

        foreach ($employees as $employee) {
            $leaveDays = $this->maybeLeave($employee, $month);

            for ($date = $month; $date->lte($end) && $date->lte($today); $date = $date->addDay()) {
                // Weekends stay empty (the grid renders them as Day Off).
                if (in_array((int) $date->dayOfWeek, [0, 6], true)) {
                    continue;
                }

                if (in_array($date->toDateString(), $leaveDays, true)) {
                    continue; // covered by an approved leave, no attendance row
                }

                $plan = $this->planForDay($employee, $date);
                if ($plan === null) {
                    continue; // absent — no rows, past working day => "not added"
                }

                $this->writeDay($employee->id, $date, $plan[0], $plan[1]);
            }
        }
    }

    /**
     * @return array{0:string,1:string}|null  [checkInTime, checkOutTime] or null for absent
     */
    private function planForDay(Employee $employee, CarbonImmutable $date): ?array
    {
        $profile = $this->people[$this->indexFor($employee)]['profile'] ?? 'ontime';
        $seed = crc32($employee->id . $date->toDateString());
        $roll = $seed % 100;

        return match ($profile) {
            'late' => $roll < 15 ? null : ($roll < 55 ? ['09:34', '17:12'] : ['09:47', '17:20']),
            'early' => $roll < 10 ? null : ($roll < 50 ? ['09:03', '15:40'] : ['09:05', '16:10']),
            'absentee' => $roll < 30 ? null : ['09:12', '17:05'],
            'mixed' => match (true) {
                $roll < 12 => null,                       // absent
                $roll < 24 => ['09:02', '12:20'],         // half day
                $roll < 40 => ['09:36', '17:10'],         // late
                $roll < 52 => ['09:00', '15:50'],         // early
                default => ['08:58', '17:35'],            // present (+ overtime sometimes)
            },
            'leave' => $roll < 8 ? null : ['09:04', '17:08'],
            default => $roll < 5 ? null : ['08:57', $roll < 30 ? '18:05' : '17:06'],
        };
    }

    /**
     * Give some employees one short approved leave block this month.
     *
     * @return array<int, string> leave dates as 'Y-m-d'
     */
    private function maybeLeave(Employee $employee, CarbonImmutable $month): array
    {
        $profile = $this->people[$this->indexFor($employee)]['profile'] ?? '';
        if ($profile !== 'leave') {
            return [];
        }

        $category = DB::table('leave_categories')->value('id');
        if ($category === null) {
            return []; // no leave categories seeded — skip, employee just shows present
        }

        $start = $month->addDays(14);
        while (in_array((int) $start->dayOfWeek, [0, 6], true)) {
            $start = $start->addDay();
        }
        $endLeave = $start->addDays(2);

        LeaveApplication::query()->updateOrCreate(
            ['employee_id' => $employee->id, 'start_date' => $start->toDateString()],
            [
                'leave_category_id' => $category,
                'end_date' => $endLeave->toDateString(),
                'total_days' => 3,
                'is_half_day' => false,
                'status' => 'approved',
                'reason' => 'Demo approved leave',
            ]
        );

        $days = [];
        for ($d = $start; $d->lte($endLeave); $d = $d->addDay()) {
            $days[] = $d->toDateString();
        }

        return $days;
    }

    private function writeDay(int $employeeId, CarbonImmutable $date, string $checkIn, string $checkOut): void
    {
        $already = AttendanceLog::query()
            ->where('employee_id', $employeeId)
            ->whereDate('attendance_date', $date->toDateString())
            ->exists();

        if ($already) {
            return;
        }

        AttendanceLog::query()->insert([
            [
                'employee_id' => $employeeId,
                'attendance_date' => $date->toDateString(),
                'check_in_at' => $date->toDateString() . ' ' . $checkIn . ':00',
                'check_out_at' => null,
                'worked_minutes' => 0,
                'status' => 'present',
                'source' => 'demo-checkin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'employee_id' => $employeeId,
                'attendance_date' => $date->toDateString(),
                'check_in_at' => null,
                'check_out_at' => $date->toDateString() . ' ' . $checkOut . ':00',
                'worked_minutes' => 0,
                'status' => 'present',
                'source' => 'demo-checkout',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    private function indexFor(Employee $employee): int
    {
        $code = (int) substr((string) $employee->employee_code, strlen(self::CODE_PREFIX));

        return max(0, $code - 1);
    }
}
