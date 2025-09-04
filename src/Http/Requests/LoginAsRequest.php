<?php

namespace EliteDevSquad\Sidecar\Http\Requests;

use EliteDevSquad\Sidecar\Sidecar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginAsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! app()->isProduction();
    }

    /**
     * @return array<string, list<string|string>>
     */
    public function rules(Sidecar $bridge): array
    {
        $userModel = $bridge->getUserModel();

        /** @var Model $userInstance */
        $userInstance = new $userModel();

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists($userInstance->getTable(), $userInstance->getKeyName()),
            ],
        ];
    }
}
