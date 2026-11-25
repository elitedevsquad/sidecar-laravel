<?php

use EliteDevSquad\SidecarLaravel\Http\Middleware\SidecarMiddleware;
use EliteDevSquad\SidecarLaravel\TinkerCompletions;
use Illuminate\Database\Eloquent\Attributes\Scope;
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
    TinkerCompletions::$perPage = 20;
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
    $scopes = [
        ['name' => 'published', 'params' => ''],
        ['name' => 'writtenBy', 'params' => 'Author $author, int $limit = …'],
    ];

    // The Scope attribute only exists from Laravel 12 on.
    if (class_exists(Scope::class)) {
        $scopes[] = ['name' => 'featured', 'params' => ''];
    }

    getJson('__devsquad-sidecar/tinker/completions')
        ->assertJsonPath('models.0.scopes', $scopes);
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

it('splits the models into pages and sends the framework only with the first', function () {
    TinkerCompletions::$perPage = 1;

    getJson('__devsquad-sidecar/tinker/completions')
        ->assertJsonPath('models.*.name', ['Article'])
        ->assertJsonPath('page', 1)
        ->assertJsonPath('last_page', 2)
        ->assertJsonPath('total', 2)
        ->assertJsonStructure(['framework' => ['builder', 'model', 'instance', 'collection', 'facades', 'helpers']]);

    getJson('__devsquad-sidecar/tinker/completions?page=2')
        ->assertJsonPath('models.*.name', ['Author'])
        ->assertJsonPath('page', 2)
        ->assertJsonPath('framework', null);
});

it('reads what the builder returns, so the editor can follow a chain', function () {
    $builder = collect(getJson('__devsquad-sidecar/tinker/completions')->json('framework.builder'))->keyBy('name');

    expect($builder['where']['returns'])->toBe('builder')
        ->and($builder['first']['returns'])->toBe('?model')
        ->and($builder['firstOrFail']['returns'])->toBe('model')
        ->and($builder['get']['returns'])->toBe('collection')
        ->and($builder['count']['returns'])->toBe('int')
        ->and($builder['whereIn']['params'])->toStartWith('$column, $values');
});

it('lists the model, instance and collection methods', function () {
    $framework = getJson('__devsquad-sidecar/tinker/completions')->json('framework');
    $names = fn (string $key) => collect($framework[$key])->pluck('returns', 'name');

    expect($names('model')['query'])->toBe('builder')
        ->and($names('model')['all'])->toBe('collection')
        ->and($names('instance')['fresh'])->toBe('?model')
        ->and($names('instance')->has('save'))->toBeTrue()
        ->and($names('instance')->has('__get'))->toBeFalse()
        ->and($names('collection')['first'])->toBe('model')
        ->and($names('collection')['map'])->toBe('collection');
});

it('reads the facades from their docblocks and the helpers of the framework', function () {
    $framework = getJson('__devsquad-sidecar/tinker/completions')->json('framework');
    $table = collect($framework['facades']['DB'])->firstWhere('name', 'table');

    expect($table['returns'])->toBe('builder')
        ->and($table['params'])->toContain('string $table, string|null $as = …')
        ->and(collect($framework['facades']['Str'])->pluck('name'))->toContain('slug')
        ->and(collect($framework['helpers'])->pluck('name'))->toContain('now', 'collect')
        ->and(collect($framework['helpers'])->pluck('name'))->not->toContain('pest');
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
