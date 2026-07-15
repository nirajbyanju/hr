<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class LeavePermissionAssignmentSeeder extends Seeder
{
    /**
     * Seed leave feature permissions and assign to default roles.
     */
    public function run(): void
    {
        $permissions = [
            ['name' => 'View Leave', 'slug' => 'leave.view', 'description' => 'View leave menus and balance information'],
            ['name' => 'Apply Leave', 'slug' => 'leave.apply', 'description' => 'Create own leave applications'],
            ['name' => 'Approve Leave', 'slug' => 'leave.approve', 'description' => 'Review and approve/reject leave applications'],
            ['name' => 'Manage Categories Leave', 'slug' => 'leave.manage-categories', 'description' => 'Create and update leave categories'],
            ['name' => 'Manage Quotas Leave', 'slug' => 'leave.manage-quotas', 'description' => 'Create and update salary-grade leave policies'],
            ['name' => 'Manage Balances Leave', 'slug' => 'leave.manage-balances', 'description' => 'Sync and adjust employee leave balances'],
            ['name' => 'Report Leave', 'slug' => 'leave.report', 'description' => 'View leave reports and exports'],
        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                ['slug' => $permission['slug']],
                [
                    'group_name' => 'leave',
                    'name' => $permission['name'],
                    'description' => $permission['description'],
                ]
            );
        }

        $rolePermissionMap = [
            'super-admin' => [
                'leave.view',
                'leave.apply',
                'leave.approve',
                'leave.manage-categories',
                'leave.manage-quotas',
                'leave.manage-balances',
                'leave.report',
            ],
            'hr-manager' => [
                'leave.view',
                'leave.apply',
                'leave.approve',
                'leave.manage-categories',
                'leave.manage-quotas',
                'leave.manage-balances',
                'leave.report',
            ],
            'department-head' => [
                'leave.view',
                'leave.apply',
                'leave.approve',
                'leave.report',
            ],
            'supervisor' => [
                'leave.view',
                'leave.apply',
                'leave.approve',
            ],
            'team-lead' => [
                'leave.view',
                'leave.apply',
                'leave.approve',
            ],
            'employee' => [
                'leave.view',
                'leave.apply',
            ],
        ];

        foreach ($rolePermissionMap as $roleSlug => $permissionSlugs) {
            $role = Role::query()->where('slug', $roleSlug)->first();
            if (! $role) {
                continue;
            }

            $permissionIds = Permission::query()
                ->whereIn('slug', $permissionSlugs)
                ->pluck('id')
                ->all();

            if ($permissionIds !== []) {
                $role->permissions()->syncWithoutDetaching($permissionIds);
            }
        }
    }
}
