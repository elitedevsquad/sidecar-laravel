<?php

namespace EliteDevSquad\SidecarLaravel;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Cache, DB};
use Throwable;

class FakeClock
{
    public const KEY = 'sidecar_fake_clock';

    public const EXPIRES_KEY = 'sidecar_fake_clock_expires_at';

    public const LIFETIME_KEY = 'sidecar_fake_clock_lifetime';

    /**
     * @var array<string, true>
     */
    private static array $shiftedConnections = [];

    public static function set(?DateTimeInterface $datetime, ?int $ttl = null): void
    {
        if ($datetime === null) {
            self::clear();

            return;
        }

        Carbon::setTestNow();

        $offset = $datetime->getTimestamp() - time();

        if ($ttl !== null && $ttl > 0) {
            $expiresAt = time() + $ttl;

            session([self::KEY => $offset, self::EXPIRES_KEY => $expiresAt, self::LIFETIME_KEY => $ttl]);
            Cache::put(self::KEY, $offset, $ttl);
            Cache::put(self::EXPIRES_KEY, $expiresAt, $ttl);
        } else {
            session([self::KEY => $offset]);
            session()->forget([self::EXPIRES_KEY, self::LIFETIME_KEY]);
            Cache::forever(self::KEY, $offset);
            Cache::forget(self::EXPIRES_KEY);
        }

        self::applyFromSession();
    }

    public static function clear(): void
    {
        session()->forget([self::KEY, self::EXPIRES_KEY, self::LIFETIME_KEY]);
        Cache::forget(self::KEY);
        Cache::forget(self::EXPIRES_KEY);

        Carbon::setTestNow();
    }

    public static function applyFromSession(): void
    {
        if (! self::isEnabled()) {
            return;
        }

        self::forgetExpiredSession();

        self::applyOffset(session(self::KEY), session(self::EXPIRES_KEY));
    }

    public static function applyFromCache(): void
    {
        if (! self::isEnabled()) {
            return;
        }

        self::applyOffset(Cache::get(self::KEY), Cache::get(self::EXPIRES_KEY));
    }

    public static function refreshFromCache(): void
    {
        if (! self::isEnabled()) {
            return;
        }

        Carbon::setTestNow();

        self::applyOffset(Cache::get(self::KEY), Cache::get(self::EXPIRES_KEY));
    }

    public static function offset(): ?int
    {
        if (! self::isEnabled()) {
            return null;
        }

        self::forgetExpiredSession();

        $offset = session(self::KEY);

        return is_numeric($offset) ? (int) $offset : null;
    }

    public static function expiresAt(): ?int
    {
        if (self::offset() === null) {
            return null;
        }

        $expiresAt = session(self::EXPIRES_KEY);

        return is_numeric($expiresAt) ? (int) $expiresAt : null;
    }

    public static function lifetime(): ?int
    {
        if (self::expiresAt() === null) {
            return null;
        }

        $lifetime = session(self::LIFETIME_KEY);

        return is_numeric($lifetime) ? (int) $lifetime : null;
    }

    public static function syncDatabase(?Connection $connection = null): void
    {
        if (! self::isEnabled() || ! config('devsquad-sidecar.fake_clock_database')) {
            return;
        }

        $shift = Carbon::hasTestNow() ? Carbon::now()->getTimestamp() - time() : 0;

        foreach ($connection ? [$connection] : DB::getConnections() as $each) {
            if (! in_array($each->getDriverName(), ['mysql', 'mariadb'], true)) {
                continue;
            }

            $name = (string) $each->getName();

            if ($shift === 0 && ! isset(self::$shiftedConnections[$name])) {
                continue;
            }

            try {
                if ($shift === 0) {
                    $each->statement('SET timestamp = DEFAULT');
                    unset(self::$shiftedConnections[$name]);
                } else {
                    $each->statement('SET timestamp = ?', [time() + $shift]);
                    self::$shiftedConnections[$name] = true;
                }
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    public static function current(): ?CarbonInterface
    {
        return Carbon::hasTestNow() ? Carbon::now() : null;
    }

    private static function forgetExpiredSession(): void
    {
        $expiresAt = session(self::EXPIRES_KEY);

        if (is_numeric($expiresAt) && (int) $expiresAt <= time()) {
            session()->forget([self::KEY, self::EXPIRES_KEY, self::LIFETIME_KEY]);
        }
    }

    private static function applyOffset(mixed $offset, mixed $expiresAt = null): void
    {
        if (! is_numeric($offset)) {
            return;
        }

        $offset = (int) $offset;
        $expiresAt = is_numeric($expiresAt) ? (int) $expiresAt : null;

        Carbon::setTestNow(
            fn (CarbonInterface $realNow): CarbonInterface => $expiresAt !== null && $realNow->getTimestamp() >= $expiresAt
                ? $realNow
                : $realNow->addSeconds($offset)
        );
    }

    private static function isEnabled(): bool
    {
        return ! app()->isProduction() && (bool) config('devsquad-sidecar.fake_clock_enabled');
    }
}
