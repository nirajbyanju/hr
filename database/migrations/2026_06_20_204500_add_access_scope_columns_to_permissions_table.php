<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table): void {
            $table->string('access_scope', 40)->default('general')->after('description');
            $table->string('access_scope_label', 80)->default('General')->after('access_scope');
            $table->string('access_scope_badge_class', 80)->default('bg-secondary')->after('access_scope_label');
            $table->string('access_scope_description')->nullable()->after('access_scope_badge_class');
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table): void {
            $table->dropColumn([
                'access_scope',
                'access_scope_label',
                'access_scope_badge_class',
                'access_scope_description',
            ]);
        });
    }
};
