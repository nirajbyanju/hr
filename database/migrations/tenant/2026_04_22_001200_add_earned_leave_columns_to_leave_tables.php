<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_policies', function (Blueprint $table): void {
            if (! Schema::hasColumn('leave_policies', 'is_earned_leave')) {
                $table->boolean('is_earned_leave')->default(false)->after('carry_forward_limit');
            }

            if (! Schema::hasColumn('leave_policies', 'earned_credit_frequency')) {
                $table->string('earned_credit_frequency', 20)->nullable()->after('is_earned_leave');
            }

            if (! Schema::hasColumn('leave_policies', 'earned_credit_days')) {
                $table->decimal('earned_credit_days', 8, 2)->default(0)->after('earned_credit_frequency');
            }
        });

        Schema::table('employee_leave_balances', function (Blueprint $table): void {
            if (! Schema::hasColumn('employee_leave_balances', 'earned_credited')) {
                $table->decimal('earned_credited', 8, 2)->default(0)->after('carried_forward');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_leave_balances', function (Blueprint $table): void {
            if (Schema::hasColumn('employee_leave_balances', 'earned_credited')) {
                $table->dropColumn('earned_credited');
            }
        });

        Schema::table('leave_policies', function (Blueprint $table): void {
            $columns = ['is_earned_leave', 'earned_credit_frequency', 'earned_credit_days'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('leave_policies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
