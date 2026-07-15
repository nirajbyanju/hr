<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_logs')) {
            return;
        }

        try {
            DB::statement('ALTER TABLE attendance_logs DROP INDEX attendance_logs_employee_id_attendance_date_unique');
        } catch (\Throwable) {
            // Ignore if index already removed.
        }

        Schema::table('attendance_logs', function (Blueprint $table): void {
            $table->index(['employee_id', 'attendance_date'], 'attendance_logs_employee_date_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('attendance_logs')) {
            return;
        }

        try {
            Schema::table('attendance_logs', function (Blueprint $table): void {
                $table->dropIndex('attendance_logs_employee_date_idx');
            });
        } catch (\Throwable) {
            // Ignore if index missing.
        }

        Schema::table('attendance_logs', function (Blueprint $table): void {
            $table->unique(['employee_id', 'attendance_date']);
        });
    }
};
