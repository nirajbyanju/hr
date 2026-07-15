<?php

namespace App\Http\Middleware;

use App\Models\AttendanceApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidAttendanceApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = (string) $request->header('Authorization');
        if (! str_starts_with($authHeader, 'Bearer ')) {
            abort(401, __('Missing Bearer token.'));
        }

        $plainToken = trim(substr($authHeader, 7));
        if ($plainToken === '') {
            abort(401, __('Invalid Bearer token.'));
        }

        $tokenHash = hash('sha256', $plainToken);
        $client = AttendanceApiClient::query()
            ->where('token_hash', $tokenHash)
            ->where('is_active', true)
            ->first();

        if (! $client) {
            abort(401, __('Invalid API token.'));
        }

        if (! $client->allowsIp($request->ip())) {
            abort(403, __('IP not allowed for this API token.'));
        }

        $client->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('attendance_api_client', $client);

        return $next($request);
    }
}
