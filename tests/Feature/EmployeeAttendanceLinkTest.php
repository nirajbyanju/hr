<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Tests\TenantTestCase;

class EmployeeAttendanceLinkTest extends TenantTestCase
{

    private function makeUserWithPermissions(array $permissionSlugs): User
    {
        $user = User::factory()->create(['account_status' => 'active']);
        $role = Role::query()->create([
            'name' => 'Test Role ' . $user->id,
            'slug' => 'test-role-' . $user->id,
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

    public function test_admin_can_create_employee_linked_to_a_user(): void
    {
        $admin = $this->makeUserWithPermissions(['employee.create', 'employee.view']);
        $staffUser = User::factory()->create(['account_status' => 'active']);

        $response = $this->actingAs($admin)->post('/employees', [
            'user_id' => $staffUser->id,
            'employee_code' => '',
            'first_name' => 'Kanchan',
            'last_name' => 'Test',
            'date_of_joining' => '2026-07-01',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
        ]);

        $response->assertSessionHasNoErrors();

        $employee = Employee::query()->where('first_name', 'Kanchan')->first();
        $this->assertNotNull($employee, 'Employee row was not created.');
        $this->assertSame($staffUser->id, $employee->user_id, 'Employee was not linked to the selected user.');

        // The link the attendance page depends on.
        $this->assertNotNull($staffUser->fresh()->employee, 'user->employee relation is null after linking.');
    }

    public function test_employee_without_linked_user_breaks_attendance_self_service(): void
    {
        $admin = $this->makeUserWithPermissions(['employee.create']);
        $staffUser = $this->makeUserWithPermissions(['attendance.view', 'attendance.clock']);

        // Admin creates the employee but leaves "User Account (optional)" blank.
        $this->actingAs($admin)->post('/employees', [
            'user_id' => '',
            'employee_code' => '',
            'first_name' => 'Unlinked',
            'last_name' => 'Employee',
            'date_of_joining' => '2026-07-01',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
        ])->assertSessionHasNoErrors();

        $this->assertNotNull(Employee::query()->where('first_name', 'Unlinked')->first());

        // The staff user has no employee profile, so a check-in fails.
        $response = $this->actingAs($staffUser)->post('/attendance', [
            'attendance_date' => now()->format('Y-m-d'),
            'entry_time' => now()->format('h:i A'),
        ]);

        $response->assertSessionHasErrors('employee_id');
    }
}
