<?php

namespace EliteDevSquad\Sidecar\Http\Controllers;

use Carbon\{Month, WeekDay};
use DateTimeInterface;
use EliteDevSquad\Sidecar\Http\Requests\ExecuteTinkerRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Throwable;

readonly class ExecuteTinkerController
{
    public function __invoke(ExecuteTinkerRequest $request): JsonResponse
    {
        /**
         * @var array{
         *     code: string,
         *     clock?: Month|WeekDay|DateTimeInterface|float|int|string|null
         * } $validated
         */
        $validated = $request->validated();

        if (isset($validated['clock']) && config('devsquad-sidecar.fake_clock_enabled')) {
            Carbon::setTestNow(Carbon::parse($validated['clock']));
        }

        try {
            Artisan::call('tinker', ['--execute' => $validated['code']]);
            $output = Artisan::output();
        } catch (Throwable $e) {
            $output = 'Error executing code: '.$e->getMessage();
        }

        $output = str($output)
            ->after('for this Tinker session.')
            ->trim()
            ->toString();

        return response()->json(['output' => $output]);
    }
}
