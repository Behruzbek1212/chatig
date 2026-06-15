<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GeneratePromptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tone' => ['nullable', Rule::in(['rasmiy', 'dostona', 'qisqa'])],
            'out_of_stock_behavior' => ['nullable', 'string', 'max:1000'],
            'haggling_policy' => ['nullable', 'string', 'max:1000'],
            'after_hours_behavior' => ['nullable', 'string', 'max:1000'],
            'extra_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
