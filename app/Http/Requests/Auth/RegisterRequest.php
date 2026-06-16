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

    /**
     * UI labels → canonical business_type slugs, so the dashboard (which shows
     * labels) and API clients (which may send slugs) both work.
     */
    private const BUSINESS_TYPE_LABELS = [
        'elektronika' => 'elektronika',
        'kiyim-kechak' => 'kiyim',
        'kiyim' => 'kiyim',
        'kosmetika' => 'kosmetika',
        'oziq-ovqat' => 'oziq_ovqat',
        'oziq_ovqat' => 'oziq_ovqat',
        'aksessuar' => 'aksessuar',
        'maishiy texnika' => 'maishiy_texnika',
        'maishiy_texnika' => 'maishiy_texnika',
        'boshqa' => 'boshqa',
    ];

    protected function prepareForValidation(): void
    {
        $rawType = mb_strtolower(trim((string) $this->input('business_type')));

        $this->merge([
            'phone' => PhoneNumber::normalize((string) $this->input('phone')) ?? $this->input('phone'),
            'business_type' => self::BUSINESS_TYPE_LABELS[$rawType] ?? $this->input('business_type'),
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
