<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forms sent directly from the visitor's browser (websites whose server cannot reach the app).
 * The site is identified by the public part of its key (/api/v1/public/{site}/…); only form and
 * availability endpoints are reachable this way, and only from the website's own address.
 */
class AuthenticatePublicSite
{
    public function handle(Request $request, Closure $next): Response
    {
        $client = ApiClient::withoutGlobalScopes()->where('key_prefix', (string) $request->route('site'))->where('is_active', true)->first();
        if (! $client || ! $client->agency?->is_active) {
            return response()->json(['message' => 'Unknown website.'], 404);
        }

        $origin = $request->headers->get('Origin') ?: $request->headers->get('Referer');
        $siteHost = parse_url((string) ($client->site_url ?: $client->webhook_url), PHP_URL_HOST);
        $originHost = $origin ? parse_url($origin, PHP_URL_HOST) : null;
        $norm = fn ($h) => preg_replace('/^www\./', '', strtolower((string) $h));
        if ($siteHost && $originHost && $norm($siteHost) !== $norm($originHost)) {
            return response()->json(['message' => 'This form is only allowed on the agency website.'], 403);
        }

        $request->route()->forgetParameter('site');
        app()->instance(ApiClient::class, $client);
        $request->attributes->set('agency_id', $client->agency_id);
        if (in_array($request->input('locale', $request->query('lang')), ['mk', 'en'], true)) {
            app()->setLocale($request->input('locale', $request->query('lang')));
        }

        return $next($request);
    }
}
