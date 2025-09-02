<?php

namespace EliteDevSquad\Sidecar\Http\Controllers;

use Carbon\{Month, WeekDay};
use DateTimeInterface;
use EliteDevSquad\Sidecar\Http\Requests\ExecuteFakeClockRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class ExecuteFakeClockController
{
    public function __invoke(ExecuteFakeClockRequest $request): JsonResponse
    {
        /**
         * @var Month|WeekDay|DateTimeInterface|float|int|string|null $datetimeInput
         */
        $datetimeInput = $request->input('datetime');

        if ($datetimeInput) {
            $datetime = Carbon::parse($datetimeInput)->setTimeFromTimeString(now()->toTimeString());

            Carbon::setTestNow($datetime);
            session(['sidecar_fake_clock' => $datetime->toDateTimeString()]);

            return response()->json([
                'output' => 'Fake clock set to '.$datetime->toDateTimeString(),
            ]);
        }

        Carbon::setTestNow();
        session()->forget('sidecar_fake_clock');

        return response()->json([
            'output' => 'Fake clock reset to real time',
        ]);
    }
}
