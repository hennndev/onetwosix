<?php

namespace App\Http\Controllers;

use App\Models\Promo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PromoController extends Controller
{
    public function index(Request $request)
    {
        $query = Promo::query();

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->status != '') {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($request->status === 'upcoming') {
                $query->where('start_date', '>', Carbon::today());
            } elseif ($request->status === 'past') {
                $query->where('end_date', '<', Carbon::today());
            }
        }

        $promos = $query->latest()->get();

        $today = Carbon::today();
        $totalPromos = Promo::count();
        $activePromos = Promo::where('is_active', true)->count();
        $upcomingPromos = Promo::where('start_date', '>', $today)->count();
        $todayPromos = Promo::where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->count();

        return view('promos.index', compact(
            'promos',
            'totalPromos',
            'activePromos',
            'upcomingPromos',
            'todayPromos'
        ));
    }

    public function store(Request $request)
    {
        $request->merge([
            'is_active' => $request->boolean('is_active'),
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_time' => 'nullable',
            'end_time' => 'nullable',
            'is_active' => 'required|boolean',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'terms_conditions' => 'nullable|string',
        ]);

        try {
            $validated['slug'] = Str::slug($validated['name']);

            if ($request->hasFile('image')) {
                $validated['image'] = $request->file('image')->store('promos', 'public');
            }

            Promo::create($validated);

            return redirect()->route('admin.promos.index')
                ->with('success', 'Promo berhasil ditambahkan');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal menambahkan promo: '.$e->getMessage()])
                ->withInput();
        }
    }

    public function update(Request $request, Promo $promo)
    {
        $request->merge([
            'is_active' => $request->boolean('is_active'),
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_time' => 'nullable',
            'end_time' => 'nullable',
            'is_active' => 'required|boolean',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'terms_conditions' => 'nullable|string',
        ]);

        try {
            $validated['slug'] = Str::slug($validated['name']);

            if ($request->hasFile('image')) {
                $validated['image'] = $request->file('image')->store('promos', 'public');
            }

            $promo->update($validated);

            return redirect()->route('admin.promos.index')
                ->with('success', 'Promo berhasil diupdate');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal mengupdate promo: '.$e->getMessage()])
                ->withInput();
        }
    }

    public function destroy(Promo $promo)
    {
        try {
            $promo->delete();

            return redirect()->route('admin.promos.index')
                ->with('success', 'Promo berhasil dihapus');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal menghapus promo: '.$e->getMessage()]);
        }
    }

    public function toggleStatus(Promo $promo)
    {
        try {
            $promo->update(['is_active' => ! $promo->is_active]);

            $message = $promo->is_active ? 'Promo berhasil diaktifkan' : 'Promo berhasil dinonaktifkan';

            return redirect()->route('admin.promos.index')->with('success', $message);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal mengubah status promo: '.$e->getMessage()]);
        }
    }
}
