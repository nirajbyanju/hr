<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\ResolvesTenantFromEmail;
use App\Http\Controllers\Controller;
use App\Http\Middleware\InitializeTenancyFromSession;
use App\Modules\Employees\Repositories\EmployeeProfileUpdateRequestRepository;
use App\Support\UserAvatarService;
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
        // Auth::attempt: users live in per-tenant databases, so without
        // switching first the credential lookup would run against the central
        // database, which has no `users` table.
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

        // Every subsequent request re-establishes the tenant from this, via
        // InitializeTenancyFromSession. Written after regenerate() so there is
        // no doubt it survives the session-fixation guard.
        $request->session()->put(
            InitializeTenancyFromSession::SESSION_KEY,
            $company->getTenantKey()
        );

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

    public function editProfile(Request $request, EmployeeProfileUpdateRequestRepository $profileUpdateRequests): View
    {
        $employee = $request->user()?->employee;
        if ($employee) {
            $employee->load([
                'user:id,email',
                'department:id,name',
                'designation:id,name',
                'salaryGrade:id,grade_name,grade_code',
                'manager:id,employee_code,first_name,last_name',
                'addresses',
                'bankAccounts',
                'emergencyContacts',
                'documents',
            ]);

            return view('hr.employees.profile_updates.create', [
                'employee' => $employee,
                'lastRejected' => $profileUpdateRequests->latestRejectedForEmployee((int) $employee->id),
                'lastPending' => $profileUpdateRequests->latestPendingForEmployee((int) $employee->id),
            ]);
        }

        return view('hr.dashboard.profile', [
            'user' => $request->user()?->load('roles:id,name'),
        ]);
    }

    public function updateProfile(Request $request, UserAvatarService $avatars): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        if (! $user) {
            throw ValidationException::withMessages([
                'name' => 'User not authenticated.',
            ]);
        }

        // Store the new file first so a failed upload aborts before we touch the
        // record; the old file is only deleted once the new path is committed.
        $newAvatarPath = $avatars->store($request->file('avatar'));
        $oldAvatarPath = $user->avatar_path;

        $attributes = [
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
        ];

        if ($newAvatarPath !== null) {
            $attributes['avatar_path'] = $newAvatarPath;
        } elseif ($request->boolean('remove_avatar')) {
            $attributes['avatar_path'] = null;
        }

        try {
            $user->update($attributes);
        } catch (\Throwable $e) {
            // Don't leave the just-uploaded file orphaned if the write fails.
            $avatars->delete($newAvatarPath);

            throw $e;
        }

        // Replaced or explicitly removed: the previous file is now unreferenced.
        if ($newAvatarPath !== null || $request->boolean('remove_avatar')) {
            $avatars->delete($oldAvatarPath);
        }

        return redirect()
            ->route('dashboard.profile.edit')
            ->with('success', __('Profile updated successfully.'));
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
