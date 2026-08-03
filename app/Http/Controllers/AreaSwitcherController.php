<?php

namespace App\Http\Controllers;

use App\Models\Area;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AreaSwitcherController extends Controller
{
    /**
     * Switch active area context in session for multi-area users.
     */
    public function switchArea(Request $request)
    {
        $request->validate([
            'area_id' => 'required',
        ]);

        $user = Auth::user();

        if (! $user || ! $user->hasMultiAreaAccess()) {
            return redirect()->back()->with('error', 'Anda tidak memiliki akses untuk mengubah area.');
        }

        $areaId = $request->input('area_id');

        if ($areaId === 'all') {
            session(['active_area_id' => 'all']);

            return redirect()->back()->with('success', 'Area aktif diubah ke: Semua Area');
        }

        $area = Area::findOrFail($areaId);
        session(['active_area_id' => $area->id]);

        return redirect()->back()->with('success', 'Area aktif berhasil diubah ke: '.$area->name);
    }
}
