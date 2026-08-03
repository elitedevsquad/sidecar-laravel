<?php

use EliteDevSquad\SidecarLaravel\FakeClock;
use EliteDevSquad\SidecarLaravel\Http\Middleware\SidecarMiddleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Cache, Session};

use function Pest\Laravel\{postJson, withoutMiddleware};

beforeEach(function () {
    Session::flush();
    Cache::flush();
    Carbon::setTestNow();
    withoutMiddleware(SidecarMiddleware::class);
});

it('sets fake clock when datetime is provided', function () {
    $fakeDate = '2025-08-25 10:30:00';

    $response = postJson('__devsquad-sidecar/execute-fake-clock', [
        'datetime' => $fakeDate,
    ])->assertOk();

    $expected = Carbon::parse($fakeDate);

    $response->assertJson([
        'output' => 'Fake clock set to '.$expected->toDateTimeString(),
    ]);

    expect(Carbon::hasTestNow())
        ->toBeTrue()
        ->and(Cache::has(FakeClock::KEY))
        ->toBeTrue()
        ->and(Carbon::now()->timestamp)
        ->toEqualWithDelta($expected->timestamp, 2);
});

it('keeps the clock moving after it is set', function () {
    postJson('__devsquad-sidecar/execute-fake-clock', [
        'datetime' => '2030-01-01 00:00:00',
    ])->assertOk();

    $first = Carbon::now();
    usleep(2000);
    $second = Carbon::now();

    expect($second->greaterThan($first))->toBeTrue();
});

it('resets fake clock when datetime is not provided', function () {
    $response = postJson('__devsquad-sidecar/execute-fake-clock')
        ->assertOk();

    $response->assertJson([
        'output' => 'Fake clock reset to real time',
    ]);

    expect(Carbon::hasTestNow())
        ->toBeFalse()
        ->and(Cache::has(FakeClock::KEY))
        ->toBeFalse();
});
