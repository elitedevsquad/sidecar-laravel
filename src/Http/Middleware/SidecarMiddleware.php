<?php

namespace EliteDevSquad\SidecarExtensionBridge\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/** @codeCoverageIgnore */
class SidecarMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $sidecarToken = Cookie::get('sidecar_token');

        $expectedToken = config('devsquad-sidecar.auth_token');

        if (is_null($sidecarToken) || is_null($expectedToken)) {
            abort(403, 'Unauthorized.');
        }

        if ($sidecarToken !== $expectedToken) {
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}
