<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('private_notes', function (Blueprint $table): void {
            $table->boolean('is_completed')->default(false)->after('is_pinned');
            $table->timestamp('completed_at')->nullable()->after('is_completed');
            $table->index(['user_id', 'is_completed'], 'pn_user_completed_idx');
        });
    }

    public function down(): void
    {
        Schema::table('private_notes', function (Blueprint $table): void {
            $table->dropIndex('pn_user_completed_idx');
            $table->dropColumn(['is_completed', 'completed_at']);
        });
    }
};
