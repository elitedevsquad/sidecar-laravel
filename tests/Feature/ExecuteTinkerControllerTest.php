<?php

namespace Tests\Feature;

use EliteDevSquad\SidecarLaravel\Http\Middleware\SidecarMiddleware;

use function Pest\Laravel\{postJson, withoutMiddleware};

beforeEach(function () {
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
    $newTime = now()->addDays(2)->toDateTimeString();

    session(['sidecar_fake_clock' => $newTime]);

    postJson('__devsquad-sidecar/execute-tinker', [
        'code' => base64_encode('now()'),
    ])
        ->assertOk();

    expect(now()->toDateTimeString())->toBe($newTime);
});
