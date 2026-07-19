<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Supports a two-stage approval flow: the applicant's supervisor signs off first
     * (recorded here, no balance effect), then HR gives the final approval (existing
     * status/approved_by/approved_at/approval_remarks columns). override_reason is set
     * when HR finalizes without a prior supervisor sign-off.
     */
    public function up(): void
    {
        Schema::table('leave_applications', function (Blueprint $table): void {
            $table->foreignId('supervisor_approved_by')->nullable()->after('leave_category_id')->constrained('users')->nullOnDelete();
            $table->timestamp('supervisor_approved_at')->nullable()->after('supervisor_approved_by');
            $table->text('supervisor_remarks')->nullable()->after('supervisor_approved_at');
            $table->text('override_reason')->nullable()->after('approval_remarks');
        });
    }

    public function down(): void
    {
        Schema::table('leave_applications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supervisor_approved_by');
            $table->dropColumn(['supervisor_approved_at', 'supervisor_remarks', 'override_reason']);
        });
    }
};
