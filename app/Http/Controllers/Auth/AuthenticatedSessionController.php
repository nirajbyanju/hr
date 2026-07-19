<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\ResolvesTenantFromEmail;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    use ResolvesTenantFromEmail;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $this->ensureIsNotRateLimited($request);

        // The company is identified by the domain part of the email
        // (nirajbyanju@ktm.com => ktm.com). This has to happen before
        // Auth::attempt: User is tenant-scoped, so without an active tenant the
        // credential lookup would search users across every company.
        $company = $this->activateTenantForEmail($credentials['email']);

        if ($company === null) {
            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages([
                'email' => __('No company is registered for this email domain.'),
            ]);
        }

        if (($reason = $company->inactiveReason()) !== null) {
            throw ValidationException::withMessages(['email' => $reason]);
        }

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        $user = Auth::user();
        if (! $user || ! $user->canAccessPortal()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $blockedStatus = $user?->blockedEmploymentStatus();
            throw ValidationException::withMessages([
                'email' => $blockedStatus
                    ? 'Your employment status is ' . str_replace('_', ' ', $blockedStatus) . '. Login is blocked. Please contact HR.'
                    : 'Your account is not approved yet. Please contact admin.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * Throttles brute-force attempts per email + IP (5 tries, then locked out
     * for the RateLimiter decay window).
     */
    protected function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    protected function throttleKey(Request $request): string
    {
        return Str::transliterate(
            Str::lower((string) $request->input('email')).'|'.$request->ip()
        );
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', __('Signed out successfully.'));
    }

    public function editPassword(): View
    {
        return view('hr.dashboard.change_password');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();
        if (! $user) {
            throw ValidationException::withMessages([
                'current_password' => 'User not authenticated.',
            ]);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()
            ->route('dashboard.password.edit')
            ->with('success', __('Password changed successfully.'));
    }
}
