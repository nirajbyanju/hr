<?php

namespace Database\Seeders;

use App\Models\TaskCategory;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use Illuminate\Database\Seeder;

class TaskLookupSeeder extends Seeder
{
    /**
     * Idempotent re-assertion of the task workflow lookup data. The core rows are already
     * inserted by the 2026_07_16_000100_create_task_lookup_tables migration (so a plain
     * `migrate` always leaves the schema usable); this seeder exists to safely re-affirm
     * name/color/order on environments where the migration ran before this seeder existed,
     * and to top up starter categories without duplicating rows.
     */
    public function run(): void
    {
        $statuses = [
            ['code' => 'draft', 'name' => 'Draft', 'color' => '#6c757d', 'sort_order' => 10, 'is_terminal' => false],
            ['code' => 'assigned', 'name' => 'Assigned', 'color' => '#0d6efd', 'sort_order' => 20, 'is_terminal' => false],
            ['code' => 'rejected', 'name' => 'Rejected', 'color' => '#dc3545', 'sort_order' => 25, 'is_terminal' => true],
            ['code' => 'accepted', 'name' => 'Accepted', 'color' => '#20c997', 'sort_order' => 30, 'is_terminal' => false],
            ['code' => 'in_progress', 'name' => 'In Progress', 'color' => '#0dcaf0', 'sort_order' => 40, 'is_terminal' => false],
            ['code' => 'on_hold', 'name' => 'On Hold', 'color' => '#ffc107', 'sort_order' => 50, 'is_terminal' => false],
            ['code' => 'under_review', 'name' => 'Under Review', 'color' => '#6f42c1', 'sort_order' => 60, 'is_terminal' => false],
            ['code' => 'changes_requested', 'name' => 'Changes Requested', 'color' => '#fd7e14', 'sort_order' => 65, 'is_terminal' => false],
            ['code' => 'approved', 'name' => 'Approved', 'color' => '#0ca678', 'sort_order' => 70, 'is_terminal' => false],
            ['code' => 'completed', 'name' => 'Completed', 'color' => '#198754', 'sort_order' => 80, 'is_terminal' => false],
            ['code' => 'closed', 'name' => 'Closed', 'color' => '#343a40', 'sort_order' => 90, 'is_terminal' => true],
        ];

        foreach ($statuses as $status) {
            TaskStatus::query()->updateOrCreate(['code' => $status['code']], $status + ['is_active' => true]);
        }

        $priorities = [
            ['code' => 'critical', 'name' => 'Critical', 'color' => '#dc3545', 'level' => 4, 'sort_order' => 10],
            ['code' => 'high', 'name' => 'High', 'color' => '#fd7e14', 'level' => 3, 'sort_order' => 20],
            ['code' => 'medium', 'name' => 'Medium', 'color' => '#ffc107', 'level' => 2, 'sort_order' => 30],
            ['code' => 'low', 'name' => 'Low', 'color' => '#198754', 'level' => 1, 'sort_order' => 40],
        ];

        foreach ($priorities as $priority) {
            TaskPriority::query()->updateOrCreate(['code' => $priority['code']], $priority + ['is_active' => true]);
        }

        $categories = [
            ['code' => 'general', 'name' => 'General', 'color' => '#6c757d'],
            ['code' => 'development', 'name' => 'Development', 'color' => '#0d6efd'],
            ['code' => 'design', 'name' => 'Design', 'color' => '#6f42c1'],
            ['code' => 'bug', 'name' => 'Bug', 'color' => '#dc3545'],
            ['code' => 'support', 'name' => 'Support', 'color' => '#0ca678'],
        ];

        foreach ($categories as $category) {
            TaskCategory::query()->updateOrCreate(['code' => $category['code']], $category + ['is_active' => true]);
        }

        $this->command?->info('Task workflow lookup data is ready.');
    }
}
