<?php

namespace EliteDevSquad\SidecarExtensionBridge\Http\Controllers;

use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Cookie;

readonly class SetSidecarTokenController
{
    public function __invoke(Request $request): Response
    {
        $request->validate([
            'token' => 'required|string|max:80|regex:/^[a-zA-Z0-9\-_]+$/',
        ]);

        $token = $request->input('token');
        $expectedToken = config('devsquad-sidecar.auth_token');

        abort_if(is_null($token) || is_null($expectedToken), 403, 'Unauthorized.');
        abort_if($token !== $expectedToken, 403, 'Unauthorized.');

        Cookie::queue('sidecar_token', $token, 60 * 24 * 7);

        return response()->noContent();
    }
}
