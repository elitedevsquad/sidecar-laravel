<?php

/**
 * Build a fake Laravel project under a temp directory and return its path.
 * The returned directory contains:
 *   - artisan          (executable stub)
 *   - .env             (minimal)
 *   - .env.example     (empty)
 *   - resources/views/ (blade stubs for CSRF injection tests)
 *
 * @param  array<string, string>  $extraEnv  Extra KEY=VALUE lines added to .env
 * @param  array<string, string>  $blades  relative path => blade content
 */
function makeFakeProject(array $extraEnv = [], array $blades = []): string
{
    $dir = sys_get_temp_dir().'/sidecar_install_test_'.uniqid();
    mkdir($dir, 0755, true);
    mkdir("{$dir}/config", 0755, true);
    mkdir("{$dir}/resources/views/layouts", 0755, true);

    // Stub artisan — vendor:publish copies the real config file into $dir/config/
    // The script calls `php artisan`, so artisan must be a valid PHP script.
    $configSrc = realpath(__DIR__.'/../../resources/config/devsquad-sidecar.php');
    $artisanScript = <<<PHP
<?php
\$args = implode(' ', \$argv);
if (str_contains(\$args, 'vendor:publish')) {
    @mkdir("{$dir}/config", 0755, true);
    copy("{$configSrc}", "{$dir}/config/devsquad-sidecar.php");
}
exit(0);
PHP;
    file_put_contents("{$dir}/artisan", $artisanScript);
    chmod("{$dir}/artisan", 0755);

    // Minimal .env
    $envLines = [
        'APP_NAME=TestApp',
        'APP_URL=http://localhost',
        'MAIL_HOST=mailpit',
        'MAIL_PORT=1025',
    ];
    foreach ($extraEnv as $key => $value) {
        $envLines[] = "{$key}={$value}";
    }
    file_put_contents("{$dir}/.env", implode("\n", $envLines)."\n");
    file_put_contents("{$dir}/.env.example", '');

    // Blade files
    foreach ($blades as $relativePath => $content) {
        $full = "{$dir}/resources/views/{$relativePath}";
        @mkdir(dirname($full), 0755, true);
        file_put_contents($full, $content);
    }

    return $dir;
}

/**
 * Run install.sh inside $projectDir with composer stubbed so it never
 * actually downloads anything.
 *
 * @return array{output: string, exit: int}
 */
function runInstallScript(string $projectDir): array
{
    $script = realpath(__DIR__.'/../../install.sh');

    $composerStub = sys_get_temp_dir().'/sidecar_composer_stub_'.uniqid();
    file_put_contents($composerStub, implode("\n", [
        '#!/usr/bin/env bash',
        'if [[ "$*" == *"show"*"elitedevsquad"* ]]; then exit 0; fi',
        'exit 0',
    ]));
    chmod($composerStub, 0755);

    $stubBin = sys_get_temp_dir().'/sidecar_stub_bin_'.uniqid();
    mkdir($stubBin, 0755, true);
    symlink($composerStub, "{$stubBin}/composer");

    $output = [];
    $exit = 0;

    exec(
        "cd \"{$projectDir}\" && SIDECAR_PROJECT_ROOT=\"{$projectDir}\" PATH={$stubBin}:\$PATH bash \"{$script}\" 2>&1",
        $output,
        $exit,
    );

    @unlink($composerStub);
    @unlink("{$stubBin}/composer");
    @rmdir($stubBin);

    return ['output' => implode("\n", $output), 'exit' => $exit];
}

function rmrf(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

it('exits successfully', function () {
    $dir = makeFakeProject();
    chdir($dir);
    $result = runInstallScript($dir);
    rmrf($dir);

    expect($result['exit'])->toBe(0);
});

it('writes DS_SIDECAR_ENABLED referencing VITE variable into .env', function () {
    $dir = makeFakeProject();
    chdir($dir);
    runInstallScript($dir);
    $env = file_get_contents("{$dir}/.env");
    rmrf($dir);

    expect($env)
        ->toContain('VITE_DS_SIDECAR_ENABLED=true')
        ->toContain('DS_SIDECAR_ENABLED="$VITE_DS_SIDECAR_ENABLED"');
});

it('writes all expected DS_SIDECAR keys into .env', function () {
    $dir = makeFakeProject();
    chdir($dir);
    runInstallScript($dir);
    $env = file_get_contents("{$dir}/.env");
    rmrf($dir);

    expect($env)
        ->toContain('DS_SIDECAR_AUTO_INJECT_ASSETS=true')
        ->toContain('DS_SIDECAR_TINKER_ENABLED=true')
        ->toContain('DS_SIDECAR_TINKER_USE_BATCH=true')
        ->toContain('DS_SIDECAR_COMMANDS_ENABLED=true')
        ->toContain('DS_SIDECAR_FAKE_CLOCK_ENABLED=true')
        ->toContain('DS_SIDECAR_ALLOWED_IPS=')
        ->toContain('DS_SIDECAR_BRANCH_URL=')
        ->toContain('DS_SIDECAR_LINK_MAIL=')
        ->toContain('DS_SIDECAR_LINK_ENVOYER=');
});

it('mirrors all keys into .env.example', function () {
    $dir = makeFakeProject();
    chdir($dir);
    runInstallScript($dir);
    $example = file_get_contents("{$dir}/.env.example");
    rmrf($dir);

    expect($example)
        ->toContain('# DevSquad Sidecar')
        ->toContain('VITE_DS_SIDECAR_ENABLED=')
        ->toContain('DS_SIDECAR_ENABLED=')
        ->toContain('DS_SIDECAR_AUTO_INJECT_ASSETS=')
        ->toContain('DS_SIDECAR_TINKER_ENABLED=')
        ->toContain('DS_SIDECAR_LINK_ENVOYER=');
});

it('does not overwrite keys already present in .env', function () {
    $dir = makeFakeProject(['DS_SIDECAR_TINKER_ENABLED' => 'false']);
    chdir($dir);
    runInstallScript($dir);
    $env = file_get_contents("{$dir}/.env");
    rmrf($dir);

    // env_ensure must not overwrite an existing value
    expect(substr_count($env, 'DS_SIDECAR_TINKER_ENABLED='))->toBe(1)
        ->and($env)->toContain('DS_SIDECAR_TINKER_ENABLED=false');
});

it('does not overwrite keys already present in .env.example', function () {
    $dir = makeFakeProject();
    // Pre-populate .env.example with a key
    file_put_contents("{$dir}/.env.example", "DS_SIDECAR_TINKER_ENABLED=false\n");
    chdir($dir);
    runInstallScript($dir);
    $example = file_get_contents("{$dir}/.env.example");
    rmrf($dir);

    expect(substr_count($example, 'DS_SIDECAR_TINKER_ENABLED='))->toBe(1)
        ->and($example)->toContain('DS_SIDECAR_TINKER_ENABLED=false');
});

it('publishes the config file', function () {
    $dir = makeFakeProject();
    chdir($dir);
    runInstallScript($dir);
    $exists = file_exists("{$dir}/config/devsquad-sidecar.php");
    rmrf($dir);

    expect($exists)->toBeTrue();
});

it('injects csrf-token into a full layout blade', function () {
    $layout = implode("\n", [
        '<!DOCTYPE html>',
        '<html lang="en">',
        '<head>',
        '    <meta charset="UTF-8">',
        '</head>',
        '<body>',
        '<div id="app"></div>',
        '</body>',
        '</html>',
    ]);

    $dir = makeFakeProject([], ['layouts/app.blade.php' => $layout]);
    chdir($dir);
    runInstallScript($dir);
    $content = file_get_contents("{$dir}/resources/views/layouts/app.blade.php");
    rmrf($dir);

    expect($content)->toContain('csrf-token');
});

it('does not inject csrf-token when already present', function () {
    $layout = implode("\n", [
        '<html><head>',
        '    <meta name="csrf-token" content="{{ csrf_token() }}">',
        '</head><body></body></html>',
    ]);

    $dir = makeFakeProject([], ['layouts/app.blade.php' => $layout]);
    chdir($dir);
    runInstallScript($dir);
    $content = file_get_contents("{$dir}/resources/views/layouts/app.blade.php");
    rmrf($dir);

    expect(substr_count($content, 'csrf-token'))->toBe(1);
});

it('skips csrf injection for mail blade files', function () {
    $mailBlade = '<html><head><meta charset="UTF-8"></head><body>Mail body</body></html>';

    $dir = makeFakeProject([], [
        'vendor/mail/html/layout.blade.php' => $mailBlade,
        'mail/message.blade.php' => $mailBlade,
        'emails/welcome.blade.php' => $mailBlade,
        'notifications/mail.blade.php' => $mailBlade,
    ]);
    chdir($dir);
    runInstallScript($dir);

    $results = [];
    foreach (['vendor/mail/html/layout.blade.php', 'mail/message.blade.php', 'emails/welcome.blade.php', 'notifications/mail.blade.php'] as $path) {
        $results[$path] = file_get_contents("{$dir}/resources/views/{$path}");
    }
    rmrf($dir);

    foreach ($results as $path => $content) {
        expect($content)->not->toContain('csrf-token', "Expected no csrf-token in {$path}");
    }
});

it('does not skip csrf injection for non-mail layout blades', function () {
    $layout = '<html><head><meta charset="UTF-8"></head><body></body></html>';

    $dir = makeFakeProject([], [
        'layouts/app.blade.php' => $layout,
        'layouts/mailbox-layout.blade.php' => $layout,
        'components/email-button.blade.php' => $layout,
    ]);
    chdir($dir);
    runInstallScript($dir);

    $results = [];
    foreach (['layouts/app.blade.php', 'layouts/mailbox-layout.blade.php', 'components/email-button.blade.php'] as $path) {
        $results[$path] = file_get_contents("{$dir}/resources/views/{$path}");
    }
    rmrf($dir);

    foreach ($results as $path => $content) {
        expect($content)->toContain('csrf-token');
    }
});
