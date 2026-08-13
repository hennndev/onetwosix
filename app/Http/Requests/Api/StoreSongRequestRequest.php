<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSongRequestRequest extends FormRequest
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
            'song_title' => ['required', 'string', 'max:255'],
            'artist' => ['required', 'string', 'max:255'],
            'tip' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'song_title.required' => 'Judul lagu wajib diisi.',
            'artist.required' => 'Nama artis wajib diisi.',
        ];
    }
}
