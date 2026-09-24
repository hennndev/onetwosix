<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
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
            'table_id' => ['required', 'exists:tables,id'],
            'reservation_date' => ['required', 'date', 'after_or_equal:today'],
            'reservation_time' => ['required', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:500'],
            'down_payment_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'table_id.required' => 'Pilih meja terlebih dahulu.',
            'table_id.exists' => 'Meja tidak ditemukan.',
            'reservation_date.required' => 'Tanggal reservasi wajib diisi.',
            'reservation_date.after_or_equal' => 'Tanggal reservasi tidak boleh di masa lalu.',
            'reservation_time.required' => 'Waktu reservasi wajib diisi.',
        ];
    }
}
