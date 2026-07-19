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
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CompanyController extends Controller
{
    /**
     * A company domain: at least two dot-separated labels, each starting and
     * ending alphanumeric. "ktm.com" passes, "ktm" and "-ktm.com" do not.
     */
    private const DOMAIN_REGEX = '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/';

    /** Slugs reserved for the platform itself. */
    private function reservedSlugs(): array
    {
        return [(string) config('tenancy.default_slug', 'default')];
    }

    /**
     * The slug is now an internal identifier only — it is derived from the name
     * rather than entered, and stays stable for the life of the company.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'company';
        $slug = $base;
        $suffix = 2;

        while (in_array($slug, $this->reservedSlugs(), true)
            || Company::query()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }

    /** Normalise the submitted domain before it is validated or stored. */
    private function normaliseDomain(Request $request): void
    {
        if ($request->has('domain')) {
            $request->merge([
                'domain' => Str::lower(trim((string) $request->input('domain'))),
            ]);
        }
    }

    /**
     * The admin has to be reachable at the company domain, otherwise the
     * account we create could never resolve a tenant at login.
     */
    private function assertAdminEmailMatchesDomain(?string $email, string $domain): void
    {
        if ($email === null || $email === '') {
            return;
        }

        if (Company::domainFromEmail($email) !== $domain) {
            throw ValidationException::withMessages([
                'admin_email' => __('The admin email must use the company domain (@:domain).', [
                    'domain' => $domain,
                ]),
            ]);
        }
    }

    public function create(): View
    {
        return view('platform.companies.form', ['company' => new Company(), 'mode' => 'create']);
    }

    public function store(Request $request, TenantProvisioningService $provisioning): RedirectResponse
    {
        $this->normaliseDomain($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'domain' => [
                'required', 'string', 'max:255',
                'regex:' . self::DOMAIN_REGEX,
                'unique:companies,domain',
            ],
            'starts_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'domain.regex' => __('Enter a full domain such as ktm.com.'),
        ], ['admin_email' => 'admin email', 'admin_password' => 'admin password']);

        $this->validateLifecycleDates($data);
        $this->assertAdminEmailMatchesDomain($data['admin_email'], $data['domain']);

        $company = $provisioning->create(
            $data['name'],
            $this->uniqueSlug($data['name']),
            $data['domain'],
            $data['admin_email'],
            $data['admin_password'],
            $data['starts_on'] ?? null,
            $data['expires_on'] ?? null
        );

        return redirect()->route('platform.dashboard')
            ->with('success', __("Company ':name' created. Staff sign in with @:domain email addresses.", [
                'name' => $company->name,
                'domain' => $company->domain,
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
        $isDefault = $company->isDefault();
        $adminUser = $this->companyAdmin($company);

        $this->normaliseDomain($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'domain' => [
                'required', 'string', 'max:255',
                'regex:' . self::DOMAIN_REGEX,
                Rule::unique('companies', 'domain')->ignore($company->id),
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
        ], [
            'domain.regex' => __('Enter a full domain such as ktm.com.'),
        ]);

        $this->validateLifecycleDates($data);
        $this->assertAdminEmailMatchesDomain($data['admin_email'] ?? null, $data['domain']);

        // Never lock everyone out of the fallback company.
        if ($isDefault) {
            $data['status'] = 'active';
        }

        DB::transaction(function () use ($company, $data, $adminUser): void {
            $company->update([
                'name' => $data['name'],
                'domain' => $data['domain'],
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
        if ($company->isDefault()) {
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
        if ($company->isDefault()) {
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
