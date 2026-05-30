<?php

use EliteDevSquad\SidecarLaravel\Http\Middleware\SidecarMiddleware;
use Illuminate\Support\Carbon;

use function Pest\Laravel\{postJson, withoutMiddleware};

beforeEach(function () {
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
    $newTime = now()->addDays(2)->toDateTimeString();

    session(['sidecar_fake_clock' => $newTime]);

    postJson('__devsquad-sidecar/execute-command', [
        'command' => 'view:clear',
    ])
        ->assertOk()
        ->assertContent('{"output":"\n   INFO  Compiled views cleared successfully.  \n\n"}');

    expect(now()->toDateTimeString())->toBe($newTime);
});

it('does not change clock when clock input is not provided', function () {
    $this->freezeTime();
    $originalTime = now()->toDateTimeString();

    postJson('__devsquad-sidecar/execute-command', [
        'command' => 'view:clear',
    ])
        ->assertOk()
        ->assertContent('{"output":"\n   INFO  Compiled views cleared successfully.  \n\n"}');

    expect(now()->toDateTimeString())->toBe($originalTime);
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
