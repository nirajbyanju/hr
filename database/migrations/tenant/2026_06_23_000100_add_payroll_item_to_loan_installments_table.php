<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_installments', function (Blueprint $table): void {
            $table->foreignId('payroll_item_id')
                ->nullable()
                ->after('paid_date')
                ->constrained('payroll_items')
                ->nullOnDelete();

            $table->index(['payroll_item_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('loan_installments', function (Blueprint $table): void {
            $table->dropIndex(['payroll_item_id', 'status']);
            $table->dropConstrainedForeignId('payroll_item_id');
        });
    }
};
