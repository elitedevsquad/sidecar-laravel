<?php

namespace EliteDevSquad\SidecarLaravel\Http\Controllers;

use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Cache};
use Throwable;

readonly class ClearUserCacheController
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            Cache::forget('sidecar_users');

            $output = 'User cache cleared successfully.';
        } catch (Throwable $e) {
            $output = 'Error executing command: '.$e->getMessage();
        }

        return response()->json(['output' => $output]);
    }
}
