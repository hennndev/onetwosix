<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class SaveEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'area_id' => ['nullable', 'exists:areas,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', File::image()->types(['jpeg', 'jpg', 'png', 'webp'])->max(2 * 1024)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable'],
            'end_time' => ['nullable'],
            'is_active' => ['required', 'boolean'],
            'price_adjustment_type' => ['required', 'in:percentage,fixed'],
            'price_adjustment_value' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.image' => 'File gambar event harus berupa gambar.',
            'image.mimes' => 'Gambar event harus berformat JPG, PNG, atau WebP.',
            'image.max' => 'Ukuran gambar event maksimal 2 MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'area_id' => $this->filled('area_id') ? (int) $this->input('area_id') : null,
        ]);
    }
}
