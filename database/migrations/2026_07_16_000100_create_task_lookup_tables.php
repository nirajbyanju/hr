<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fixed workflow status codes the TaskWorkflowService transition table matches by string.
     * Seeded here (not only in a seeder) so a plain `migrate` always leaves the schema usable —
     * the tasks-table backfill migration that runs right after this one depends on these rows.
     *
     * @return array<int, array<string, mixed>>
     */
    private function statusRows(): array
    {
        $rows = [
            ['draft', 'Draft', '#6c757d', 10, false],
            ['assigned', 'Assigned', '#0d6efd', 20, false],
            ['rejected', 'Rejected', '#dc3545', 25, true],
            ['accepted', 'Accepted', '#20c997', 30, false],
            ['in_progress', 'In Progress', '#0dcaf0', 40, false],
            ['on_hold', 'On Hold', '#ffc107', 50, false],
            ['under_review', 'Under Review', '#6f42c1', 60, false],
            ['changes_requested', 'Changes Requested', '#fd7e14', 65, false],
            ['approved', 'Approved', '#0ca678', 70, false],
            ['completed', 'Completed', '#198754', 80, false],
            ['closed', 'Closed', '#343a40', 90, true],
        ];

        return array_map(fn (array $row) => [
            'code' => $row[0],
            'name' => $row[1],
            'color' => $row[2],
            'sort_order' => $row[3],
            'is_terminal' => $row[4],
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function priorityRows(): array
    {
        $rows = [
            ['critical', 'Critical', '#dc3545', 4, 10],
            ['high', 'High', '#fd7e14', 3, 20],
            ['medium', 'Medium', '#ffc107', 2, 30],
            ['low', 'Low', '#198754', 1, 40],
        ];

        return array_map(fn (array $row) => [
            'code' => $row[0],
            'name' => $row[1],
            'color' => $row[2],
            'level' => $row[3],
            'sort_order' => $row[4],
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function categoryRows(): array
    {
        $rows = [
            ['General', 'general', '#6c757d'],
            ['Development', 'development', '#0d6efd'],
            ['Design', 'design', '#6f42c1'],
            ['Bug', 'bug', '#dc3545'],
            ['Support', 'support', '#0ca678'],
        ];

        return array_map(fn (array $row) => [
            'name' => $row[0],
            'code' => $row[1],
            'color' => $row[2],
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $rows);
    }

    public function up(): void
    {
        Schema::create('task_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 60);
            $table->string('color', 20)->default('#6c757d');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_terminal')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('task_priorities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 60);
            $table->string('color', 20)->default('#6c757d');
            $table->unsignedTinyInteger('level')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('task_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('code', 40)->nullable()->unique();
            $table->string('color', 20)->default('#6c757d');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('task_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('color', 20)->default('#6c757d');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('task_statuses')->insert($this->statusRows());
        DB::table('task_priorities')->insert($this->priorityRows());
        DB::table('task_categories')->insert($this->categoryRows());
    }

    public function down(): void
    {
        Schema::dropIfExists('task_tags');
        Schema::dropIfExists('task_categories');
        Schema::dropIfExists('task_priorities');
        Schema::dropIfExists('task_statuses');
    }
};
