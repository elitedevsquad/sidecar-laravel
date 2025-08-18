<?php

namespace EliteDevSquad\SidecarExtensionBridge\Http\Requests;

use EliteDevSquad\SidecarExtensionBridge\SidecarBridge;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginAsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(SidecarBridge $bridge): array
    {
        $userModel    = $bridge->getUserModel();
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
