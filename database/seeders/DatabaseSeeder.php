<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SystemSettingsSeeder::class,
            AdminUserSeeder::class,
            LeavePolicySeeder::class,
            TaskLookupSeeder::class,
            TaskTagSeeder::class,
        ]);

        // Demo login accounts (config/demo_users.php) are for local development only
        // and must never be seeded into staging or production.
        if (app()->environment('local')) {
            $this->call(DemoUserSeeder::class);
        }
    }
}
