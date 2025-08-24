<?php

namespace EliteDevSquad\SidecarExtensionBridge\Http\Controllers;

use EliteDevSquad\SidecarExtensionBridge\Http\Requests\ExecuteFakeClockRequest;
use Illuminate\Support\Carbon;

class ExecuteFakeClockController
{
    public function __invoke(ExecuteFakeClockRequest $request)
    {
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
