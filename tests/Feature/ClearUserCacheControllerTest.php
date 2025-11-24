<?php

use EliteDevSquad\SidecarLaravel\Http\Middleware\SidecarMiddleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use function Pest\Laravel\post;
use function Pest\Laravel\withoutMiddleware;

beforeEach(function () {
    withoutMiddleware(SidecarMiddleware::class);
});

it('clears user cache successfully', function () {
    Cache::shouldReceive('forget')
        ->once()
        ->with('sidecar_users')
        ->andReturnTrue();

    post('__devsquad-sidecar/clear-user-cache')
        ->assertOk()
        ->assertExactJson([
            'output' => 'User cache cleared successfully.',
        ]);
});

it('returns error message when an exception occurs', function () {
    Cache::shouldReceive('forget')
        ->once()
        ->with('sidecar_users')
        ->andThrow(new Exception('failure'));

    post('__devsquad-sidecar/clear-user-cache')
        ->assertOk()
        ->assertExactJson([
            'output' => 'Error executing command: failure',
        ]);
});
