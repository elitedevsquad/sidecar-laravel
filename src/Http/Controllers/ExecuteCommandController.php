<?php

namespace EliteDevSquad\SidecarExtensionBridge\Http\Controllers;

use EliteDevSquad\SidecarExtensionBridge\Http\Requests\ExecuteCommandRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Throwable;

readonly class ExecuteCommandController
{
    public function __invoke(ExecuteCommandRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            Artisan::call($validated['command']);
            $output = Artisan::output();
        } catch (Throwable $e) {
            $output = 'Error executing command: ' . $e->getMessage();
        }

        return response()->json(['output' => $output]);
    }
}
