<?php

use EliteDevSquad\SidecarLaravel\Http\Middleware\SidecarMiddleware;
use Illuminate\Support\Facades\Config;

use function Pest\Laravel\{getJson, withoutMiddleware};

beforeEach(function () {
    withoutMiddleware(SidecarMiddleware::class);
});

afterEach(function () {
    $file = storage_path('logs/laravel.log');

    if (file_exists($file)) {
        unlink($file);
    }
});

it('returns the recent log errors', function () {
    Config::set('devsquad-sidecar.health_enabled', true);

    $directory = storage_path('logs');

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $now = now()->format('Y-m-d H:i:s');

    file_put_contents($directory.'/laravel.log', "[{$now}] local.ERROR: redis is down\n");

    getJson('__devsquad-sidecar/logs')
        ->assertOk()
        ->assertJson([
            'log_errors' => 1,
            'recent_logs' => [['level' => 'ERROR', 'message' => 'redis is down']],
        ])
        ->assertJsonStructure(['last_error_at', 'updated_at']);
});

it('refuses when logs are disabled', function () {
    Config::set('devsquad-sidecar.health_enabled', false);

    getJson('__devsquad-sidecar/logs')->assertForbidden();
});
