<?php

use EliteDevSquad\SidecarLaravel\Http\Middleware\SidecarMiddleware;
use EliteDevSquad\SidecarLaravel\Sidecar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Tests\User;

use function Pest\Laravel\{actingAs, getJson, withoutMiddleware};
use function PHPUnit\Framework\assertInstanceOf;

beforeEach(function () {
    $this->user = User::first();
    $this->sidecar = Mockery::mock(Sidecar::class);

    $this->sidecar->shouldReceive('getUserModel')->andReturn(User::class);

    app()->instance(Sidecar::class, $this->sidecar);

    actingAs($this->user);
});

it('returns full JSON payload', function () {
    $this->sidecar->shouldReceive('getUserMap')->andReturn([
        'id' => 'id',
        'name' => 'name',
        'email' => 'email',
        'role' => 'admin',
    ]);
    $this->sidecar->shouldReceive('getUserQueryBuilder')->andReturn(User::query());

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
        'authenticated' => true,
        'current_user' => $this->user->id,
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

it('should replace userQuery with custom query', function () {
    $this->sidecar->shouldReceive('getUserMap')->andReturn([
        'id' => 'id',
        'name' => 'name',
        'email' => 'email',
        'role' => 'admin',
    ]);

    $this->sidecar->shouldReceive('getUserQueryBuilder')->andReturnUsing(function () {
        return User::query()->where('id', $this->user->id);
    });

    withoutMiddleware(SidecarMiddleware::class);

    $response = getJson('__devsquad-sidecar/data')
        ->assertOk();

    $response->assertJsonCount(1, 'users');

    $response->assertJson([
        'users' => [
            [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'role' => 'user',
            ],
        ],
    ]);
});

it('resolves user query builder instance', function () {
    Sidecar::$userBuilder = User::query()->where('id', 1);

    assertInstanceof(Builder::class, Sidecar::$userBuilder);
});

it('retrieves nested relation fields from userMap', function () {
    $this->sidecar->shouldReceive('getUserQueryBuilder')->andReturn(User::with('role'));
    $this->sidecar->shouldReceive('getUserMap')->andReturn(
        [
            'id' => 'id',
            'name' => 'name',
            'email' => 'email',
            'role' => 'role.name',
        ]
    );

    withoutMiddleware(SidecarMiddleware::class);

    $response = getJson('__devsquad-sidecar/data')
        ->assertOk();

    $response->assertJsonCount(2, 'users');

    $response->assertJson([
        'users' => [
            [
                'id' => 1,
                'name' => 'Luan',
                'email' => 'luanfreitas10@protonmail.com',
                'role' => 'admin',
            ],
            [
                'id' => 2,
                'name' => 'John Doe',
                'email' => 'jonh_doe@gmail.com',
                'role' => 'user',
            ],
        ],
    ]);
});

it('aborts when sidecar is disabled', function () {
    Config::set('devsquad-sidecar.enabled', false);

    getJson('__devsquad-sidecar/data')
        ->assertForbidden();
});

it('returns empty users array when without_users is true', function () {
    $this->sidecar->shouldReceive('getUserMap')->andReturn([
        'id' => 'id',
        'name' => 'name',
        'email' => 'email',
        'role' => 'admin',
    ]);
    $this->sidecar->shouldNotReceive('getUserQueryBuilder');

    Config::set('devsquad-sidecar.enabled', true);

    withoutMiddleware(SidecarMiddleware::class);

    $response = getJson('__devsquad-sidecar/data?without_users=true')
        ->assertOk();

    $response->assertJson([
        'users' => [],
    ]);
});

it('returns users when without_users is false', function () {
    $this->sidecar->shouldReceive('getUserMap')->andReturn([
        'id' => 'id',
        'name' => 'name',
        'email' => 'email',
        'role' => 'admin',
    ]);
    $this->sidecar->shouldReceive('getUserQueryBuilder')->andReturn(User::query());

    Config::set('devsquad-sidecar.enabled', true);

    withoutMiddleware(SidecarMiddleware::class);

    $response = getJson('__devsquad-sidecar/data?without_users=false')
        ->assertOk();

    $response->assertJsonCount(2, 'users');
});
