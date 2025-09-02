<?php

namespace EliteDevSquad\Sidecar\Http\Requests;

use EliteDevSquad\Sidecar\Sidecar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginAsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(Sidecar $bridge): array
    {
        $userModel = $bridge->getUserModel();
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
