<?php

namespace App\Http\Requests\ShopFact;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShopFactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'string', 'max:255'],
            'value' => ['sometimes', 'string', 'max:2000'],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
