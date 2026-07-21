<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Self-service profile photo for account users (admins). Employees
            // keep their own avatar on the employees table; this is only for
            // users without an employee record. Stored as a relative path under
            // public/, matching the app's other upload columns.
            $table->string('avatar_path')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('avatar_path');
        });
    }
};
