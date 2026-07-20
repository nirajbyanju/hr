<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds the CENTRAL database, which holds platform administrators and the
 * company list.
 *
 * Only the platform administrator is seeded. Companies are not: each one owns a
 * separate database that has to be provisioned, which happens through the
 * console or `tenant:create`. A tenant's own database is seeded by
 * TenantDatabaseSeeder as part of that provisioning.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(CentralAdminSeeder::class);

        $this->command?->newLine();
        $this->command?->line('  Add a company + its database : php artisan tenant:create "Acme Ltd" acme.com');
    }
}
