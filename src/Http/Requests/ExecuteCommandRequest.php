<?php

namespace EliteDevSquad\SidecarLaravel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteCommandRequest extends FormRequest
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
            'command' => ['string', 'required'],
            'clock' => ['nullable', 'date', 'date_format:Y-m-d H:i:s'],
        ];
    }
}
