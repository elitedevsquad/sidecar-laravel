<?php

namespace EliteDevSquad\SidecarLaravel\Http\Controllers;

use Composer\InstalledVersions;
use EliteDevSquad\SidecarLaravel\Http\Resources\SidecarUserResource;
use EliteDevSquad\SidecarLaravel\Sidecar;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Auth, Cache, Http};

class GetSidecarDataController
{
    public function __construct(private readonly Sidecar $sidecar) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! config('devsquad-sidecar.enabled')) {
            abort(403, 'Sidecar is disabled.');
        }

        $withoutUsers = $request->boolean('without_users');

        /** @var string $defaultConnection */
        $defaultConnection = config('database.default', '');

        /** @var string $database */
        $database = config("database.connections.$defaultConnection.database", '');

        /** @var string $branchUrl */
        $branchUrl = config('devsquad-sidecar.branch_url', '');

        /** @var string $projectName */
        $projectName = config('app.name', '');

        $users = $withoutUsers ? [] : $this->getUsers();

        return response()->json([
            'enabled' => true,
            'project_name' => $projectName,
            'authenticated' => true,
            'current_user' => Cache::rememberForever('sidecar_current_user', fn () => Auth::id()),
            'branch' => $this->getBranch(),
            'database' => $database,
            'environment' => app()->environment(),
            'users' => $users,
            'links' => config('devsquad-sidecar.links', []),
            'commands' => config('devsquad-sidecar.commands', []),
            'branch_url' => $branchUrl,
            'fake_clock' => session('sidecar_fake_clock'),
            'version' => $this->getPackageVersion(),
            'package_updated' => $this->isPackageUpdated() ? 'Yes' : 'No',
            'datetime' => now(),
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

    private function getPackageVersion(): string
    {
        return InstalledVersions::getPrettyVersion('elitedevsquad/sidecar-laravel') ?? 'unknown'; // @codeCoverageIgnore
    }

    private function isPackageUpdated(): bool
    {
        $currentVersion = $this->getPackageVersion();

        return Cache::remember('sidecar_package_updated', now()->addHours(2), function () use ($currentVersion): bool {
            try {
                $response = Http::withHeaders(['User-Agent' => 'Sidecar-Laravel'])
                    ->get('https://api.github.com/repos/EliteDevSquad/sidecar-laravel/releases/latest');

                if ($response->successful()) {
                    /** @var string $latestVersion */
                    $latestVersion = $response->json('tag_name');

                    return version_compare(ltrim($currentVersion, 'v'), ltrim($latestVersion, 'v'), '>=');
                }
            } catch (\Exception) {
                return true;
            }

            return true;
        });
    }
}
