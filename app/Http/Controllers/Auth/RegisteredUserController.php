<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\ResolvesTenantFromEmail;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    use ResolvesTenantFromEmail;

    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        // Resolve the tenant BEFORE the rules that touch the database. The
        // `unique:users` rule below builds its query from the default
        // connection, and until we switch that is central — which has no
        // `users` table.
        $email = $request->string('email')->lower()->trim()->value();

        if ($this->activateTenantForEmail($email) === null) {
            throw ValidationException::withMessages([
                'email' => __('No company is registered for this email domain.'),
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'account_status' => 'pending_approval',
        ]);

        event(new Registered($user));

        return redirect()->route('login')->with('success', __('Signup successful. Please wait for admin approval before login.'));
    }
}
