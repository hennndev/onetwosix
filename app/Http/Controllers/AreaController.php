<?php

namespace App\Http\Controllers;

use App\Actions\Areas\SaveArea;
use App\Http\Requests\StoreAreaRequest;
use App\Http\Requests\UpdateAreaRequest;
use App\Models\Area;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AreaController extends Controller
{
    public function index(): View
    {
        $areas = Area::orderBy('sort_order')->orderBy('name')->get();

        return view('areas.index', compact('areas'));
    }

    public function store(StoreAreaRequest $request, SaveArea $saveArea): RedirectResponse
    {
        $attributes = $request->validated();
        $attributes['is_active'] = $request->boolean('is_active');
        $attributes['sort_order'] ??= 0;
        $saveArea->handle($attributes);

        return redirect()->route('admin.areas.index')->with('success', 'Area berhasil ditambahkan!');
    }

    public function update(UpdateAreaRequest $request, Area $area, SaveArea $saveArea): RedirectResponse
    {
        $attributes = $request->validated();
        $attributes['is_active'] = $request->boolean('is_active');
        $attributes['sort_order'] ??= 0;
        $saveArea->handle($attributes, $area);

        return redirect()->route('admin.areas.index')->with('success', 'Area berhasil diupdate!');
    }

    public function destroy(Area $area): RedirectResponse
    {
        $imagePath = $area->image;
        $area->delete();

        if ($imagePath) {
            Storage::disk('public')->delete($imagePath);
        }

        return redirect()->route('admin.areas.index')->with('success', 'Area berhasil dihapus!');
    }
}
