<?php

namespace EliteDevSquad\Sidecar\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/** @codeCoverageIgnore */
class SidecarMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $this->validateToken();
        $this->validateEnabled();
        $this->validateFeatures($request);

        return $next($request);
    }

    private function validateToken(): void
    {
        $sidecarToken = Cookie::get('sidecar_token');
        $expectedToken = config('devsquad-sidecar.auth_token');

        if (empty($sidecarToken) || empty($expectedToken) || $sidecarToken !== $expectedToken) {
            abort(403, 'Unauthorized.');
        }
    }

    private function validateEnabled(): void
    {
        if (! config('devsquad-sidecar.enabled')) {
            abort(403, 'Sidecar is disabled.');
        }
    }

    private function validateFeatures(Request $request): void
    {
        $path = $request->path();

        $features = [
            'commands_enabled' => ['execute-command', 'Commands are disabled.'],
            'tinker_enabled' => ['execute-tinker', 'Tinker is disabled.'],
            'fake_clock_enabled' => ['execute-fake-clock', 'Fake Clock is disabled.'],
        ];

        foreach ($features as $configKey => [$suffix, $message]) {
            if (! config("devsquad-sidecar.{$configKey}") && str_ends_with($path, $suffix)) {
                abort(403, $message);
            }
        }
    }
}
