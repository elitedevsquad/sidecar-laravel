<?php

use EliteDevSquad\SidecarLaravel\{CommandRunner, FakeClock};
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/sidecar-runner-'.uniqid();
    mkdir($this->root);

    file_put_contents(
        $this->root.'/artisan',
        "<?php\n\$args = array_slice(\$argv, 1);\nforeach (\$args as \$arg) { echo \$arg, PHP_EOL; }\n"
    );

    app()->setBasePath($this->root);
});

afterEach(function () {
    @unlink($this->root.'/artisan');
    @rmdir($this->root);
});

it('keeps a value containing a space as a single argument', function () {
    $output = app(CommandRunner::class)->run('app:greet', ['who' => 'two words']);

    expect(explode(PHP_EOL, $output))->toContain('two words');
});

it('passes a boolean option as a bare flag and drops a false one', function () {
    $output = app(CommandRunner::class)->run('app:greet', ['--loud' => true, '--quiet-mode' => false]);

    $lines = explode(PHP_EOL, $output);

    expect($lines)->toContain('--loud')
        ->and($lines)->not->toContain('--quiet-mode');
});

it('repeats a list option once per item', function () {
    $output = app(CommandRunner::class)->run('app:greet', ['--tag' => ['a', 'b']]);

    expect(explode(PHP_EOL, $output))->toContain('--tag=a', '--tag=b');
});

it('always runs without interaction, so a prompt cannot hang the request', function () {
    $output = app(CommandRunner::class)->run('app:greet');

    expect(explode(PHP_EOL, $output))->toContain('--no-interaction');
});

it('reports an empty run rather than returning nothing', function () {
    file_put_contents($this->root.'/artisan', "<?php\n");

    $output = app(CommandRunner::class)->run('app:silent');

    expect($output)->toBe('Command executed successfully - app:silent');
});

it('leaves the fake clock where a subprocess can find it', function () {
    // The runner spawns a separate process, so nothing in this one's memory
    // reaches it. The fake clock survives because it is written to the cache
    // and the service provider applies it from there in console context.
    FakeClock::set(now()->addDays(30));

    expect(Cache::has(FakeClock::KEY))->toBeTrue();
});

it('passes a typed command line through as written', function () {
    $output = app(CommandRunner::class)->run('app:sync', ['--part=1', '--message=two words', '--dry-run']);

    expect(explode(PHP_EOL, $output))->toContain('--part=1', '--message=two words', '--dry-run');
});
