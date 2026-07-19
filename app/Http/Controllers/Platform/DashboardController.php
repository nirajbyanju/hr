<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $companies = Company::query()->orderBy('name')->get();

        // Cross-tenant counts: bypass the tenant scope.
        $userCounts = User::query()->withoutGlobalScope('tenant')
            ->selectRaw('company_id, count(*) as aggregate')
            ->groupBy('company_id')->pluck('aggregate', 'company_id');

        $employeeCounts = Employee::query()->withoutGlobalScope('tenant')
            ->selectRaw('company_id, count(*) as aggregate')
            ->groupBy('company_id')->pluck('aggregate', 'company_id');

        $stats = [
            'companies' => $companies->count(),
            'active' => $companies->filter->isActive()->count(),
            'pending' => $companies->filter->isPending()->count(),
            'suspended' => $companies->where('status', 'suspended')->count(),
            'expired' => $companies->filter->isExpired()->count(),
            'users' => User::query()->withoutGlobalScope('tenant')->count(),
            'employees' => Employee::query()->withoutGlobalScope('tenant')->count(),
        ];

        return view('platform.dashboard', [
            'companies' => $companies,
            'userCounts' => $userCounts,
            'employeeCounts' => $employeeCounts,
            'stats' => $stats,
            'defaultSlug' => config('tenancy.default_slug', 'default'),
            'tenancyDomain' => config('tenancy.domain', 'localhost'),
        ]);
    }
}
