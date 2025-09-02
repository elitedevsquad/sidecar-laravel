<?php

namespace EliteDevSquad\Sidecar\Http\Resources;

use EliteDevSquad\Sidecar\Sidecar;
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
        /** @var Sidecar $bridge */
        $bridge = app(Sidecar::class);
        $userMap = $bridge->getUserMap();

        return [
            'id' => $this->resource->{$userMap['id']},
            'name' => $this->resource->{$userMap['name']},
            'email' => $this->resource->{$userMap['email']},
            'role' => $this->resource->{$userMap['role']} ?? 'user',
        ];
    }
}
