<?php

namespace EliteDevSquad\SidecarExtensionBridge\Http\Controllers;

use EliteDevSquad\SidecarExtensionBridge\Http\Resources\SidecarUserResource;
use EliteDevSquad\SidecarExtensionBridge\SidecarBridge;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class GetSidecarDataController
{
    public function __construct(private readonly SidecarBridge $bridge) {}

    public function __invoke(): JsonResponse
    {
        return response()->json([
            'enabled' => true,
            'project_name' => config('app.name'),
            'currentUser' => Auth::id(),
            'branch' => $this->getBranch(),
            'database' => config('database.connections.'.config('database.default').'.database'),
            'environment' => app()->environment(),
            'users' => $this->getUsers(),
            'links' => config('devsquad-sidecar.links'),
            'commands' => config('devsquad-sidecar.commands'),
            'branch_url' => config('devsquad-sidecar.branch_url'),
            'fake_clock' => session('sidecar_fake_clock'),
            'datetime' => now(),
        ]);
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
        return trim(config('devsquad-sidecar.branch_name') ?: shell_exec('git branch --show-current') ?: '');
    }
}
