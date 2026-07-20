<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records how much of an approved leave application was covered by the employee's
     * balance ("paid") versus taken beyond it ("unpaid"). Populated at approval time;
     * payroll reads unpaid_days to reduce salary for the affected pay period.
     */
    public function up(): void
    {
        Schema::table('leave_applications', function (Blueprint $table): void {
            $table->decimal('paid_days', 8, 2)->nullable()->after('total_days');
            $table->decimal('unpaid_days', 8, 2)->nullable()->after('paid_days');
        });
    }

    public function down(): void
    {
        Schema::table('leave_applications', function (Blueprint $table): void {
            $table->dropColumn(['paid_days', 'unpaid_days']);
        });
    }
};
