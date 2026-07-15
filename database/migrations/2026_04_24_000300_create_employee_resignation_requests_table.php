<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_resignation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('supervisor_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('notice_date')->nullable();
            $table->date('requested_last_working_day');
            $table->text('reason');
            $table->text('handover_notes')->nullable();
            $table->enum('status', [
                'pending_supervisor',
                'supervisor_rejected',
                'pending_final',
                'final_rejected',
                'approved',
            ])->default('pending_supervisor');

            $table->foreignId('supervisor_action_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('supervisor_action_at')->nullable();
            $table->text('supervisor_remarks')->nullable();

            $table->foreignId('final_action_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('final_action_at')->nullable();
            $table->date('final_last_working_day')->nullable();
            $table->text('final_remarks')->nullable();

            $table->timestamps();

            $table->index(['employee_id', 'status'], 'err_emp_status_idx');
            $table->index(['supervisor_employee_id', 'status'], 'err_sup_status_idx');
            $table->index('requested_last_working_day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_resignation_requests');
    }
};
