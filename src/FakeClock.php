<?php

namespace EliteDevSquad\SidecarLaravel;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class FakeClock
{
    public const KEY = 'sidecar_fake_clock';

    public static function set(?DateTimeInterface $datetime): void
    {
        if ($datetime === null) {
            self::clear();

            return;
        }

        $offset = $datetime->getTimestamp() - time();

        session([self::KEY => $offset]);
        Cache::forever(self::KEY, $offset);

        self::applyFromSession();
    }

    public static function clear(): void
    {
        session()->forget(self::KEY);
        Cache::forget(self::KEY);

        Carbon::setTestNow();
    }

    public static function applyFromSession(): void
    {
        if (! self::isEnabled()) {
            return;
        }

        self::applyOffset(session(self::KEY));
    }

    public static function applyFromCache(): void
    {
        if (! self::isEnabled()) {
            return;
        }

        self::applyOffset(Cache::get(self::KEY));
    }

    public static function current(): ?CarbonInterface
    {
        return Carbon::hasTestNow() ? Carbon::now() : null;
    }

    private static function applyOffset(mixed $offset): void
    {
        if (! is_numeric($offset)) {
            return;
        }

        $offset = (int) $offset;

        Carbon::setTestNow(
            fn (CarbonInterface $realNow): CarbonInterface => $realNow->addSeconds($offset)
        );
    }

    private static function isEnabled(): bool
    {
        return ! app()->isProduction() && (bool) config('devsquad-sidecar.fake_clock_enabled');
    }
}
