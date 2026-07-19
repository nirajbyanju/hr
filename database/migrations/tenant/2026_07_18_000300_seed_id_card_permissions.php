<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $adminMeta = [
            'access_scope' => 'admin',
            'access_scope_label' => 'Admin / Global',
            'access_scope_badge_class' => 'bg-danger',
            'access_scope_description' => 'User can access company-wide records, setup, approval, payroll, or reports.',
        ];
        $teamMeta = [
            'access_scope' => 'team',
            'access_scope_label' => 'Department / Team',
            'access_scope_badge_class' => 'bg-info',
            'access_scope_description' => 'User can access assigned team, department, or approval-scope records.',
        ];

        $permissions = [
            ['slug' => 'id_card.view', 'group_name' => 'id_card', 'name' => 'View Id Card', 'meta' => $adminMeta],
            ['slug' => 'id_card.generate', 'group_name' => 'id_card', 'name' => 'Generate Id Card', 'meta' => $adminMeta],
            ['slug' => 'id_card.print', 'group_name' => 'id_card', 'name' => 'Print Id Card', 'meta' => $adminMeta],
            ['slug' => 'id_card.manage', 'group_name' => 'id_card', 'name' => 'Manage Id Card', 'meta' => $adminMeta],
            ['slug' => 'attendance.scan', 'group_name' => 'attendance', 'name' => 'Scan Attendance', 'meta' => $teamMeta],
        ];

        $ids = [];
        foreach ($permissions as $permission) {
            $model = Permission::query()->updateOrCreate(
                ['slug' => $permission['slug']],
                array_merge([
                    'group_name' => $permission['group_name'],
                    'name' => $permission['name'],
                    'description' => $permission['name'],
                ], $permission['meta'])
            );
            $ids[] = $model->id;
        }

        // Attach to the roles that manage the workforce, without detaching any
        // permissions an admin may have customised through the UI.
        foreach (['admin', 'super-admin', 'hr-admin', 'hr-manager'] as $roleSlug) {
            $role = Role::query()->where('slug', $roleSlug)->first();
            $role?->permissions()->syncWithoutDetaching($ids);
        }
    }

    public function down(): void
    {
        Permission::query()
            ->whereIn('slug', ['id_card.view', 'id_card.generate', 'id_card.print', 'id_card.manage', 'attendance.scan'])
            ->delete();
    }
};
