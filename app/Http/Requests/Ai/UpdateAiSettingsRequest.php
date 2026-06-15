<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'system_prompt' => ['required', 'string', 'max:10000'],
            'mode' => ['required', Rule::in(['suggest', 'auto'])],
            'working_hours' => ['nullable', 'array'],
            'raw_inputs' => ['nullable', 'array'],
        ];
    }
}
