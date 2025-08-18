<?php

use EliteDevSquad\SidecarExtensionBridge\Http\Middleware\SidecarMiddleware;
use EliteDevSquad\SidecarExtensionBridge\SidecarBridge;
use Illuminate\Support\Facades\Config;

use function Pest\Laravel\{actingAs, getJson, withoutMiddleware};

use Tests\User;

beforeEach(function () {
    $this->user   = User::first();
    $this->bridge = Mockery::mock(SidecarBridge::class);

    $this->bridge->shouldReceive('getUserModel')->andReturn(User::class);
    $this->bridge->shouldReceive('getUserMap')->andReturn([
        'id'    => 'id',
        'name'  => 'name',
        'email' => 'email',
        'role'  => 'role',
    ]);

    app()->instance(SidecarBridge::class, $this->bridge);

    actingAs($this->user);
});

it('returns full JSON payload', function () {
    Config::set('app.name', 'My App');
    Config::set('devsquad-sidecar-bridge.enabled', true);
    Config::set('devsquad-sidecar-bridge.auth_token', 'test_token');
    Config::set('devsquad-sidecar-bridge.branch_name', 'main');
    Config::set('devsquad-sidecar-bridge.links', ['docs' => 'url']);
    Config::set('devsquad-sidecar-bridge.commands', ['migrate']);
    Config::set('devsquad-sidecar-bridge.branch_url', 'http://repo/branch');

    withoutMiddleware(SidecarMiddleware::class);

    $response = getJson('__devsquad-sidecar/data')
        ->assertOk();

    $response->assertJson([
        'project_name' => 'My App',
        'enabled'      => true,
        'currentUser'  => $this->user->id,
        'branch'       => 'main',
        'database'     => ':memory:',
        'environment'  => 'testing',
        'users'        => [
            [
                'id'    => $this->user->id,
                'name'  => $this->user->name,
                'email' => $this->user->email,
                'role'  => 'user',
            ],
        ],
        'links' => [
            'docs' => 'url',
        ],
        'commands'   => ['migrate'],
        'branch_url' => 'http://repo/branch',
    ]);
});
