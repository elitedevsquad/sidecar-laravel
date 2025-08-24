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
            'command' => ['array', 'required_array_keys:name,command', 'required'],
            'clock' => ['nullable', 'date', 'date_format:Y-m-d H:i:s'],
        ];
    }
}
