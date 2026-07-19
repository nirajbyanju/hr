<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Core tenant-owned tables scoped in Phase 1 of the multi-tenancy rollout.
     *
     * @var array<int, string>
     */
    private array $tables = ['users', 'employees', 'departments', 'designations', 'holidays', 'announcements'];

    public function up(): void
    {
        // 1. Ensure a default company exists to own all pre-existing data.
        $appName = Schema::hasTable('system_settings')
            ? DB::table('system_settings')->where('key', 'app_name')->value('value')
            : null;

        $slug = (string) config('tenancy.default_slug', 'default');
        $companyId = DB::table('companies')->where('slug', $slug)->value('id');

        if (! $companyId) {
            $companyId = DB::table('companies')->insertGetId([
                'name' => $appName ?: config('app.name', 'SamriddhiHR'),
                'slug' => $slug,
                'status' => 'active',
                'settings' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. Add company_id (indexed, app-enforced FK — real FKs land with MySQL)
        //    and backfill every existing row to the default company.
        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'company_id')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->unsignedBigInteger('company_id')->nullable();
                    $blueprint->index('company_id');
                });
            }

            DB::table($table)->whereNull('company_id')->update(['company_id' => $companyId]);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'company_id')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->dropColumn('company_id');
                });
            }
        }
    }
};
