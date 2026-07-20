<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Tenancy\TenantProvisioningService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateTenantCommand extends Command
{
    protected $signature = 'tenant:create {name : Company display name} {domain : Company email domain, e.g. ktm.com} {--email= : Admin email} {--password= : Admin password}';

    protected $description = 'Create a new company (tenant) and its admin user';

    public function handle(TenantProvisioningService $provisioning): int
    {
        $name = (string) $this->argument('name');
        $domain = Str::lower(trim((string) $this->argument('domain')));

        if (! preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $domain)) {
            $this->error("Invalid domain '{$domain}'. Use a full domain such as ktm.com.");

            return self::FAILURE;
        }

        if (Company::query()->where('domain', $domain)->exists()) {
            $this->error("A company with domain '{$domain}' already exists.");

            return self::FAILURE;
        }

        $email = (string) ($this->option('email') ?: "admin@{$domain}");
        $password = (string) ($this->option('password') ?: Str::password(12));

        // The tenant is resolved from the email domain at login, so an admin
        // outside the company domain could never sign in.
        if (Company::domainFromEmail($email) !== $domain) {
            $this->error("Admin email {$email} must use the company domain (@{$domain}).");

            return self::FAILURE;
        }

        // No cross-tenant email check: emails are unique per tenant database
        // now, and the unique index on companies.domain plus the domain match
        // above already make a cross-tenant collision impossible.
        $this->line('Provisioning database, running migrations and seeding...');

        $company = $provisioning->create($name, $this->uniqueSlug($name), $domain, $email, $password);

        $this->info("Company '{$name}' created.");
        $this->line('  Domain    : ' . $domain);
        $this->line('  Database  : ' . $company->getAttribute('tenancy_db_name'));
        $this->line('  Admin     : ' . $email);
        $this->line('  Password  : ' . $password);

        return self::SUCCESS;
    }

    /** The slug is an internal identifier, and also the tenant database name. */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'company';
        $slug = $base;
        $suffix = 2;
        $reserved = ['mysql', 'information_schema', 'performance_schema', 'sys'];

        while (in_array($slug, $reserved, true) || Company::query()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }
}
