<?php

namespace EliteDevSquad\SidecarLaravel\Activity;

use EliteDevSquad\SidecarLaravel\FakeClock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ActivityLog
{
    public const RECENT_LIMIT = 50;

    public const BODY_LIMIT = 16384;

    /**
     * @return array{testers: list<array{id: string, name: string, started_at: int, last_seen: int, ttl: int}>, recent: list<array<string, mixed>>}
     */
    public function snapshot(): array
    {
        if (! $this->available()) {
            return ['testers' => [], 'recent' => []];
        }

        return $this->exclusive(function (): array {
            $read = $this->readState();
            $state = $this->prepare($read);

            if ($state !== $read) {
                $this->writeState($state);
            }

            return $this->present($state);
        });
    }

    /**
     * @return array{testers: list<array{id: string, name: string, started_at: int, last_seen: int, ttl: int}>, recent: list<array<string, mixed>>}
     */
    public function heartbeat(string $id, string $name, int $ttl): array
    {
        if (! $this->available()) {
            return ['testers' => [], 'recent' => []];
        }

        return $this->exclusive(function () use ($id, $name, $ttl): array {
            $state = $this->prepare($this->readState());
            $now = time();
            $existing = $state['testers'][$id] ?? null;
            $started = is_array($existing);

            $state['testers'][$id] = [
                'name' => $name,
                'started_at' => $started ? (int) $existing['started_at'] : $now,
                'last_seen' => $now,
                'ttl' => $ttl,
            ];

            if (! $started) {
                $state = $this->append($state, [
                    'type' => 'presence',
                    'summary' => 'started testing',
                    'tester' => $id,
                    'name' => $name,
                ]);
            }

            $this->writeState($state);

            return $this->present($state);
        });
    }

    /**
     * @return array{testers: list<array{id: string, name: string, started_at: int, last_seen: int, ttl: int}>, recent: list<array<string, mixed>>}
     */
    public function stop(string $id): array
    {
        if (! $this->available()) {
            return ['testers' => [], 'recent' => []];
        }

        return $this->exclusive(function () use ($id): array {
            $state = $this->prepare($this->readState());
            $existing = $state['testers'][$id] ?? null;

            if (is_array($existing)) {
                unset($state['testers'][$id]);
                $state = $this->append($state, [
                    'type' => 'presence',
                    'summary' => 'stopped testing',
                    'tester' => $id,
                    'name' => (string) $existing['name'],
                ]);
            }

            $this->writeState($state);

            return $this->present($state);
        });
    }

    /**
     * @param  array<string, mixed>  $action
     */
    public function record(array $action): void
    {
        if (! $this->available()) {
            return;
        }

        $this->exclusive(function () use ($action): void {
            $state = $this->prepare($this->readState());
            $state = $this->append($state, $action);
            $this->writeState($state);
        });
    }

    /**
     * @return list<array{id: string, name: string, started_at: int, last_seen: int, ttl: int}>
     */
    public function others(?string $exceptId): array
    {
        $testers = $this->snapshot()['testers'];

        if ($exceptId === null) {
            return $testers;
        }

        return array_values(array_filter(
            $testers,
            fn (array $tester): bool => $tester['id'] !== $exceptId
        ));
    }

    /**
     * @return array{testers: list<array{id: string, name: string, started_at: int, last_seen: int, ttl: int}>, day: string, entries: list<array<string, mixed>>}
     */
    public function full(?string $day, ?string $tester): array
    {
        $day = $this->validDay($day) ?? date('Y-m-d');

        if (! $this->available()) {
            return ['testers' => [], 'day' => $day, 'entries' => []];
        }

        $testers = $this->snapshot()['testers'];
        $entries = [];

        foreach ($this->readLines($this->dayPath($day)) as $line) {
            if ($tester !== null && $tester !== '' && ($line['tester'] ?? null) !== $tester) {
                continue;
            }

            $entries[] = $line;
        }

        return [
            'testers' => $testers,
            'day' => $day,
            'entries' => $entries,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function entry(string $id): ?array
    {
        if (! $this->available()) {
            return null;
        }

        return $this->exclusive(function () use ($id): ?array {
            $state = $this->readState();

            foreach ($state['recent'] as $summary) {
                if (($summary['id'] ?? null) !== $id) {
                    continue;
                }

                $ref = $summary['ref'] ?? null;

                if (($summary['details'] ?? null) === 'Details expired' || ! is_array($ref)) {
                    return [
                        'id' => $id,
                        'expired' => true,
                        'details' => 'Details expired',
                        'summary' => $summary,
                    ];
                }

                $line = $this->readSlice($ref);

                if ($line === null) {
                    return [
                        'id' => $id,
                        'expired' => true,
                        'details' => 'Details expired',
                        'summary' => $summary,
                    ];
                }

                return $line;
            }

            foreach ($this->activityFiles() as $file) {
                foreach ($this->readLines($file) as $line) {
                    if (($line['id'] ?? null) === $id) {
                        return $line;
                    }
                }
            }

            return null;
        });
    }

    /**
     * @return array{files: int, bytes: int, testers: int, paths: list<string>, message: string}
     */
    public function clear(?int $days, bool $all, bool $dryRun): array
    {
        if (! $this->directoryExistsOrCanBeMade() && ! $all) {
            return $this->clearResult(0, 0, 0, [], $dryRun);
        }

        return $this->exclusive(function () use ($days, $all, $dryRun): array {
            $removedFiles = 0;
            $removedBytes = 0;
            $paths = [];
            $expiredTesters = 0;

            if ($all) {
                foreach ($this->directoryFiles() as $file) {
                    $paths[] = basename($file);
                    $removedBytes += (int) (@filesize($file) ?: 0);
                    $removedFiles++;

                    if (! $dryRun) {
                        @unlink($file);
                    }
                }

                if (! $dryRun) {
                    $this->writeState($this->emptyState());
                }

                return $this->clearResult($removedFiles, $removedBytes, 0, $paths, $dryRun);
            }

            $state = $this->readState();
            $keepFrom = $this->oldestKeptDay($days ?? $this->retentionDays());

            foreach ($this->activityFiles() as $file) {
                $day = $this->fileDay(basename($file));

                if ($day === null || $day >= $keepFrom) {
                    continue;
                }

                $paths[] = basename($file);
                $removedBytes += (int) (@filesize($file) ?: 0);
                $removedFiles++;

                if (! $dryRun) {
                    @unlink($file);
                }
            }

            $now = time();

            foreach ($state['testers'] as $id => $tester) {
                if ((int) $tester['last_seen'] + (int) $tester['ttl'] >= $now) {
                    continue;
                }

                $expiredTesters++;

                if (! $dryRun) {
                    unset($state['testers'][$id]);
                }
            }

            if (! $dryRun) {
                $state['recent'] = $this->markMissingDetails($state['recent']);
                $state['cleaned_on'] = date('Y-m-d');
                $this->writeState($state);
            }

            return $this->clearResult($removedFiles, $removedBytes, $expiredTesters, $paths, $dryRun);
        });
    }

    public function enabled(): bool
    {
        if (app()->environment('local')) {
            return false;
        }

        $value = config('devsquad-sidecar.presence_enabled');

        if ($value === null || $value === '') {
            return true;
        }

        return $this->bool($value);
    }

    public function requireConfirm(): bool
    {
        return $this->bool(config('devsquad-sidecar.presence_require_confirm', true));
    }

    public function available(): bool
    {
        return $this->enabled() && $this->writable();
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function exclusive(callable $callback): mixed
    {
        $dir = $this->directory();

        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('Sidecar activity storage is not writable.');
        }

        $handle = fopen($dir.'/.lock', 'c');

        if ($handle === false) {
            throw new RuntimeException('Sidecar activity storage is not writable.');
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException('Could not lock Sidecar activity storage.');
            }

            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @param  array{testers: array<string, array{name: string, started_at: int, last_seen: int, ttl: int}>, recent: list<array<string, mixed>>, cleaned_on: string|null, clock_notice: string|null}  $state
     * @return array{testers: array<string, array{name: string, started_at: int, last_seen: int, ttl: int}>, recent: list<array<string, mixed>>, cleaned_on: string|null, clock_notice: string|null}
     */
    private function prepare(array $state): array
    {
        $today = date('Y-m-d');

        if ($state['cleaned_on'] !== $today) {
            $state = $this->pruneOldFiles($state, null);
            $state['cleaned_on'] = $today;
        }

        $state = $this->dropExpired($state);
        $state = $this->noticeExternalClockReset($state);

        return $state;
    }

    /**
     * @param  array{testers: array<string, array{name: string, started_at: int, last_seen: int, ttl: int}>, recent: list<array<string, mixed>>, cleaned_on: string|null, clock_notice: string|null}  $state
     * @return array{testers: array<string, array{name: string, started_at: int, last_seen: int, ttl: int}>, recent: list<array<string, mixed>>, cleaned_on: string|null, clock_notice: string|null}
     */
    private function pruneOldFiles(array $state, ?int $days): array
    {
        $keepFrom = $this->oldestKeptDay($days ?? $this->retentionDays());

        foreach ($this->activityFiles() as $file) {
            $day = $this->fileDay(basename($file));

            if ($day !== null && $day < $keepFrom) {
                @unlink($file);
            }
        }

        $state['recent'] = $this->markMissingDetails($state['recent']);

        return $state;
    }

    /**
     * @param  list<array<string, mixed>>  $recent
     * @return list<array<string, mixed>>
     */
    private function markMissingDetails(array $recent): array
    {
        $kept = [];

        foreach ($recent as $entry) {
            $ref = $entry['ref'] ?? null;
            $file = is_array($ref) ? ($ref['file'] ?? null) : null;

            if (is_string($file) && ! is_file($this->directory().'/'.$file)) {
                unset($entry['ref']);
                $entry['details'] = 'Details expired';
            }

            $kept[] = $entry;
        }

        return $kept;
    }

    /**
     * @param  array{testers: array<string, array{name: string, started_at: int, last_seen: int, ttl: int}>, recent: list<array<string, mixed>>, cleaned_on: string|null, clock_notice: string|null}  $state
     * @return array{testers: array<string, array{name: string, started_at: int, last_seen: int, ttl: int}>, recent: list<array<string, mixed>>, cleaned_on: string|null, clock_notice: string|null}
     */
    private function dropExpired(array $state): array
    {
        $now = time();

        foreach ($state['testers'] as $id => $tester) {
            $ends = (int) $tester['last_seen'] + (int) $tester['ttl'];

            if ($ends >= $now) {
                continue;
            }

            unset($state['testers'][$id]);
            $state = $this->append($state, [
                'type' => 'presence',
                'summary' => 'testing time ended',
                'tester' => $id,
                'name' => $tester['name'],
                'ended_at' => $ends,
            ]);
        }

        return $state;
    }

    /**
     * @param  array{testers: array<string, array{name: string, started_at: int, last_seen: int, ttl: int}>, recent: list<array<string, mixed>>, cleaned_on: string|null, clock_notice: string|null}  $state
     * @return array{testers: array<string, array{name: string, started_at: int, last_seen: int, ttl: int}>, recent: list<array<string, mixed>>, cleaned_on: string|null, clock_notice: string|null}
     */
    private function noticeExternalClockReset(array $state): array
    {
        $last = null;

        foreach (array_reverse($state['recent']) as $entry) {
            if (($entry['type'] ?? null) === 'clock') {
                $last = $entry;
                break;
            }
        }

        if (! is_array($last) || ($last['clock'] ?? null) !== 'set') {
            return $state;
        }

        if (is_numeric(Cache::get(FakeClock::KEY))) {
            return $state;
        }

        $noticed = $last['id'] ?? null;

        if ($state['clock_notice'] === $noticed) {
            return $state;
        }

        $state['clock_notice'] = is_string($noticed) ? $noticed : null;

        return $this->append($state, [
            'type' => 'clock',
            'clock' => 'external',
            'summary' => 'Clock was reset outside Sidecar (cache cleared?)',
            'name' => 'Someone',
            'tester' => null,
        ]);
    }

    /**
     * @param  array{testers: array<string, array{name: string, started_at: int, last_seen: int, ttl: int}>, recent: list<array<string, mixed>>, cleaned_on: string|null, clock_notice: string|null}  $state
     * @param  array<string, mixed>  $action
     * @return array{testers: array<string, array{name: string, started_at: int, last_seen: int, ttl: int}>, recent: list<array<string, mixed>>, cleaned_on: string|null, clock_notice: string|null}
     */
    private function append(array $state, array $action): array
    {
        $id = Str::ulid()->toString();
        $at = is_numeric($action['at'] ?? null) ? (int) $action['at'] : time();
        $code = $this->clip($action['code'] ?? null);
        $output = $this->clip($action['output'] ?? null);

        /** @var list<string> $over */
        $over = array_values(array_filter(
            is_array($action['over'] ?? null) ? $action['over'] : [],
            fn (mixed $name): bool => is_string($name) && $name !== ''
        ));

        /** @var list<string> $overIds */
        $overIds = array_values(array_filter(
            is_array($action['over_ids'] ?? null) ? $action['over_ids'] : [],
            fn (mixed $testerId): bool => is_string($testerId) && $testerId !== ''
        ));

        $heavy = [
            'id' => $id,
            'at' => $at,
            'app_at' => $this->applicationTimestamp(),
            'tester' => $action['tester'] ?? null,
            'name' => is_string($action['name'] ?? null) ? $action['name'] : 'Someone',
            'branch' => $this->branch(),
            'type' => is_string($action['type'] ?? null) ? $action['type'] : 'command',
            'summary' => is_string($action['summary'] ?? null) ? $action['summary'] : '',
            'status' => $action['status'] ?? null,
            'ms' => is_numeric($action['ms'] ?? null) ? (int) $action['ms'] : null,
            'over' => $over,
            'over_ids' => $overIds,
            'code' => $code['text'],
            'output' => $output['text'],
            'truncated' => $code['truncated'] || $output['truncated'],
        ];

        foreach (['clock', 'from', 'to', 'user_id', 'batch_id', 'command', 'ended_at'] as $extra) {
            if (array_key_exists($extra, $action)) {
                $heavy[$extra] = $action[$extra];
            }
        }

        $encoded = json_encode($heavy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        if ($encoded === false) {
            return $state;
        }

        $line = $encoded."\n";
        $day = date('Y-m-d', $at);
        $filename = 'activity-'.$day.'.jsonl';
        $path = $this->directory().'/'.$filename;
        $offset = is_file($path) ? (int) filesize($path) : 0;
        $written = file_put_contents($path, $line, FILE_APPEND | LOCK_EX);

        if ($written === false) {
            return $state;
        }

        $summary = [
            'id' => $id,
            'at' => $at,
            'app_at' => $heavy['app_at'],
            'tester' => $heavy['tester'],
            'name' => $heavy['name'],
            'branch' => $heavy['branch'],
            'type' => $heavy['type'],
            'summary' => $heavy['summary'],
            'status' => $heavy['status'],
            'ms' => $heavy['ms'],
            'over' => $over,
            'over_ids' => $overIds,
            'ref' => [
                'file' => $filename,
                'offset' => $offset,
                'length' => strlen($line),
            ],
        ];

        if (isset($heavy['clock'])) {
            $summary['clock'] = $heavy['clock'];
        }

        $state['recent'][] = $summary;
        $state['recent'] = array_slice($state['recent'], -self::RECENT_LIMIT);

        return $state;
    }

    /**
     * @param  array<array-key, mixed>  $ref
     * @return array<string, mixed>|null
     */
    private function readSlice(array $ref): ?array
    {
        $file = $ref['file'] ?? null;
        $offset = $ref['offset'] ?? null;
        $length = $ref['length'] ?? null;

        if (! is_string($file) || ! is_numeric($offset) || ! is_numeric($length)) {
            return null;
        }

        $path = $this->directory().'/'.basename($file);

        if (! is_file($path)) {
            return null;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            return null;
        }

        try {
            $bytes = (int) $length;

            if ($bytes < 1 || fseek($handle, (int) $offset) !== 0) {
                return null;
            }

            $chunk = fread($handle, $bytes);

            if (! is_string($chunk) || $chunk === '') {
                return null;
            }

            return $this->decodeObject(trim($chunk));
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readLines(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $lines = [];
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $decoded = $this->decodeObject($line);

                if ($decoded !== null) {
                    $lines[] = $decoded;
                }
            }
        } finally {
            fclose($handle);
        }

        return $lines;
    }

    /**
     * @param  array{testers: array<string, array{name: string, started_at: int, last_seen: int, ttl: int}>, recent: list<array<string, mixed>>, cleaned_on: string|null, clock_notice: string|null}  $state
     * @return array{testers: list<array{id: string, name: string, started_at: int, last_seen: int, ttl: int}>, recent: list<array<string, mixed>>}
     */
    private function present(array $state): array
    {
        $testers = [];

        foreach ($state['testers'] as $id => $tester) {
            $testers[] = [
                'id' => (string) $id,
                'name' => $tester['name'],
                'started_at' => (int) $tester['started_at'],
                'last_seen' => (int) $tester['last_seen'],
                'ttl' => (int) $tester['ttl'],
            ];
        }

        return [
            'testers' => $testers,
            'recent' => $state['recent'],
        ];
    }

    /**
     * @return array{testers: array<string, array{name: string, started_at: int, last_seen: int, ttl: int}>, recent: list<array<string, mixed>>, cleaned_on: string|null, clock_notice: string|null}
     */
    private function readState(): array
    {
        $path = $this->directory().'/state.json';

        if (! is_file($path)) {
            return $this->emptyState();
        }

        try {
            $decoded = json_decode((string) file_get_contents($path), true);
        } catch (Throwable) {
            return $this->emptyState();
        }

        if (! is_array($decoded)) {
            return $this->emptyState();
        }

        return $this->normalizeState($decoded);
    }

    /**
     * @param  array{testers: array<string, array{name: string, started_at: int, last_seen: int, ttl: int}>, recent: list<array<string, mixed>>, cleaned_on: string|null, clock_notice: string|null}  $state
     */
    private function writeState(array $state): void
    {
        $encoded = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            return;
        }

        file_put_contents($this->directory().'/state.json', $encoded, LOCK_EX);
    }

    /**
     * @param  array<mixed, mixed>  $decoded
     * @return array{testers: array<string, array{name: string, started_at: int, last_seen: int, ttl: int}>, recent: list<array<string, mixed>>, cleaned_on: string|null, clock_notice: string|null}
     */
    private function normalizeState(array $decoded): array
    {
        $state = $this->emptyState();
        $testers = $decoded['testers'] ?? null;

        if (is_array($testers)) {
            foreach ($testers as $id => $tester) {
                if (! is_array($tester)) {
                    continue;
                }

                $started = $tester['started_at'] ?? 0;
                $seen = $tester['last_seen'] ?? 0;
                $ttl = $tester['ttl'] ?? 600;

                $state['testers'][(string) $id] = [
                    'name' => is_string($tester['name'] ?? null) ? $tester['name'] : 'Someone',
                    'started_at' => is_numeric($started) ? (int) $started : 0,
                    'last_seen' => is_numeric($seen) ? (int) $seen : 0,
                    'ttl' => is_numeric($ttl) ? (int) $ttl : 600,
                ];
            }
        }

        $recent = $decoded['recent'] ?? null;

        if (is_array($recent)) {
            foreach ($recent as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $state['recent'][] = $this->stringKeyed($entry);
            }
        }

        $state['cleaned_on'] = is_string($decoded['cleaned_on'] ?? null) ? $decoded['cleaned_on'] : null;
        $state['clock_notice'] = is_string($decoded['clock_notice'] ?? null) ? $decoded['clock_notice'] : null;

        return $state;
    }

    /**
     * @return array{testers: array<string, array{name: string, started_at: int, last_seen: int, ttl: int}>, recent: list<array<string, mixed>>, cleaned_on: string|null, clock_notice: string|null}
     */
    private function emptyState(): array
    {
        return [
            'testers' => [],
            'recent' => [],
            'cleaned_on' => null,
            'clock_notice' => null,
        ];
    }

    private function applicationTimestamp(): ?int
    {
        $offset = session(FakeClock::KEY);

        if (! is_numeric($offset)) {
            $offset = Cache::get(FakeClock::KEY);
        }

        if (! is_numeric($offset)) {
            return null;
        }

        return time() + (int) $offset;
    }

    private function branch(): ?string
    {
        $branch = config('devsquad-sidecar.branch_name', '');
        $branch = trim(is_string($branch) ? $branch : '');

        return $branch !== '' ? $branch : null;
    }

    /**
     * @return array{text: string|null, truncated: bool}
     */
    private function clip(mixed $value): array
    {
        if (! is_string($value)) {
            return ['text' => null, 'truncated' => false];
        }

        if (strlen($value) <= self::BODY_LIMIT) {
            return ['text' => $value, 'truncated' => false];
        }

        return ['text' => substr($value, 0, self::BODY_LIMIT), 'truncated' => true];
    }

    private function retentionDays(): int
    {
        $days = config('devsquad-sidecar.activity_retention_days', 2);

        return max(1, is_numeric($days) ? (int) $days : 2);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeObject(string $json): ?array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        return $this->stringKeyed($decoded);
    }

    /**
     * @param  array<mixed, mixed>  $value
     * @return array<string, mixed>
     */
    private function stringKeyed(array $value): array
    {
        $object = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $object[$key] = $item;
            }
        }

        return $object;
    }

    private function oldestKeptDay(int $days): string
    {
        if ($days <= 0) {
            return '9999-99-99';
        }

        return date('Y-m-d', strtotime('-'.($days - 1).' days') ?: time());
    }

    private function validDay(?string $day): ?string
    {
        if ($day === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) !== 1) {
            return null;
        }

        return $day;
    }

    private function fileDay(string $name): ?string
    {
        if (preg_match('/^activity-(\d{4}-\d{2}-\d{2})\.jsonl$/', $name, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function dayPath(string $day): string
    {
        return $this->directory().'/activity-'.$day.'.jsonl';
    }

    /**
     * @return list<string>
     */
    private function activityFiles(): array
    {
        return array_values(array_filter(
            $this->directoryFiles(),
            fn (string $file): bool => $this->fileDay(basename($file)) !== null
        ));
    }

    /**
     * @return list<string>
     */
    private function directoryFiles(): array
    {
        $dir = $this->directory();

        if (! is_dir($dir)) {
            return [];
        }

        $files = scandir($dir);

        if ($files === false) {
            return [];
        }

        $paths = [];

        foreach ($files as $name) {
            if ($name === '.' || $name === '..' || $name === '.lock') {
                continue;
            }

            $paths[] = $dir.'/'.$name;
        }

        return $paths;
    }

    /**
     * @param  list<string>  $paths
     * @return array{files: int, bytes: int, testers: int, paths: list<string>, message: string}
     */
    private function clearResult(int $files, int $bytes, int $testers, array $paths, bool $dryRun): array
    {
        $fileWord = $files === 1 ? 'file' : 'files';
        $testerWord = $testers === 1 ? 'tester' : 'testers';
        $verb = $dryRun ? 'Would remove' : 'Removed';

        return [
            'files' => $files,
            'bytes' => $bytes,
            'testers' => $testers,
            'paths' => $paths,
            'message' => sprintf('%s %d activity %s (%s) and %d expired %s.', $verb, $files, $fileWord, $this->formatBytes($bytes), $testers, $testerWord),
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / 1048576, 1).' MB';
    }

    private function directory(): string
    {
        return storage_path('app/sidecar');
    }

    private function writable(): bool
    {
        $dir = $this->directory();

        if (is_dir($dir)) {
            return is_writable($dir);
        }

        if (file_exists($dir)) {
            return false;
        }

        $parent = dirname($dir);

        return is_dir($parent) && is_writable($parent);
    }

    private function directoryExistsOrCanBeMade(): bool
    {
        return $this->writable();
    }

    private function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $filtered = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $filtered ?? false;
    }
}
