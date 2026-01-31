<?php

namespace EliteDevSquad\SidecarLaravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** @codeCoverageIgnore */
class SidecarMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $this->validateAuth();
        $this->validatePermissions($request);
        $this->validateEnabled();

        return $next($request);
    }

    private function validateAuth(): void
    {
        if (! Auth::check()) {
            abort(403, 'Unauthorized. Please log in.');
        }
    }

    private function validatePermissions(Request $request): void
    {
        if (! str_contains($request->path(), 'execute')) {
            return;
        }

        $allowedIps = config('devsquad-sidecar.allowed_ips', []);

        if (empty($allowedIps)) {
            return;
        }

        $clientIp = $request->ip();

        foreach ($allowedIps as $allowedIp) {
            if (str_contains($clientIp, $allowedIp)) {
                return;
            }
        }

        abort(403, "Unauthorized IP: {$clientIp}. You are not authorized to execute this action.");
    }

    private function validateEnabled(): void
    {
        if (! config('devsquad-sidecar.enabled')) {
            abort(403, 'Sidecar is disabled.');
        }
    }
}
