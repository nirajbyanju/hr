<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Switches the request onto the signed-in user's tenant database.
 *
 * The tenant id is read from the SESSION, never from the user record. That is
 * deliberate: once users live in per-tenant databases you cannot load the user
 * to discover their tenant, because you need the tenant first in order to know
 * which database to look in. The session is written at login (see
 * AuthenticatedSessionController) and is readable as soon as StartSession has
 * run, with no database dependency beyond central.
 *
 * Ordering (pinned in bootstrap/app.php): after StartSession, because it reads
 * the session; before SubstituteBindings, because route-model bindings resolve
 * against whatever the default connection is — binding first would query the
 * central database for tenant records.
 */
class InitializeTenancyFromSession
{
    public const SESSION_KEY = 'tenant_id';

    public function handle(Request $request, Closure $next): Response
    {
        // The platform console is central by definition.
        if ($request->is('platform', 'platform/*')) {
            return $next($request);
        }

        if (config('tenancy.allow_dev_override')) {
            $this->applyDevOverride($request);
        }

        $tenantId = $request->session()->get(self::SESSION_KEY);

        // Guest (e.g. the login page): the tenant is not known until an email
        // is submitted, and the login controller resolves it from that.
        if ($tenantId === null) {
            return $next($request);
        }

        $company = Company::find($tenantId);

        // The tenant was deleted while this session was open.
        if ($company === null) {
            return $this->endSession($request, __('This company account no longer exists.'));
        }

        // Re-checked on every request, not just at login, so suspending or
        // expiring a company takes effect immediately for users already in.
        if (($reason = $company->inactiveReason()) !== null) {
            return $this->endSession($request, $reason);
        }

        tenancy()->initialize($company);

        return $next($request);
    }

    /**
     * Local-only: ?tenant=<slug> switches tenant without logging in, so a
     * tenant can be exercised in development without creating a user first.
     */
    private function applyDevOverride(Request $request): void
    {
        $slug = $request->query('tenant');

        if ($slug === null) {
            return;
        }

        if ($slug === '') {
            $request->session()->forget(self::SESSION_KEY);

            return;
        }

        $company = Company::query()->where('slug', $slug)->first();

        if ($company !== null) {
            $request->session()->put(self::SESSION_KEY, $company->getTenantKey());
        }
    }

    private function endSession(Request $request, string $reason): Response
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => $reason]);
    }
}
