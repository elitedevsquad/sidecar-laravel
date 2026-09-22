<?php

use EliteDevSquad\SidecarLaravel\Health;

function writeLog(string $contents): string
{
    $directory = storage_path('logs');

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $file = $directory.'/laravel.log';

    file_put_contents($file, $contents);

    return $file;
}

afterEach(function () {
    $file = storage_path('logs/laravel.log');

    if (file_exists($file)) {
        unlink($file);
    }
});

it('returns log errors and the newest recent lines', function () {
    $now = now()->format('Y-m-d H:i:s');

    writeLog(<<<LOG
    [{$now}] local.ERROR: Target class [LeadSeeder] does not exist.
    [{$now}] local.INFO: nothing to see here
    [{$now}] local.CRITICAL: redis is down
    [2000-01-01 00:00:00] local.ERROR: too old to count
    LOG);

    $health = (new Health())->toArray();

    expect($health)->not->toHaveKey('queued_jobs')
        ->and($health)->not->toHaveKey('failed_jobs')
        ->and($health['log_errors'])->toBe(2)
        ->and($health['last_error_at'])->not->toBeNull()
        ->and($health['updated_at'])->not->toBeNull()
        ->and($health['recent_logs'])->toHaveCount(2)
        ->and($health['recent_logs'][0]['level'])->toBe('CRITICAL')
        ->and($health['recent_logs'][0]['message'])->toBe('redis is down')
        ->and($health['recent_logs'][1]['level'])->toBe('ERROR')
        ->and($health['recent_logs'][1]['message'])->toBe('Target class [LeadSeeder] does not exist.');
});

it('returns at most the ten most recent errors, newest first', function () {
    $now = now()->format('Y-m-d H:i:s');
    $lines = [];

    for ($i = 1; $i <= 12; $i++) {
        $lines[] = "[{$now}] local.ERROR: error {$i}";
    }

    writeLog(implode("\n", $lines));

    $health = (new Health())->toArray();

    expect($health['log_errors'])->toBe(12)
        ->and($health['recent_logs'])->toHaveCount(10)
        ->and($health['recent_logs'][0]['message'])->toBe('error 12')
        ->and($health['recent_logs'][9]['message'])->toBe('error 3');
});

it('returns null log errors when there is no log file', function () {
    $file = storage_path('logs/laravel.log');

    if (file_exists($file)) {
        unlink($file);
    }

    $health = (new Health())->toArray();

    expect($health['log_errors'])->toBeNull()
        ->and($health['last_error_at'])->toBeNull()
        ->and($health['recent_logs'])->toBe([]);
});

it('truncates long log messages', function () {
    $now = now()->format('Y-m-d H:i:s');
    $long = str_repeat('a', 500);

    writeLog("[{$now}] local.ERROR: {$long}\n");

    $message = (new Health())->toArray()['recent_logs'][0]['message'];

    expect($message)->toHaveLength(400)
        ->and($message)->toEndWith('...');
});

it('ignores log lines with an unparseable timestamp', function () {
    writeLog("[not a date] local.ERROR: broken line\n");

    $health = (new Health())->toArray();

    expect($health['log_errors'])->toBe(0)
        ->and($health['recent_logs'])->toBe([]);
});
