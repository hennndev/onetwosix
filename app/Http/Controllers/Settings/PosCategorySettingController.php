<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SavePosCategorySettingRequest;
use App\Models\InventoryItem;
use App\Models\PosCategorySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PosCategorySettingController extends Controller
{
    public function index(): View
    {
        $knownTypes = InventoryItem::distinct()->orderBy('category_type')->pluck('category_type');
        $settings = PosCategorySetting::all()->keyBy('category_type');

        return view('settings.pos-categories', compact('knownTypes', 'settings'));
    }

    public function save(SavePosCategorySettingRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        foreach ($validated['categories'] as $categoryType => $data) {
            $setting = PosCategorySetting::firstOrNew(['category_type' => $categoryType]);

            $setting->fill([
                'show_in_pos' => (bool) ($data['show_in_pos'] ?? false),
                'is_menu' => (bool) ($data['is_menu'] ?? false),
                'preparation_location' => $setting->preparation_location ?? 'bar',
            ]);

            $setting->save();
        }

        PosCategorySetting::clearCache();

        return redirect()->route('admin.settings.pos-categories.index')
            ->with('success', 'Pengaturan POS berhasil disimpan.');
    }
}
