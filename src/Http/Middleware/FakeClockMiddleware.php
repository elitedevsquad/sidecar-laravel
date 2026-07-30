<?php

namespace EliteDevSquad\SidecarLaravel\Http\Middleware;

use Closure;
use EliteDevSquad\SidecarLaravel\FakeClock;
use Illuminate\Http\Request;

class FakeClockMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        FakeClock::applyFromSession();

        return $next($request);
    }
}
