<?php

namespace EliteDevSquad\SidecarLaravel\Http\Controllers;

use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Cookie;

readonly class SetSidecarTokenController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|max:80',
        ]);

        $token = $request->input('token');

        $expectedToken = config('devsquad-sidecar.auth_token');

        if ($token !== $expectedToken) {
            return response()->json(status: 403);
        }

        $tokenDurationInMinutes = config('devsquad-sidecar.token_duration_in_minutes');

        Cookie::queue('sidecar_token', $token, $tokenDurationInMinutes);

        return response()->json([
            'success' => true,
        ]);
    }
}
