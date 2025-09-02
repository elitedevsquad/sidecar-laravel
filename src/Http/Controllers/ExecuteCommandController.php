<?php

namespace EliteDevSquad\Sidecar\Http\Controllers;

use DateTimeInterface;
use EliteDevSquad\Sidecar\Http\Requests\ExecuteCommandRequest;
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
         *     command?: array{command: string},
         *     clock?: string|int|float|DateTimeInterface|null
         * } $validated
         */
        $validated = $request->validated();

        if (isset($validated['clock']) && config('devsquad-sidecar.fake_clock_enabled')) {
            $time = $validated['clock'];
            Carbon::setTestNow(Carbon::parse($time));
        }

        try {
            /** @var string $command */
            $command = $validated['command']['command'] ?? '';

            Artisan::call($command);
            $output = Artisan::output();
        } catch (Throwable $e) {
            $output = 'Error executing command: '.$e->getMessage();
        }

        return response()->json(['output' => $output]);
    }
}
