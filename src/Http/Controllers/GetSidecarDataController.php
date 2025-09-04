<?php

namespace EliteDevSquad\Sidecar\Http\Controllers;

use EliteDevSquad\Sidecar\Http\Resources\SidecarUserResource;
use EliteDevSquad\Sidecar\Sidecar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class GetSidecarDataController
{
    public function __construct(private readonly Sidecar $bridge) {}

    public function __invoke(): JsonResponse
    {
        /** @var string $defaultConnection */
        $defaultConnection = config('database.default', '');

        /** @var string $database */
        $database = config("database.connections.$defaultConnection.database", '');

        /** @var string $branchUrl */
        $branchUrl = config('devsquad-sidecar.branch_url', '');

        /** @var string $projectName */
        $projectName = config('app.name', '');

        return response()->json([
            'enabled' => true,
            'project_name' => $projectName,
            'current_user' => Auth::id(),
            'branch' => $this->getBranch(),
            'database' => $database,
            'environment' => app()->environment(),
            'users' => $this->getUsers(),
            'links' => config('devsquad-sidecar.links', []),
            'commands' => config('devsquad-sidecar.commands', []),
            'branch_url' => $branchUrl,
            'fake_clock' => session('sidecar_fake_clock'),
            'datetime' => now(),
            'features' => [
                'commands' => (bool) config('devsquad-sidecar.commands_enabled', false),
                'tinker' => (bool) config('devsquad-sidecar.tinker_enabled', false),
                'fake_clock' => (bool) config('devsquad-sidecar.fake_clock_enabled', false),
            ],
        ]);
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function getUsers(): array
    {
        $userModel = $this->bridge->getUserModel();

        /** @var Model $model */
        $model = app($userModel);

        $users = $model::query()->get();

        return SidecarUserResource::collection($users)->all(); // @phpstan-ignore-line
    }

    private function getBranch(): string
    {
        /** @var string $branch */
        $branch = config('devsquad-sidecar.branch_name');

        return trim($branch ?: shell_exec('git branch --show-current') ?: '');
    }
}
