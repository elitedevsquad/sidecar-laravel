<?php

namespace EliteDevSquad\SidecarExtensionBridge\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'command' => ['required', 'string'],
        ];
    }
}
