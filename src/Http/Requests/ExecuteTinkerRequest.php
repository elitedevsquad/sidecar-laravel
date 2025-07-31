<?php

namespace EliteDevSquad\SidecarExtensionBridge\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteTinkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => base64_decode($this->input('code')),
        ]);
    }
}
