<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePosDiscountApprovalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_type' => ['required', 'in:booking,walk-in'],
            'customer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'walk_in_customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'table_id' => ['nullable', 'integer', 'exists:tables,id'],
            'selected_item_ids' => ['required', 'array', 'min:1'],
            'selected_item_ids.*' => ['required', 'integer', 'distinct', 'exists:inventory_items,id'],
            'discount_type' => ['required', 'in:percentage,nominal'],
            'discount_value' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:500'],
            'manager_auth_code' => ['required', 'digits:4'],
        ];
    }

    public function messages(): array
    {
        return [
            'selected_item_ids.required' => 'Pilih minimal satu item untuk didiskon.',
            'reason.required' => 'Alasan diskon wajib diisi.',
            'manager_auth_code.digits' => 'Auth code manager harus 4 digit.',
        ];
    }
}
