<?php

namespace EliteDevSquad\SidecarExtensionBridge\Http\Controllers;

use EliteDevSquad\SidecarExtensionBridge\Http\Requests\ExecuteFakeClockRequest;
use Illuminate\Support\Carbon;

class ExecuteFakeClockController
{
    public function __invoke(ExecuteFakeClockRequest $request)
    {
        Carbon::setTestNow(
            $datetime = Carbon::parse($request->input('datetime') ?? now())->setTimeFromTimeString(now()->toTimeString())
        );

        return response()->json([
            'output' => 'Fake clock set to '.$datetime->toDateTimeString(),
        ]);
    }
}
