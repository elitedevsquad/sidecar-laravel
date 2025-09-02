<?php

use EliteDevSquad\Sidecar\Http\Middleware\SidecarMiddleware;
use EliteDevSquad\Sidecar\Sidecar;
use Illuminate\Support\Facades\Config;
use Tests\User;

use function Pest\Laravel\{actingAs, getJson, withoutMiddleware};

beforeEach(function () {
    $this->user = User::first();
    $this->bridge = Mockery::mock(Sidecar::class);

    $this->bridge->shouldReceive('getUserModel')->andReturn(User::class);
    $this->bridge->shouldReceive('getUserMap')->andReturn([
        'id' => 'id',
        'name' => 'name',
        'email' => 'email',
        'role' => 'role',
    ]);

    app()->instance(Sidecar::class, $this->bridge);

    actingAs($this->user);
});

it('returns full JSON payload', function () {
    Config::set('app.name', 'My App');
    Config::set('devsquad-sidecar.enabled', true);
    Config::set('devsquad-sidecar.auth_token', 'test_token');
    Config::set('devsquad-sidecar.branch_name', 'main');
    Config::set('devsquad-sidecar.links', ['docs' => 'url']);
    Config::set('devsquad-sidecar.commands', ['migrate']);
    Config::set('devsquad-sidecar.branch_url', 'http://repo/branch');

    withoutMiddleware(SidecarMiddleware::class);

    $response = getJson('__devsquad-sidecar/data')
        ->assertOk();

    $response->assertJson([
        'project_name' => 'My App',
        'enabled' => true,
        'currentUser' => $this->user->id,
        'branch' => 'main',
        'database' => ':memory:',
        'environment' => 'testing',
        'users' => [
            [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'role' => 'user',
            ],
        ],
        'links' => [
            'docs' => 'url',
        ],
        'commands' => ['migrate'],
        'branch_url' => 'http://repo/branch',
    ]);
});
