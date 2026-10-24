<?php

namespace App\Http\Middleware;

use App\Models\Agency;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Website API: a type the super admin turned off for the agency answers 404 (route parameter or "type" field decides) */
class AgencyModule
{
    public const BY_TYPE = ['tour' => 'tours', 'event' => 'events', 'accommodation' => 'accommodations', 'charter' => 'charters', 'visa' => 'visas'];

    public function handle(Request $request, Closure $next, ?string $module = null): Response
    {
        $module ??= self::BY_TYPE[(string) $request->input('type')] ?? null;
        if ($module === 'custom' && ($t = $request->route('type') ?? $request->input('record_type'))) {
            $module = 'custom:'.$t;
        }
        $agency = Agency::find((int) $request->attributes->get('agency_id'));
        if ($module && $agency && ! $agency->hasModule($module)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return $next($request);
    }
}
