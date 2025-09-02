<?php

namespace Tests\Feature;

use EliteDevSquad\Sidecar\Http\Middleware\SidecarMiddleware;
use EliteDevSquad\Sidecar\Sidecar;
use Tests\User;

use function Pest\Laravel\{postJson, withoutMiddleware};

it('logs in as given user id', function () {
    Sidecar::$userModel = User::class;

    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@test.com',
    ]);

    withoutMiddleware(SidecarMiddleware::class);

    postJson('__devsquad-sidecar/login-as', [
        'user_id' => $user->id,
    ])
        ->assertOk()
        ->assertJson([
            'status' => 'success',
            'redirect' => '/',
        ]);

    expect(auth()->id())->toBe($user->id);
});
