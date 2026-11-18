<?php

namespace EliteDevSquad\SidecarLaravel\Http\Controllers;

use EliteDevSquad\SidecarLaravel\TinkerCompletions;
use Illuminate\Http\JsonResponse;

readonly class ListTinkerCompletionsController
{
    public function __construct(private TinkerCompletions $completions) {}

    public function __invoke(): JsonResponse
    {
        abort_unless((bool) config('devsquad-sidecar.enabled'), 403, 'Sidecar is disabled.');
        abort_unless((bool) config('devsquad-sidecar.tinker_enabled', true), 403, 'Tinker is disabled.');

        return response()->json([
            'models' => $this->completions->models(),
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
