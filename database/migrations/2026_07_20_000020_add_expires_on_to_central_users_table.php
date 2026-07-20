<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An optional end date for a platform administrator account, mirroring the
 * subscription window already on `companies`.
 *
 * NULL means the account never expires, which is what every existing
 * administrator gets — adding this must not lock anyone out of the console.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('central_users', function (Blueprint $table): void {
            $table->date('expires_on')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('central_users', function (Blueprint $table): void {
            $table->dropColumn('expires_on');
        });
    }
};
