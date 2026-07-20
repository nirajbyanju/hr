<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_loans', function (Blueprint $table): void {
            $table->foreignId('applied_by')->nullable()->after('employee_id')->constrained('users')->nullOnDelete();
            $table->foreignId('supervisor_approved_by')->nullable()->after('remarks')->constrained('users')->nullOnDelete();
            $table->timestamp('supervisor_approved_at')->nullable()->after('supervisor_approved_by');
            $table->foreignId('final_approved_by')->nullable()->after('supervisor_approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('final_approved_at')->nullable()->after('final_approved_by');
            $table->foreignId('rejected_by')->nullable()->after('final_approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
        });
    }

    public function down(): void
    {
        Schema::table('employee_loans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('applied_by');
            $table->dropConstrainedForeignId('supervisor_approved_by');
            $table->dropColumn('supervisor_approved_at');
            $table->dropConstrainedForeignId('final_approved_by');
            $table->dropColumn('final_approved_at');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn('rejected_at');
        });
    }
};
