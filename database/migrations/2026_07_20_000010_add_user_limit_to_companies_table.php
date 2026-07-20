<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seat cap per tenant: the most user accounts a company may hold in its own
 * database. NULL means unlimited, which is what every existing company gets —
 * adding a cap must not lock anyone out of an account they already have.
 *
 * Like every other real column on `companies`, this must also be listed in
 * Company::getCustomColumns() or stancl's VirtualColumn sweeps it into `data`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->unsignedInteger('user_limit')->nullable()->after('expires_on');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('user_limit');
        });
    }
};
