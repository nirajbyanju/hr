<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The original "allow multiple entries per day" migration dropped the unique index
     * using MySQL-only raw SQL, so on SQLite the unique(employee_id, attendance_date)
     * index was never actually removed. This corrective migration drops it portably
     * for databases still stuck in that state, restoring multi-entry-per-day support.
     */
    public function up(): void
    {
        if (! Schema::hasTable('attendance_logs')) {
            return;
        }

        try {
            Schema::table('attendance_logs', function (Blueprint $table): void {
                $table->dropUnique('attendance_logs_employee_id_attendance_date_unique');
            });
        } catch (\Throwable) {
            // Index already absent — nothing to do.
        }
    }

    public function down(): void
    {
        // Intentionally not restored: re-adding the unique index would break the
        // multiple-entries-per-day behaviour this migration exists to enable.
    }
};
