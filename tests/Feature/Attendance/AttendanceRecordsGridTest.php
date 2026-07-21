<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Attendance\Services\AttendanceCalendarService as Cal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Tests\TenantTestCase;

/**
 * The monthly attendance grid: each cell's derived state and the per-employee
 * total. Uses a fixed month in the past so "future" and "not-added" are stable.
 */
class AttendanceRecordsGridTest extends TenantTestCase
{
    private CarbonImmutable $month;

    protected function setUp(): void
    {
        parent::setUp();
        // A fully-past month so no cell is "future".
        $this->month = CarbonImmutable::today()->subMonthNoOverflow()->startOfMonth();
    }

    private function employee(string $code): Employee
    {
        return Employee::forceCreate([
            'employee_code' => $code,
            'first_name' => 'Grid',
            'last_name' => $code,
            'gender' => 'male',
            'date_of_joining' => $this->month->subYear()->toDateString(),
            'employment_status' => 'active',
        ]);
    }

    /** A weekday in the month, offset working days from the 1st. */
    private function weekday(int $nth): CarbonImmutable
    {
        $date = $this->month;
        $count = 0;
        while (true) {
            if (! in_array((int) $date->dayOfWeek, [0, 6], true)) {
                if (++$count === $nth) {
                    return $date;
                }
            }
            $date = $date->addDay();
        }
    }

    private function checkIn(int $employeeId, CarbonImmutable $date, string $in, string $out): void
    {
        AttendanceLog::insert([
            ['employee_id' => $employeeId, 'attendance_date' => $date->toDateString(), 'check_in_at' => $date->toDateString() . " $in:00", 'check_out_at' => null, 'worked_minutes' => 0, 'status' => 'present', 'source' => 'test', 'created_at' => now(), 'updated_at' => now()],
            ['employee_id' => $employeeId, 'attendance_date' => $date->toDateString(), 'check_in_at' => null, 'check_out_at' => $date->toDateString() . " $out:00", 'worked_minutes' => 0, 'status' => 'present', 'source' => 'test', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_the_grid_derives_each_cell_state(): void
    {
        $emp = $this->employee('GRID-1');

        $present = $this->weekday(1);
        $late = $this->weekday(2);
        $half = $this->weekday(3);
        // weekday(4) intentionally left with no record => not_added

        $this->checkIn($emp->id, $present, '08:58', '17:10');   // on time
        $this->checkIn($emp->id, $late, '09:40', '17:05');      // after 09:00 + 15m grace
        $this->checkIn($emp->id, $half, '09:00', '12:00');      // 3h < 4h half-day threshold

        $holiday = $this->weekday(5);
        Holiday::forceCreate(['title' => 'Test Holiday', 'holiday_date' => $holiday->toDateString(), 'holiday_type' => 'company']);

        $grid = app(Cal::class)->buildMonthlyGrid(collect([$emp]), $this->month->year, $this->month->month);
        $cells = $grid['rows'][0]['cells'];

        $this->assertSame(Cal::STATE_PRESENT, $cells[$present->day]['state']);
        $this->assertSame(Cal::STATE_PRESENT, $cells[$late->day]['state']);
        $this->assertContains('late', $cells[$late->day]['subs']);
        $this->assertSame(Cal::STATE_HALF_DAY, $cells[$half->day]['state']);
        $this->assertSame(Cal::STATE_NOT_ADDED, $cells[$this->weekday(4)->day]['state']);
        $this->assertSame(Cal::STATE_HOLIDAY, $cells[$holiday->day]['state']);

        // A weekend column is a day off.
        $saturday = $this->month;
        while ((int) $saturday->dayOfWeek !== 6) {
            $saturday = $saturday->addDay();
        }
        $this->assertSame(Cal::STATE_DAY_OFF, $cells[$saturday->day]['state']);
    }

    public function test_an_approved_leave_shows_on_leave(): void
    {
        $emp = $this->employee('GRID-2');
        $day = $this->weekday(2);
        $category = \App\Models\LeaveCategory::query()->value('id')
            ?? \App\Models\LeaveCategory::forceCreate(['name' => 'Annual Test', 'code' => 'ANNT'])->id;

        LeaveApplication::forceCreate([
            'employee_id' => $emp->id,
            'leave_category_id' => $category,
            'start_date' => $day->toDateString(),
            'end_date' => $day->addDays(2)->toDateString(),
            'total_days' => 3,
            'is_half_day' => false,
            'status' => 'approved',
            'reason' => 'test',
        ]);

        $grid = app(Cal::class)->buildMonthlyGrid(collect([$emp]), $this->month->year, $this->month->month);

        $this->assertSame(Cal::STATE_ON_LEAVE, $grid['rows'][0]['cells'][$day->day]['state']);
    }

    public function test_total_denominator_is_the_months_working_days(): void
    {
        $emp = $this->employee('GRID-3');
        $this->checkIn($emp->id, $this->weekday(1), '09:00', '17:05'); // 1 present day

        $grid = app(Cal::class)->buildMonthlyGrid(collect([$emp]), $this->month->year, $this->month->month);
        $total = $grid['rows'][0]['total'];

        // Denominator excludes weekends; with no holidays it's the weekday count.
        $weekdays = 0;
        for ($d = $this->month; $d->lte($this->month->endOfMonth()); $d = $d->addDay()) {
            if (! in_array((int) $d->dayOfWeek, [0, 6], true)) {
                $weekdays++;
            }
        }
        $this->assertSame($weekdays, $total['working']);
        $this->assertSame(1.0, $total['present']);
    }

    public function test_the_page_renders_for_an_authorized_user(): void
    {
        $user = new User();
        $user->forceFill(['name' => 'Viewer', 'email' => 'viewer@grid.test', 'password' => Hash::make('P@ssword123'), 'account_status' => 'active', 'approved_at' => now()])->save();

        $role = Role::forceCreate(['name' => 'Att Viewer', 'slug' => 'att-viewer']);
        $perm = Permission::query()->firstOrCreate(['slug' => 'attendance.view'], ['name' => 'View Attendance', 'group_name' => 'attendance']);
        $role->permissions()->attach($perm->id);
        $user->roles()->attach($role->id, ['assigned_at' => now()]);

        $this->employee('GRID-4');

        $this->actingAs($user)
            ->get('/attendance/records?month=' . $this->month->month . '&year=' . $this->month->year)
            ->assertOk()
            ->assertSee($this->month->format('F Y'))
            ->assertSee('Attendance Records');
    }
}
