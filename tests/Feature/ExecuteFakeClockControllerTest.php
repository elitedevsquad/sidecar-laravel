<?php

use EliteDevSquad\SidecarLaravel\Http\Middleware\SidecarMiddleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;

use function Pest\Laravel\{postJson, withoutMiddleware};

beforeEach(function () {
    Session::flush();
    Carbon::setTestNow();
    withoutMiddleware(SidecarMiddleware::class);
});

it('sets fake clock when datetime is provided', function () {
    $fakeDate = '2025-08-25 10:30:00';

    $response = postJson('__devsquad-sidecar/execute-fake-clock', [
        'datetime' => $fakeDate,
    ])->assertOk();

    $expected = Carbon::parse($fakeDate)
        ->setTimeFromTimeString(now()->toTimeString());

    $response->assertJson([
        'output' => 'Fake clock set to '.$expected->toDateTimeString(),
    ]);

    expect(Carbon::getTestNow()->toDateTimeString())
        ->toBe($expected->toDateTimeString())
        ->and(session('sidecar_fake_clock'))
        ->toBe($expected->toDateTimeString());
});

it('resets fake clock when datetime is not provided', function () {
    $response = postJson('__devsquad-sidecar/execute-fake-clock')
        ->assertOk();

    $response->assertJson([
        'output' => 'Fake clock reset to real time',
    ]);

    expect(Carbon::getTestNow())
        ->toBeNull()
        ->and(session()->has('sidecar_fake_clock'))
        ->toBeFalse();
});
