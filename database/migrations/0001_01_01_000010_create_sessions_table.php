<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sessions live in the CENTRAL database, pinned by SESSION_CONNECTION.
 *
 * They must be readable before the tenant is known — the session payload is
 * what carries `tenant_id`, which is how each request decides which tenant
 * database to switch to. A session stored per-tenant would be unreadable at
 * the moment it is needed.
 *
 * `user_id` is a plain nullable index with no foreign key, since the user it
 * refers to lives in a tenant database. It is informational only; the tenant
 * is resolved from the payload, not from this column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
