<?php

namespace EliteDevSquad\SidecarLaravel\Http\Controllers;

use DateTimeInterface;
use EliteDevSquad\SidecarLaravel\Http\Requests\ExecuteCommandRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Throwable;

readonly class ExecuteCommandController
{
    public function __invoke(ExecuteCommandRequest $request): JsonResponse
    {
        /**
         * @var array{
         *     command: string,
         *     clock?: string|int|float|DateTimeInterface|null
         * } $data
         */
        $data = $request->validated();

        if (isset($data['clock']) && config('devsquad-sidecar.fake_clock_enabled')) {
            Carbon::setTestNow($request->date('clock'));
        }

        try {
            /** @var string $command */
            $command = $data['command'];

            Artisan::call($command);

            $output = Artisan::output();

            $output = ((! $output) && ($output != '&nbsp;')) // @phpstan-ignore-line
                ? 'Command executed successfully - '.$command
                : $output;
        } catch (Throwable $e) {
            $output = 'Error executing command: '.$e->getMessage();
        }

        return response()->json(['output' => $output]);
    }
}
