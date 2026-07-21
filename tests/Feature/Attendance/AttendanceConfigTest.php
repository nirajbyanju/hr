<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendancePolicy;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TenantTestCase;

/**
 * Attendance policies and shifts CRUD, their computed stats, and the shift
 * working-time / night-shift calculations.
 */
class AttendanceConfigTest extends TenantTestCase
{
    private ?User $manager = null;

    /** One manager per test, created on first use — calling it twice must not
     *  try to re-create the same role slug. */
    private function manager(): User
    {
        if ($this->manager !== null) {
            return $this->manager;
        }

        $user = new User();
        $user->forceFill(['name' => 'Mgr', 'email' => 'mgr@cfg.test', 'password' => Hash::make('P@ssword123'), 'account_status' => 'active', 'approved_at' => now()])->save();

        $role = Role::forceCreate(['name' => 'Att Config', 'slug' => 'att-config']);
        foreach (['attendance.view', 'attendance.manage'] as $slug) {
            $perm = Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug, 'group_name' => 'attendance']);
            $role->permissions()->attach($perm->id);
        }
        $user->roles()->attach($role->id, ['assigned_at' => now()]);

        return $this->manager = $user;
    }

    // --- Policies -----------------------------------------------------------

    public function test_a_policy_can_be_created_and_listed(): void
    {
        $this->actingAs($this->manager())
            ->post('/attendance/policies', [
                'name' => 'Standard Policy',
                'description' => 'Default',
                'late_arrival_grace_minutes' => 15,
                'early_departure_grace_minutes' => 10,
                'overtime_rate_per_hour' => 150,
                'status' => 'active',
            ])->assertRedirect();

        $this->assertDatabaseHas('attendance_policies', ['name' => 'Standard Policy', 'late_arrival_grace_minutes' => 15]);

        $this->actingAs($this->manager())->get('/attendance/policies')
            ->assertOk()->assertSee('Standard Policy')->assertSee('Avg Late Grace');
    }

    public function test_policy_stats_average_grace_and_overtime(): void
    {
        AttendancePolicy::create(['name' => 'A', 'late_arrival_grace_minutes' => 10, 'early_departure_grace_minutes' => 10, 'overtime_rate_per_hour' => 100, 'status' => 'active']);
        AttendancePolicy::create(['name' => 'B', 'late_arrival_grace_minutes' => 20, 'early_departure_grace_minutes' => 20, 'overtime_rate_per_hour' => 200, 'status' => 'inactive']);

        $this->actingAs($this->manager())->get('/attendance/policies')
            ->assertOk()
            ->assertSee('15 min')       // avg late grace (10+20)/2
            ->assertSee('$150.00');     // avg overtime (100+200)/2
    }

    public function test_a_policy_status_can_be_toggled(): void
    {
        $policy = AttendancePolicy::create(['name' => 'Toggle', 'late_arrival_grace_minutes' => 5, 'early_departure_grace_minutes' => 5, 'overtime_rate_per_hour' => 120, 'status' => 'active']);

        $this->actingAs($this->manager())->patch("/attendance/policies/{$policy->id}/status")->assertRedirect();
        $this->assertSame('inactive', $policy->fresh()->status);

        $this->actingAs($this->manager())->patch("/attendance/policies/{$policy->id}/status")->assertRedirect();
        $this->assertSame('active', $policy->fresh()->status);
    }

    public function test_a_policy_can_be_deleted(): void
    {
        $policy = AttendancePolicy::create(['name' => 'Del', 'late_arrival_grace_minutes' => 5, 'early_departure_grace_minutes' => 5, 'overtime_rate_per_hour' => 120, 'status' => 'active']);

        $this->actingAs($this->manager())->delete("/attendance/policies/{$policy->id}")->assertRedirect();
        $this->assertDatabaseMissing('attendance_policies', ['id' => $policy->id]);
    }

    // --- Shifts -------------------------------------------------------------

    public function test_a_shift_can_be_created(): void
    {
        $this->actingAs($this->manager())
            ->post('/attendance/shifts', [
                'name' => 'Morning Shift',
                'start_time' => '09:00',
                'end_time' => '18:00',
                'break_duration_minutes' => 60,
                'grace_period_minutes' => 15,
                'is_night_shift' => '0',
                'status' => 'active',
            ])->assertRedirect();

        $this->assertDatabaseHas('shifts', ['name' => 'Morning Shift', 'break_duration_minutes' => 60]);
    }

    public function test_working_hours_account_for_the_break(): void
    {
        $shift = Shift::create(['name' => 'Day', 'start_time' => '09:00', 'end_time' => '18:00', 'break_duration_minutes' => 60, 'grace_period_minutes' => 15, 'is_night_shift' => false, 'status' => 'active']);

        // 09:00–18:00 is 9h, minus the 60-minute break = 8.0h.
        $this->assertSame(8.0, $shift->workingHours());
        $this->assertSame('09:00 - 18:00', $shift->hoursLabel());
    }

    public function test_a_night_shift_spans_midnight(): void
    {
        $shift = Shift::create(['name' => 'Night', 'start_time' => '22:00', 'end_time' => '07:00', 'break_duration_minutes' => 60, 'grace_period_minutes' => 15, 'is_night_shift' => true, 'status' => 'active']);

        // 22:00 → 07:00 next day is 9h, minus 60m break = 8.0h.
        $this->assertSame(8.0, $shift->workingHours());
    }

    public function test_shift_stats_count_day_and_night(): void
    {
        Shift::create(['name' => 'D1', 'start_time' => '09:00', 'end_time' => '17:00', 'break_duration_minutes' => 0, 'grace_period_minutes' => 0, 'is_night_shift' => false, 'status' => 'active']);
        Shift::create(['name' => 'D2', 'start_time' => '14:00', 'end_time' => '22:00', 'break_duration_minutes' => 0, 'grace_period_minutes' => 0, 'is_night_shift' => false, 'status' => 'active']);
        Shift::create(['name' => 'N1', 'start_time' => '22:00', 'end_time' => '06:00', 'break_duration_minutes' => 0, 'grace_period_minutes' => 0, 'is_night_shift' => true, 'status' => 'inactive']);

        $this->actingAs($this->manager())->get('/attendance/shifts')
            ->assertOk()
            ->assertSee('Night Shifts')
            ->assertSee('Day Shifts');
    }
}
