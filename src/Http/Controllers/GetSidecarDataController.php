<?php

namespace EliteDevSquad\SidecarLaravel\Http\Controllers;

use EliteDevSquad\SidecarLaravel\Http\Resources\SidecarUserResource;
use EliteDevSquad\SidecarLaravel\Sidecar;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Auth, Cache};

class GetSidecarDataController
{
    public function __construct(private readonly Sidecar $sidecar) {}

    public function __invoke(Request $request): JsonResponse
    {
        $initialRequest = $request->get('without_users');

        /** @var string $defaultConnection */
        $defaultConnection = config('database.default', '');

        /** @var string $database */
        $database = config("database.connections.$defaultConnection.database", '');

        /** @var string $branchUrl */
        $branchUrl = config('devsquad-sidecar.branch_url', '');

        /** @var string $projectName */
        $projectName = config('app.name', '');

        $users = $initialRequest == 'false' ? $this->getUsers() : [];

        return response()->json([
            'enabled' => true,
            'project_name' => $projectName,
            'current_user' => Cache::rememberForever('sidecar_current_user', fn () => Auth::id()),
            'branch' => $this->getBranch(),
            'database' => $database,
            'environment' => app()->environment(),
            'users' => $users,
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
        $builder = $this->sidecar->getUserQueryBuilder();

        $users = Cache::rememberForever('sidecar_users', fn () => $builder->get()); // @phpstan-ignore-line

        return SidecarUserResource::collection($users)->all(); // @phpstan-ignore-line
    }

    private function getBranch(): string
    {
        /** @var string $branch */
        $branch = config('devsquad-sidecar.branch_name');

        return trim($branch ?: shell_exec('git branch --show-current') ?: '');
    }
}
