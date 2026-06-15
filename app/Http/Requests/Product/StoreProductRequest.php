<?php

namespace App\Http\Requests\Product;

use App\Services\Inventory\ProductImageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'integer', 'min:0'],
            'quantity' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:255'],
            'condition' => ['nullable', Rule::in(['new', 'used'])],
            'brand' => ['nullable', 'string', 'max:255'],
            'images' => ['nullable', 'array', 'max:'.ProductImageService::MAX_IMAGES],
            'images.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tovar nomini kiriting.',
            'price.required' => 'Narxini kiriting.',
            'images.max' => 'Eng ko\'pi bilan 10 ta rasm yuklash mumkin.',
            'images.*.image' => 'Fayl rasm bo\'lishi kerak.',
        ];
    }
}
