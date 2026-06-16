<?php

namespace App\Http\Requests\ShopFact;

use Illuminate\Foundation\Http\FormRequest;

class StoreShopFactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'value' => ['required', 'string', 'max:2000'],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'label.required' => 'Nomini kiriting.',
            'value.required' => 'Ma\'lumotni kiriting.',
        ];
    }
}
