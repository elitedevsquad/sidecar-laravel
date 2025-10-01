<?php

namespace EliteDevSquad\SidecarLaravel\Traits;

use Closure;
use DateTimeInterface;
use Illuminate\Support\Carbon;

trait WithFakeClock
{
    public function setFakeClock(): void
    {
        $hasFakeClock = session()->has('sidecar_fake_clock') && config('devsquad-sidecar.fake_clock_enabled');

        if (! $hasFakeClock) {
            return;
        }

        /** @var Closure|DateTimeInterface|string|false|null $clock */
        $clock = session('sidecar_fake_clock');
        Carbon::setTestNow($clock);
    }
}
