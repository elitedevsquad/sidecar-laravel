<?php

use EliteDevSquad\SidecarLaravel\Http\Middleware\SidecarMiddleware;
use EliteDevSquad\SidecarLaravel\{Sidecar, UserDirectory};
use Illuminate\Support\Facades\{Config, DB};
use Tests\User;

use function Pest\Laravel\{getJson, withoutMiddleware};

beforeEach(function () {
    withoutMiddleware(SidecarMiddleware::class);
    Config::set('devsquad-sidecar.enabled', true);

    $this->previousMap = Sidecar::$userMap;
    $this->previousBuilder = Sidecar::$userBuilder;

    Sidecar::$userMap = ['id' => 'id', 'name' => 'name', 'email' => 'email', 'role' => 'role.name'];
    Sidecar::$userBuilder = User::with('role');

    foreach (range(3, 45) as $i) {
        DB::table('users')->insert(['name' => "Seeded {$i}", 'email' => "seeded{$i}@example.com"]);
        DB::table('roles')->insert(['name' => $i % 2 ? 'editor' : 'viewer', 'user_id' => $i]);
    }
});

afterEach(function () {
    Sidecar::$userMap = $this->previousMap;
    Sidecar::$userBuilder = $this->previousBuilder;
});

it('returns the first 30 users with pagination meta', function () {
    getJson('__devsquad-sidecar/users')
        ->assertOk()
        ->assertJsonCount(30, 'data')
        ->assertJsonPath('data.0.id', 1)
        ->assertJsonPath('data.0.role', 'admin')
        ->assertJsonStructure(['data' => [['id', 'name', 'email', 'role', 'login_url']]])
        ->assertJsonPath('meta', ['current_page' => 1, 'per_page' => 30, 'total' => 45, 'has_more' => true]);
});

it('returns the next page', function () {
    getJson('__devsquad-sidecar/users?page=2')
        ->assertOk()
        ->assertJsonCount(15, 'data')
        ->assertJsonPath('data.0.id', 31)
        ->assertJsonPath('meta.has_more', false)
        ->assertJsonPath('roles', null);
});

it('caps the page size', function () {
    getJson('__devsquad-sidecar/users?per_page=500')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);
});

it('searches by name and email', function () {
    getJson('__devsquad-sidecar/users?search=john')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'John Doe');

    getJson('__devsquad-sidecar/users?search=seeded12@')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', 12);
});

it('filters by a role read through a relation', function () {
    getJson('__devsquad-sidecar/users?role=admin')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', 1);
});

it('lists the roles on the first page', function () {
    getJson('__devsquad-sidecar/users')
        ->assertOk()
        ->assertJsonPath('roles', ['admin', 'editor', 'user', 'viewer']);
});

it('returns only the requested ids', function () {
    getJson('__devsquad-sidecar/users?ids=2,40')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', 2)
        ->assertJsonPath('data.1.id', 40);
});

it('ignores map entries that are not columns', function () {
    Sidecar::$userMap = ['id' => 'id', 'name' => 'full_name', 'email' => 'email', 'role' => 'missing.name'];

    getJson('__devsquad-sidecar/users?search=jonh_doe')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    getJson('__devsquad-sidecar/users?role=admin')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    getJson('__devsquad-sidecar/users')
        ->assertOk()
        ->assertJsonPath('roles', null);
});

it('finds a user by id when the search is a number', function () {
    getJson('__devsquad-sidecar/users?search=40')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', 40);
});

it('returns nothing when no mapped field can be searched', function () {
    Sidecar::$userMap = ['id' => 'id', 'name' => 'full_name', 'email' => 'missing', 'role' => 'role.name'];

    getJson('__devsquad-sidecar/users?search=luan')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('filters and lists roles stored in a plain column', function () {
    Sidecar::$userMap = ['id' => 'id', 'name' => 'name', 'email' => 'email', 'role' => 'name'];

    $response = getJson('__devsquad-sidecar/users?role=Luan')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', 1);

    expect($response->json('roles'))->toContain('Luan', 'John Doe', 'Seeded 3');
});

it('has no role list without a role mapping', function () {
    Sidecar::$userMap = ['id' => 'id', 'name' => 'name', 'email' => 'email', 'role' => ''];

    getJson('__devsquad-sidecar/users')
        ->assertOk()
        ->assertJsonPath('roles', null);
});

it('skips a relation path that is not a relation', function (string $path) {
    Sidecar::$userMap = ['id' => 'id', 'name' => 'name', 'email' => 'email', 'role' => $path];

    getJson('__devsquad-sidecar/users?role=admin')
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('roles', null);
})->with([
    'method returning a value' => 'getTable.name',
    'method that needs arguments' => 'setAttribute.name',
]);

it('has no role list when the role query fails', function () {
    Sidecar::$userMap = ['id' => 'id', 'name' => 'name', 'email' => 'email', 'role' => 'name'];
    Sidecar::$userBuilder = User::query()->whereRaw('no_such_column = 1');

    expect(app(UserDirectory::class)->roles())->toBeNull();
});

it('does not change the configured builder between requests', function () {
    getJson('__devsquad-sidecar/users?search=john')->assertOk();

    getJson('__devsquad-sidecar/users')
        ->assertOk()
        ->assertJsonPath('meta.total', 45);
});

it('aborts when sidecar is disabled', function () {
    Config::set('devsquad-sidecar.enabled', false);

    getJson('__devsquad-sidecar/users')->assertForbidden();
});
