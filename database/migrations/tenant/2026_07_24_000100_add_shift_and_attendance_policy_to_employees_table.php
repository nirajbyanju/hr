<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attaches an employee to a work shift and an attendance policy.
 *
 * Both tables already existed as standalone catalogues with nothing pointing at
 * them, so attendance was derived from one company-wide work window for every
 * employee. With these columns an employee on a night shift or a different
 * grace period is measured against their own schedule.
 *
 * Nullable: an employee without an assignment keeps falling back to the
 * company-wide Settings values, so existing rows are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->foreignId('shift_id')->nullable()->after('salary_grade_id')
                ->constrained('shifts')->nullOnDelete();
            $table->foreignId('attendance_policy_id')->nullable()->after('shift_id')
                ->constrained('attendance_policies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shift_id');
            $table->dropConstrainedForeignId('attendance_policy_id');
        });
    }
};
