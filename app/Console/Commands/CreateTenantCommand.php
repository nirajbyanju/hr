<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use App\Tenancy\TenantProvisioningService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateTenantCommand extends Command
{
    protected $signature = 'tenant:create {name : Company display name} {slug : Subdomain slug} {--email= : Admin email} {--password= : Admin password}';

    protected $description = 'Create a new company (tenant) and its admin user';

    public function handle(TenantProvisioningService $provisioning): int
    {
        $name = (string) $this->argument('name');
        $slug = Str::slug((string) $this->argument('slug'));

        if ($slug === '') {
            $this->error('Invalid slug.');

            return self::FAILURE;
        }

        if (Company::query()->where('slug', $slug)->exists()) {
            $this->error("A company with slug '{$slug}' already exists.");

            return self::FAILURE;
        }

        $email = (string) ($this->option('email') ?: "admin@{$slug}.local");
        $password = (string) ($this->option('password') ?: Str::password(12));

        // Phase 1: emails are still globally unique (per-company uniqueness lands
        // with the MySQL move), so guard across all tenants.
        if (User::query()->withoutGlobalScope('tenant')->where('email', $email)->exists()) {
            $this->error("A user with email {$email} already exists.");

            return self::FAILURE;
        }

        $provisioning->create($name, $slug, $email, $password);

        $this->info("Company '{$name}' created.");
        $this->line('  Subdomain : ' . $slug . '.' . config('tenancy.domain'));
        $this->line('  Admin     : ' . $email);
        $this->line('  Password  : ' . $password);

        return self::SUCCESS;
    }
}
