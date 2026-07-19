<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\User;
use App\Tenancy\TenantProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CompanyController extends Controller
{
    /** Slugs that cannot be used (central / reserved subdomains). */
    private function reservedSlugs(): array
    {
        return array_merge(
            (array) config('tenancy.central_subdomains', []),
            [(string) config('tenancy.default_slug', 'default')]
        );
    }

    public function create(): View
    {
        return view('platform.companies.form', ['company' => new Company(), 'mode' => 'create']);
    }

    public function store(Request $request, TenantProvisioningService $provisioning): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required', 'string', 'max:63',
                'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/',
                Rule::notIn($this->reservedSlugs()),
                'unique:companies,slug',
            ],
            'starts_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'confirmed', Password::defaults()],
        ], [], ['admin_email' => 'admin email', 'admin_password' => 'admin password']);
        $this->validateLifecycleDates($data);

        $company = $provisioning->create(
            $data['name'],
            $data['slug'],
            $data['admin_email'],
            $data['admin_password'],
            $data['starts_on'] ?? null,
            $data['expires_on'] ?? null
        );

        return redirect()->route('platform.dashboard')
            ->with('success', __("Company ':name' created at :host", [
                'name' => $company->name,
                'host' => $company->slug . '.' . config('tenancy.domain'),
            ]));
    }

    public function edit(Company $company): View
    {
        return view('platform.companies.form', [
            'company' => $company,
            'mode' => 'edit',
            'adminUser' => $this->companyAdmin($company),
        ]);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $isDefault = $company->slug === config('tenancy.default_slug');
        $adminUser = $this->companyAdmin($company);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required', 'string', 'max:63',
                'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/',
                Rule::notIn(array_diff($this->reservedSlugs(), [$company->slug])),
                Rule::unique('companies', 'slug')->ignore($company->id),
            ],
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'starts_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date'],
            'admin_email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($adminUser?->id),
            ],
            'admin_password' => ['nullable', 'confirmed', Password::defaults()],
        ]);
        $this->validateLifecycleDates($data);

        // Never lock everyone out of the fallback company.
        if ($isDefault) {
            $data['slug'] = $company->slug;
            $data['status'] = 'active';
        }

        DB::transaction(function () use ($company, $data, $adminUser): void {
            $company->update([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'status' => $data['status'],
                'starts_on' => $data['starts_on'] ?? null,
                'expires_on' => $data['expires_on'] ?? null,
            ]);

            if ($adminUser !== null) {
                $adminUpdates = [];

                if (! empty($data['admin_email'])) {
                    $adminUpdates['email'] = $data['admin_email'];
                }

                if (! empty($data['admin_password'])) {
                    $adminUpdates['password'] = Hash::make($data['admin_password']);
                }

                if ($adminUpdates !== []) {
                    $adminUser->forceFill($adminUpdates)->save();
                }
            }
        });

        return redirect()->route('platform.dashboard')->with('success', __('Company updated.'));
    }

    public function toggleStatus(Company $company): RedirectResponse
    {
        if ($company->slug === config('tenancy.default_slug')) {
            return back()->with('error', __('The default company cannot be suspended.'));
        }

        $company->update([
            'status' => $company->status === 'active' ? 'suspended' : 'active',
        ]);

        return back()->with('success', __("Company ':name' is now :status.", [
            'name' => $company->name,
            'status' => $company->status,
        ]));
    }

    public function destroy(Company $company): RedirectResponse
    {
        if ($company->slug === config('tenancy.default_slug')) {
            return back()->with('error', __('The default company cannot be deleted.'));
        }

        DB::transaction(function () use ($company): void {
            $id = $company->id;

            // Remove the tenant's scoped data. Employee force-deletes cascade to
            // their attendance / payroll / id-card rows via existing FKs.
            Employee::query()->withoutGlobalScope('tenant')->where('company_id', $id)->forceDelete();
            Designation::query()->withoutGlobalScope('tenant')->where('company_id', $id)->forceDelete();
            Department::query()->withoutGlobalScope('tenant')->where('company_id', $id)->forceDelete();
            Announcement::query()->withoutGlobalScope('tenant')->where('company_id', $id)->forceDelete();
            Holiday::query()->withoutGlobalScope('tenant')->where('company_id', $id)->delete();
            User::query()->withoutGlobalScope('tenant')->where('company_id', $id)->delete();

            $company->delete();
        });

        return redirect()->route('platform.dashboard')
            ->with('success', __("Company ':name' and its data were deleted.", ['name' => $company->name]));
    }

    private function companyAdmin(Company $company): ?User
    {
        return User::query()
            ->withoutGlobalScope('tenant')
            ->where('company_id', $company->id)
            ->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))
            ->orderBy('id')
            ->first();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateLifecycleDates(array $data): void
    {
        if (empty($data['starts_on']) || empty($data['expires_on'])) {
            return;
        }

        if ($data['expires_on'] < $data['starts_on']) {
            throw ValidationException::withMessages([
                'expires_on' => __('The expiry date must be on or after the start date.'),
            ]);
        }
    }
}
