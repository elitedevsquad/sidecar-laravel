<?php

namespace EliteDevSquad\Sidecar\Http\Controllers;

use EliteDevSquad\Sidecar\Http\Requests\LoginAsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

readonly class LoginAsUserController
{
    public function __invoke(LoginAsRequest $request): JsonResponse
    {
        $data = $request->validated();

        session()->put('fake_login', true);

        Auth::loginUsingId($data['user_id']);

        return response()->json([
            'status' => 'success',
            'redirect' => '/',
        ]);
    }
}
