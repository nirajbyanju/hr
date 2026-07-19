<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Companies are identified by their own domain (e.g. "ktm.com"), which is
 * matched against the domain part of a user's login email. This replaces the
 * previous subdomain-based identification that keyed off `slug`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('domain')->nullable()->unique()->after('slug');
        });

        // Existing installs: the default company owns every seeded account, all
        // of which share one email domain. Without this backfill those users
        // could no longer resolve a tenant at login.
        DB::table('companies')
            ->where('slug', config('tenancy.default_slug', 'default'))
            ->whereNull('domain')
            ->update(['domain' => config('tenancy.default_domain', 'samriddhihr.local')]);
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropUnique(['domain']);
            $table->dropColumn('domain');
        });
    }
};
