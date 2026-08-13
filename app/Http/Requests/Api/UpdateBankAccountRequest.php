<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bank_name' => ['sometimes', 'string', 'max:100'],
            'account_number' => ['sometimes', 'string', 'max:50'],
            'account_holder' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bank_name.max' => 'Nama bank maksimal 100 karakter.',
            'account_number.max' => 'Nomor rekening maksimal 50 karakter.',
            'account_holder.max' => 'Nama pemilik rekening maksimal 255 karakter.',
        ];
    }
}
