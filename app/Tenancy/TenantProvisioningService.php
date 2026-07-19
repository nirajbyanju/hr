<?php

namespace App\Tenancy;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Creates a new tenant (company) and its first admin user. Shared by the
 * tenant:create console command and the platform console UI.
 */
class TenantProvisioningService
{
    public function create(
        string $name,
        string $slug,
        string $domain,
        string $adminEmail,
        string $adminPassword,
        ?string $startsOn = null,
        ?string $expiresOn = null
    ): Company
    {
        return DB::transaction(function () use ($name, $slug, $domain, $adminEmail, $adminPassword, $startsOn, $expiresOn): Company {
            $company = Company::query()->create([
                'name' => $name,
                'slug' => $slug,
                'domain' => $domain,
                'status' => 'active',
                'starts_on' => $startsOn,
                'expires_on' => $expiresOn,
            ]);

            $user = new User();
            $user->forceFill([
                'name' => 'Admin',
                'email' => $adminEmail,
                'password' => Hash::make($adminPassword),
                'account_status' => 'active',
                'approved_at' => now(),
                'company_id' => $company->id,
            ])->save();

            $adminRole = Role::query()->where('slug', 'admin')->first();
            if ($adminRole !== null) {
                $user->roles()->syncWithoutDetaching([
                    $adminRole->id => ['assigned_by' => null, 'assigned_at' => now()],
                ]);
            }

            return $company;
        });
    }
}
