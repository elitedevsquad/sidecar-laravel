<?php

namespace EliteDevSquad\SidecarLaravel\Http\Middleware;

use Closure;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class FakeClockMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (session()->has('sidecar_fake_clock')) {
            Log::debug('Fake clock activated by DevSquad Sidecar');

            /** @var Closure|DateTimeInterface|string|false|null $clock */
            $clock = session('sidecar_fake_clock');
            Carbon::setTestNow($clock);
        }

        return $next($request);
    }
}
