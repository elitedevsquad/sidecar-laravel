<?php

use EliteDevSquad\SidecarLaravel\Http\Middleware\SidecarMiddleware;
use EliteDevSquad\SidecarLaravel\TinkerCompletions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Config, Schema};
use Tests\Fixtures\Models\Article;

use function Pest\Laravel\{getJson, withoutMiddleware};

beforeEach(function () {
    withoutMiddleware(SidecarMiddleware::class);
    Config::set('devsquad-sidecar.enabled', true);

    TinkerCompletions::$paths = [__DIR__.'/../Fixtures/Models'];

    Schema::create('articles', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->unsignedBigInteger('author_id');
        $table->timestamp('published_at')->nullable();
    });

    Schema::create('authors', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
});

afterEach(function () {
    TinkerCompletions::$paths = null;
});

it('lists only the concrete models it finds', function () {
    getJson('__devsquad-sidecar/tinker/completions')
        ->assertOk()
        ->assertJsonPath('models.*.name', ['Article', 'Author'])
        ->assertJsonPath('models.0.class', 'Tests\Fixtures\Models\Article')
        ->assertJsonPath('models.0.table', 'articles')
        ->assertJsonStructure(['generated_at']);
});

it('lists the columns with their cast', function () {
    $columns = collect(getJson('__devsquad-sidecar/tinker/completions')->json('models.0.columns'))->keyBy('name');

    expect($columns->keys()->all())->toBe(['id', 'title', 'author_id', 'published_at'])
        ->and($columns['published_at']['cast'])->toBe('datetime')
        ->and($columns['title']['cast'])->toBeNull()
        ->and($columns['title']['type'])->not->toBe('');
});

it('lists the scopes without the query parameter', function () {
    getJson('__devsquad-sidecar/tinker/completions')
        ->assertJsonPath('models.0.scopes', [
            ['name' => 'published', 'params' => ''],
            ['name' => 'writtenBy', 'params' => 'Author $author, int $limit = …'],
            ['name' => 'featured', 'params' => ''],
        ]);
});

it('lists the relations with the related model', function () {
    getJson('__devsquad-sidecar/tinker/completions')
        ->assertJsonPath('models.0.relations', [
            ['name' => 'author', 'type' => 'BelongsTo', 'related' => 'Author'],
            ['name' => 'reviewer', 'type' => 'BelongsTo', 'related' => 'Author'],
            ['name' => 'comments', 'type' => 'HasMany', 'related' => 'Comment'],
        ])
        ->assertJsonPath('models.1.relations', [
            ['name' => 'articles', 'type' => 'HasMany', 'related' => 'Article'],
        ]);
});

it('reads the relations without running them', function () {
    Article::$ran = false;

    getJson('__devsquad-sidecar/tinker/completions')->assertOk();

    expect(Article::$ran)->toBeFalse();
});

it('lists the public methods and leaves out accessors and protected ones', function () {
    getJson('__devsquad-sidecar/tinker/completions')
        ->assertJsonPath('models.0.methods', [
            ['name' => 'isPublished', 'params' => '', 'returns' => 'bool'],
        ]);
});

it('returns no columns when the table does not exist', function () {
    Schema::drop('authors');

    getJson('__devsquad-sidecar/tinker/completions')
        ->assertOk()
        ->assertJsonPath('models.1.columns', []);
});

it('returns no models when the folder does not exist', function () {
    TinkerCompletions::$paths = [__DIR__.'/missing'];

    getJson('__devsquad-sidecar/tinker/completions')
        ->assertOk()
        ->assertJsonPath('models', []);
});

it('refuses when tinker is disabled', function () {
    Config::set('devsquad-sidecar.tinker_enabled', false);

    getJson('__devsquad-sidecar/tinker/completions')->assertForbidden();
});

it('refuses an ip that is not allowed', function () {
    app()->forgetInstance(SidecarMiddleware::class);
    Config::set('devsquad-sidecar.allowed_ips', ['10.0.0.1']);

    getJson('__devsquad-sidecar/tinker/completions')->assertForbidden();
});

it('answers an allowed ip', function () {
    app()->forgetInstance(SidecarMiddleware::class);
    Config::set('devsquad-sidecar.allowed_ips', ['127.0.0.1']);

    getJson('__devsquad-sidecar/tinker/completions')->assertOk();
});

it('aborts when sidecar is disabled', function () {
    Config::set('devsquad-sidecar.enabled', false);

    getJson('__devsquad-sidecar/tinker/completions')->assertForbidden();
});
