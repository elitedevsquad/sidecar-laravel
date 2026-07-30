<?php

namespace EliteDevSquad\SidecarLaravel\Http\Controllers;

use EliteDevSquad\SidecarLaravel\FakeClock;
use EliteDevSquad\SidecarLaravel\Http\Requests\ExecuteFakeClockRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class ExecuteFakeClockController
{
    public function __invoke(ExecuteFakeClockRequest $request): JsonResponse
    {
        /**
         * @var Carbon|null $datetimeInput
         */
        $datetimeInput = $request->date('datetime');

        if ($datetimeInput) {
            FakeClock::set($datetimeInput);

            return response()->json(['output' => 'Fake clock set to '.$datetimeInput->toDateTimeString()]);
        }

        FakeClock::set(null);

        return response()->json(['output' => 'Fake clock reset to real time']);
    }
}
