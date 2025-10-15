<?php

namespace EliteDevSquad\SidecarLaravel\Http\Resources;

use EliteDevSquad\SidecarLaravel\Sidecar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SidecarUserResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Sidecar $sidecar */
        $sidecar = app(Sidecar::class);
        $userMap = $sidecar->getUserMap();

        return [
            'id' => data_get($this->resource, $userMap['id']),
            'name' => data_get($this->resource, $userMap['name']),
            'email' => data_get($this->resource, $userMap['email']),
            'role' => data_get($this->resource, $userMap['role'] ?? null) ?? 'user',
        ];
    }
}
