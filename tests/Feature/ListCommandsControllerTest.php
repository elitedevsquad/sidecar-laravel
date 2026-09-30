<?php

use EliteDevSquad\SidecarLaravel\{CommandCatalog, CommandRunner};
use EliteDevSquad\SidecarLaravel\Http\Middleware\SidecarMiddleware;
use Illuminate\Foundation\Console\ViewClearCommand;
use Illuminate\Support\Facades\{Artisan, Config};
use Tests\FakeCommandRunner;

use function Pest\Laravel\{getJson, withoutMiddleware};

beforeEach(function () {
    withoutMiddleware(SidecarMiddleware::class);
    Config::set('devsquad-sidecar.commands_enabled', true);
    Config::set('devsquad-sidecar.blocked_commands', []);
    Config::set('devsquad-sidecar.included_commands', []);

    $this->runner = new FakeCommandRunner();
    app()->instance(CommandRunner::class, $this->runner);
});

function listedNames(): array
{
    return collect(getJson('__devsquad-sidecar/commands')->json('commands'))->pluck('name')->all();
}

function probe(): ?array
{
    return collect(getJson('__devsquad-sidecar/commands')->json('commands'))
        ->firstWhere('name', 'sidecar-test:probe');
}

it('lists the application commands with their signature', function () {
    $probe = probe();

    expect($probe)->not->toBeNull()
        ->and($probe['description'])->toBe('Probe used by the command catalog tests')
        ->and($probe['arguments'])->toBe([[
            'name' => 'who',
            'description' => 'Who to greet',
            'required' => true,
            'array' => false,
            'default' => null,
        ]]);
});

it('types each option so the panel can pick a control', function () {
    $options = collect(probe()['options'])->keyBy('name');

    expect($options['loud']['type'])->toBe('boolean')
        ->and($options['loud']['default'])->toBeFalse()
        ->and($options['times']['type'])->toBe('value')
        ->and($options['times']['default'])->toBe('1')
        ->and($options['times']['description'])->toBe('How many times')
        ->and($options['tag']['type'])->toBe('list');
});

it('leaves framework commands out', function () {
    $names = collect(getJson('__devsquad-sidecar/commands')->json('commands'))->pluck('name');

    expect($names)->not->toContain('migrate')
        ->and($names)->not->toContain('view:clear');
});

it('omits the inherited console options', function () {
    $names = collect(probe()['options'])->pluck('name');

    expect($names)->not->toContain('help')
        ->and($names)->not->toContain('no-interaction');
});

it('hides commands blocked by name', function () {
    Config::set('devsquad-sidecar.blocked_commands', ['sidecar-test:*']);

    $names = collect(getJson('__devsquad-sidecar/commands')->json('commands'))->pluck('name');

    expect($names)->not->toContain('sidecar-test:probe');
});

it('hides commands blocked by class, which a name pattern cannot separate', function () {
    Config::set('devsquad-sidecar.blocked_commands', ['Tests\FakeCatalogCommand']);

    $names = collect(getJson('__devsquad-sidecar/commands')->json('commands'))->pluck('name');

    expect($names)->not->toContain('sidecar-test:probe');
});

it('refuses to list when commands are disabled', function () {
    Config::set('devsquad-sidecar.commands_enabled', false);

    getJson('__devsquad-sidecar/commands')->assertForbidden();
});

it('reports a blocked command as not executable', function () {
    Config::set('devsquad-sidecar.blocked_commands', ['sidecar-test:probe']);

    $catalog = app(CommandCatalog::class);

    expect($catalog->isExecutable('sidecar-test:probe'))->toBeFalse()
        ->and($catalog->isExecutable('view:clear'))->toBeTrue();
});

it('lists package commands named in included_commands', function () {
    Config::set('devsquad-sidecar.included_commands', ['view:*']);

    expect(listedNames())->toContain('view:clear', 'view:cache', 'sidecar-test:probe')
        ->not->toContain('migrate');
});

it('includes a package command by class', function () {
    Config::set('devsquad-sidecar.included_commands', [ViewClearCommand::class]);

    expect(listedNames())->toContain('view:clear')
        ->not->toContain('view:cache');
});

it('keeps a blocked command out even when it is included', function () {
    Config::set('devsquad-sidecar.included_commands', ['view:*']);
    Config::set('devsquad-sidecar.blocked_commands', ['view:clear']);

    expect(listedNames())->toContain('view:cache')
        ->not->toContain('view:clear');
});

it('reads the catalog from a console process, which sees console-only commands', function () {
    $this->runner->captured = "Deprecated: something\n".json_encode([
        ['name' => 'horizon:status', 'description' => 'Get the current status of Horizon', 'arguments' => [], 'options' => []],
    ]);

    expect(listedNames())->toBe(['horizon:status'])
        ->and($this->runner->name)->toBe('sidecar:catalog');
});

it('falls back to the web request catalog when the console process does not answer with a list', function () {
    $this->runner->captured = 'Command "sidecar:catalog" is not defined.';

    expect(listedNames())->toContain('sidecar-test:probe');
});

it('prints the catalog as JSON from the console command, without listing itself', function () {
    Config::set('devsquad-sidecar.included_commands', ['sidecar:*']);

    Artisan::call('sidecar:catalog');

    $names = collect(json_decode(Artisan::output(), true))->pluck('name');

    expect($names)->toContain('sidecar-test:probe')
        ->not->toContain('sidecar:catalog');
});
