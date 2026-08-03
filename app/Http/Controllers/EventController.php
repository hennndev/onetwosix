<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EventController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        $areas = $user ? $user->getAccessibleAreas() : Area::where('is_active', true)->orderBy('sort_order')->get();
        $selectedAreaId = $user ? $user->resolveActiveAreaId($request->input('area_id'), $request->has('area_id')) : ($request->filled('area_id')
            ? ($request->input('area_id') === 'all' ? null : (int) $request->input('area_id'))
            : (session('active_area_id') && session('active_area_id') !== 'all' ? (int) session('active_area_id') : null));

        $areaFilter = fn ($q) => $q->when(
            $selectedAreaId,
            fn ($sq) => $sq->where(
                fn ($sub) => $sub->whereNull('area_id')->orWhere('area_id', $selectedAreaId)
            )
        );

        $query = Event::with('area')->tap($areaFilter);

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

        $events = $query->latest()->get();

        $today = Carbon::today();
        $totalEvents = Event::tap($areaFilter)->count();
        $todayEvents = Event::tap($areaFilter)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->count();
        $upcomingEvents = Event::tap($areaFilter)->where('start_date', '>', $today)->count();
        $activeEvents = Event::tap($areaFilter)->where('is_active', true)->count();

        return view('events.index', compact(
            'events',
            'totalEvents',
            'todayEvents',
            'upcomingEvents',
            'activeEvents',
            'areas',
            'selectedAreaId'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'is_active' => $request->boolean('is_active'),
            'area_id' => $request->filled('area_id') ? (int) $request->input('area_id') : null,
        ]);

        $validated = $request->validate([
            'area_id' => 'nullable|exists:areas,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_time' => 'nullable',
            'end_time' => 'nullable',
            'is_active' => 'required|boolean',
            'price_adjustment_type' => 'required|in:percentage,fixed',
            'price_adjustment_value' => 'required|numeric|min:0',
        ]);

        try {
            $validated['slug'] = Str::slug($validated['name']);

            Event::create($validated);

            return redirect()->route('admin.events.index')
                ->with('success', 'Event berhasil ditambahkan');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal menambahkan event: '.$e->getMessage()])
                ->withInput();
        }
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $request->merge([
            'is_active' => $request->boolean('is_active'),
            'area_id' => $request->filled('area_id') ? (int) $request->input('area_id') : null,
        ]);

        $validated = $request->validate([
            'area_id' => 'nullable|exists:areas,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_time' => 'nullable',
            'end_time' => 'nullable',
            'is_active' => 'required|boolean',
            'price_adjustment_type' => 'required|in:percentage,fixed',
            'price_adjustment_value' => 'required|numeric|min:0',
        ]);

        try {
            $validated['slug'] = Str::slug($validated['name']);

            $event->update($validated);

            return redirect()->route('admin.events.index')
                ->with('success', 'Event berhasil diupdate');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal mengupdate event: '.$e->getMessage()])
                ->withInput();
        }
    }

    public function destroy(Event $event)
    {
        try {
            $event->delete();

            return redirect()->route('admin.events.index')
                ->with('success', 'Event berhasil dihapus');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal menghapus event: '.$e->getMessage()]);
        }
    }

    public function toggleStatus(Event $event)
    {
        try {
            $event->update(['is_active' => ! $event->is_active]);

            $message = $event->is_active ? 'Event berhasil diaktifkan' : 'Event berhasil dinonaktifkan';

            return redirect()->route('admin.events.index')->with('success', $message);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal mengubah status event: '.$e->getMessage()]);
        }
    }
}
