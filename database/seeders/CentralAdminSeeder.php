<?php

namespace Database\Seeders;

use App\Models\CentralUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates the platform (landlord) administrator in the CENTRAL database, so a
 * fresh `migrate:fresh --seed` leaves you able to sign in at /platform/login.
 *
 * Tenant companies and their admins are NOT seeded — those are created through
 * the console or `tenant:create`, which provisions a database per company.
 */
class CentralAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = Str::lower(trim((string) env('PLATFORM_ADMIN_EMAIL', 'super@samriddhihr.local')));
        $name = (string) env('PLATFORM_ADMIN_NAME', 'Platform Admin');
        $password = (string) env('PLATFORM_ADMIN_PASSWORD', 'P@ssword123');

        $existing = CentralUser::query()->where('email', $email)->first();

        // Never silently reset the password of an account that already exists —
        // re-seeding a live install must not hand out known credentials.
        // Use `php artisan central:create-admin` to add another, or reset by hand.
        if ($existing !== null) {
            $this->command?->info('Platform administrator already exists: ' . $email);
            $this->command?->line('  Password unchanged. Run `php artisan central:create-admin` to add another.');

            return;
        }

        CentralUser::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => true,
        ]);

        $this->command?->info('Platform administrator created.');
        $this->command?->line('  Console  : ' . url('/platform/login'));
        $this->command?->line('  Email    : ' . $email);
        $this->command?->line('  Password : ' . $password);

        if (! app()->environment('local')) {
            $this->command?->warn('Set PLATFORM_ADMIN_PASSWORD in .env before seeding outside local development.');
        }
    }
}
