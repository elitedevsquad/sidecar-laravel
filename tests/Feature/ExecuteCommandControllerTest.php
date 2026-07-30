<?php

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

it('executes a valid artisan command', function () {
    postJson('__devsquad-sidecar/execute-command', [
        'command' => 'view:clear',
    ])
        ->assertOk()
        ->assertContent('{"output":"\n   INFO  Compiled views cleared successfully.  \n\n"}');
});

it('change clock when clock input is provided', function () {
    $target = now()->addDays(2);

    FakeClock::set($target);

    postJson('__devsquad-sidecar/execute-command', [
        'command' => 'view:clear',
    ])
        ->assertOk()
        ->assertContent('{"output":"\n   INFO  Compiled views cleared successfully.  \n\n"}');

    expect(now()->timestamp)->toEqualWithDelta($target->timestamp, 2);
});

it('does not change clock when clock input is not provided', function () {
    $originalTime = now()->timestamp;

    postJson('__devsquad-sidecar/execute-command', [
        'command' => 'view:clear',
    ])
        ->assertOk()
        ->assertContent('{"output":"\n   INFO  Compiled views cleared successfully.  \n\n"}');

    expect(Carbon::hasTestNow())
        ->toBeFalse()
        ->and(now()->timestamp)
        ->toEqualWithDelta($originalTime, 2);
});

it('handles exception when executing artisan command', function () {
    postJson('__devsquad-sidecar/execute-command', [
        'command' => 'bad',
    ])
        ->assertOk()
        ->assertJson([
            'output' => 'Error executing command: The command "bad" does not exist.',
        ]);
});

it('handles default output when no output is provided', function () {
    postJson('__devsquad-sidecar/execute-command', [
        'command' => 'tinker --execute="empty"',
    ])
        ->assertOk()
        ->assertContent('{"output":"Command executed successfully - tinker --execute=\"empty\""}');
});
