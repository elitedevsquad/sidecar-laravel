<?php

namespace EliteDevSquad\SidecarLaravel\Http\Controllers;

use Composer\InstalledVersions;
use EliteDevSquad\SidecarLaravel\{CommandCatalog, FakeClock, Sidecar};
use EliteDevSquad\SidecarLaravel\Http\Resources\SidecarUserResource;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Auth, Cache, Http};

class GetSidecarDataController
{
    public function __construct(
        private readonly Sidecar $sidecar,
        private readonly CommandCatalog $catalog,
    ) {}

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
            'current_user' => Auth::id(),
            'current_user_data' => $this->getCurrentUser(),
            'users_paginated' => true,
            'branch' => $this->getBranch(),
            'app_tag' => $this->getAppTag(),
            'badge_fallback' => config('devsquad-sidecar.badge_fallback'),
            'database' => $database,
            'environment' => app()->environment(),
            'users' => $users,
            'links' => config('devsquad-sidecar.links', []),
            'commands' => $this->getLegacyCommands($request),
            'commands_introspection' => true,
            'branch_url' => $branchUrl,
            'fake_clock' => FakeClock::current()?->format('Y-m-d H:i:s'),
            'fake_clock_offset' => FakeClock::offset(),
            'timezone' => config('app.timezone'),
            'health_enabled' => (bool) config('devsquad-sidecar.health_enabled'),
            'version' => $this->getPackageVersion(),
            'package_updated' => $this->isPackageUpdated() ? 'Yes' : 'No',
            'latest_version' => $this->getLatestVersion(),
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

    /**
     * @return array<mixed>|null
     */
    private function getCurrentUser(): ?array
    {
        $user = Auth::user();

        return $user ? SidecarUserResource::make($user)->resolve() : null;
    }

    private function getBranch(): string
    {
        /** @var string $branch */
        $branch = config('devsquad-sidecar.branch_name');

        return trim($branch ?: shell_exec('git branch --show-current') ?: '');
    }

    private function getAppTag(): ?string
    {
        try {
            $tag = shell_exec('git describe --tags --abbrev=0 2>/dev/null');

            return $tag ? trim($tag) : null;
        } catch (\Exception) {
            return null;
        }
    }

    private function getPackageVersion(): string
    {
        return InstalledVersions::getPrettyVersion('elitedevsquad/sidecar-laravel') ?? 'unknown'; // @codeCoverageIgnore
    }

    private function isPackageUpdated(): bool
    {
        $latestVersion = $this->getLatestVersion();

        if ($latestVersion === null) {
            return true;
        }

        return version_compare(ltrim($this->getPackageVersion(), 'v'), ltrim($latestVersion, 'v'), '>=');
    }

    private function getLatestVersion(): ?string
    {
        /** @var string $latestVersion */
        $latestVersion = Cache::remember('sidecar_package_latest', now()->addHours(2), function (): string {
            try {
                $response = Http::withHeaders(['User-Agent' => 'Sidecar-Laravel'])
                    ->get('https://api.github.com/repos/EliteDevSquad/sidecar-laravel/releases/latest');

                $tag = $response->successful() ? $response->json('tag_name') : null;

                return is_string($tag) ? $tag : '';
            } catch (\Exception) {
                return '';
            }
        });

        return $latestVersion !== '' ? $latestVersion : null;
    }

    /**
     * @return array<int, mixed>
     */
    private function getLegacyCommands(Request $request): array
    {
        /** @var array<int, mixed> $configured */
        $configured = config('devsquad-sidecar.commands', []);

        if (! $request->boolean('legacy_commands') || ! config('devsquad-sidecar.commands_enabled', true)) {
            return $configured;
        }

        $discovered = array_map(
            fn (array $command) => ['name' => $command['name'], 'command' => $command['name']],
            $this->catalog->all()
        );

        return [...$configured, ...$discovered];
    }
}
