<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\CentralUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Manage platform (landlord) administrators from the console.
 *
 * There is no role model here: a row in `central_users` IS a platform
 * administrator, so every administrator can manage every other one. That makes
 * lockout the real risk, and the guards below exist to prevent it — the console
 * must never be left with nobody able to sign in, and an administrator must
 * never be able to revoke their own access by accident.
 */
class AdminController extends Controller
{
    private function currentAdmin(): CentralUser
    {
        return Auth::guard('central')->user();
    }

    private function isSelf(CentralUser $admin): bool
    {
        return $admin->getKey() === $this->currentAdmin()->getKey();
    }

    /**
     * Administrators who can actually sign in, ignoring one row.
     *
     * Used to answer "would this leave anyone with access?" before disabling,
     * expiring or deleting an account. Counts usability rather than rows: a
     * disabled or expired account is not a way back in.
     */
    private function otherUsableAdminCount(CentralUser $excluding): int
    {
        return CentralUser::query()
            ->whereKeyNot($excluding->getKey())
            ->get()
            ->filter->isUsable()
            ->count();
    }

    /** Refuse a change that would leave nobody able to sign in. */
    private function assertNotLastUsableAdmin(CentralUser $admin, string $field, string $message): void
    {
        if ($admin->isUsable() && $this->otherUsableAdminCount($admin) === 0) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->input('q')),
            'status' => (string) $request->input('status', ''),
        ];

        $admins = CentralUser::query()
            ->search($filters['q'])
            ->status($filters['status'])
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        // Counts describe the whole table, not the filtered page — they are the
        // summary the filters act on, so they must not move when a filter does.
        $all = CentralUser::query()->get();

        return view('platform.admins.index', [
            'admins' => $admins,
            'filters' => $filters,
            'stats' => [
                'total' => $all->count(),
                'active' => $all->filter->isUsable()->count(),
                'disabled' => $all->where('is_active', false)->count(),
                'expired' => $all->filter->isExpired()->count(),
            ],
        ]);
    }

    public function show(CentralUser $admin): View
    {
        return view('platform.admins.show', [
            'admin' => $admin,
            'isSelf' => $this->isSelf($admin),
            'isLastUsable' => $admin->isUsable() && $this->otherUsableAdminCount($admin) === 0,
        ]);
    }

    public function create(): View
    {
        return view('platform.admins.form', ['admin' => new CentralUser(), 'mode' => 'create']);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:central_users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'expires_on' => ['nullable', 'date', 'after_or_equal:today'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $admin = CentralUser::query()->create([
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'password' => $data['password'],
            'is_active' => $request->boolean('is_active', true),
            'expires_on' => $data['expires_on'] ?? null,
        ]);

        return redirect()->route('platform.admins.show', $admin)
            ->with('success', __("Platform administrator ':name' created.", ['name' => $admin->name]));
    }

    public function edit(CentralUser $admin): View
    {
        return view('platform.admins.form', [
            'admin' => $admin,
            'mode' => 'edit',
            'isSelf' => $this->isSelf($admin),
        ]);
    }

    /**
     * Details only. The password is deliberately not editable here — it has its
     * own screen, so a routine name change can never silently reset a
     * colleague's credentials.
     */
    public function update(Request $request, CentralUser $admin): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('central_users', 'email')->ignore($admin->getKey())],
            'expires_on' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $active = $this->isSelf($admin) ? true : $request->boolean('is_active');
        $expiresOn = $data['expires_on'] ?? null;

        // An administrator editing themselves cannot switch their own account
        // off, by either control — they would be signed out by the middleware
        // on the very next request with no way back in.
        if ($this->isSelf($admin) && $expiresOn !== null && $expiresOn < today()->toDateString()) {
            throw ValidationException::withMessages([
                'expires_on' => __('You cannot set your own account to expire in the past.'),
            ]);
        }

        $wouldBeUsable = $active && ($expiresOn === null || $expiresOn >= today()->toDateString());

        if (! $wouldBeUsable) {
            $this->assertNotLastUsableAdmin(
                $admin,
                $active ? 'expires_on' : 'is_active',
                __('This is the only administrator who can sign in. Add another before disabling this one.')
            );
        }

        $admin->update([
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'is_active' => $active,
            'expires_on' => $expiresOn,
        ]);

        return redirect()->route('platform.admins.show', $admin)
            ->with('success', __('Administrator updated.'));
    }

    public function toggleStatus(CentralUser $admin): RedirectResponse
    {
        if ($this->isSelf($admin)) {
            return back()->with('error', __('You cannot disable your own account.'));
        }

        if ($admin->is_active) {
            $this->assertNotLastUsableAdmin(
                $admin,
                'is_active',
                __('This is the only administrator who can sign in. Add another before disabling this one.')
            );
        }

        $admin->update(['is_active' => ! $admin->is_active]);

        return back()->with('success', __("':name' is now :status.", [
            'name' => $admin->name,
            'status' => $admin->is_active ? __('active') : __('disabled'),
        ]));
    }

    public function editPassword(CentralUser $admin): View
    {
        return view('platform.admins.password', [
            'admin' => $admin,
            'isSelf' => $this->isSelf($admin),
        ]);
    }

    /**
     * Set another administrator's password, or change your own.
     *
     * Changing your own requires the current password; resetting someone
     * else's does not, because an administrator locked out of their account is
     * exactly who needs the reset. Both paths log the target out everywhere by
     * cycling the remember token.
     */
    public function updatePassword(Request $request, CentralUser $admin): RedirectResponse
    {
        $rules = ['password' => ['required', 'confirmed', Password::defaults()]];

        if ($this->isSelf($admin)) {
            $rules['current_password'] = ['required', 'current_password:central'];
        }

        $request->validate($rules, [
            'current_password.current_password' => __('That is not your current password.'),
        ]);

        $admin->forceFill([
            'password' => $request->input('password'),
            'remember_token' => Str::random(60),
        ])->save();

        if ($this->isSelf($admin)) {
            // Cycling the token invalidates this session's remember cookie too;
            // reissue it so the admin who just changed their own password is
            // not thrown out by their own change.
            Auth::guard('central')->login($admin);
            $request->session()->regenerate();

            return redirect()->route('platform.admins.show', $admin)
                ->with('success', __('Your password was changed.'));
        }

        return redirect()->route('platform.admins.show', $admin)
            ->with('success', __("Password changed for ':name'. They are signed out everywhere.", [
                'name' => $admin->name,
            ]));
    }

    public function destroy(CentralUser $admin): RedirectResponse
    {
        if ($this->isSelf($admin)) {
            return back()->with('error', __('You cannot delete your own account.'));
        }

        if ($this->otherUsableAdminCount($admin) === 0) {
            return back()->with('error', __('This is the only administrator who can sign in. Add another before deleting this one.'));
        }

        $name = $admin->name;
        $admin->delete();

        return redirect()->route('platform.admins.index')
            ->with('success', __("Administrator ':name' was deleted.", ['name' => $name]));
    }
}
