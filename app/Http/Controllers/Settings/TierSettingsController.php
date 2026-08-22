<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreTierRequest;
use App\Http\Requests\Settings\UpdateTierRequest;
use App\Models\Tier;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TierSettingsController extends Controller
{
    public function index(): View
    {
        $tiers = Tier::orderBy('level')->get();

        return view('settings.tier-settings', compact('tiers'));
    }

    public function store(StoreTierRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $validated['level'] = (int) Tier::max('level') + 1;
        $validated['is_first_tier'] = ! Tier::exists();

        if ($validated['is_first_tier']) {
            $validated['minimum_spent'] = 0;
        }

        Tier::create($validated);

        return redirect()->route('admin.settings.tier-settings.index')
            ->with('success', 'Tier berhasil ditambahkan.');
    }

    public function update(UpdateTierRequest $request, Tier $tier): RedirectResponse
    {
        $validated = $request->validated();

        if ($tier->is_first_tier) {
            $validated['minimum_spent'] = 0;
        }

        $tier->update($validated);

        return redirect()->route('admin.settings.tier-settings.index')
            ->with('success', 'Tier berhasil diupdate.');
    }

    public function destroy(Tier $tier): RedirectResponse
    {
        $wasFirstTier = $tier->is_first_tier;
        $tier->delete();

        if ($wasFirstTier) {
            $newFirst = Tier::orderBy('level')->first();
            $newFirst?->update(['is_first_tier' => true, 'minimum_spent' => 0]);
        }

        return redirect()->route('admin.settings.tier-settings.index')
            ->with('success', 'Tier berhasil dihapus.');
    }
}
