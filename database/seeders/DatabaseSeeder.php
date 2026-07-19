<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds the CENTRAL database, which holds only platform administrators and the
 * company list. There is nothing to seed: companies are created through the
 * platform console (or `tenant:create`), and platform administrators are
 * created deliberately with `central:create-admin` rather than appearing by
 * default — they are the keys to every tenant.
 *
 * A tenant's own database is seeded by TenantDatabaseSeeder, which the
 * provisioning pipeline runs automatically when the company is created.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->command?->info('Central database needs no seed data.');
        $this->command?->line('  Create a platform administrator : php artisan central:create-admin');
        $this->command?->line('  Create a company + its database : php artisan tenant:create "Acme Ltd" acme.com');
    }
}
