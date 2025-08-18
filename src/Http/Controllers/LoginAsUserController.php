<?php

namespace EliteDevSquad\SidecarExtensionBridge\Http\Controllers;

use EliteDevSquad\SidecarExtensionBridge\Http\Requests\LoginAsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

readonly class LoginAsUserController
{
    public function __invoke(LoginAsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        session()->put('fake_login', true);
        Auth::loginUsingId($validated['user_id']);

        return response()->json([
            'status'   => 'success',
            'redirect' => '/',
        ]);
    }
}
