<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active tenant for each web request:
 *   1. the platform (landlord) console always runs in the default context
 *   2. the signed-in user's own company
 *   3. ?tenant= / X-Tenant override (local/dev only)
 *   4. no tenant — a guest on the login page has not identified one yet
 *
 * Guests are identified by the domain part of the email they submit, which
 * AuthenticatedSessionController resolves before authenticating. Once signed
 * in, the user record itself is the source of truth, so the tenant can never
 * drift out of sync with the session.
 *
 * Ordering matters: this must run after StartSession (it reads the session to
 * resolve the user) and before SubstituteBindings (route-model bindings resolve
 * through the tenant global scope, so binding first would expose other tenants'
 * records by id). See bootstrap/app.php.
 */
class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $company = $this->resolve($request);

        if ($company !== null && ! $request->is('platform', 'platform/*')) {
            $reason = $company->inactiveReason();

            if ($reason !== null) {
                abort(403, $reason);
            }
        }

        if ($company !== null) {
            app(Tenancy::class)->set($company);
        }

        return $next($request);
    }

    private function resolve(Request $request): ?Company
    {
        try {
            // The platform console always runs in the default/central context so
            // the super-admin is resolvable regardless of which company they
            // belong to or any dev override that may be set.
            if ($request->is('platform', 'platform/*')) {
                return $this->defaultCompany();
            }

            if (Auth::hasUser() || Auth::check()) {
                $company = $this->companyForUser();

                if ($company !== null) {
                    return $company;
                }
            }

            if (config('tenancy.allow_dev_override')) {
                $company = $this->devOverride($request);

                if ($company !== null) {
                    return $company;
                }
            }

            // Guest: the tenant is not known until an email is submitted.
            return null;
        } catch (\Throwable) {
            // Tenancy tables not migrated yet (fresh install / early boot) — run
            // without a tenant rather than failing the request.
            return null;
        }
    }

    private function companyForUser(): ?Company
    {
        $companyId = Auth::user()?->company_id;

        return $companyId === null
            ? null
            : Company::query()->find($companyId);
    }

    /**
     * Development-only tenant selector, so a tenant can be exercised locally
     * without creating a user for it first. The selection is remembered in a
     * cookie so it survives redirects.
     */
    private function devOverride(Request $request): ?Company
    {
        $explicit = $request->query('tenant') ?? $request->header('X-Tenant');

        if ($explicit !== null) {
            if ($explicit === '') {
                Cookie::queue(Cookie::forget('dev_tenant'));

                return null;
            }

            Cookie::queue('dev_tenant', $explicit, 120);

            return Company::query()->where('slug', $explicit)->first();
        }

        $cookie = $request->cookie('dev_tenant');

        if (is_string($cookie) && $cookie !== '') {
            return Company::query()->where('slug', $cookie)->first();
        }

        return null;
    }

    private function defaultCompany(): ?Company
    {
        return Company::query()->where('slug', config('tenancy.default_slug'))->first();
    }
}
