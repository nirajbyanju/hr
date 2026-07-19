<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Tenancy\TenantStatsRefresher;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        // A single central query. Counting live across N tenant databases would
        // mean N connections plus 2N COUNT(*) queries per page load, and one
        // broken tenant database would take the whole console down. The
        // counters are refreshed by tenants:refresh-stats and the button below.
        $companies = Company::query()->orderBy('name')->get();

        $stats = [
            'companies' => $companies->count(),
            'active' => $companies->filter->isActive()->count(),
            'pending' => $companies->filter->isPending()->count(),
            'suspended' => $companies->where('status', 'suspended')->count(),
            'expired' => $companies->filter->isExpired()->count(),
            'provisioning' => $companies->where('status', 'provisioning')->count(),
            'users' => $companies->sum('users_count'),
            'employees' => $companies->sum('employees_count'),
        ];

        return view('platform.dashboard', [
            'companies' => $companies,
            'stats' => $stats,
        ]);
    }

    public function refreshStats(TenantStatsRefresher $refresher): RedirectResponse
    {
        $result = $refresher->refreshAll();

        if ($result['failed'] === []) {
            return back()->with('success', __('Counts refreshed for :count companies.', [
                'count' => $result['refreshed'],
            ]));
        }

        return back()->with('error', __('Refreshed :count, but could not reach: :failed', [
            'count' => $result['refreshed'],
            'failed' => implode(', ', $result['failed']),
        ]));
    }
}
