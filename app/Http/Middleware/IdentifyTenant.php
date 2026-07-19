<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active tenant for each web request:
 *   1. ?tenant= / X-Tenant override (local/dev only)
 *   2. the request subdomain (acme.example.com => "acme")
 *   3. the configured default company (single-company installs / central host)
 */
class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $company = $this->resolve($request);

        if ($company !== null && ! $company->isActive() && ! $request->is('platform', 'platform/*')) {
            $message = match (true) {
                $company->isExpired() => __('This company account has expired. Please contact support.'),
                $company->isPending() => __('This company account is not active yet. Please contact support.'),
                default => __('This company account is suspended. Please contact support.'),
            };

            abort(403, $message);
        }

        if ($company !== null) {
            app(Tenancy::class)->set($company);
        }

        return $next($request);
    }

    private function resolve(Request $request): ?Company
    {
        try {
            // The platform (landlord) console always runs in the default/central
            // context so the super-admin is resolvable regardless of any override.
            if ($request->is('platform', 'platform/*')) {
                return $this->defaultCompany();
            }

            if (config('tenancy.allow_dev_override')) {
                $explicit = $request->query('tenant') ?? $request->header('X-Tenant');

                if ($explicit !== null) {
                    // Remember the selection in a cookie so it survives the
                    // post-login redirect on plain localhost — no subdomain/DNS
                    // needed to test a tenant in development.
                    if ($explicit === '') {
                        Cookie::queue(Cookie::forget('dev_tenant'));
                    } else {
                        Cookie::queue('dev_tenant', $explicit, 120);
                        $company = Company::query()->where('slug', $explicit)->first();
                        if ($company !== null) {
                            return $company;
                        }
                    }
                } elseif (is_string($cookie = $request->cookie('dev_tenant')) && $cookie !== '') {
                    $company = Company::query()->where('slug', $cookie)->first();
                    if ($company !== null) {
                        return $company;
                    }
                }
            }

            $subdomain = $this->subdomain($request->getHost());

            if ($subdomain !== null && ! in_array($subdomain, config('tenancy.central_subdomains', []), true)) {
                $company = Company::query()->where('slug', $subdomain)->first();
                if ($company !== null) {
                    return $company;
                }
            }

            return $this->defaultCompany();
        } catch (\Throwable) {
            // Tenancy tables not migrated yet (fresh install / early boot) — run
            // without a tenant rather than failing the request.
            return null;
        }
    }

    private function subdomain(string $host): ?string
    {
        $domain = (string) config('tenancy.domain');

        if ($host === $domain || ! str_ends_with($host, '.' . $domain)) {
            return null;
        }

        return substr($host, 0, -1 * (strlen($domain) + 1));
    }

    private function defaultCompany(): ?Company
    {
        return Company::query()->where('slug', config('tenancy.default_slug'))->first();
    }
}
