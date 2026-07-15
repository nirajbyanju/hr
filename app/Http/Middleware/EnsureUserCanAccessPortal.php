<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanAccessPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->canAccessPortal()) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $blockedStatus = $user->blockedEmploymentStatus();
        $message = $blockedStatus
            ? 'Your employment status is ' . str_replace('_', ' ', $blockedStatus) . '. Login is blocked. Please contact HR.'
            : 'Your account is not approved yet. Please contact admin.';

        return redirect()->route('login')->with('error', $message);
    }
}
