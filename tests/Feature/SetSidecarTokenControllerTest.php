<?php

use Illuminate\Support\Facades\{Config};

use function Pest\Laravel\post;

it('stores token in cookie if valid', function () {
    Config::set('devsquad-sidecar.auth_token', 'my-token');

    post('__devsquad-sidecar/token', ['token' => 'my-token'])
        ->assertNoContent()
        ->assertCookie('sidecar_token', 'my-token');
});

it('fails if token is missing or invalid', function () {
    Config::set('devsquad-sidecar.auth_token', 'expected');

    post('__devsquad-sidecar/token', ['token' => null])
        ->assertStatus(302);

    post('__devsquad-sidecar/token', ['token' => 'wrong'])
        ->assertForbidden();
});

it('fails if auth_token is not configured', function () {
    Config::set('devsquad-sidecar.auth_token', null);

    post('__devsquad-sidecar/token', ['token' => 'any'])
        ->assertForbidden();
});
