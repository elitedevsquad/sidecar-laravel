<?php

namespace EliteDevSquad\SidecarLaravel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteTinkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! app()->isProduction();
    }

    /**
     * @return array<string, list<string|string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
            'clock' => ['nullable', 'date', 'string', 'date_format:Y-m-d H:i:s'],
        ];
    }

    protected function prepareForValidation(): void
    {
        /** @var string $code */
        $code = $this->input('code');

        $this->merge([
            'code' => base64_decode($code),
        ]);
    }
}
