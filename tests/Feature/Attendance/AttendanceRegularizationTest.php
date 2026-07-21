<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceLog;
use App\Models\AttendanceRegularization;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Tests\TenantTestCase;

/**
 * Attendance regularization requests and, crucially, that approving one writes
 * the corrected times back onto the day's attendance_logs.
 */
class AttendanceRegularizationTest extends TenantTestCase
{
    private function reviewer(): User
    {
        $user = new User();
        $user->forceFill(['name' => 'HR', 'email' => 'hr@reg.test', 'password' => Hash::make('P@ssword123'), 'account_status' => 'active', 'approved_at' => now()])->save();

        $role = Role::forceCreate(['name' => 'Att Reviewer', 'slug' => 'att-reviewer']);
        foreach (['attendance.view', 'attendance.manage', 'attendance.approve_time_change'] as $slug) {
            $perm = Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug, 'group_name' => 'attendance']);
            $role->permissions()->attach($perm->id);
        }
        $user->roles()->attach($role->id, ['assigned_at' => now()]);

        return $user;
    }

    private function employee(string $code = 'REG-1'): Employee
    {
        return Employee::forceCreate([
            'employee_code' => $code,
            'first_name' => 'Reg',
            'last_name' => $code,
            'gender' => 'male',
            'date_of_joining' => CarbonImmutable::today()->subYear()->toDateString(),
            'employment_status' => 'active',
        ]);
    }

    private function attendance(int $employeeId, string $date, string $in, string $out): void
    {
        AttendanceLog::insert([
            ['employee_id' => $employeeId, 'attendance_date' => $date, 'check_in_at' => "$date $in:00", 'check_out_at' => null, 'worked_minutes' => 0, 'status' => 'present', 'source' => 'test', 'created_at' => now(), 'updated_at' => now()],
            ['employee_id' => $employeeId, 'attendance_date' => $date, 'check_in_at' => null, 'check_out_at' => "$date $out:00", 'worked_minutes' => 0, 'status' => 'present', 'source' => 'test', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_the_index_shows_status_counts(): void
    {
        $emp = $this->employee();
        AttendanceRegularization::create(['employee_id' => $emp->id, 'attendance_date' => '2026-07-01', 'requested_check_in_at' => '2026-07-01 09:00', 'requested_check_out_at' => '2026-07-01 17:00', 'reason' => 'x', 'status' => 'pending']);
        AttendanceRegularization::create(['employee_id' => $emp->id, 'attendance_date' => '2026-07-02', 'requested_check_in_at' => '2026-07-02 09:00', 'requested_check_out_at' => '2026-07-02 17:00', 'reason' => 'x', 'status' => 'approved']);

        $this->actingAs($this->reviewer())
            ->get('/attendance/regularizations')
            ->assertOk()
            ->assertSee('Attendance Regularizations')
            ->assertSee('Total Requests');
    }

    public function test_a_request_can_be_created(): void
    {
        $emp = $this->employee();
        $this->attendance($emp->id, '2026-07-10', '09:20', '17:00');

        $this->actingAs($this->reviewer())
            ->post('/attendance/regularizations', [
                'employee_id' => $emp->id,
                'attendance_date' => '2026-07-10',
                'requested_check_in' => '09:00',
                'requested_check_out' => '18:00',
                'reason' => 'Forgot to clock in',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('attendance_regularizations', [
            'employee_id' => $emp->id,
            'attendance_date' => '2026-07-10',
            'status' => 'pending',
        ]);

        // The original times were snapshotted from the day's logs.
        $reg = AttendanceRegularization::first();
        $this->assertSame('09:20', $reg->original_check_in_at->format('H:i'));
        $this->assertSame('17:00', $reg->original_check_out_at->format('H:i'));
    }

    public function test_approving_rewrites_the_days_attendance(): void
    {
        $emp = $this->employee();
        $this->attendance($emp->id, '2026-07-11', '09:40', '16:30'); // late + early original

        $reg = AttendanceRegularization::create([
            'employee_id' => $emp->id,
            'attendance_date' => '2026-07-11',
            'requested_check_in_at' => '2026-07-11 09:00',
            'requested_check_out_at' => '2026-07-11 18:00',
            'reason' => 'System error',
            'status' => 'pending',
        ]);

        $this->actingAs($this->reviewer())
            ->post("/attendance/regularizations/{$reg->id}/approve")
            ->assertRedirect();

        $this->assertSame('approved', $reg->fresh()->status);
        $this->assertNotNull($reg->fresh()->reviewed_at);

        // The day's attendance now reflects the requested times.
        $logs = AttendanceLog::where('employee_id', $emp->id)->whereDate('attendance_date', '2026-07-11')->get();
        $in = $logs->pluck('check_in_at')->filter()->min();
        $out = $logs->pluck('check_out_at')->filter()->max();
        $this->assertSame('09:00', \Carbon\Carbon::parse($in)->format('H:i'));
        $this->assertSame('18:00', \Carbon\Carbon::parse($out)->format('H:i'));
        $this->assertTrue($logs->every(fn ($l) => str_starts_with($l->source, 'regularization')));
    }

    public function test_rejecting_records_the_decision_and_leaves_attendance_untouched(): void
    {
        $emp = $this->employee();
        $this->attendance($emp->id, '2026-07-12', '09:30', '17:00');

        $reg = AttendanceRegularization::create([
            'employee_id' => $emp->id,
            'attendance_date' => '2026-07-12',
            'requested_check_in_at' => '2026-07-12 09:00',
            'requested_check_out_at' => '2026-07-12 18:00',
            'reason' => 'x',
            'status' => 'pending',
        ]);

        $this->actingAs($this->reviewer())
            ->post("/attendance/regularizations/{$reg->id}/reject", ['review_remarks' => 'No proof'])
            ->assertRedirect();

        $this->assertSame('rejected', $reg->fresh()->status);
        $this->assertSame('No proof', $reg->fresh()->review_remarks);

        // Attendance is unchanged — still the original test rows.
        $this->assertTrue(
            AttendanceLog::where('employee_id', $emp->id)->whereDate('attendance_date', '2026-07-12')->get()
                ->every(fn ($l) => $l->source === 'test')
        );
    }

    public function test_a_plain_employee_only_sees_their_own_requests(): void
    {
        $mine = $this->employee('REG-MINE');
        $other = $this->employee('REG-OTHER');

        AttendanceRegularization::create(['employee_id' => $mine->id, 'attendance_date' => '2026-07-01', 'requested_check_in_at' => '2026-07-01 09:00', 'requested_check_out_at' => '2026-07-01 17:00', 'reason' => 'mine', 'status' => 'pending']);
        AttendanceRegularization::create(['employee_id' => $other->id, 'attendance_date' => '2026-07-01', 'requested_check_in_at' => '2026-07-01 09:00', 'requested_check_out_at' => '2026-07-01 17:00', 'reason' => 'theirs', 'status' => 'pending']);

        // A user linked to $mine with only the base view permission.
        $user = new User();
        $user->forceFill(['name' => 'Emp', 'email' => 'emp@reg.test', 'password' => Hash::make('P@ssword123'), 'account_status' => 'active', 'approved_at' => now()])->save();
        $mine->update(['user_id' => $user->id]);
        $role = Role::forceCreate(['name' => 'Att Self', 'slug' => 'att-self']);
        $perm = Permission::query()->firstOrCreate(['slug' => 'attendance.clock'], ['name' => 'clock', 'group_name' => 'attendance']);
        $role->permissions()->attach($perm->id);
        $user->roles()->attach($role->id, ['assigned_at' => now()]);

        $this->actingAs($user)
            ->get('/attendance/regularizations')
            ->assertOk()
            ->assertSee('mine')
            ->assertDontSee('theirs');
    }
}
