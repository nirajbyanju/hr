<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Platform (landlord) console login, on the `central` guard.
 *
 * Authorization is guard membership: a row in `central_users` IS a platform
 * administrator, so there is no role check. Tenant staff authenticate on the
 * `web` guard against their own database and cannot reach this guard at all.
 */
class AuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::guard('central')->check()) {
            return redirect()->route('platform.dashboard');
        }

        return view('platform.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('central')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $admin = Auth::guard('central')->user();

        $reason = $admin?->inactiveReason();

        if ($admin === null || $reason !== null) {
            Auth::guard('central')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => $reason ?? __('This platform administrator account is disabled.'),
            ]);
        }

        $request->session()->regenerate();

        $admin->forceFill(['last_login_at' => now()])->save();

        // Always land in the console (ignore any tenant-app intended URL).
        return redirect()->route('platform.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('central')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('platform.login')->with('success', __('Signed out.'));
    }
}
