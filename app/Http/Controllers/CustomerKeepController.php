<?php

namespace App\Http\Controllers;

use App\Models\CustomerKeep;
use App\Models\CustomerUser;
use App\Models\TableReservation;
use Illuminate\Http\Request;

class CustomerKeepController extends Controller
{
    public function index(Request $request): \Illuminate\View\View
    {
        $tab = $request->get('tab', 'all');

        $query = CustomerKeep::with(['customerUser.user', 'customerUser.profile'])
            ->latest();

        if ($tab === 'active') {
            $query->where('status', 'active');
        } elseif ($tab === 'used') {
            $query->where('status', 'used');
        } elseif ($tab === 'weekday') {
            $query->where('type', 'weekday');
        } elseif ($tab === 'weekend') {
            $query->where('type', 'weekend_event');
        }

        $scannedKeepId = $request->integer('scan_keep');

        if ($scannedKeepId > 0) {
            $query->whereKey($scannedKeepId)
                ->when($request->filled('scan_item'), fn ($keepQuery) => $keepQuery->where('item_name', $request->string('scan_item')->toString()))
                ->when($request->filled('scan_type'), fn ($keepQuery) => $keepQuery->where('type', $request->string('scan_type')->toString()))
                ->when($request->filled('scan_quantity'), fn ($keepQuery) => $keepQuery->where('quantity', (float) $request->input('scan_quantity')))
                ->when($request->filled('scan_unit'), fn ($keepQuery) => $keepQuery->where('unit', $request->string('scan_unit')->toString()));
        }

        $keeps = $query->get();

        $totalActive = CustomerKeep::where('status', 'active')->count();
        $totalUsed = CustomerKeep::where('status', 'used')->count();
        $totalItems = CustomerKeep::count();
        $weekdayCount = CustomerKeep::where('type', 'weekday')->where('status', 'active')->count();
        $weekendCount = CustomerKeep::where('type', 'weekend_event')->where('status', 'active')->count();

        $todayBookingUserIds = TableReservation::whereDate('reservation_date', today())
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->pluck('customer_id')
            ->unique();

        $todayCustomers = CustomerUser::with(['user', 'profile'])
            ->whereIn('user_id', $todayBookingUserIds)
            ->get();

        $allCustomers = CustomerUser::with(['user', 'profile'])->get();

        // Pre-processed for JavaScript (avoids arrow functions in @json blade directives)
        $todayCustomersData = $todayCustomers->map(fn ($c) => [
            'id' => $c->id,
            'user_id' => $c->user_id,
            'name' => $c->profile?->name ?? $c->user?->name ?? 'Unknown',
            'code' => $c->customer_code,
        ])->values()->toArray();

        $allCustomersData = $allCustomers->map(fn ($c) => [
            'id' => $c->id,
            'user_id' => $c->user_id,
            'name' => $c->profile?->name ?? $c->user?->name ?? 'Unknown',
            'code' => $c->customer_code,
        ])->values()->toArray();

        $dayOfWeek = now()->dayOfWeek;
        $todayType = ($dayOfWeek >= 1 && $dayOfWeek <= 4) ? 'weekday' : 'weekend_event';
        $todayLabel = $todayType === 'weekday' ? 'Weekday (Senin-Kamis)' : 'Weekend/Event (Jum-Minggu)';

        return view('customer-keep.index', compact(
            'keeps',
            'totalActive',
            'totalUsed',
            'totalItems',
            'weekdayCount',
            'weekendCount',
            'todayCustomersData',
            'allCustomersData',
            'tab',
            'scannedKeepId',
            'todayType',
            'todayLabel'
        ));
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'customer_user_id' => 'required|exists:customer_users,id',
            'item_name' => 'required|string|max:255',
            'type' => 'required|in:weekday,weekend_event',
            'quantity' => 'required|numeric|min:0.1',
            'unit' => 'required|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $customerUser = CustomerUser::findOrFail($validated['customer_user_id']);
        $hasBookingToday = TableReservation::whereDate('reservation_date', today())
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where('customer_id', $customerUser->user_id)
            ->exists();

        if (! $hasBookingToday) {
            return redirect()->route('admin.customer-keep.index')
                ->withErrors(['customer_user_id' => 'Customer ini tidak memiliki booking hari ini.'])
                ->withInput();
        }

        $validated['status'] = 'active';
        $validated['stored_at'] = now();

        CustomerKeep::create($validated);

        return redirect()->route('admin.customer-keep.index')
            ->with('success', 'Item keep berhasil ditambahkan!');
    }

    public function update(Request $request, CustomerKeep $customerKeep): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'customer_user_id' => 'required|exists:customer_users,id',
            'item_name' => 'required|string|max:255',
            'type' => 'required|in:weekday,weekend_event',
            'quantity' => 'required|numeric|min:0.1',
            'unit' => 'required|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $customerKeep->update($validated);

        return redirect()->route('admin.customer-keep.index')
            ->with('success', 'Item keep berhasil diupdate!');
    }

    public function markUsed(CustomerKeep $customerKeep): \Illuminate\Http\RedirectResponse
    {
        $customerKeep->update([
            'status' => 'used',
            'opened_at' => now(),
        ]);

        return redirect()->route('admin.customer-keep.index')
            ->with('success', 'Item keep ditandai sudah digunakan!');
    }

    public function destroy(CustomerKeep $customerKeep): \Illuminate\Http\RedirectResponse
    {
        $customerKeep->delete();

        return redirect()->route('admin.customer-keep.index')
            ->with('success', 'Item keep berhasil dihapus!');
    }
}
