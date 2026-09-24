<?php

namespace EliteDevSquad\SidecarLaravel\Http\Controllers;

use EliteDevSquad\SidecarLaravel\UserDirectory;
use Illuminate\Http\{JsonResponse, Request};

readonly class ListUsersController
{
    public function __construct(private UserDirectory $directory) {}

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless((bool) config('devsquad-sidecar.enabled'), 403, 'Sidecar is disabled.');

        $ids = $this->ids($request->input('ids'));

        $result = $this->directory->page(
            search: $request->string('search')->toString(),
            role: $request->string('role')->toString(),
            ids: $ids,
            page: $request->integer('page', 1),
            perPage: $request->integer('per_page', UserDirectory::PER_PAGE),
        );

        $withRoles = $ids === [] && $result['meta']['current_page'] === 1;

        return response()->json([
            ...$result,
            'roles' => $withRoles ? $this->directory->roles() : null,
        ]);
    }

    /**
     * @return array<int, int|string>
     */
    private function ids(mixed $input): array
    {
        $values = is_string($input) ? explode(',', $input) : (is_array($input) ? $input : []);

        return array_values(array_filter(
            array_map(fn (mixed $id) => is_scalar($id) ? trim((string) $id) : '', $values),
            fn (string $id) => $id !== ''
        ));
    }
}
