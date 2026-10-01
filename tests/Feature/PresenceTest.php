<?php

use EliteDevSquad\SidecarLaravel\Activity\ActivityLog;
use EliteDevSquad\SidecarLaravel\CommandRunner;
use EliteDevSquad\SidecarLaravel\{FakeClock, Sidecar};
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\{Bus, Cache, Config};
use Tests\{FakeCommandRunner, User};

use function Pest\Laravel\{actingAs, deleteJson, getJson, postJson};

function resetActivity(): void
{
    $dir = storage_path('app/sidecar');

    if (is_file($dir)) {
        unlink($dir);
    }

    if (! is_dir($dir)) {
        return;
    }

    foreach (scandir($dir) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }

        $path = $dir.'/'.$name;

        if (is_file($path)) {
            unlink($path);
        }
    }
}

/**
 * @return list<array<string, mixed>>
 */
function activityRecent(): array
{
    $path = storage_path('app/sidecar/state.json');

    if (! is_file($path)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    return is_array($decoded['recent'] ?? null) ? $decoded['recent'] : [];
}

/**
 * @param  array<string, string>  $headers
 */
function asTester(string $id, string $name, array $headers = []): array
{
    return ['X-Sidecar-Tester' => $id.';'.$name, ...$headers];
}

beforeEach(function () {
    resetActivity();
    Cache::flush();
    Sidecar::$userModel = User::class;
    Sidecar::$userBuilder = User::query();
    Config::set('devsquad-sidecar.presence_enabled', true);
    Config::set('devsquad-sidecar.presence_require_confirm', true);
    Config::set('devsquad-sidecar.branch_name', 'feature/presence');

    $this->runner = new FakeCommandRunner();
    app()->instance(CommandRunner::class, $this->runner);
});

it('keeps one tester across heartbeats and removes them on stop', function () {
    $headers = asTester('anasouza1', 'Ana Souza');

    postJson('__devsquad-sidecar/presence', ['id' => 'anasouza1', 'name' => 'Ana Souza', 'ttl' => 600])
        ->assertOk()
        ->assertJsonPath('testers.0.name', 'Ana Souza')
        ->assertJsonPath('testers.0.ttl', 600);

    $started = json_decode((string) file_get_contents(storage_path('app/sidecar/state.json')), true)['testers']['anasouza1']['started_at'];

    postJson('__devsquad-sidecar/presence', ['id' => 'anasouza1', 'name' => 'Ana Souza', 'ttl' => 1800])
        ->assertOk()
        ->assertJsonCount(1, 'testers')
        ->assertJsonPath('testers.0.ttl', 1800);

    $again = json_decode((string) file_get_contents(storage_path('app/sidecar/state.json')), true);

    expect($again['testers']['anasouza1']['started_at'])->toBe($started)
        ->and(collect(activityRecent())->where('summary', 'started testing'))->toHaveCount(1);

    deleteJson('__devsquad-sidecar/presence', ['id' => 'anasouza1'], $headers)
        ->assertOk()
        ->assertJsonPath('testers', []);

    expect(collect(activityRecent())->contains(fn (array $entry) => $entry['summary'] === 'stopped testing'))->toBeTrue();
});

it('drops a tester after their time and records the end', function () {
    postJson('__devsquad-sidecar/presence', ['id' => 'anasouza1', 'name' => 'Ana Souza', 'ttl' => 60])->assertOk();

    $state = json_decode((string) file_get_contents(storage_path('app/sidecar/state.json')), true);
    $state['testers']['anasouza1']['last_seen'] = time() - 120;
    file_put_contents(storage_path('app/sidecar/state.json'), json_encode($state));

    getJson('__devsquad-sidecar/activity')
        ->assertOk()
        ->assertJsonPath('testers', []);

    $ended = collect(activityRecent())->firstWhere('summary', 'testing time ended');

    expect($ended['name'])->toBe('Ana Souza')
        ->and($ended['at'])->toBeGreaterThanOrEqual(time() - 5);

    $line = getJson('__devsquad-sidecar/activity/'.$ended['id'])->assertOk()->json();

    expect($line['ended_at'])->toBe($state['testers']['anasouza1']['last_seen'] + 60);
});

it('is on by default outside local when the env var is unset', function () {
    $config = require __DIR__.'/../../resources/config/devsquad-sidecar.php';

    expect($config['presence_enabled'])->toBeNull();

    Config::set('devsquad-sidecar.presence_enabled', $config['presence_enabled']);

    expect(app(ActivityLog::class)->enabled())->toBeTrue();
});

it('reads the state without rewriting it when nothing changed', function () {
    postJson('__devsquad-sidecar/presence', ['id' => 'anasouza1', 'name' => 'Ana Souza', 'ttl' => 600])->assertOk();

    $path = storage_path('app/sidecar/state.json');
    touch($path, time() - 3600);
    clearstatcache(true, $path);
    $before = filemtime($path);

    getJson('__devsquad-sidecar/activity')->assertOk();
    clearstatcache(true, $path);

    expect(filemtime($path))->toBe($before)
        ->and(file_get_contents($path))->not->toContain("\n    ");
});

it('answers 423 until the caller confirms, and never blocks a request without the header', function () {
    postJson('__devsquad-sidecar/presence', ['id' => 'brunolima', 'name' => 'Bruno Lima', 'ttl' => 600])->assertOk();

    postJson('__devsquad-sidecar/execute-command', ['command' => 'migrate:fresh --seed'], asTester('anasouza1', 'Ana Souza'))
        ->assertStatus(423)
        ->assertJsonPath('testers.0.name', 'Bruno Lima');

    expect($this->runner->name)->toBeNull();

    postJson('__devsquad-sidecar/execute-command', [
        'command' => 'migrate:fresh --seed',
        'confirm_over' => true,
    ], asTester('anasouza1', 'Ana Souza'))
        ->assertOk();

    $confirmed = collect(activityRecent())->firstWhere('summary', 'migrate:fresh --seed');

    expect($confirmed['over'])->toBe(['Bruno Lima'])
        ->and($confirmed['over_ids'])->toBe(['brunolima'])
        ->and($confirmed['name'])->toBe('Ana Souza')
        ->and($confirmed['status'])->toBe('ok')
        ->and($confirmed['branch'])->toBe('feature/presence')
        ->and($confirmed['app_at'])->toBeNull();

    $this->runner->name = null;

    postJson('__devsquad-sidecar/execute-command', ['command' => 'view:clear'])
        ->assertOk();

    $anonymous = collect(activityRecent())->firstWhere('summary', 'view:clear');

    expect($anonymous['name'])->toBe('Someone')
        ->and($anonymous['over'])->toBe(['Bruno Lima']);
});

it('skips the modal when confirmation is turned off and still records the action', function () {
    Config::set('devsquad-sidecar.presence_require_confirm', false);
    postJson('__devsquad-sidecar/presence', ['id' => 'brunolima', 'name' => 'Bruno Lima', 'ttl' => 600])->assertOk();

    postJson('__devsquad-sidecar/execute-command', ['command' => 'view:clear'], asTester('anasouza1', 'Ana Souza'))
        ->assertOk();

    expect(collect(activityRecent())->firstWhere('summary', 'view:clear')['name'])->toBe('Ana Souza');
});

it('records every action with real time and application time', function () {
    $when = now()->addDay()->startOfHour();

    postJson('__devsquad-sidecar/execute-fake-clock', ['datetime' => $when->toIso8601String()], asTester('anasouza1', 'Ana Souza'))
        ->assertOk();

    $clock = collect(activityRecent())->firstWhere('type', 'clock');

    expect($clock['clock'])->toBe('set')
        ->and($clock['summary'])->toContain('set the clock to')
        ->and($clock['app_at'])->not->toBeNull()
        ->and($clock['at'])->toEqualWithDelta(time(), 2);

    postJson('__devsquad-sidecar/execute-tinker', [
        'code' => base64_encode('$name = "Luan";'),
    ], asTester('anasouza1', 'Ana Souza'))->assertOk();

    $tinker = collect(activityRecent())->firstWhere('type', 'tinker');
    $heavy = getJson('__devsquad-sidecar/activity/'.$tinker['id'])->assertOk()->json();

    expect($heavy['code'])->toBe('$name = "Luan";')
        ->and($heavy['id'])->toBe($tinker['id']);

    Bus::fake();

    postJson('__devsquad-sidecar/execute-tinker-on-queue', [
        'code' => base64_encode('dispatch()'),
    ], asTester('anasouza1', 'Ana Souza'))->assertOk();

    $queued = collect(activityRecent())->first(fn (array $entry) => $entry['summary'] === 'ran Tinker on the queue');
    $queuedHeavy = getJson('__devsquad-sidecar/activity/'.$queued['id'])->json();

    expect($queued['summary'])->toBe('ran Tinker on the queue')
        ->and($queuedHeavy['batch_id'] ?? null)->not->toBeNull();

    $user = User::create(['name' => 'Ada Lovelace', 'email' => 'ada@sidecar.test']);

    postJson('__devsquad-sidecar/login-as', ['user_id' => $user->id], asTester('anasouza1', 'Ana Souza'))
        ->assertOk();

    expect(collect(activityRecent())->contains(fn (array $entry) => $entry['summary'] === 'signed in as Ada Lovelace'))->toBeTrue();

    postJson('__devsquad-sidecar/clear-user-cache', [], asTester('anasouza1', 'Ana Souza'))->assertOk();

    expect(collect(activityRecent())->contains(fn (array $entry) => $entry['type'] === 'cache'))->toBeTrue();

    postJson('__devsquad-sidecar/execute-fake-clock', ['datetime' => null], asTester('anasouza1', 'Ana Souza'))->assertOk();

    expect(collect(activityRecent())->contains(fn (array $entry) => ($entry['clock'] ?? null) === 'reset'))->toBeTrue();
});

it('keeps the heavy line before the summary points at it and caps the summary list', function () {
    foreach (range(1, 55) as $index) {
        postJson('__devsquad-sidecar/execute-command', ['command' => 'view:clear --pass='.$index])->assertOk();
    }

    $recent = activityRecent();

    expect($recent)->toHaveCount(50);

    $lines = file(storage_path('app/sidecar/activity-'.date('Y-m-d').'.jsonl'), FILE_IGNORE_NEW_LINES);
    expect($lines)->toHaveCount(55);

    $second = $recent[1];
    $slice = file_get_contents(
        storage_path('app/sidecar/'.$second['ref']['file']),
        false,
        null,
        $second['ref']['offset'],
        $second['ref']['length']
    );

    expect(json_decode((string) $slice, true)['id'])->toBe($second['id']);

    file_put_contents(
        storage_path('app/sidecar/activity-'.date('Y-m-d').'.jsonl'),
        json_encode(['id' => 'orphan-line', 'type' => 'command', 'summary' => 'orphan'])."\n",
        FILE_APPEND
    );

    expect(collect(activityRecent())->contains(fn (array $entry) => $entry['id'] === 'orphan-line'))->toBeFalse();

    getJson('__devsquad-sidecar/activity?full=1')
        ->assertOk()
        ->assertJsonFragment(['id' => 'orphan-line']);
});

it('caps code and output at 16 KB', function () {
    $this->runner->output = str_repeat('x', 20_000);

    postJson('__devsquad-sidecar/execute-command', ['command' => 'view:clear'])->assertOk();

    $entry = activityRecent()[0];
    $heavy = getJson('__devsquad-sidecar/activity/'.$entry['id'])->json();

    expect(strlen($heavy['output']))->toBe(ActivityLog::BODY_LIMIT)
        ->and($heavy['truncated'])->toBeTrue();

    postJson('__devsquad-sidecar/execute-tinker', [
        'code' => base64_encode(str_repeat('y', 20_000)),
    ])->assertOk();

    $tinker = collect(activityRecent())->first(fn (array $item) => $item['type'] === 'tinker');
    $body = getJson('__devsquad-sidecar/activity/'.$tinker['id'])->json();

    expect(strlen($body['code']))->toBe(ActivityLog::BODY_LIMIT)
        ->and($body['truncated'])->toBeTrue();
});

it('reads one entry by its offset and says when the details are gone', function () {
    postJson('__devsquad-sidecar/execute-command', ['command' => 'view:clear'])->assertOk();

    $entry = activityRecent()[0];
    unlink(storage_path('app/sidecar/'.$entry['ref']['file']));

    $state = json_decode((string) file_get_contents(storage_path('app/sidecar/state.json')), true);
    $state['cleaned_on'] = null;
    file_put_contents(storage_path('app/sidecar/state.json'), json_encode($state));

    getJson('__devsquad-sidecar/activity')
        ->assertOk()
        ->assertJsonPath('recent.0.details', 'Details expired');

    getJson('__devsquad-sidecar/activity/'.$entry['id'])
        ->assertOk()
        ->assertJsonPath('details', 'Details expired');
});

it('filters a day and one tester', function () {
    postJson('__devsquad-sidecar/execute-command', ['command' => 'view:clear'], asTester('anasouza1', 'Ana Souza'))->assertOk();
    postJson('__devsquad-sidecar/execute-command', ['command' => 'cache:clear'], asTester('brunolima', 'Bruno Lima'))->assertOk();

    getJson('__devsquad-sidecar/activity?full=1&tester=anasouza1&day='.date('Y-m-d'))
        ->assertOk()
        ->assertJsonCount(1, 'entries')
        ->assertJsonPath('entries.0.name', 'Ana Souza');
});

it('notices when the fake clock disappeared outside Sidecar', function () {
    postJson('__devsquad-sidecar/execute-fake-clock', [
        'datetime' => now()->addDay()->toIso8601String(),
    ])->assertOk();

    Cache::forget(FakeClock::KEY);

    getJson('__devsquad-sidecar/activity')->assertOk();

    expect(collect(activityRecent())->contains(
        fn (array $entry) => $entry['summary'] === 'Clock was reset outside Sidecar (cache cleared?)'
    ))->toBeTrue();

    $count = collect(activityRecent())->where('summary', 'Clock was reset outside Sidecar (cache cleared?)')->count();

    getJson('__devsquad-sidecar/activity')->assertOk();

    expect(collect(activityRecent())->where('summary', 'Clock was reset outside Sidecar (cache cleared?)')->count())->toBe($count);
});

it('hides presence when storage cannot be written and does not fail the action', function () {
    $dir = storage_path('app/sidecar');
    resetActivity();

    if (is_dir($dir)) {
        rmdir($dir);
    }

    file_put_contents($dir, 'not-a-directory');

    getJson('__devsquad-sidecar/data')->assertOk()->assertJsonPath('presence', false);

    postJson('__devsquad-sidecar/execute-command', ['command' => 'view:clear'])->assertOk();

    unlink($dir);
});

it('turns presence off on local and when the flag is off', function () {
    $this->app['env'] = 'local';
    Config::set('devsquad-sidecar.presence_enabled', null);

    expect(app(ActivityLog::class)->enabled())->toBeFalse();

    Config::set('devsquad-sidecar.presence_enabled', true);

    expect(app(ActivityLog::class)->enabled())->toBeFalse();

    getJson('__devsquad-sidecar/data')->assertOk()->assertJsonPath('presence', false);

    Config::set('devsquad-sidecar.presence_enabled', false);
    $this->app['env'] = 'testing';

    postJson('__devsquad-sidecar/presence', ['id' => 'brunolima', 'name' => 'Bruno Lima', 'ttl' => 600])->assertOk();
    Config::set('devsquad-sidecar.presence_enabled', true);
    postJson('__devsquad-sidecar/presence', ['id' => 'brunolima', 'name' => 'Bruno Lima', 'ttl' => 600])->assertOk();
    Config::set('devsquad-sidecar.presence_enabled', false);

    postJson('__devsquad-sidecar/execute-command', ['command' => 'view:clear'], asTester('anasouza1', 'Ana Souza'))
        ->assertOk();
});

it('advertises presence on the data payload when the log can be written', function () {
    getJson('__devsquad-sidecar/data')
        ->assertOk()
        ->assertJsonPath('presence', true)
        ->assertJsonPath('presence_require_confirm', true);
});

it('protects presence and activity with the same allowed ips as execute', function () {
    Config::set('devsquad-sidecar.allowed_ips', ['10.0.0.1']);

    actingAs(User::create(['name' => 'Ada', 'email' => 'ada@sidecar.test']))
        ->postJson('__devsquad-sidecar/presence', ['id' => 'anasouza1', 'name' => 'Ana Souza', 'ttl' => 600])
        ->assertForbidden();

    actingAs(User::first())
        ->getJson('__devsquad-sidecar/activity')
        ->assertForbidden();
});

it('clears old activity, expired testers, and can wipe everything', function () {
    $dir = storage_path('app/sidecar');
    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    file_put_contents($dir.'/activity-2000-01-01.jsonl', "{\"id\":\"old\"}\n");
    file_put_contents($dir.'/state.json', json_encode([
        'testers' => [
            'gone-tester' => ['name' => 'Gone', 'started_at' => 10, 'last_seen' => 10, 'ttl' => 10],
            'anasouza1' => ['name' => 'Ana Souza', 'started_at' => time(), 'last_seen' => time(), 'ttl' => 600],
        ],
        'recent' => [[
            'id' => 'old',
            'type' => 'command',
            'summary' => 'old',
            'ref' => ['file' => 'activity-2000-01-01.jsonl', 'offset' => 0, 'length' => 14],
        ]],
        'cleaned_on' => date('Y-m-d'),
        'clock_notice' => null,
    ]));

    $this->artisan('sidecar:activity:clear --dry-run')
        ->expectsOutputToContain('activity-2000-01-01.jsonl')
        ->expectsOutputToContain('Would remove 1 activity file')
        ->assertSuccessful();

    expect(is_file($dir.'/activity-2000-01-01.jsonl'))->toBeTrue();

    $cleared = app(ActivityLog::class)->clear(null, false, false);

    expect($cleared['message'])->toContain('Removed 1 activity file')
        ->and($cleared['message'])->toContain('1 expired tester')
        ->and($cleared['testers'])->toBe(1);

    expect(is_file($dir.'/activity-2000-01-01.jsonl'))->toBeFalse();

    $state = json_decode((string) file_get_contents($dir.'/state.json'), true);

    expect($state['testers'])->not->toHaveKey('gone-tester')
        ->and($state['recent'][0]['details'] ?? null)->toBe('Details expired');

    file_put_contents($dir.'/activity-'.date('Y-m-d').'.jsonl', "{\"id\":\"today\"}\n");

    $this->artisan('sidecar:activity:clear --all')
        ->expectsConfirmation('Delete all Sidecar activity and presence data?', 'no')
        ->expectsOutputToContain('Nothing was deleted.')
        ->assertSuccessful();

    expect(is_file($dir.'/activity-'.date('Y-m-d').'.jsonl'))->toBeTrue();

    $this->artisan('sidecar:activity:clear --all --force')
        ->expectsOutputToContain('Removed')
        ->assertSuccessful();

    expect(is_file($dir.'/activity-'.date('Y-m-d').'.jsonl'))->toBeFalse()
        ->and(json_decode((string) file_get_contents($dir.'/state.json'), true)['testers'])->toBe([]);
});

it('registers the daily cleanup and honours the switch', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'sidecar:activity:clear'));

    expect($event)->not->toBeNull()
        ->and($event->filtersPass(app()))->toBeTrue();

    Config::set('devsquad-sidecar.activity_cleanup_schedule', false);

    expect($event->filtersPass(app()))->toBeFalse();
});

it('stays quiet when presence is off', function () {
    Config::set('devsquad-sidecar.presence_enabled', false);
    $log = app(ActivityLog::class);

    $log->record(['type' => 'command', 'summary' => 'cache:clear']);

    expect($log->snapshot())->toBe(['testers' => [], 'recent' => []])
        ->and($log->stop('anasouza1'))->toBe(['testers' => [], 'recent' => []])
        ->and($log->full(null, null)['entries'])->toBe([])
        ->and($log->entry('anything'))->toBeNull()
        ->and(is_file(storage_path('app/sidecar/state.json')))->toBeFalse();
});

it('finds an entry that left the recent list and answers 404 for an unknown one', function () {
    $log = app(ActivityLog::class);

    foreach (range(1, ActivityLog::RECENT_LIMIT + 2) as $index) {
        $log->record(['type' => 'command', 'summary' => "app:step {$index}", 'output' => "step {$index}"]);
    }

    $first = $log->full(null, null)['entries'][0];

    expect(collect(activityRecent())->pluck('id'))->not->toContain($first['id']);

    getJson('__devsquad-sidecar/activity/'.$first['id'])
        ->assertOk()
        ->assertJsonPath('output', 'step 1');

    getJson('__devsquad-sidecar/activity/01UNKNOWN')->assertNotFound();
});

it('says the details expired when the heavy file or its reference is broken', function () {
    $log = app(ActivityLog::class);
    $log->record(['type' => 'command', 'summary' => 'app:sync']);

    $path = storage_path('app/sidecar/state.json');
    $state = json_decode((string) file_get_contents($path), true);
    $entry = $state['recent'][0];

    $state['recent'] = [
        [...$entry, 'id' => 'bad-offset', 'ref' => ['file' => $entry['ref']['file'], 'offset' => 'x', 'length' => 10]],
        [...$entry, 'id' => 'no-length', 'ref' => ['file' => $entry['ref']['file'], 'offset' => 0, 'length' => 0]],
        [...$entry, 'id' => 'past-end', 'ref' => ['file' => $entry['ref']['file'], 'offset' => 999999, 'length' => 10]],
        [...$entry, 'id' => 'garbage', 'ref' => ['file' => $entry['ref']['file'], 'offset' => 1, 'length' => 5]],
        $entry,
    ];
    file_put_contents($path, json_encode($state));

    foreach (['bad-offset', 'no-length', 'past-end', 'garbage'] as $id) {
        expect($log->entry($id)['details'] ?? null)->toBe('Details expired');
    }

    unlink(storage_path('app/sidecar/'.$entry['ref']['file']));

    expect($log->entry($entry['id'])['details'])->toBe('Details expired');
});

it('starts over from a broken state file and skips broken lines', function () {
    $log = app(ActivityLog::class);
    $dir = storage_path('app/sidecar');
    @mkdir($dir, 0775, true);

    file_put_contents($dir.'/state.json', '{not json');
    expect($log->snapshot())->toBe(['testers' => [], 'recent' => []]);

    file_put_contents($dir.'/state.json', '1');
    expect($log->snapshot())->toBe(['testers' => [], 'recent' => []]);

    file_put_contents($dir.'/state.json', json_encode(['testers' => ['anasouza1' => 'nope'], 'recent' => ['nope', [1 => 'x', 'id' => 'kept']]]));
    $snapshot = $log->snapshot();

    expect($snapshot['testers'])->toBe([])
        ->and($snapshot['recent'])->toBe([['id' => 'kept']]);

    $log->record(['type' => 'command', 'summary' => 'app:sync']);
    $day = date('Y-m-d');
    file_put_contents($dir."/activity-{$day}.jsonl", "not json\n1\n", FILE_APPEND);

    expect($log->full($day, null)['entries'])->toHaveCount(1);
});

it('prunes old activity on the first write of a day', function () {
    $log = app(ActivityLog::class);
    $log->record(['type' => 'command', 'summary' => 'app:sync']);

    $dir = storage_path('app/sidecar');
    file_put_contents($dir.'/activity-2000-01-01.jsonl', "{}\n");

    $state = json_decode((string) file_get_contents($dir.'/state.json'), true);
    $state['cleaned_on'] = '2000-01-02';
    file_put_contents($dir.'/state.json', json_encode($state));

    $log->snapshot();

    expect(is_file($dir.'/activity-2000-01-01.jsonl'))->toBeFalse()
        ->and(is_file($dir.'/activity-'.date('Y-m-d').'.jsonl'))->toBeTrue();
});

it('reports cleared sizes in KB and MB, keeps current days, and can keep nothing', function () {
    $log = app(ActivityLog::class);
    $log->record(['type' => 'command', 'summary' => 'app:sync']);

    $dir = storage_path('app/sidecar');
    file_put_contents($dir.'/activity-2000-01-01.jsonl', str_repeat('a', 2048));

    expect($log->clear(null, false, false)['message'])->toContain('2.0 KB')
        ->and(is_file($dir.'/activity-'.date('Y-m-d').'.jsonl'))->toBeTrue();

    file_put_contents($dir.'/activity-2000-01-01.jsonl', str_repeat('a', 2 * 1048576));

    expect($log->clear(null, false, true)['message'])->toContain('2.0 MB');

    expect($log->clear(0, false, true)['paths'])->toContain('activity-'.date('Y-m-d').'.jsonl');
});

it('reads boolean switches written as strings', function () {
    Config::set('devsquad-sidecar.presence_require_confirm', 'false');
    expect(app(ActivityLog::class)->requireConfirm())->toBeFalse();

    Config::set('devsquad-sidecar.presence_require_confirm', 'maybe');
    expect(app(ActivityLog::class)->requireConfirm())->toBeFalse();
});

it('treats a malformed tester header as anonymous', function () {
    postJson('__devsquad-sidecar/presence', ['id' => 'brunolima', 'name' => 'Bruno Lima', 'ttl' => 600])->assertOk();

    postJson('__devsquad-sidecar/execute-command', ['command' => 'view:clear'], ['X-Sidecar-Tester' => 'bad;Ana Souza'])
        ->assertOk();

    expect(collect(activityRecent())->firstWhere('summary', 'view:clear')['name'])->toBe('Someone');
});

it('spells out list, flag and skipped parameters in the command summary', function () {
    postJson('__devsquad-sidecar/execute-command', [
        'command' => 'app:sync',
        'parameters' => ['part' => 2, '--dry-run' => true, '--force' => false, '--queue' => null, '--tags' => ['a']],
    ])->assertOk();

    postJson('__devsquad-sidecar/execute-command', [
        'command' => 'app:sync',
        'parameters' => ['--part=2', 3, ['nested']],
    ])->assertOk();

    $summaries = collect(activityRecent())->where('type', 'command')->pluck('summary')->all();

    expect($summaries)->toContain('app:sync part=2 --dry-run')
        ->and($summaries)->toContain('app:sync --part=2 3');
});

it('does not record a clock change that failed validation', function () {
    postJson('__devsquad-sidecar/execute-fake-clock', ['datetime' => 'not a date'])->assertUnprocessable();

    expect(collect(activityRecent())->where('type', 'clock'))->toHaveCount(0);
});
