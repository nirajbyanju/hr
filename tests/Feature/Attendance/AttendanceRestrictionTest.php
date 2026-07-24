<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\AttendanceRestrictions;
use Tests\TenantTestCase;

/**
 * The IP allowlist and geofence gate self-service punches on the System-Based
 * Attendance page. Administrators stay exempt so records can still be corrected
 * from off site.
 */
class AttendanceRestrictionTest extends TenantTestCase
{
    /**
     * @param array<int, string> $permissionSlugs
     */
    private function makeUserWithPermissions(array $permissionSlugs): User
    {
        $user = User::factory()->create(['account_status' => 'active']);
        $role = Role::query()->create([
            'name' => 'Role ' . $user->id,
            'slug' => 'role-' . $user->id,
        ]);

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

    private function makeEmployeeFor(User $user, string $codePrefix): Employee
    {
        return Employee::query()->create([
            'user_id' => $user->id,
            'employee_code' => $codePrefix . '-' . $user->id,
            'first_name' => 'Res',
            'last_name' => 'Tricted',
            'date_of_joining' => now()->subYear()->format('Y-m-d'),
            'employment_type' => 'full_time',
            'employment_status' => 'active',
        ]);
    }

    private function employeeUser(): User
    {
        $user = $this->makeUserWithPermissions(['attendance.view', 'attendance.clock']);
        $this->makeEmployeeFor($user, 'RES');

        return $user->fresh();
    }

    private function restrictIp(string $allowed): void
    {
        SystemSetting::put(AttendanceRestrictions::SETTING_IP_ENABLED, '1', ['group_name' => 'attendance']);
        SystemSetting::put(AttendanceRestrictions::SETTING_ALLOWED_IPS, $allowed, ['group_name' => 'attendance']);
        SystemSetting::forgetCache();
    }

    private function restrictGeofence(string $lat, string $lng, string $radius): void
    {
        SystemSetting::put(AttendanceRestrictions::SETTING_GEO_ENABLED, '1', ['group_name' => 'attendance']);
        SystemSetting::put(AttendanceRestrictions::SETTING_LATITUDE, $lat, ['group_name' => 'attendance']);
        SystemSetting::put(AttendanceRestrictions::SETTING_LONGITUDE, $lng, ['group_name' => 'attendance']);
        SystemSetting::put(AttendanceRestrictions::SETTING_RADIUS, $radius, ['group_name' => 'attendance']);
        SystemSetting::forgetCache();
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function punch(User $user, array $extra = [], string $ip = '198.51.100.7'): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('attendance.store'), array_merge([
                'attendance_date' => now()->format('Y-m-d'),
                'entry_time' => '09:05 AM',
            ], $extra));
    }

    public function test_a_punch_from_an_allowed_ip_is_recorded(): void
    {
        $user = $this->employeeUser();
        $this->restrictIp('198.51.100.0/24');

        $this->punch($user)->assertSessionHasNoErrors();

        $this->assertSame(1, AttendanceLog::query()->count());
    }

    public function test_a_punch_from_a_disallowed_ip_is_rejected(): void
    {
        $user = $this->employeeUser();
        $this->restrictIp('203.0.113.0/24');

        $this->punch($user)->assertSessionHasErrors('entry_type');

        $this->assertSame(0, AttendanceLog::query()->count());
    }

    public function test_the_allowlist_is_ignored_while_the_restriction_is_off(): void
    {
        $user = $this->employeeUser();
        SystemSetting::put(AttendanceRestrictions::SETTING_IP_ENABLED, '0', ['group_name' => 'attendance']);
        SystemSetting::put(AttendanceRestrictions::SETTING_ALLOWED_IPS, '203.0.113.0/24', ['group_name' => 'attendance']);
        SystemSetting::forgetCache();

        $this->punch($user)->assertSessionHasNoErrors();

        $this->assertSame(1, AttendanceLog::query()->count());
    }

    public function test_a_punch_inside_the_geofence_is_recorded(): void
    {
        $user = $this->employeeUser();
        $this->restrictGeofence('27.7172', '85.3240', '200');

        $this->punch($user, ['client_latitude' => '27.7175', 'client_longitude' => '85.3240'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, AttendanceLog::query()->count());
    }

    public function test_a_punch_outside_the_geofence_is_rejected(): void
    {
        $user = $this->employeeUser();
        $this->restrictGeofence('27.7172', '85.3240', '200');

        // ~333m north of the configured point.
        $this->punch($user, ['client_latitude' => '27.7202', 'client_longitude' => '85.3240'])
            ->assertSessionHasErrors('entry_type');

        $this->assertSame(0, AttendanceLog::query()->count());
    }

    public function test_a_punch_without_coordinates_is_rejected_while_the_geofence_is_on(): void
    {
        $user = $this->employeeUser();
        $this->restrictGeofence('27.7172', '85.3240', '200');

        $this->punch($user)->assertSessionHasErrors('entry_type');

        $this->assertSame(0, AttendanceLog::query()->count());
    }

    public function test_an_administrator_is_exempt_from_the_restrictions(): void
    {
        $admin = $this->makeUserWithPermissions(['attendance.view', 'attendance.clock', 'attendance.manage']);
        $employee = $this->makeEmployeeFor($admin, 'ADM');

        $this->restrictIp('203.0.113.0/24');
        $this->restrictGeofence('27.7172', '85.3240', '200');

        $this->punch($admin->fresh(), ['employee_id' => $employee->id, 'entry_type' => 'checkin'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, AttendanceLog::query()->count());
    }
}
