<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A request to correct an attendance record's clock-in / clock-out times.
 *
 * When approved, the requested times are written back onto the day's
 * attendance_logs, so the correction actually takes effect — see
 * AttendanceRegularizationController::approve().
 *
 * The original times are snapshotted at request time so the card keeps showing
 * "what it was" even if the underlying attendance changes later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_regularizations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('attendance_log_id')->nullable()->constrained('attendance_logs')->nullOnDelete();
            $table->date('attendance_date');

            // Snapshot of the record as it stood when the request was raised.
            $table->timestamp('original_check_in_at')->nullable();
            $table->timestamp('original_check_out_at')->nullable();

            // The corrected times being asked for.
            $table->timestamp('requested_check_in_at')->nullable();
            $table->timestamp('requested_check_out_at')->nullable();

            $table->text('reason');
            $table->string('status', 20)->default('pending'); // pending | approved | rejected

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_remarks')->nullable();

            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['attendance_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_regularizations');
    }
};
