<?php

use EliteDevSquad\SidecarLaravel\Http\Middleware\SidecarMiddleware;
use EliteDevSquad\SidecarLaravel\Sidecar;
use Illuminate\Support\Facades\{Artisan, Config, URL};
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

it('accepts a relative signed link opened from another host', function () {
    $user = User::first();

    $path = URL::temporarySignedRoute('devsquad-sidecar.login-as', now()->addHour(), ['user' => $user->id], absolute: false);

    get('http://localhost:8082'.$path.'&redirect='.urlencode('http://localhost:8082/dashboard'))
        ->assertRedirect('http://localhost:8082/dashboard');

    expect(auth()->id())->toBe($user->id);
});

it('prints a login link that works on the given host', function () {
    $user = User::first();

    Artisan::call('sidecar:login-url', ['user' => $user->id, '--base' => 'http://localhost:8082', '--redirect' => '/dashboard']);

    $link = trim(Artisan::output());

    expect($link)->toStartWith('http://localhost:8082/__devsquad-sidecar/login-as/'.$user->id);

    get($link)->assertRedirect('http://localhost:8082/dashboard');

    expect(auth()->id())->toBe($user->id);
});
