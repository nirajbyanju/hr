<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * A crashed test run leaves its tenant databases behind, and stancl refuses to
 * create a database that already exists. Run this to clean up.
 */
class DropTestTenantDatabasesCommand extends Command
{
    protected $signature = 'tenants:drop-test-dbs {--prefix=test_tenant_ : Database name prefix to drop}';

    protected $description = 'Drop leftover test tenant databases';

    public function handle(): int
    {
        $prefix = (string) $this->option('prefix');

        if ($prefix === '' || $prefix === 'tenant_') {
            $this->error('Refusing to run without a test-specific prefix — that would drop real tenant databases.');

            return self::FAILURE;
        }

        $databases = collect(DB::connection('mysql')->select('SHOW DATABASES'))
            ->map(fn ($row) => array_values((array) $row)[0])
            ->filter(fn (string $name) => str_starts_with($name, $prefix));

        foreach ($databases as $name) {
            DB::connection('mysql')->statement('DROP DATABASE `' . $name . '`');
            $this->line('  dropped ' . $name);
        }

        $this->info($databases->count() . ' test tenant database(s) dropped.');

        return self::SUCCESS;
    }
}
