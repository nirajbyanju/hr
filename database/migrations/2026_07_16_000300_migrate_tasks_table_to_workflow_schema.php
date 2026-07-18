<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Maps the legacy `tasks.status` enum value to a `task_statuses.code`.
     * 'todo' is split depending on whether the task already has an assignee.
     */
    private function mapStatusCode(?string $legacyStatus, bool $hasAssignee): string
    {
        return match ($legacyStatus) {
            'in_progress' => 'in_progress',
            'review' => 'under_review',
            'done' => 'completed',
            'blocked' => 'on_hold',
            'cancelled' => 'closed',
            default => $hasAssignee ? 'assigned' : 'draft',
        };
    }

    private function mapPriorityCode(?string $legacyPriority): string
    {
        return match ($legacyPriority) {
            'high' => 'high',
            'urgent' => 'critical',
            'low' => 'low',
            default => 'medium',
        };
    }

    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('project_id')->constrained('task_categories')->restrictOnDelete();
            $table->foreignId('status_id')->nullable()->after('status')->constrained('task_statuses')->restrictOnDelete();
            $table->foreignId('priority_id')->nullable()->after('priority')->constrained('task_priorities')->restrictOnDelete();
            $table->foreignId('owner_employee_id')->nullable()->after('assigned_to_employee_id')->constrained('employees')->nullOnDelete();
            $table->foreignId('parent_task_id')->nullable()->after('owner_employee_id')->constrained('tasks')->nullOnDelete();
            $table->string('visibility', 20)->default('public')->after('parent_task_id');
            $table->boolean('is_team_task')->default(false)->after('visibility');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
        });

        $statusIds = DB::table('task_statuses')->pluck('id', 'code');
        $priorityIds = DB::table('task_priorities')->pluck('id', 'code');
        $now = now();

        DB::table('tasks')->orderBy('id')->chunkById(200, function ($tasks) use ($statusIds, $priorityIds, $now) {
            foreach ($tasks as $task) {
                $hasAssignee = ! empty($task->assigned_to_employee_id);
                $statusCode = $this->mapStatusCode($task->status ?? null, $hasAssignee);
                $priorityCode = $this->mapPriorityCode($task->priority ?? null);
                $statusId = $statusIds[$statusCode] ?? $statusIds['draft'];
                $priorityId = $priorityIds[$priorityCode] ?? $priorityIds['medium'];

                DB::table('tasks')->where('id', $task->id)->update([
                    'status_id' => $statusId,
                    'priority_id' => $priorityId,
                    'owner_employee_id' => $task->assigned_to_employee_id,
                ]);

                if ($hasAssignee) {
                    $assignedAt = $task->created_at ?? $now;
                    $reachedSortOrder = DB::table('task_statuses')->where('id', $statusId)->value('sort_order');
                    $acceptedSortOrder = $statusIds->has('accepted')
                        ? DB::table('task_statuses')->where('id', $statusIds['accepted'])->value('sort_order')
                        : 0;

                    $assignmentId = DB::table('task_assignments')->insertGetId([
                        'task_id' => $task->id,
                        'employee_id' => $task->assigned_to_employee_id,
                        'status_id' => $statusId,
                        'is_owner' => true,
                        'progress_percent' => $task->progress_percent ?? 0,
                        'assigned_at' => $assignedAt,
                        'accepted_at' => $reachedSortOrder >= $acceptedSortOrder ? $assignedAt : null,
                        'started_at' => $statusCode === 'in_progress' ? $assignedAt : null,
                        'completed_at' => $statusCode === 'completed' ? ($task->updated_at ?? $now) : null,
                        'estimated_hours' => $task->estimated_hours ?? null,
                        'actual_hours' => $task->actual_hours ?? null,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('task_status_history')->insert([
                        'task_id' => $task->id,
                        'task_assignment_id' => $assignmentId,
                        'from_status_id' => null,
                        'to_status_id' => $statusId,
                        'changed_by' => null,
                        'reason' => 'Migrated from legacy schema.',
                        'changed_at' => $assignedAt,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('status_id')->nullable(false)->change();
            $table->foreignId('priority_id')->nullable(false)->change();
        });

        // MySQL requires every foreign key column to retain a backing index. The
        // (project_id, status) and (assigned_to_employee_id, status) composites are
        // the only indexes serving the project_id and assigned_to_employee_id foreign
        // keys, so they cannot be dropped while those keys still rely on them (error
        // 1553). project_id survives this migration, so give it a dedicated index
        // first; assigned_to_employee_id is being removed, so drop its foreign key.
        Schema::table('tasks', function (Blueprint $table) {
            $table->index('project_id');
            $table->dropForeign(['assigned_to_employee_id']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'status']);
            $table->dropIndex(['assigned_to_employee_id', 'status']);
            $table->dropColumn(['assigned_to_employee_id', 'status', 'priority']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['status_id']);
            $table->index(['category_id']);
            $table->index(['owner_employee_id', 'status_id']);
            $table->index(['parent_task_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['parent_task_id']);
            $table->dropIndex(['owner_employee_id', 'status_id']);
            $table->dropIndex(['category_id']);
            $table->dropIndex(['status_id']);

            $table->enum('status', ['todo', 'in_progress', 'review', 'done', 'blocked', 'cancelled'])->default('todo')->after('description');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium')->after('description');
            $table->foreignId('assigned_to_employee_id')->nullable()->after('created_by_employee_id')->constrained('employees')->nullOnDelete();
        });

        $statusCodes = DB::table('task_statuses')->pluck('code', 'id');
        $priorityCodes = DB::table('task_priorities')->pluck('code', 'id');

        $legacyStatusFor = fn (string $code) => match ($code) {
            'in_progress' => 'in_progress',
            'under_review', 'changes_requested' => 'review',
            'approved', 'completed' => 'done',
            'on_hold' => 'blocked',
            'closed', 'rejected' => 'cancelled',
            default => 'todo',
        };
        $legacyPriorityFor = fn (string $code) => match ($code) {
            'critical' => 'urgent',
            'high' => 'high',
            'low' => 'low',
            default => 'medium',
        };

        DB::table('tasks')->orderBy('id')->chunkById(200, function ($tasks) use ($statusCodes, $priorityCodes, $legacyStatusFor, $legacyPriorityFor) {
            foreach ($tasks as $task) {
                $owner = DB::table('task_assignments')->where('task_id', $task->id)->where('is_owner', true)->value('employee_id')
                    ?? $task->owner_employee_id;

                DB::table('tasks')->where('id', $task->id)->update([
                    'status' => $legacyStatusFor($statusCodes[$task->status_id] ?? 'draft'),
                    'priority' => $legacyPriorityFor($priorityCodes[$task->priority_id] ?? 'medium'),
                    'assigned_to_employee_id' => $owner,
                ]);
            }
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['parent_task_id']);
            $table->dropForeign(['owner_employee_id']);
            $table->dropForeign(['priority_id']);
            $table->dropForeign(['status_id']);
            $table->dropForeign(['category_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropForeign(['deleted_by']);
            $table->dropColumn([
                'category_id', 'status_id', 'priority_id', 'owner_employee_id',
                'parent_task_id', 'visibility', 'is_team_task',
                'created_by', 'updated_by', 'deleted_by',
            ]);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['project_id', 'status']);
            $table->index(['assigned_to_employee_id', 'status']);
        });

        // Remove the standalone project_id index added in up(); the composite above
        // now backs the project_id foreign key again, so this stays a clean inverse.
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['project_id']);
        });
    }
};
