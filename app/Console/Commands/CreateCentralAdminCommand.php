<?php

namespace App\Console\Commands;

use App\Models\CentralUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

/**
 * Creates a platform (landlord) administrator in the central database.
 * These accounts are intentionally not seeded — they are the keys to every
 * tenant, so they are created deliberately rather than appearing by default.
 */
class CreateCentralAdminCommand extends Command
{
    protected $signature = 'central:create-admin
        {--name= : Display name}
        {--email= : Login email}
        {--password= : Password (generated when omitted)}';

    protected $description = 'Create a platform administrator in the central database';

    public function handle(): int
    {
        $name = (string) ($this->option('name') ?: $this->ask('Name', 'Platform Admin'));
        $email = Str::lower(trim((string) ($this->option('email') ?: $this->ask('Email'))));
        $password = (string) ($this->option('password') ?: Str::password(16));

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email', 'max:255', Rule::unique('central_users', 'email')],
                'password' => ['required', 'string', 'min:8'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        CentralUser::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => true,
        ]);

        $this->info('Platform administrator created.');
        $this->line('  Console  : ' . url('/platform/login'));
        $this->line('  Email    : ' . $email);
        $this->line('  Password : ' . $password);

        return self::SUCCESS;
    }
}
