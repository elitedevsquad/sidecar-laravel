<?php

namespace EliteDevSquad\SidecarLaravel\Http\Controllers;

use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Cookie;

readonly class SetSidecarTokenController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|max:80|regex:/^[a-zA-Z0-9\-_]+$/',
        ]);

        $token = $request->input('token');

        $expectedToken = config('devsquad-sidecar.auth_token');

        if ($token !== $expectedToken) {
            return response()->json(status: 403);
        }

        Cookie::queue('sidecar_token', $token, 60 * 24 * 7);

        return response()->json([
            'success' => true,
        ]);
    }
}
