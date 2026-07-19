<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rebuilds a company row for a tenant database that still exists but has lost
 * it — typically after a migrate:fresh wiped the central database while the
 * tenant databases survived.
 *
 * The database is adopted as-is: no migrations, no seeding, nothing destructive.
 */
class RelinkTenantDatabaseCommand extends Command
{
    protected $signature = 'tenants:relink
        {database : Existing tenant database name, e.g. tenant_ktm_group}
        {--name= : Company display name}
        {--domain= : Company email domain, e.g. ktm.com}
        {--slug= : Internal slug (defaults to the database name minus the prefix)}';

    protected $description = 'Recreate the company row for an existing tenant database';

    public function handle(): int
    {
        $database = (string) $this->argument('database');

        if (! $this->databaseExists($database)) {
            $this->error("Database '{$database}' does not exist.");

            return self::FAILURE;
        }

        $existing = Company::query()->get()
            ->first(fn (Company $c) => $c->getAttribute('tenancy_db_name') === $database);

        if ($existing !== null) {
            $this->error("'{$database}' is already linked to company '{$existing->slug}'.");

            return self::FAILURE;
        }

        $prefix = (string) config('tenancy.database.prefix');
        $slug = Str::slug((string) ($this->option('slug') ?: Str::after($database, $prefix)));
        $name = (string) ($this->option('name') ?: Str::headline($slug));
        $domain = Str::lower(trim((string) ($this->option('domain') ?: $this->ask('Company email domain (e.g. ktm.com)'))));

        if (! preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $domain)) {
            $this->error("Invalid domain '{$domain}'. Use a full domain such as ktm.com.");

            return self::FAILURE;
        }

        if (Company::query()->where('domain', $domain)->exists()) {
            $this->error("Domain '{$domain}' is already used by another company.");

            return self::FAILURE;
        }

        if (Company::query()->where('slug', $slug)->exists()) {
            $this->error("Slug '{$slug}' is already used by another company.");

            return self::FAILURE;
        }

        // withoutEvents so TenantCreated does not fire — the database already
        // exists, and CreateDatabase would fail on it. That also means stancl's
        // id generator does not run, so the key is set explicitly.
        Company::withoutEvents(function () use ($name, $slug, $domain, $database): void {
            $company = new Company();

            $company->forceFill([
                'id' => (string) Str::uuid(),
                'name' => $name,
                'slug' => $slug,
                'domain' => $domain,
                'status' => 'active',
            ]);

            $company->setInternal('db_name', $database);
            $company->save();
        });

        $this->info("Linked '{$name}' to {$database}.");
        $this->line('  Domain : ' . $domain);
        $this->line('  Staff sign in at ' . url('/login') . ' with their @' . $domain . ' address.');
        $this->newLine();
        $this->line('Run `php artisan tenants:refresh-stats` to update the console counts.');

        return self::SUCCESS;
    }

    private function databaseExists(string $name): bool
    {
        return DB::connection('mysql')->select(
            'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
            [$name]
        ) !== [];
    }
}
