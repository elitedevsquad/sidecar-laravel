<?php

namespace Tests\Feature;

use EliteDevSquad\SidecarExtensionBridge\Http\Middleware\SidecarMiddleware;
use EliteDevSquad\SidecarExtensionBridge\SidecarBridge;

use function Pest\Laravel\{postJson, withoutMiddleware};

use Tests\User;

it('logs in as given user id', function () {
    SidecarBridge::$userModel = User::class;

    $user = User::create([
        'name'  => 'Test User',
        'email' => 'test@test.com',
    ]);

    withoutMiddleware(SidecarMiddleware::class);

    postJson('__devsquad-sidecar/login-as', [
        'user_id' => $user->id,
    ])
        ->assertOk()
        ->assertJson([
            'status'   => 'success',
            'redirect' => '/',
        ]);

    expect(auth()->id())->toBe($user->id);
});
