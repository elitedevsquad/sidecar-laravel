<?php

namespace EliteDevSquad\SidecarExtensionBridge\Http\Controllers;

use EliteDevSquad\SidecarExtensionBridge\Http\Requests\{ExecuteCommandRequest, ExecuteTinkerRequest, LoginAsRequest};
use EliteDevSquad\SidecarExtensionBridge\Http\Resources\SidecarUserResource;
use EliteDevSquad\SidecarExtensionBridge\SidecarBridge;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Artisan, Auth, Cookie};
use Throwable;

readonly class SidecarController
{
    public function __construct(
        private SidecarBridge $bridge
    ) {}

    public function getData(): JsonResponse
    {
        return response()->json([
            'enabled' => true,
            'project_name' => config('app.name'),
            'currentUser' => Auth::id(),
            'branch' => $this->getBranch(),
            'database' => config('database.connections.'.config('database.default').'.database'),
            'environment' => app()->environment(),
            'users' => $this->getUsers(),
            'links' => config('devsquad-sidecar-bridge.links'),
            'commands' => config('devsquad-sidecar-bridge.commands'),
            'branch_url' => config('devsquad-sidecar-bridge.branch_url'),
        ]);
    }

    public function loginAs(LoginAsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        session()->put('fake_login', true);

        Auth::loginUsingId($validated['user_id']);

        return response()->json([
            'status' => 'success',
            'redirect' => '/',
        ]);
    }

    public function executeCommand(ExecuteCommandRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            Artisan::call($validated['command']);
            $output = Artisan::output();
        } catch (Throwable $e) {
            $output = 'Error executing command: '.$e->getMessage();
        }

        return response()->json(['output' => $output]);
    }

    public function executeTinker(ExecuteTinkerRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            Artisan::call('tinker', ['--execute' => $validated['code']]);

            $output = Artisan::output();
        } catch (Throwable $e) {
            $output = 'Error executing code: '.$e->getMessage();
        }

        $output = str($output)->after('for this Tinker session.')->trim();

        return response()->json(['output' => (string) $output]);
    }

    private function getUsers(): array
    {
        /** @var Model $userModel */
        $userModel = $this->bridge->getUserModel();
        $users = app($userModel)::query()->get();

        return SidecarUserResource::collection($users)->all();
    }

    private function getBranch(): string
    {
        return trim(config('devsquad-sidecar-bridge.branch_name') ?: shell_exec('git branch --show-current') ?: '');
    }

    public function setToken(Request $request)
    {
        $token = $request->input('token');

        $request->validate([
            'token' => 'required|string|max:80|regex:/^[a-zA-Z0-9\-_]+$/',
        ]);

        $expectedToken = config('devsquad-sidecar-bridge.auth_token');

        if (is_null($token) || is_null($expectedToken)) {
            abort(403, 'Unauthorized.');
        }

        if ($token !== $expectedToken) {
            abort(403, 'Unauthorized.');
        }

        Cookie::queue('sidecar_token', $token, 60 * 24 * 7);

        return response()->noContent();
    }
}
