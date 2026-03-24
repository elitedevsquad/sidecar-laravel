<?php

namespace EliteDevSquad\SidecarLaravel\Http\Controllers;

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
            Carbon::setTestNow($datetimeInput);

            session(['sidecar_fake_clock' => $datetimeInput->toDateTimeString()]);

            return response()->json(['output' => 'Fake clock set to '.$datetimeInput->toDateTimeString()]);
        }

        Carbon::setTestNow();

        session(['sidecar_fake_clock' => null]);

        return response()->json(['output' => 'Fake clock reset to real time']);
    }
}
