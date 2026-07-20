<?php

namespace App\Console\Commands;

use App\Tenancy\TenantStatsRefresher;
use Illuminate\Console\Command;

class RefreshTenantStatsCommand extends Command
{
    protected $signature = 'tenants:refresh-stats';

    protected $description = 'Recompute the per-company user/employee counts shown in the platform console';

    public function handle(TenantStatsRefresher $refresher): int
    {
        $result = $refresher->refreshAll();

        $this->info("Refreshed {$result['refreshed']} companies.");

        if ($result['failed'] !== []) {
            $this->warn('Could not reach: ' . implode(', ', $result['failed']));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
