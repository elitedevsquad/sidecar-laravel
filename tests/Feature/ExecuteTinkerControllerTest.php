<?php

namespace Tests\Feature;

use EliteDevSquad\SidecarLaravel\FakeClock;
use EliteDevSquad\SidecarLaravel\Http\Middleware\SidecarMiddleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\{postJson, withoutMiddleware};

beforeEach(function () {
    Cache::flush();
    Carbon::setTestNow();
    withoutMiddleware(SidecarMiddleware::class);
});

it('handles exception when executing tinker code', function () {

    postJson('__devsquad-sidecar/execute-tinker', ['code' => base64_encode('bad')])
        ->assertOk()
        ->assertJson([
            'output' => 'Error executing code: oops',
        ]);
});

it('change clock when clock input is provided', function () {
    $target = now()->addDays(2);

    FakeClock::set($target);

    postJson('__devsquad-sidecar/execute-tinker', [
        'code' => base64_encode('now()'),
    ])
        ->assertOk();

    expect(now()->timestamp)->toEqualWithDelta($target->timestamp, 2);
});

it('refuses tinker code when tinker is disabled', function (string $route) {
    config(['devsquad-sidecar.tinker_enabled' => false]);

    postJson("__devsquad-sidecar/{$route}", ['code' => base64_encode('1 + 1')])
        ->assertForbidden();
})->with(['execute-tinker', 'execute-tinker-on-queue']);
