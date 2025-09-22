<?php

use Illuminate\Support\Facades\{Config};

use function Pest\Laravel\post;

it('stores token in cookie if valid', function () {
    Config::set('devsquad-sidecar.auth_token', 'my-token');

    post('__devsquad-sidecar/token', ['token' => 'my-token'])
        ->assertOk()
        ->assertExactJson(['success' => true])
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

it('rejects when token is longer than 80 characters', function () {
    Config::set('devsquad-sidecar.auth_token', 'expected');

    $tooLongToken = Str::repeat('a', 81);

    post('__devsquad-sidecar/token', ['token' => $tooLongToken])
        ->assertSessionHasErrors('token');
});

it('rejects when token is not a string', function () {
    Config::set('devsquad-sidecar.auth_token', 'expected');

    post('__devsquad-sidecar/token', ['token' => 12345])
        ->assertSessionHasErrors('token');
});

it('queues cookie with correct expiration time', function () {
    Config::set('devsquad-sidecar.auth_token', 'expected');

    $response = post('__devsquad-sidecar/token', ['token' => 'expected'])
        ->assertOk()
        ->assertCookie('sidecar_token', 'expected');

    $cookie = $response->getCookie('sidecar_token');

    expect($cookie->getName())->toBe('sidecar_token')
        ->and($cookie->getValue())->toBe('expected')
        ->and($cookie->getExpiresTime())->toBeGreaterThan(time());
});

it('returns forbidden when provided token differs from expected', function () {
    Config::set('devsquad-sidecar.auth_token', 'expected');

    post('__devsquad-sidecar/token', ['token' => 'different'])
        ->assertForbidden();
});
