<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Website API: authenticates with "X-Api-Key: ta_xxxxxxxx_…" or "Authorization: Bearer ta_…" */
class AuthenticateApiClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $client = ApiClient::findByKey($request->header('X-Api-Key') ?: $request->bearerToken());

        if (! $client || ! $client->agency?->is_active) {
            return response()->json(['message' => 'Invalid or missing API key.'], 401);
        }

        if (! $client->last_used_at || $client->last_used_at->lt(now()->subMinute())) {
            $client->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        app()->instance(ApiClient::class, $client);
        $request->attributes->set('agency_id', $client->agency_id);

        if (in_array($request->query('lang'), ['mk', 'en'], true)) {
            app()->setLocale($request->query('lang'));
        }

        return $next($request);
    }
}
