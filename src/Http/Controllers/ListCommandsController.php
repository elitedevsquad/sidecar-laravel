<?php

namespace EliteDevSquad\SidecarLaravel\Http\Controllers;

use EliteDevSquad\SidecarLaravel\CommandCatalog;
use Illuminate\Http\JsonResponse;

readonly class ListCommandsController
{
    public function __construct(private CommandCatalog $catalog) {}

    public function __invoke(): JsonResponse
    {
        abort_unless((bool) config('devsquad-sidecar.commands_enabled', true), 403, 'Commands are disabled.');

        return response()->json([
            'commands' => $this->catalog->all(),
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
