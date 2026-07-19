<?php

namespace App\Tenancy;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Creates a tenant company: its central row, its own database (migrated and
 * seeded), and its first admin user inside that database.
 *
 * There is no wrapping transaction, and there cannot be one. CREATE DATABASE is
 * DDL — MySQL issues an implicit commit and cannot roll it back — and the work
 * spans two connections anyway. Instead the company is created in a
 * `provisioning` state that Company::isActive() rejects, so a half-built tenant
 * is never loggable-into, and a failure triggers a compensating rollback.
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
    ): Company {
        // Creating the row fires TenantCreated, which synchronously creates the
        // database, runs database/migrations/tenant, and seeds it.
        //
        // The row is INSERTed before that pipeline runs, so a failure inside it
        // leaves a committed company behind. Catch it separately and clean up,
        // otherwise a half-provisioned company blocks the domain and slug
        // forever.
        try {
            $company = Company::create([
                'name' => $name,
                'slug' => $slug,
                'domain' => $domain,
                'status' => 'provisioning',
                'starts_on' => $startsOn,
                'expires_on' => $expiresOn,
            ]);
        } catch (Throwable $e) {
            $orphan = Company::query()->where('slug', $slug)->first();

            if ($orphan !== null) {
                $this->rollback($orphan);
            }

            throw $e;
        }

        try {
            $company->run(function () use ($adminEmail, $adminPassword): void {
                $user = new User();
                $user->forceFill([
                    'name' => 'Admin',
                    'email' => $adminEmail,
                    'password' => Hash::make($adminPassword),
                    'account_status' => 'active',
                    'approved_at' => now(),
                ])->save();

                $adminRole = Role::query()->where('slug', 'admin')->first();

                if ($adminRole === null) {
                    throw new RuntimeException(
                        'The tenant database has no "admin" role — seeding did not complete.'
                    );
                }

                $user->roles()->syncWithoutDetaching([
                    $adminRole->id => ['assigned_by' => null, 'assigned_at' => now()],
                ]);
            });

            $company->update(['status' => 'active']);
        } catch (Throwable $e) {
            $this->rollback($company);

            throw $e;
        }

        return $company->refresh();
    }

    /**
     * Undo a failed provision. Deleting the company fires TenantDeleted, which
     * drops its database.
     *
     * The inner catch matters: if provisioning failed at CreateDatabase there is
     * no database to drop, and letting DeleteDatabase throw here would mask the
     * real error. Log the orphan instead so it can be cleaned up by hand.
     */
    private function rollback(Company $company): void
    {
        try {
            $company->delete();
        } catch (Throwable $e) {
            Log::error('Tenant rollback failed; database may be orphaned.', [
                'company' => $company->slug,
                'database' => $company->getAttribute('tenancy_db_name'),
                'error' => $e->getMessage(),
            ]);

            Company::withoutEvents(fn () => $company->forceDelete());
        }
    }
}
