<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceLog;
use App\Models\AttendancePolicy;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Shift;
use App\Models\SystemSetting;
use App\Models\User;
use App\Modules\Attendance\Services\AttendanceCalendarService;
use Illuminate\Support\Carbon;
use Tests\TenantTestCase;

/**
 * An employee's shift and attendance policy decide when they count as late,
 * early or on overtime. Without an assignment they fall back to the
 * company-wide work window in Settings.
 */
class EmployeeShiftAndPolicyTest extends TenantTestCase
{
    /**
     * @param array<int, string> $permissionSlugs
     */
    private function makeUserWithPermissions(array $permissionSlugs): User
    {
        $user = User::factory()->create(['account_status' => 'active']);
        $role = Role::query()->create(['name' => 'Role ' . $user->id, 'slug' => 'role-' . $user->id]);

        foreach ($permissionSlugs as $slug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'group_name' => 'test']
            );
            $role->permissions()->attach($permission->id);
        }

        $user->roles()->attach($role->id);

        return $user;
    }

    private function companyWindow(): void
    {
        // 09:00–17:00, 8h standard, 15 min grace.
        foreach ([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
            'standard_work_hours' => '8',
            'half_day_hours' => '4',
            'late_grace_minutes' => '15',
            'weekend_days' => 'sat,sun',
        ] as $key => $value) {
            SystemSetting::put($key, $value, ['group_name' => 'attendance']);
        }
        SystemSetting::forgetCache();
    }

    private function makeEmployee(array $attributes = []): Employee
    {
        return Employee::query()->create(array_merge([
            'employee_code' => 'SP-' . fake()->unique()->numerify('#####'),
            'first_name' => 'Shift',
            'last_name' => 'Worker',
            'date_of_joining' => '2026-01-01',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
        ], $attributes));
    }

    /**
     * One row per punch, which is the shape the app writes and the shape
     * sumCompletedSessions() pairs up: a row carrying check_in_at opens a
     * session and a later row carrying check_out_at closes it.
     */
    private function log(Employee $employee, string $date, string $in, string $out): void
    {
        $checkIn = Carbon::parse($date . ' ' . $in . ':00');
        $checkOut = Carbon::parse($date . ' ' . $out . ':00');

        AttendanceLog::query()->create([
            'employee_id' => $employee->id,
            'attendance_date' => $date,
            'check_in_at' => $checkIn,
            'worked_minutes' => 0,
            'status' => 'present',
            'source' => 'manual',
        ]);

        AttendanceLog::query()->create([
            'employee_id' => $employee->id,
            'attendance_date' => $date,
            'check_out_at' => $checkOut,
            'worked_minutes' => (int) $checkIn->diffInMinutes($checkOut),
            'status' => 'present',
            'source' => 'manual',
        ]);
    }

    /** @return array<int, string> the sub-flags on that day's cell */
    private function flagsFor(Employee $employee, string $date): array
    {
        $employee->load(['shift', 'attendancePolicy']);
        $day = (int) Carbon::parse($date)->day;

        $grid = app(AttendanceCalendarService::class)->buildMonthlyGrid(
            collect([$employee]),
            (int) Carbon::parse($date)->year,
            (int) Carbon::parse($date)->month,
        );

        return $grid['rows'][0]['cells'][$day]['subs'] ?? [];
    }

    // ---- form / persistence -------------------------------------------------

    public function test_an_employee_can_be_created_with_a_shift_and_policy(): void
    {
        $admin = $this->makeUserWithPermissions(['employee.create', 'employee.view']);
        $shift = Shift::query()->create([
            'name' => 'Evening', 'start_time' => '14:00', 'end_time' => '22:00', 'status' => 'active',
        ]);
        $policy = AttendancePolicy::query()->create([
            'name' => 'Strict', 'late_arrival_grace_minutes' => 5, 'status' => 'active',
        ]);

        $this->actingAs($admin)->post('/employees', [
            'first_name' => 'Gita',
            'last_name' => 'Sharma',
            'date_of_joining' => '2026-07-01',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
            'shift_id' => $shift->id,
            'attendance_policy_id' => $policy->id,
        ])->assertSessionHasNoErrors();

        $employee = Employee::query()->where('first_name', 'Gita')->firstOrFail();
        $this->assertSame($shift->id, $employee->shift_id);
        $this->assertSame($policy->id, $employee->attendance_policy_id);
        $this->assertSame('Evening', $employee->shift->name);
        $this->assertSame('Strict', $employee->attendancePolicy->name);
    }

    public function test_shift_and_policy_are_optional(): void
    {
        $admin = $this->makeUserWithPermissions(['employee.create', 'employee.view']);

        $this->actingAs($admin)->post('/employees', [
            'first_name' => 'Nomad',
            'last_name' => 'Worker',
            'date_of_joining' => '2026-07-01',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
        ])->assertSessionHasNoErrors();

        $employee = Employee::query()->where('first_name', 'Nomad')->firstOrFail();
        $this->assertNull($employee->shift_id);
        $this->assertNull($employee->attendance_policy_id);
    }

    public function test_an_unknown_shift_is_rejected(): void
    {
        $admin = $this->makeUserWithPermissions(['employee.create']);

        $this->actingAs($admin)->post('/employees', [
            'first_name' => 'Bad',
            'last_name' => 'Shift',
            'date_of_joining' => '2026-07-01',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
            'shift_id' => 999999,
        ])->assertSessionHasErrors('shift_id');
    }

    // ---- the assignment actually drives attendance --------------------------

    public function test_without_a_shift_the_company_window_decides_lateness(): void
    {
        $this->companyWindow();
        $employee = $this->makeEmployee();

        // 09:30 is past the 09:00 + 15 min company grace.
        $this->log($employee, '2026-07-06', '09:30', '17:00');

        $this->assertContains('late', $this->flagsFor($employee, '2026-07-06'));
    }

    public function test_an_afternoon_shift_is_not_late_at_its_own_start(): void
    {
        $this->companyWindow();
        $shift = Shift::query()->create([
            'name' => 'Afternoon', 'start_time' => '14:00', 'end_time' => '22:00', 'status' => 'active',
        ]);
        $employee = $this->makeEmployee(['shift_id' => $shift->id]);

        // 14:05 is five hours past the company start, but on time for this shift.
        $this->log($employee, '2026-07-06', '14:05', '22:00');

        $this->assertNotContains('late', $this->flagsFor($employee, '2026-07-06'));
    }

    public function test_a_policy_grace_period_overrides_the_company_one(): void
    {
        $this->companyWindow();
        $policy = AttendancePolicy::query()->create([
            'name' => 'Strict', 'late_arrival_grace_minutes' => 5, 'status' => 'active',
        ]);
        $employee = $this->makeEmployee(['attendance_policy_id' => $policy->id]);

        // 09:10 is inside the company's 15 min grace but outside this policy's 5.
        $this->log($employee, '2026-07-06', '09:10', '17:00');

        $this->assertContains('late', $this->flagsFor($employee, '2026-07-06'));
    }

    public function test_an_early_departure_grace_suppresses_the_early_flag(): void
    {
        $this->companyWindow();
        $policy = AttendancePolicy::query()->create([
            'name' => 'Lenient',
            'late_arrival_grace_minutes' => 15,
            'early_departure_grace_minutes' => 20,
            'status' => 'active',
        ]);
        $employee = $this->makeEmployee(['attendance_policy_id' => $policy->id]);

        // Out at 16:50 — ten minutes early, inside the twenty minute tolerance.
        $this->log($employee, '2026-07-06', '09:00', '16:50');

        $this->assertNotContains('early', $this->flagsFor($employee, '2026-07-06'));
    }

    public function test_a_night_shift_is_not_flagged_early_every_day(): void
    {
        $this->companyWindow();
        $shift = Shift::query()->create([
            'name' => 'Night', 'start_time' => '22:00', 'end_time' => '06:00',
            'is_night_shift' => true, 'status' => 'active',
        ]);
        $employee = $this->makeEmployee(['shift_id' => $shift->id]);

        // Clocking out at 06:00 has a lower minute-of-day than the 06:00 end
        // only because the shift wraps midnight; it must not read as early.
        $this->log($employee, '2026-07-06', '22:00', '23:30');

        $this->assertNotContains('early', $this->flagsFor($employee, '2026-07-06'));
    }

    public function test_a_shorter_shift_reaches_overtime_sooner(): void
    {
        $this->companyWindow();
        $shift = Shift::query()->create([
            'name' => 'Six Hour', 'start_time' => '09:00', 'end_time' => '15:00', 'status' => 'active',
        ]);
        $employee = $this->makeEmployee(['shift_id' => $shift->id]);

        // Seven hours worked: overtime against a six hour shift, but under the
        // company's eight hour standard.
        $this->log($employee, '2026-07-06', '09:00', '16:00');

        $this->assertContains('overtime', $this->flagsFor($employee, '2026-07-06'));
    }
}
