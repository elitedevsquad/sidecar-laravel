<?php

namespace EliteDevSquad\SidecarLaravel\Http\Controllers;

use EliteDevSquad\SidecarLaravel\Http\Requests\LoginAsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

readonly class LoginAsUserController
{
    public function __invoke(LoginAsRequest $request): JsonResponse
    {
        $data = $request->validated();

        session()->put('fake_login', true);

        cache()->forget('sidecar_current_user');

        Auth::loginUsingId($data['user_id']);

        return response()->json([
            'status' => 'success',
            'redirect' => '/',
        ]);
    }
}
