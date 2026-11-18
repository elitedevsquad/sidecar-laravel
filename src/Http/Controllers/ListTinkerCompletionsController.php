<?php

namespace EliteDevSquad\SidecarLaravel\Http\Controllers;

use EliteDevSquad\SidecarLaravel\{TinkerCatalog, TinkerCompletions};
use Illuminate\Http\{JsonResponse, Request};

readonly class ListTinkerCompletionsController
{
    public function __construct(private TinkerCompletions $completions, private TinkerCatalog $catalog) {}

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless((bool) config('devsquad-sidecar.enabled'), 403, 'Sidecar is disabled.');
        abort_unless((bool) config('devsquad-sidecar.tinker_enabled', true), 403, 'Tinker is disabled.');

        $page = max(1, $request->integer('page', 1));

        return response()->json([
            ...$this->completions->page($page),
            'framework' => $page === 1 ? $this->catalog->describe() : null,
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
