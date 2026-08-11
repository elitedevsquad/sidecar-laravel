<?php

use EliteDevSquad\SidecarLaravel\Http\Middleware\SidecarMiddleware;
use EliteDevSquad\SidecarLaravel\Sidecar;
use Illuminate\Support\Facades\{Config, URL};
use Tests\User;

use function Pest\Laravel\{get, withoutMiddleware};

beforeEach(function () {
    Sidecar::$userModel = User::class;
    Config::set('devsquad-sidecar.enabled', true);
    withoutMiddleware(SidecarMiddleware::class);
});

it('logs in as the user via a signed url and redirects to root', function () {
    $user = User::first();

    $url = URL::temporarySignedRoute('devsquad-sidecar.login-as', now()->addHour(), ['user' => $user->id]);

    get($url)->assertRedirect('/');

    expect(auth()->id())->toBe($user->id);
});

it('redirects to a same-host destination', function () {
    $user = User::first();
    $destination = url('/dashboard');

    $url = URL::temporarySignedRoute('devsquad-sidecar.login-as', now()->addHour(), ['user' => $user->id]);

    get($url.'&redirect='.urlencode($destination))->assertRedirect($destination);

    expect(auth()->id())->toBe($user->id);
});

it('ignores an external redirect and falls back to root', function () {
    $user = User::first();

    $url = URL::temporarySignedRoute('devsquad-sidecar.login-as', now()->addHour(), ['user' => $user->id]);

    get($url.'&redirect='.urlencode('https://evil.example.com/steal'))->assertRedirect('/');
});

it('aborts on an invalid signature', function () {
    $user = User::first();

    get('__devsquad-sidecar/login-as/'.$user->id.'?expires=9999999999&signature=deadbeef')
        ->assertForbidden();

    expect(auth()->check())->toBeFalse();
});

it('aborts when sidecar is disabled', function () {
    Config::set('devsquad-sidecar.enabled', false);

    $user = User::first();

    $url = URL::temporarySignedRoute('devsquad-sidecar.login-as', now()->addHour(), ['user' => $user->id]);

    get($url)->assertForbidden();
});
