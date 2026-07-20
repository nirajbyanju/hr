<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ejects a platform administrator whose account was disabled or expired after
 * they signed in.
 *
 * Checked on every console request rather than only at login, for the same
 * reason InitializeTenancyFromSession re-checks the company: disabling an
 * account has to take effect immediately, not whenever the session happens to
 * lapse. Without this, revoking access would leave an open console session
 * fully privileged until the admin logged out on their own.
 */
class EnsureCentralAdminIsUsable
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('central')->user();

        if ($admin === null) {
            return $next($request);
        }

        // Read straight from the database: the authenticated instance can be
        // served from the session payload and would not show a change made by
        // another administrator moments ago.
        $current = $admin->fresh();

        if ($current !== null && $current->inactiveReason() === null) {
            return $next($request);
        }

        $reason = $current?->inactiveReason() ?? __('This platform administrator account no longer exists.');

        Auth::guard('central')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('platform.login')->withErrors(['email' => $reason]);
    }
}
