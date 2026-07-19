<?php

namespace App\Tenancy;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Throwable;

/**
 * Recomputes the denormalised per-company counts shown on the platform console.
 *
 * The console reads these columns instead of aggregating live, because a live
 * cross-tenant GROUP BY no longer exists: each company's users and employees
 * are in a different database.
 */
class TenantStatsRefresher
{
    /**
     * @return array{refreshed: int, failed: array<int, string>}
     */
    public function refreshAll(): array
    {
        $refreshed = 0;
        $failed = [];

        foreach (Company::query()->where('status', '!=', 'provisioning')->get() as $company) {
            if ($this->refresh($company)) {
                $refreshed++;

                continue;
            }

            $failed[] = $company->slug;
        }

        return ['refreshed' => $refreshed, 'failed' => $failed];
    }

    /**
     * One tenant fails independently — a dropped or half-provisioned database
     * must not stop the rest from refreshing, nor take down the console.
     */
    public function refresh(Company $company): bool
    {
        try {
            $counts = $company->run(fn () => [
                'users_count' => User::query()->count(),
                'employees_count' => Employee::query()->count(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        $company->update($counts + ['stats_synced_at' => now()]);

        return true;
    }
}
