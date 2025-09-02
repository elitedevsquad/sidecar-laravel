<?php

namespace EliteDevSquad\Sidecar\Http\Controllers;

use EliteDevSquad\Sidecar\Http\Requests\ExecuteCommandRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Throwable;

readonly class ExecuteCommandController
{
    public function __invoke(ExecuteCommandRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (isset($validated['clock']) && config('devsquad-sidecar.fake_clock_enabled')) {
            Carbon::setTestNow(Carbon::parse($validated['clock']));
        }

        try {
            Artisan::call($validated['command']['command']);
            $output = Artisan::output();
        } catch (Throwable $e) {
            $output = 'Error executing command: '.$e->getMessage();
        }

        return response()->json(['output' => $output]);
    }
}
