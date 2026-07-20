<?php

namespace Tests\Feature\Tasks;

use App\Models\Employee;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\User;

trait CreatesTaskFixtures
{
    /** @param array<int, string> $permissionSlugs */
    protected function makeUserWithPermissions(array $permissionSlugs, string $roleSlug): User
    {
        $user = User::factory()->create(['account_status' => 'active']);

        Employee::query()->create([
            'user_id' => $user->id,
            'employee_code' => 'EMP-' . $user->id,
            'first_name' => 'Test',
            'last_name' => ucfirst($roleSlug),
            'date_of_joining' => now()->subYear(),
            'employment_status' => 'active',
        ]);

        // Both slug and name must be unique per fixture: the tenant database is
        // seeded with the real role catalogue (Admin, HR Admin, …) and MySQL's
        // unique index on `name` is case-insensitive, so a plain "admin" here
        // would collide with the seeded "Admin".
        $role = Role::query()->create([
            'slug' => $roleSlug . '-' . $user->id,
            'name' => $roleSlug . '-' . $user->id,
            'is_system' => false,
        ]);
        $permissionIds = Permission::query()->whereIn('slug', $permissionSlugs)->pluck('id');
        $role->permissions()->sync($permissionIds);
        $user->roles()->attach($role->id, ['assigned_at' => now()]);

        return $user->fresh(['employee']);
    }

    protected function makeProject(): Project
    {
        return Project::query()->create([
            'name' => 'Test Project',
            'project_code' => 'PRJ-' . uniqid(),
            'status' => 'active',
        ]);
    }

    protected function makeTask(Project $project): Task
    {
        $draft = TaskStatus::query()->where('code', 'draft')->firstOrFail();
        $priority = TaskPriority::query()->where('code', 'medium')->firstOrFail();

        return Task::query()->create([
            'project_id' => $project->id,
            'status_id' => $draft->id,
            'priority_id' => $priority->id,
            'title' => 'Test Task',
            'visibility' => 'public',
        ]);
    }

    protected function selfServiceSlugs(): array
    {
        return [
            'task.view', 'task.comment', 'task.advance-status', 'task.complete',
            'task.watch', 'task.transfer-request', 'task_comment.create', 'task_comment.update',
            'task_comment.delete', 'task_checklist.check', 'task_attachment.view',
            'task_attachment.upload', 'task_attachment.preview',
        ];
    }

    /**
     * Mirrors what an Admin actually resolves to in AdminUserSeeder (every task permission),
     * so tests don't accidentally pass or fail on a narrower grant than production has.
     */
    protected function adminTierSlugs(): array
    {
        return [
            'task.view', 'task.create', 'task.update', 'task.delete', 'task.assign', 'task.assign-team',
            'task.transfer-request', 'task.transfer-approve', 'task.review-approve', 'task.review-reject',
            'task.reopen', 'task.close', 'task.complete', 'task.advance-status', 'task.watch', 'task.comment',
            'task_comment.create', 'task_comment.update', 'task_comment.delete',
            'task_checklist.manage', 'task_checklist.check',
            'task_attachment.view', 'task_attachment.upload', 'task_attachment.preview', 'task_attachment.delete',
            'task_transfer.view',
        ];
    }
}
