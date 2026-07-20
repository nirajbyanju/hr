<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform (landlord) administrators. Lives in the CENTRAL database only —
 * tenant staff live in their own company's database and can never appear here.
 *
 * There is no role table: a row in this table IS a platform administrator.
 * A single privilege level does not justify a parallel role system, and a
 * tenant-side "super-admin" role would be a privilege-escalation path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('central_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('central_users');
    }
};
