<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Finds tenant databases that no company row points at, and drops them.
 *
 * These accumulate because dropping the central database (migrate:fresh, or
 * deleting company rows by hand) does not touch tenant databases — the migrator
 * has no idea they exist. The row disappears; the database stays.
 *
 * Use `tenants:relink` instead if the data is still wanted.
 */
class PruneTenantDatabasesCommand extends Command
{
    protected $signature = 'tenants:prune
        {--force : Drop without confirmation}
        {--prefix= : Database prefix to scan (defaults to tenancy.database.prefix)}';

    protected $description = 'Drop tenant databases that no company row points at';

    public function handle(): int
    {
        $prefix = (string) ($this->option('prefix') ?: config('tenancy.database.prefix'));

        if ($prefix === '') {
            $this->error('Refusing to scan with an empty prefix — that would match every database.');

            return self::FAILURE;
        }

        $orphans = $this->orphans($prefix);

        if ($orphans === []) {
            $this->info('No orphaned tenant databases.');

            return self::SUCCESS;
        }

        $this->warn(count($orphans) . ' orphaned tenant database(s) found:');

        foreach ($orphans as $database) {
            $this->line(sprintf(
                '  %-28s users=%-5s employees=%s',
                $database,
                $this->countIn($database, 'users'),
                $this->countIn($database, 'employees')
            ));
        }

        $this->newLine();
        $this->warn('Dropping a database cannot be undone. To keep one, run:');
        $this->line('  php artisan tenants:relink <database> --name="…" --domain=example.com');
        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('Drop all of the above?', false)) {
            $this->info('Nothing dropped.');

            return self::SUCCESS;
        }

        foreach ($orphans as $database) {
            DB::connection('mysql')->statement('DROP DATABASE `' . $database . '`');
            $this->line('  dropped ' . $database);
        }

        $this->info(count($orphans) . ' database(s) dropped.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function orphans(string $prefix): array
    {
        // Note "test_tenant_x" does not start with "tenant_", so the default
        // prefix will not sweep up test databases. Those have their own command.
        $databases = collect(DB::connection('mysql')->select('SHOW DATABASES'))
            ->map(fn ($row) => array_values((array) $row)[0])
            ->filter(fn (string $name) => str_starts_with($name, $prefix));

        $linked = Company::query()->get()
            ->map(fn (Company $company) => $company->getAttribute('tenancy_db_name'))
            ->filter()
            ->all();

        return $databases->reject(fn (string $name) => in_array($name, $linked, true))
            ->values()
            ->all();
    }

    private function countIn(string $database, string $table): string
    {
        try {
            return (string) DB::connection('mysql')
                ->selectOne("SELECT COUNT(*) AS c FROM `{$database}`.`{$table}`")->c;
        } catch (\Throwable) {
            return '-';
        }
    }
}
