<?php

namespace EliteDevSquad\SidecarLaravel;

use Carbon\CarbonImmutable;

class Health
{
    private const LOG_TAIL_BYTES = 262144;

    private const RECENT_LOGS_LIMIT = 10;

    private const MESSAGE_LIMIT = 400;

    /**
     * @return array{log_errors: int|null, last_error_at: string|null, recent_logs: list<array{at: string, level: string, message: string}>, recent_errors: list<array{at: string, level: string, message: string}>, updated_at: string}|null
     */
    public function toArray(): ?array
    {
        if (! config('devsquad-sidecar.health_enabled')) {
            return null;
        }

        $log = $this->logErrors();

        return [
            'log_errors' => $log['count'],
            'last_error_at' => $log['last_error_at'],
            'recent_logs' => $log['recent'],
            'recent_errors' => $log['recent'],
            'updated_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }

    /**
     * @return array{count: int|null, last_error_at: string|null, recent: list<array{at: string, level: string, message: string}>}
     */
    private function logErrors(): array
    {
        $file = $this->currentLogFile();

        if ($file === null) {
            return ['count' => null, 'last_error_at' => null, 'recent' => []];
        }

        /** @var int $hours */
        $hours = config('devsquad-sidecar.health_log_window_hours', 24);

        $since = CarbonImmutable::now()->subHours($hours);
        $count = 0;
        $last = null;
        $entries = [];

        foreach (explode("\n", $this->tail($file)) as $line) {
            if (preg_match('/^\[([^\]]+)\].*\.(ERROR|CRITICAL|ALERT|EMERGENCY):\s*(.*)$/', $line, $matches) !== 1) {
                continue;
            }

            try {
                $at = CarbonImmutable::parse($matches[1]);
            } catch (\Throwable) {
                continue;
            }

            if ($at->lessThan($since)) {
                continue;
            }

            $count++;

            if ($last === null || $at->greaterThan($last)) {
                $last = $at;
            }

            $entries[] = [
                'at' => $at->toIso8601String(),
                'level' => $matches[2],
                'message' => $this->truncate($matches[3]),
            ];
        }

        return [
            'count' => $count,
            'last_error_at' => $last?->toIso8601String(),
            'recent' => array_reverse(array_slice($entries, -self::RECENT_LOGS_LIMIT)),
        ];
    }

    private function truncate(string $message): string
    {
        $message = trim($message);

        if (strlen($message) <= self::MESSAGE_LIMIT) {
            return $message;
        }

        return substr($message, 0, self::MESSAGE_LIMIT - 3).'...';
    }

    private function currentLogFile(): ?string
    {
        $directory = storage_path('logs');

        if (! is_dir($directory)) {
            return null;
        }

        $files = glob($directory.'/*.log') ?: [];
        $newest = null;
        $newestTime = 0;

        foreach ($files as $file) {
            $time = filemtime($file) ?: 0;

            if ($time >= $newestTime) {
                $newest = $file;
                $newestTime = $time;
            }
        }

        return $newest;
    }

    private function tail(string $file): string
    {
        $size = filesize($file) ?: 0;
        $handle = fopen($file, 'rb');

        if ($handle === false) {
            return '';
        }

        if ($size > self::LOG_TAIL_BYTES) {
            fseek($handle, -self::LOG_TAIL_BYTES, SEEK_END);
        }

        $contents = stream_get_contents($handle) ?: '';

        fclose($handle);

        return $contents;
    }
}
