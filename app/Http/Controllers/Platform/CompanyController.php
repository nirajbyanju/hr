<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Tenancy\TenantProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    /** Slugs that would collide with a MySQL system database. */
    private function reservedSlugs(): array
    {
        return ['mysql', 'information_schema', 'performance_schema', 'sys'];
    }

    /**
     * The slug is an internal identifier, derived from the name rather than
     * entered. It also becomes the tenant's database name
     * (tenant_<slug>), so it stays stable for the life of the company.
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
     * The form asks for the local part only and renders the domain beside it,
     * so "admin" arrives here rather than "admin@ktm.com". Complete it from
     * the submitted domain. A value that already carries its own @domain is
     * left untouched and checked by assertAdminEmailMatchesDomain().
     */
    private function attachDomainToAdminEmail(Request $request): void
    {
        $email = trim((string) $request->input('admin_email'));
        $domain = (string) $request->input('domain');

        if ($email === '' || $domain === '') {
            return;
        }

        // A trailing @ is what you get from typing "admin@" out of habit.
        $local = rtrim($email, '@');

        if ($local !== '' && ! str_contains($local, '@')) {
            $request->merge(['admin_email' => $local . '@' . $domain]);
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
        $this->attachDomainToAdminEmail($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'domain' => [
                'required', 'string', 'max:255',
                'regex:' . self::DOMAIN_REGEX,
                'unique:companies,domain',
            ],
            'starts_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date'],
            // Minimum 1: provisioning creates the admin account, so a cap of 0
            // would leave the company unusable the moment it is created.
            'user_limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
            // No unique:users rule — `users` lives in the tenant database,
            // which does not exist yet, and the new tenant's is empty anyway.
            'admin_email' => ['required', 'email', 'max:255'],
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
            $data['expires_on'] ?? null,
            isset($data['user_limit']) ? (int) $data['user_limit'] : null
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
        $this->normaliseDomain($request);
        $this->attachDomainToAdminEmail($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'domain' => [
                'required', 'string', 'max:255',
                'regex:' . self::DOMAIN_REGEX,
                Rule::unique('companies', 'domain')->ignore($company->getKey(), $company->getKeyName()),
            ],
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'starts_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date'],
            // Lowering the cap below the current headcount is allowed and does
            // not remove anyone; it just stops new accounts being created.
            'user_limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
            // Uniqueness of admin_email is checked inside the tenant database
            // below; a validation rule here would query central.
            'admin_email' => ['nullable', 'email', 'max:255'],
            'admin_password' => ['nullable', 'confirmed', Password::defaults()],
        ], [
            'domain.regex' => __('Enter a full domain such as ktm.com.'),
        ]);

        $this->validateLifecycleDates($data);
        $this->assertAdminEmailMatchesDomain($data['admin_email'] ?? null, $data['domain']);

        // Every read AND write of a tenant model has to happen inside run().
        // A model fetched inside run() but saved outside resolves its
        // connection at save time — by then the default is central again, and
        // the write would land in the wrong database.
        $company->run(function () use ($data): void {
            $adminUser = $this->findTenantAdmin();

            if ($adminUser === null) {
                return;
            }

            $adminUpdates = [];

            if (! empty($data['admin_email'])) {
                $clash = User::query()
                    ->where('email', $data['admin_email'])
                    ->whereKeyNot($adminUser->getKey())
                    ->exists();

                if ($clash) {
                    throw ValidationException::withMessages([
                        'admin_email' => __('That email is already used inside this company.'),
                    ]);
                }

                $adminUpdates['email'] = $data['admin_email'];
            }

            if (! empty($data['admin_password'])) {
                $adminUpdates['password'] = Hash::make($data['admin_password']);
            }

            if ($adminUpdates !== []) {
                $adminUser->forceFill($adminUpdates)->save();
            }
        });

        $company->update([
            'name' => $data['name'],
            'domain' => $data['domain'],
            'status' => $data['status'],
            'starts_on' => $data['starts_on'] ?? null,
            'expires_on' => $data['expires_on'] ?? null,
            'user_limit' => isset($data['user_limit']) ? (int) $data['user_limit'] : null,
        ]);

        return redirect()->route('platform.dashboard')->with('success', __('Company updated.'));
    }

    public function toggleStatus(Company $company): RedirectResponse
    {
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
        $name = $company->name;

        // Deleting the tenant fires TenantDeleted, which DROPs its database —
        // that is the entire teardown. End tenancy first so we are not holding
        // an open connection to the database being dropped.
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $company->delete();

        return redirect()->route('platform.dashboard')
            ->with('success', __("Company ':name' and its database were deleted.", ['name' => $name]));
    }

    /** Must be called from inside $company->run(). */
    private function findTenantAdmin(): ?User
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))
            ->orderBy('id')
            ->first();
    }

    /**
     * The company's admin user, read from inside the tenant database.
     * Returns null if the tenant database is unreachable (e.g. a provisioning
     * failure left it missing) so the console still renders.
     */
    private function companyAdmin(Company $company): ?User
    {
        if ($company->status === 'provisioning') {
            return null;
        }

        try {
            return $company->run(fn () => $this->findTenantAdmin());
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
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
