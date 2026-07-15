<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_policies', function (Blueprint $table): void {
            $table->string('accrual_mode', 20)->default('none')->after('days_allocated');
            $table->decimal('accrual_rate_per_month', 8, 2)->nullable()->after('accrual_mode');
            $table->decimal('accrual_cap', 8, 2)->nullable()->after('accrual_rate_per_month');
        });

        Schema::table('employee_leave_balances', function (Blueprint $table): void {
            $table->decimal('earned', 8, 2)->default(0)->after('allocated');
        });
    }

    public function down(): void
    {
        Schema::table('employee_leave_balances', function (Blueprint $table): void {
            $table->dropColumn('earned');
        });

        Schema::table('leave_policies', function (Blueprint $table): void {
            $table->dropColumn(['accrual_mode', 'accrual_rate_per_month', 'accrual_cap']);
        });
    }
};
