<?php

namespace EliteDevSquad\SidecarExtensionBridge\Http\Resources;

use EliteDevSquad\SidecarExtensionBridge\SidecarBridge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SidecarUserResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        /** @var SidecarBridge $bridge */
        $bridge = app(SidecarBridge::class);
        $userMap = $bridge->getUserMap();

        return [
            'id' => $this->resource->{$userMap['id']},
            'name' => $this->resource->{$userMap['name']},
            'email' => $this->resource->{$userMap['email']},
            'role' => $this->resource->{$userMap['role']} ?? 'user',
        ];
    }
}
