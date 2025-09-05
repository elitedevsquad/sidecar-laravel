<?php

namespace EliteDevSquad\SidecarLaravel\Http\Controllers;

use Carbon\{Month, WeekDay};
use DateTimeInterface;
use EliteDevSquad\SidecarLaravel\Http\Requests\ExecuteTinkerRequest;
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
         * } $data
         */
        $data = $request->validated();

        if (isset($data['clock']) && config('devsquad-sidecar.fake_clock_enabled')) {
            Carbon::setTestNow($request->date('clock'));
        }

        try {
            Artisan::call('tinker', ['--execute' => $data['code']]);
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
