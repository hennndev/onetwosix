<?php

namespace App\Http\Requests\Settings;

use App\Models\Tier;
use Illuminate\Foundation\Http\FormRequest;

class StoreTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'discount_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'minimum_spent' => ['required', 'integer', 'min:0'],
            'color' => ['required', 'string', 'in:'.implode(',', array_keys(Tier::COLORS))],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
