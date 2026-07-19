<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table): void {
            $table->string('announcement_type', 30)->default('announcement')->after('title');
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending')->after('published_by');
            $table->foreignId('approved_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');

            $table->index(['announcement_type', 'is_active']);
            $table->index(['approval_status', 'is_active']);
            $table->index(['publish_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table): void {
            $table->dropIndex(['announcement_type', 'is_active']);
            $table->dropIndex(['approval_status', 'is_active']);
            $table->dropIndex(['publish_at', 'expires_at']);

            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['announcement_type', 'approval_status', 'approved_at']);
        });
    }
};
