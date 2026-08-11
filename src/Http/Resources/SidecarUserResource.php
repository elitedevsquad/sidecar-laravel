<?php

namespace EliteDevSquad\SidecarLaravel\Http\Resources;

use EliteDevSquad\SidecarLaravel\Sidecar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

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

        $id = data_get($this->resource, $userMap['id']);

        return [
            'id' => $id,
            'name' => data_get($this->resource, $userMap['name']),
            'email' => data_get($this->resource, $userMap['email']),
            'role' => data_get($this->resource, $userMap['role'] ?? 'user') ?? 'user',
            'login_url' => $this->loginUrl($id),
        ];
    }

    private function loginUrl(mixed $id): ?string
    {
        if (app()->isProduction()) {
            return null;
        }

        return URL::temporarySignedRoute('devsquad-sidecar.login-as', now()->addHour(), ['user' => $id]);
    }
}
