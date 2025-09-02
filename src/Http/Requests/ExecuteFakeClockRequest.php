<?php

namespace EliteDevSquad\Sidecar\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteFakeClockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'datetime' => ['nullable', 'string', 'date'],
        ];
    }
}
