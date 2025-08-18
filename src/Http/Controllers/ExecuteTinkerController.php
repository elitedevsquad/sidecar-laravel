<?php

namespace EliteDevSquad\SidecarExtensionBridge\Http\Controllers;

use EliteDevSquad\SidecarExtensionBridge\Http\Requests\ExecuteTinkerRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Throwable;

readonly class ExecuteTinkerController
{
    public function __invoke(ExecuteTinkerRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            Artisan::call('tinker', ['--execute' => $validated['code']]);
            $output = Artisan::output();
        } catch (Throwable $e) {
            $output = 'Error executing code: ' . $e->getMessage();
        }

        $output = str($output)->after('for this Tinker session.')->trim();

        return response()->json(['output' => (string) $output]);
    }
}
