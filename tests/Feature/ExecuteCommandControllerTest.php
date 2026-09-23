<?php

use EliteDevSquad\SidecarLaravel\{CommandRunner, FakeClock};
use EliteDevSquad\SidecarLaravel\Http\Middleware\SidecarMiddleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Cache, Config};
use Tests\FakeCommandRunner;

use function Pest\Laravel\{postJson, withoutMiddleware};

beforeEach(function () {
    Cache::flush();
    Carbon::setTestNow();
    withoutMiddleware(SidecarMiddleware::class);
    Config::set('devsquad-sidecar.blocked_commands', []);

    $this->runner = new FakeCommandRunner();
    app()->instance(CommandRunner::class, $this->runner);
});

it('runs the command it was given', function () {
    postJson('__devsquad-sidecar/execute-command', ['command' => 'view:clear'])
        ->assertOk()
        ->assertJson(['output' => 'fake output']);

    expect($this->runner->name)->toBe('view:clear');
});

it('change clock when clock input is provided', function () {
    $target = now()->addDays(2);

    FakeClock::set($target);

    postJson('__devsquad-sidecar/execute-command', ['command' => 'view:clear'])->assertOk();

    expect(now()->timestamp)->toEqualWithDelta($target->timestamp, 2);
});

it('does not change clock when clock input is not provided', function () {
    $originalTime = now()->timestamp;

    postJson('__devsquad-sidecar/execute-command', ['command' => 'view:clear'])->assertOk();

    expect(Carbon::hasTestNow())
        ->toBeFalse()
        ->and(now()->timestamp)
        ->toEqualWithDelta($originalTime, 2);
});

it('reports a failure instead of throwing', function () {
    $this->runner->output = 'Command failed with exit code 1';

    postJson('__devsquad-sidecar/execute-command', ['command' => 'bad'])
        ->assertOk()
        ->assertJson(['output' => 'Command failed with exit code 1']);
});

it('keeps accepting a command written as one string', function () {
    postJson('__devsquad-sidecar/execute-command', ['command' => 'queue:work --once'])->assertOk();

    expect($this->runner->name)->toBe('queue:work')
        ->and($this->runner->parameters)->toBe(['--once']);
});

it('strips the quotes from a command written as one string', function () {
    postJson('__devsquad-sidecar/execute-command', [
        'command' => 'sidecar-test:probe "two words" --queue="high priority" --tag=\'a b\'',
    ])->assertOk();

    expect($this->runner->name)->toBe('sidecar-test:probe')
        ->and($this->runner->parameters)->toBe(['two words', '--queue=high priority', '--tag=a b']);
});

it('passes structured parameters through untouched', function () {
    postJson('__devsquad-sidecar/execute-command', [
        'command' => 'sidecar-test:probe',
        // A value with a space is exactly what string concatenation breaks on.
        'parameters' => ['who' => 'two words', '--loud' => true],
    ])->assertOk();

    expect($this->runner->name)->toBe('sidecar-test:probe')
        ->and($this->runner->parameters)->toBe(['who' => 'two words', '--loud' => true]);
});

it('refuses a blocked command, which the list only hides', function () {
    Config::set('devsquad-sidecar.blocked_commands', ['sidecar-test:*']);

    postJson('__devsquad-sidecar/execute-command', [
        'command' => 'sidecar-test:probe',
        'parameters' => ['who' => 'world'],
    ])
        ->assertForbidden()
        ->assertJson(['output' => 'This command is blocked by the Sidecar configuration.']);

    expect($this->runner->name)->toBeNull();
});

it('blocks a name separated by a tab, not just by a space', function () {
    Config::set('devsquad-sidecar.blocked_commands', ['sidecar-test:probe']);

    // The check and the run have to split the string the same way, or a tab
    // hides the name from one of them.
    postJson('__devsquad-sidecar/execute-command', ['command' => "sidecar-test:probe\t--loud"])
        ->assertForbidden();

    expect($this->runner->name)->toBeNull();
});
