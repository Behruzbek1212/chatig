<?php

namespace App\Http\Requests\Auth;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => PhoneNumber::normalize((string) $this->input('phone')) ?? $this->input('phone'),
        ]);
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+998\d{9}$/', 'unique:users,phone'],
            'company_name' => ['required', 'string', 'max:255'],
            'business_type' => ['required', Rule::in(config('chatig.business_types'))],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Telefon raqami noto\'g\'ri formatda.',
            'phone.unique' => 'Bu raqam allaqachon ro\'yxatdan o\'tgan.',
            'company_name.required' => 'Kompaniya nomini kiriting.',
            'business_type.in' => 'Biznes turi noto\'g\'ri.',
        ];
    }
}
