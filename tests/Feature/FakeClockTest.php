<?php

use EliteDevSquad\SidecarLaravel\FakeClock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Cache, Session};

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
