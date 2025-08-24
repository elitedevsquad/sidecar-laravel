<?php

namespace EliteDevSquad\SidecarExtensionBridge\Http\Controllers;

use EliteDevSquad\SidecarExtensionBridge\Http\Requests\ExecuteCommandRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Throwable;

readonly class ExecuteCommandController
{
    public function __invoke(ExecuteCommandRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (isset($validated['clock'])) {
            Carbon::setTestNow(Carbon::parse($validated['clock']));
        }

        try {
            Artisan::call($validated['command']);
            $output = Artisan::output();
        } catch (Throwable $e) {
            $output = 'Error executing command: '.$e->getMessage();
        }

        return response()->json(['output' => $output]);
    }
}
