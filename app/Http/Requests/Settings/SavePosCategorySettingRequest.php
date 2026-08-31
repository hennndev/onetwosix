<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class SavePosCategorySettingRequest extends FormRequest
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
            'categories' => ['present', 'array'],
            'categories.*' => ['array:_present,show_in_pos,is_menu,areas'],
            'categories.*._present' => ['required'],
            'categories.*.show_in_pos' => ['nullable', 'boolean'],
            'categories.*.is_menu' => ['nullable', 'boolean'],
            'categories.*.areas' => ['nullable', 'array'],
            'categories.*.areas.*' => ['integer', 'exists:areas,id'],
        ];
    }
}
