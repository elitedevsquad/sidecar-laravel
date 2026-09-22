<?php

namespace EliteDevSquad\SidecarLaravel\Http\Controllers;

use EliteDevSquad\SidecarLaravel\Health;
use Illuminate\Http\JsonResponse;

readonly class GetLogsController
{
    public function __construct(private Health $health) {}

    public function __invoke(): JsonResponse
    {
        abort_unless((bool) config('devsquad-sidecar.health_enabled'), 403, 'Logs are disabled.');

        return response()->json($this->health->toArray());
    }
}
