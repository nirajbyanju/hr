<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_profile_update_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resubmission_of_id')->nullable()->constrained('employee_profile_update_requests')->nullOnDelete();
            $table->string('approval_status', 20)->default('pending');
            $table->json('payload');
            $table->text('review_comments')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'approval_status'], 'epr_employee_status_idx');
            $table->index(['approval_status', 'submitted_at'], 'epr_status_submitted_idx');
            $table->index(['submitted_by_user_id', 'created_at'], 'epr_submitted_by_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_profile_update_requests');
    }
};
