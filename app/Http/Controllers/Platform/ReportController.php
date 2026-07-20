<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Per-tenant usage reporting for the platform console: how many accounts each
 * company has created against its seat limit, and where it sits in its
 * subscription window.
 *
 * Reads the denormalised counters on `companies` for the same reason the
 * dashboard does — aggregating live would open one connection per tenant on
 * every page load, and a single unreachable tenant database would take the
 * whole report down. Rows carry stats_synced_at so a stale figure is visible
 * as stale rather than silently wrong.
 */
class ReportController extends Controller
{
    public function index(): View
    {
        $rows = $this->rows();

        return view('platform.reports.usage', [
            'rows' => $rows,
            'totals' => [
                'companies' => $rows->count(),
                'users' => $rows->sum('users'),
                'employees' => $rows->sum('employees'),
                'seats' => $rows->whereNotNull('limit')->sum('limit'),
                'at_limit' => $rows->where('at_limit', true)->count(),
                'expiring_soon' => $rows->where('expiring_soon', true)->count(),
            ],
        ]);
    }

    /**
     * The same report as a CSV download. Streamed rather than built in memory
     * so the response size stays flat as tenant count grows.
     */
    public function export(): StreamedResponse
    {
        $rows = $this->rows();
        $filename = 'tenant-usage-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');

            fputcsv($out, [
                'Company', 'Domain', 'Database', 'Status', 'User accounts',
                'Account limit', 'Seats left', 'Usage %', 'Employees',
                'Start date', 'Expiry date', 'Days to expiry', 'Counts synced at',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['name'],
                    $row['domain'],
                    $row['database'],
                    $row['status'],
                    $row['users'],
                    $row['limit'] ?? 'Unlimited',
                    $row['seats_left'] ?? 'Unlimited',
                    $row['usage_percent'] === null ? '' : $row['usage_percent'] . '%',
                    $row['employees'],
                    $row['starts_on'],
                    $row['expires_on'],
                    $row['days_left'] ?? '',
                    $row['synced_at'],
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * One flat row per company, shared by the HTML table and the CSV so the
     * download can never drift from what is on screen.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(): Collection
    {
        return Company::query()->orderBy('name')->get()->map(function (Company $company): array {
            $users = (int) $company->users_count;
            $limit = $company->user_limit;
            $daysLeft = $company->daysUntilExpiry();

            return [
                'id' => $company->getKey(),
                'name' => $company->name,
                'domain' => $company->domain ?? '—',
                'database' => $company->getAttribute('tenancy_db_name') ?? '—',
                'status' => $this->statusLabel($company),
                'users' => $users,
                'limit' => $limit,
                'seats_left' => $company->seatsRemaining(),
                // Capped at 100 so a company over a lowered limit reads as
                // "full" rather than showing an impossible 140%.
                'usage_percent' => $limit === null ? null : min(100, (int) round($users / max(1, $limit) * 100)),
                'at_limit' => $limit !== null && $users >= $limit,
                'employees' => (int) $company->employees_count,
                'starts_on' => $company->starts_on?->format('Y-m-d') ?? '',
                'expires_on' => $company->expires_on?->format('Y-m-d') ?? '',
                'days_left' => $daysLeft,
                'expiring_soon' => $daysLeft !== null && $daysLeft >= 0 && $daysLeft <= 30,
                'synced_at' => $company->stats_synced_at?->format('Y-m-d H:i') ?? 'Never',
            ];
        });
    }

    private function statusLabel(Company $company): string
    {
        return match (true) {
            $company->status === 'provisioning' => 'Provisioning',
            $company->isExpired() => 'Expired',
            $company->isPending() => 'Pending',
            $company->status === 'active' => 'Active',
            default => 'Suspended',
        };
    }
}
