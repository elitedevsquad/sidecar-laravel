<?php

use EliteDevSquad\SidecarLaravel\FakeClock;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Cache, Queue, Session};

beforeEach(function () {
    Session::flush();
    Cache::flush();
    Carbon::setTestNow();
    config(['devsquad-sidecar.fake_clock_enabled' => true]);
});

it('applies the persisted clock in a fresh process without a session', function () {
    $target = Carbon::parse('2030-06-15 08:00:00');

    // A browser request persists the clock into the shared cache...
    FakeClock::set($target);

    // ...then a separate CLI process (e.g. schedule:run) boots with a clean
    // in-memory state and no session, and re-hydrates the clock from cache.
    Carbon::setTestNow();
    Session::flush();
    expect(Carbon::hasTestNow())->toBeFalse();

    FakeClock::applyFromCache();

    expect(Carbon::hasTestNow())
        ->toBeTrue()
        ->and(Carbon::now()->timestamp)
        ->toEqualWithDelta($target->timestamp, 2);
});

it('applies a numeric-string offset (e.g. Redis-style cache return)', function () {
    // Redis returns numeric values as strings.
    Cache::forever(FakeClock::KEY, '3600');

    FakeClock::applyFromCache();

    expect(Carbon::hasTestNow())
        ->toBeTrue()
        ->and(Carbon::now()->timestamp)
        ->toEqualWithDelta(time() + 3600, 2);
});

it('keeps time moving when applied from cache', function () {
    FakeClock::set(Carbon::parse('2030-06-15 08:00:00'));
    Carbon::setTestNow();
    FakeClock::applyFromCache();

    $first = Carbon::now();
    usleep(2000);
    $second = Carbon::now();

    expect($second->greaterThan($first))->toBeTrue();
});

it('clears the clock (session and cache) and returns to real time', function () {
    FakeClock::set(Carbon::parse('2030-06-15 08:00:00'));

    FakeClock::clear();

    expect(Carbon::hasTestNow())
        ->toBeFalse()
        ->and(Cache::has(FakeClock::KEY))
        ->toBeFalse()
        ->and(session()->has(FakeClock::KEY))
        ->toBeFalse();
});

it('resets the clock when set(null) is called', function () {
    FakeClock::set(Carbon::parse('2030-06-15 08:00:00'));

    FakeClock::set(null);

    expect(Cache::has(FakeClock::KEY))->toBeFalse()
        ->and(session()->has(FakeClock::KEY))->toBeFalse();
});

it('applies the per-browser clock from the session', function () {
    $target = Carbon::parse('2030-06-15 08:00:00');
    FakeClock::set($target);

    // Drop only the in-memory mock; the session still holds the offset.
    Carbon::setTestNow();

    FakeClock::applyFromSession();

    expect(Carbon::now()->timestamp)->toEqualWithDelta($target->timestamp, 2);
});

it('exposes the current fake now only when a clock is active', function () {
    expect(FakeClock::current())->toBeNull();

    $target = Carbon::parse('2030-06-15 08:00:00');
    FakeClock::set($target);

    expect(FakeClock::current())->not->toBeNull()
        ->and(FakeClock::current()?->timestamp)->toEqualWithDelta($target->timestamp, 2);
});

it('does nothing when the feature is disabled', function () {
    config(['devsquad-sidecar.fake_clock_enabled' => false]);

    Cache::forever(FakeClock::KEY, 3600);

    FakeClock::applyFromCache();

    expect(Carbon::hasTestNow())
        ->toBeFalse()
        ->and(FakeClock::current())
        ->toBeNull();
});

it('reports the session offset in seconds', function () {
    expect(FakeClock::offset())->toBeNull();

    FakeClock::set(Carbon::now()->addDays(3));

    expect(FakeClock::offset())->toEqualWithDelta(3 * 86400, 2);

    FakeClock::set(null);

    expect(FakeClock::offset())->toBeNull();
});

it('reports no offset when the feature is disabled', function () {
    session([FakeClock::KEY => 3600]);
    config(['devsquad-sidecar.fake_clock_enabled' => false]);

    expect(FakeClock::offset())->toBeNull();
});

it('keeps the clock until it is reset when no ttl is given', function () {
    FakeClock::set(Carbon::now()->addHour());

    expect(FakeClock::expiresAt())->toBeNull()
        ->and(Cache::has(FakeClock::EXPIRES_KEY))->toBeFalse();
});

it('reports when a clock with a ttl returns to real time', function () {
    FakeClock::set(Carbon::now()->addHour(), 3600);

    expect(FakeClock::expiresAt())->toEqualWithDelta(time() + 3600, 2);

    Carbon::setTestNow();

    expect(Cache::get(FakeClock::EXPIRES_KEY))->toEqualWithDelta(time() + 3600, 2);
});

it('keeps the cache entry for the ttl in real time when a clock was already set', function () {
    FakeClock::set(Carbon::now()->addDays(2));
    FakeClock::set(Carbon::now()->addHour(), 60);

    Carbon::setTestNow();
    Carbon::setTestNow(Carbon::now()->addSeconds(61));

    expect(Cache::has(FakeClock::KEY))->toBeFalse();
});

it('returns to real time once the ttl is over', function () {
    session([FakeClock::KEY => 3600, FakeClock::EXPIRES_KEY => time() - 1]);

    FakeClock::applyFromSession();

    expect(Carbon::hasTestNow())->toBeFalse()
        ->and(FakeClock::offset())->toBeNull()
        ->and(FakeClock::expiresAt())->toBeNull()
        ->and(session()->has(FakeClock::KEY))->toBeFalse();
});

it('does not forget a clock in the cache when this browser\'s clock expires', function () {
    Cache::forever(FakeClock::KEY, 7200);
    session([FakeClock::KEY => 3600, FakeClock::EXPIRES_KEY => time() - 1]);

    FakeClock::applyFromSession();

    expect(Cache::get(FakeClock::KEY))->toBe(7200);
});

it('returns to real time in a long-running process once the ttl is over', function () {
    Cache::forever(FakeClock::KEY, 3600);
    Cache::forever(FakeClock::EXPIRES_KEY, time() - 1);

    FakeClock::applyFromCache();

    expect(Carbon::now()->timestamp)->toEqualWithDelta(time(), 2);
});

it('drops a previous ttl when the clock is set without one', function () {
    FakeClock::set(Carbon::now()->addHour(), 60);
    FakeClock::set(Carbon::now()->addHour());

    expect(FakeClock::expiresAt())->toBeNull()
        ->and(Cache::has(FakeClock::EXPIRES_KEY))->toBeFalse();
});

it('tells how long a clock with a ttl was set for', function () {
    FakeClock::set(Carbon::now()->addHour(), 1800);

    expect(FakeClock::lifetime())->toBe(1800);

    FakeClock::set(Carbon::now()->addHour());

    expect(FakeClock::lifetime())->toBeNull();
});

it('lets a queue worker pick up a clock set after it started', function () {
    $seen = null;

    Queue::before(function () use (&$seen): void {
        $seen = Carbon::now()->timestamp;
    });

    Cache::forever(FakeClock::KEY, 86400);

    Queue::connection('sync')->push(new QueuedNothing());

    expect($seen)->toEqualWithDelta(time() + 86400, 2);
});

it('lets a queue worker return to real time when the clock is reset', function () {
    Cache::forever(FakeClock::KEY, 86400);
    FakeClock::applyFromCache();

    Cache::forget(FakeClock::KEY);

    Queue::connection('sync')->push(new QueuedNothing());

    expect(Carbon::now()->timestamp)->toEqualWithDelta(time(), 2);
});

it('moves the database clock on MySQL while traveling, and puts it back after', function () {
    config(['devsquad-sidecar.fake_clock_database' => true]);

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getName')->andReturn('mysql');
    $connection->shouldReceive('statement')
        ->once()
        ->withArgs(fn ($query, $bindings) => $query === 'SET timestamp = ?' && abs($bindings[0] - (time() + 3600)) <= 2);
    $connection->shouldReceive('statement')->once()->with('SET timestamp = DEFAULT');

    Cache::forever(FakeClock::KEY, 3600);
    FakeClock::applyFromCache();
    FakeClock::syncDatabase($connection);

    FakeClock::clear();
    FakeClock::syncDatabase($connection);
    FakeClock::syncDatabase($connection);
});

it('leaves the database clock alone unless it is turned on', function () {
    config(['devsquad-sidecar.fake_clock_database' => false]);

    $connection = Mockery::mock(Connection::class);
    $connection->shouldNotReceive('statement');

    Cache::forever(FakeClock::KEY, 3600);
    FakeClock::applyFromCache();
    FakeClock::syncDatabase($connection);
});

it('leaves databases without a per-connection timestamp alone', function () {
    config(['devsquad-sidecar.fake_clock_database' => true]);

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('pgsql');
    $connection->shouldNotReceive('statement');

    Cache::forever(FakeClock::KEY, 3600);
    FakeClock::applyFromCache();
    FakeClock::syncDatabase($connection);
});

class QueuedNothing implements ShouldQueue
{
    public function handle(): void {}
}

it('keeps going when the database refuses the timestamp', function () {
    config(['devsquad-sidecar.fake_clock_database' => true]);

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mariadb');
    $connection->shouldReceive('getName')->andReturn('mariadb');
    $connection->shouldReceive('statement')->andThrow(new RuntimeException('read only'));

    Cache::forever(FakeClock::KEY, 3600);
    FakeClock::applyFromCache();

    FakeClock::syncDatabase($connection);

    expect(Carbon::now()->timestamp)->toEqualWithDelta(time() + 3600, 2);
});
