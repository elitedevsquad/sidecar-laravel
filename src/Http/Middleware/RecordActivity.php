<?php

namespace EliteDevSquad\SidecarLaravel\Http\Middleware;

use Closure;
use EliteDevSquad\SidecarLaravel\Activity\{ActivityLog, TesterHeader};
use EliteDevSquad\SidecarLaravel\FakeClock;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Auth, Cache};
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RecordActivity
{
    public function __construct(private readonly ActivityLog $log) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $kind = $this->kind($request);

        if ($kind === null || ! $this->log->enabled()) {
            return $next($request);
        }

        $tester = TesterHeader::from($request);
        $others = $this->log->others($tester?->id);

        if ($this->mustConfirm($request, $kind, $tester, $others)) {
            return response()->json([
                'message' => 'Another tester is active.',
                'testers' => $others,
            ], 423);
        }

        $clockBefore = $kind === 'clock' ? $this->clockStamp() : null;
        $started = hrtime(true);
        $response = $next($request);

        if ($response instanceof Response && $response->getStatusCode() === 422) {
            return $response;
        }

        try {
            $this->log->record($this->action(
                $request,
                $response,
                $kind,
                $tester,
                $others,
                (int) ((hrtime(true) - $started) / 1_000_000),
                $clockBefore,
            ));
        } catch (Throwable $e) {
            report($e);
        }

        return $response;
    }

    /**
     * @param  list<array{id: string, name: string, started_at: int, last_seen: int, ttl: int}>  $others
     */
    private function mustConfirm(Request $request, string $kind, ?TesterHeader $tester, array $others): bool
    {
        if ($tester === null || ! $this->log->requireConfirm() || ! $this->log->available()) {
            return false;
        }

        if (! in_array($kind, ['command', 'tinker', 'tinker-queue', 'clock'], true)) {
            return false;
        }

        if ($request->boolean('confirm_over')) {
            return false;
        }

        return $others !== [];
    }

    /**
     * @param  list<array{id: string, name: string, started_at: int, last_seen: int, ttl: int}>  $others
     * @return array<string, mixed>
     */
    private function action(Request $request, mixed $response, string $kind, ?TesterHeader $tester, array $others, int $ms, ?string $clockBefore): array
    {
        $output = $this->outputOf($response);
        $ranOver = $request->boolean('confirm_over') || $tester === null;

        $action = [
            'type' => $kind === 'tinker-queue' ? 'tinker' : $kind,
            'summary' => '',
            'status' => $this->statusOf($response, $output),
            'ms' => $ms,
            'tester' => $tester?->id,
            'name' => $tester === null ? 'Someone' : $tester->name,
            'output' => $output,
            'over' => $ranOver ? $this->names($others) : [],
            'over_ids' => $ranOver ? array_column($others, 'id') : [],
        ];

        if ($kind === 'command') {
            $summary = $this->commandSummary($request);
            $action['summary'] = $summary;
            $action['command'] = $summary;

            return $action;
        }

        if ($kind === 'tinker' || $kind === 'tinker-queue') {
            $action['summary'] = $kind === 'tinker-queue' ? 'ran Tinker on the queue' : 'ran Tinker';
            $action['code'] = is_string($request->input('code')) ? $request->input('code') : '';

            if (preg_match('/Batch ID:\s*(\S+)/', $output, $matches) === 1) {
                $action['batch_id'] = $matches[1];
            }

            return $action;
        }

        if ($kind === 'clock') {
            $datetime = $request->input('datetime');
            $setting = is_string($datetime) && trim($datetime) !== '';

            if ($setting) {
                $parsed = $request->date('datetime');
                $timezone = config('app.timezone');
                $label = $parsed
                    ? $parsed->timezone(is_string($timezone) && $timezone !== '' ? $timezone : 'UTC')->format('M j, H:i')
                    : trim($datetime);
                $action['summary'] = 'set the clock to '.$label;
                $action['clock'] = 'set';
            } else {
                $action['summary'] = 'reset the clock';
                $action['clock'] = 'reset';
            }

            $action['from'] = $clockBefore;
            $action['to'] = $this->clockStamp();

            return $action;
        }

        if ($kind === 'login') {
            $action['summary'] = 'signed in as '.$this->userLabel();
            $action['user_id'] = $request->input('user_id');

            return $action;
        }

        $action['summary'] = 'cleared the user cache';

        return $action;
    }

    private function kind(Request $request): ?string
    {
        $path = '/'.ltrim($request->path(), '/');

        return match (true) {
            str_ends_with($path, '/execute-command') => 'command',
            str_ends_with($path, '/execute-tinker-on-queue') => 'tinker-queue',
            str_ends_with($path, '/execute-tinker') => 'tinker',
            str_ends_with($path, '/execute-fake-clock') => 'clock',
            str_ends_with($path, '/clear-user-cache') => 'cache',
            $request->isMethod('POST') && str_ends_with($path, '/login-as') => 'login',
            default => null,
        };
    }

    private function commandSummary(Request $request): string
    {
        $rawCommand = $request->input('command', '');
        $command = trim(is_string($rawCommand) ? $rawCommand : '');
        $parameters = $request->input('parameters');

        if (! is_array($parameters) || $parameters === []) {
            return $command;
        }

        $parts = [$command];

        foreach ($parameters as $key => $value) {
            if (is_int($key)) {
                if (is_string($value) || is_int($value) || is_float($value)) {
                    $parts[] = (string) $value;
                }

                continue;
            }

            if ($value === true) {
                $parts[] = (string) $key;

                continue;
            }

            if ($value === false || $value === null) {
                continue;
            }

            if (is_string($value) || is_int($value) || is_float($value)) {
                $parts[] = $key.'='.$value;
            }
        }

        return trim(implode(' ', $parts));
    }

    private function userLabel(): string
    {
        $user = Auth::user();

        if ($user === null) {
            return 'a user';
        }

        foreach (['name', 'first_name', 'email'] as $attribute) {
            $value = $user->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        $identifier = $user->getAuthIdentifier();

        return is_scalar($identifier) ? '#'.(string) $identifier : '#user';
    }

    private function outputOf(mixed $response): string
    {
        if (! $response instanceof JsonResponse) {
            return '';
        }

        /** @var mixed $data */
        $data = $response->getData(true);

        if (! is_array($data)) {
            return '';
        }

        return is_string($data['output'] ?? null) ? $data['output'] : '';
    }

    private function statusOf(mixed $response, string $output): string
    {
        if ($response instanceof Response && $response->getStatusCode() >= 400) {
            return 'error';
        }

        if (str_starts_with($output, 'Error executing') || str_starts_with($output, 'Command failed')) {
            return 'error';
        }

        return 'ok';
    }

    private function clockStamp(): ?string
    {
        $offset = session(FakeClock::KEY);

        if (! is_numeric($offset)) {
            $offset = Cache::get(FakeClock::KEY);
        }

        if (! is_numeric($offset)) {
            return null;
        }

        return date('Y-m-d H:i:s', time() + (int) $offset);
    }

    /**
     * @param  list<array{id: string, name: string, started_at: int, last_seen: int, ttl: int}>  $testers
     * @return list<string>
     */
    private function names(array $testers): array
    {
        return array_map(
            fn (array $tester): string => $tester['name'],
            $testers
        );
    }
}
