<?php

namespace App\Http\Requests;

use App\Models\Tabel;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTablePositionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        $tableIds = collect($this->input('tables', []))
            ->pluck('id')
            ->filter(fn (mixed $id): bool => filter_var($id, FILTER_VALIDATE_INT) !== false)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique();

        if ($tableIds->isEmpty()) {
            return true;
        }

        $accessibleAreaIds = $user->getAccessibleAreas()->pluck('id');

        return ! Tabel::query()
            ->whereIn('id', $tableIds)
            ->whereNotIn('area_id', $accessibleAreaIds)
            ->exists();
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tables' => ['required', 'array', 'min:1', 'max:500'],
            'tables.*.id' => ['required', 'integer', 'distinct', 'exists:tables,id'],
            'tables.*.position_x' => ['required', 'numeric', 'between:0,100'],
            'tables.*.position_y' => ['required', 'numeric', 'between:0,100'],
        ];
    }
}
