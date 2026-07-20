<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_comments', function (Blueprint $table) {
            $table->foreignId('parent_comment_id')->nullable()->after('task_id')->constrained('task_comments')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->index('parent_comment_id');
        });

        Schema::create('task_comment_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_comment_id')->constrained('task_comments')->cascadeOnDelete();
            $table->foreignId('mentioned_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->timestamps();

            // Explicit short name: the auto-generated
            // task_comment_mentions_task_comment_id_mentioned_employee_id_unique
            // exceeds MySQL's 64-character identifier limit.
            $table->unique(['task_comment_id', 'mentioned_employee_id'], 'tcm_comment_employee_unique');
            $table->index('mentioned_employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_comment_mentions');

        Schema::table('task_comments', function (Blueprint $table) {
            $table->dropIndex(['parent_comment_id']);
            $table->dropForeign(['parent_comment_id']);
            $table->dropForeign(['updated_by']);
            $table->dropForeign(['deleted_by']);
            $table->dropColumn(['parent_comment_id', 'updated_by', 'deleted_by', 'deleted_at']);
        });
    }
};
