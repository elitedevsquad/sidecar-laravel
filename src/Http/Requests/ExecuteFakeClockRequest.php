<?php

namespace EliteDevSquad\Sidecar\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteFakeClockRequest extends FormRequest
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
            'datetime' => ['nullable', 'string', 'date'],
        ];
    }
}
