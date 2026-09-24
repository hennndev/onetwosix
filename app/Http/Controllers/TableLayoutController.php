<?php

namespace App\Http\Controllers;

use App\Actions\Tables\SaveTablePositions;
use App\Http\Requests\UpdateTablePositionsRequest;
use App\Models\Area;
use App\Models\Tabel;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class TableLayoutController extends Controller
{
    public function edit(): View
    {
        $accessibleAreaIds = auth()->user()->getAccessibleAreas()->pluck('id');
        $areas = $this->areasQuery()
            ->whereIn('id', $accessibleAreaIds)
            ->get();

        return view('tables.layout', compact('areas'));
    }

    public function preview(?Area $area = null, ?string $status = null, ?Tabel $table = null): View
    {
        if (($status || $table) && (! $area || ! $status || ! $table)) {
            abort(404);
        }

        if ($area && ! $area->is_active) {
            abort(404);
        }

        if ($table && (
            ! $table->is_active
            || $table->area_id !== $area?->id
            || $table->status !== $status
        )) {
            abort(404);
        }

        $areas = $this->areasQuery()->get();
        $activeAreaId = $area?->id ?? $areas->first()?->id;

        return view('tables.layout-preview', compact('areas', 'activeAreaId'));
    }

    public function update(UpdateTablePositionsRequest $request, SaveTablePositions $saveTablePositions): JsonResponse
    {
        $saveTablePositions->handle($request->validated('tables'));

        return response()->json(['message' => 'Posisi meja berhasil disimpan.']);
    }

    private function areasQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Area::query()
            ->where('is_active', true)
            ->with(['tables' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('table_number')])
            ->orderBy('sort_order')
            ->orderBy('name');
    }
}
