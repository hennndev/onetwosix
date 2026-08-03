<?php

namespace App\Http\Controllers;

use App\Models\BarOrderItem;
use App\Models\Billing;
use App\Models\Dashboard;
use App\Models\InventoryItem;
use App\Models\KitchenOrderItem;
use App\Models\RecapHistory;
use App\Models\Tabel;
use App\Models\TableReservation;
use App\Services\DashboardSyncService;
use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    public function index(\Illuminate\Http\Request $request)
    {
        $user = auth()->user();
        $areas = $user ? $user->getAccessibleAreas() : \App\Models\Area::where('is_active', true)->orderBy('sort_order')->get();
        $selectedAreaId = $user ? $user->resolveActiveAreaId($request->input('area_id'), $request->has('area_id')) : ($request->filled('area_id')
            ? ($request->input('area_id') === 'all' ? null : (int) $request->input('area_id'))
            : (session('active_area_id') && session('active_area_id') !== 'all' ? (int) session('active_area_id') : null));

        [$windowStart, $windowEnd] = \App\Models\RecapHistory::resolveActiveWindow($selectedAreaId);
        $lastCloseAt = RecapHistory::query()
            ->when($selectedAreaId, fn ($q) => $q->where('area_id', $selectedAreaId))
            ->latest('created_at')
            ->value('created_at');

        // --- Revenue & Transactions (paid billings today) ---
        $todayBillings = Billing::query()
            ->where('billing_status', 'paid')
            ->when($selectedAreaId, function ($query) use ($selectedAreaId) {
                $query->whereHas('tableSession.table', fn ($t) => $t->where('area_id', $selectedAreaId));
            })
            ->where(function ($query) {
                $query->where('is_booking', true)
                    ->orWhere('is_walk_in', true);
            })
            ->where(function ($query) use ($windowStart, $windowEnd) {
                $query->where(function ($paidAtQuery) use ($windowStart, $windowEnd) {
                    $paidAtQuery->whereNotNull('paid_at')
                        ->where('paid_at', '>=', $windowStart)
                        ->where('paid_at', '<', $windowEnd);
                })->orWhere(function ($fallbackQuery) use ($windowStart, $windowEnd) {
                    $fallbackQuery->whereNull('paid_at')
                        ->where('updated_at', '>=', $windowStart)
                        ->where('updated_at', '<', $windowEnd);
                });
            })
            ->when($lastCloseAt, function ($query) use ($lastCloseAt) {
                $query->where(function ($lastCloseQuery) use ($lastCloseAt) {
                    $lastCloseQuery->where(function ($paidAtQuery) use ($lastCloseAt) {
                        $paidAtQuery->whereNotNull('paid_at')
                            ->where('paid_at', '>', $lastCloseAt);
                    })->orWhere(function ($fallbackQuery) use ($lastCloseAt) {
                        $fallbackQuery->whereNull('paid_at')
                            ->where('updated_at', '>', $lastCloseAt);
                    });
                });
            });

        $revenueToday = (clone $todayBillings)->sum('grand_total');
        $transactionsToday = (clone $todayBillings)->count();

        // Items sold today (bar + kitchen orders)
        $barItemsSold = BarOrderItem::whereHas(
            'barOrder',
            fn ($q) => $q->where('created_at', '>=', $windowStart)
                ->where('created_at', '<', $windowEnd)
                ->when($selectedAreaId, fn ($inner) => $inner->whereHas('order.tableSession.table', fn ($t) => $t->where('area_id', $selectedAreaId)))
                ->when($lastCloseAt, fn ($innerQuery) => $innerQuery->where('created_at', '>', $lastCloseAt))
        )->sum('quantity');

        $kitchenItemsSold = KitchenOrderItem::whereHas(
            'kitchenOrder',
            fn ($q) => $q->where('created_at', '>=', $windowStart)
                ->where('created_at', '<', $windowEnd)
                ->when($selectedAreaId, fn ($inner) => $inner->whereHas('order.tableSession.table', fn ($t) => $t->where('area_id', $selectedAreaId)))
                ->when($lastCloseAt, fn ($innerQuery) => $innerQuery->where('created_at', '>', $lastCloseAt))
        )->sum('quantity');

        $itemsSoldToday = $barItemsSold + $kitchenItemsSold;

        // --- Bookings ---
        $bookingPending = TableReservation::where('status', 'pending')->count();
        $bookingConfirmed = TableReservation::where('status', 'confirmed')->count();
        $bookingCompleted = TableReservation::where('status', 'completed')
            ->where('updated_at', '>=', $windowStart)
            ->where('updated_at', '<', $windowEnd)
            ->count();

        // --- Tables ---
        $totalTables = Tabel::where('is_active', true)
            ->when($selectedAreaId, fn ($q) => $q->where('area_id', $selectedAreaId))
            ->count();
        $availableTables = Tabel::where('is_active', true)
            ->when($selectedAreaId, fn ($q) => $q->where('area_id', $selectedAreaId))
            ->where('status', 'available')
            ->count();

        // --- Inventory ---
        $totalProducts = InventoryItem::count();
        $lowStockCount = InventoryItem::whereColumn('stock_quantity', '<=', 'threshold')->where('stock_quantity', '>', 0)->count();
        $outOfStockCount = InventoryItem::where('stock_quantity', 0)->count();

        // --- Dashboard aggregate totals ---
        $dashboardAggregate = Dashboard::query()
            ->when($selectedAreaId, fn ($q) => $q->where('area_id', $selectedAreaId), fn ($q) => $q->whereNull('area_id'))
            ->first();

        $dashboardTotalFood = (float) ($dashboardAggregate?->total_food ?? 0);
        $dashboardTotalAlcohol = (float) ($dashboardAggregate?->total_alcohol ?? 0);
        $dashboardTotalBeverage = (float) ($dashboardAggregate?->total_beverage ?? 0);
        $dashboardTotalCigarette = (float) ($dashboardAggregate?->total_cigarette ?? 0);
        $dashboardTotalBreakage = (float) ($dashboardAggregate?->total_breakage ?? 0);
        $dashboardTotalRoom = (float) ($dashboardAggregate?->total_room ?? 0);
        $dashboardTotalStaffMeal = (float) ($dashboardAggregate?->total_staff_meal ?? 0);
        $dashboardTotalComplimentQuantity = (int) ($dashboardAggregate?->total_compliment_quantity ?? 0);
        $dashboardTotalFocQuantity = (int) ($dashboardAggregate?->total_foc_quantity ?? 0);
        $dashboardTotalLd = (float) ($dashboardAggregate?->total_ld ?? 0);
        $dashboardTotalLdQuantity = (int) ($dashboardAggregate?->total_ld_quantity ?? 0);
        $dashboardTotalPenjualanRokok = (int) ($dashboardAggregate?->total_penjualan_rokok ?? 0);
        $dashboardTotalTax = (float) ($dashboardAggregate?->total_tax ?? 0);
        $dashboardTotalServiceCharge = (float) ($dashboardAggregate?->total_service_charge ?? 0);
        $dashboardTotalDp = (float) ($dashboardAggregate?->total_dp ?? 0);
        $dashboardGrossSales = (float) ($dashboardAggregate?->total_amount ?? 0) + $dashboardTotalDp;
        $dashboardNetSales = max(0.0, $dashboardGrossSales - $dashboardTotalTax - $dashboardTotalServiceCharge);
        $dashboardTotalCash = (float) ($dashboardAggregate?->total_cash ?? 0);
        $dashboardTotalTransfer = (float) ($dashboardAggregate?->total_transfer ?? 0);
        $dashboardTotalDebit = (float) ($dashboardAggregate?->total_debit ?? 0);
        $dashboardTotalKredit = (float) ($dashboardAggregate?->total_kredit ?? 0);
        $dashboardTotalQris = (float) ($dashboardAggregate?->total_qris ?? 0);
        $dashboardTotalKitchenItems = (int) ($dashboardAggregate?->total_kitchen_items ?? 0);
        $dashboardTotalBarItems = (int) ($dashboardAggregate?->total_bar_items ?? 0);

        return view('dashboard', compact(
            'areas',
            'selectedAreaId',
            'revenueToday',
            'transactionsToday',
            'itemsSoldToday',
            'bookingPending',
            'bookingConfirmed',
            'bookingCompleted',
            'totalTables',
            'availableTables',
            'totalProducts',
            'lowStockCount',
            'outOfStockCount',
            'dashboardTotalFood',
            'dashboardTotalAlcohol',
            'dashboardTotalBeverage',
            'dashboardTotalCigarette',
            'dashboardTotalBreakage',
            'dashboardTotalRoom',
            'dashboardTotalStaffMeal',
            'dashboardTotalComplimentQuantity',
            'dashboardTotalFocQuantity',
            'dashboardTotalLd',
            'dashboardTotalLdQuantity',
            'dashboardTotalPenjualanRokok',
            'dashboardTotalTax',
            'dashboardTotalServiceCharge',
            'dashboardTotalDp',
            'dashboardGrossSales',
            'dashboardNetSales',
            'dashboardTotalCash',
            'dashboardTotalTransfer',
            'dashboardTotalDebit',
            'dashboardTotalKredit',
            'dashboardTotalQris',
            'dashboardTotalKitchenItems',
            'dashboardTotalBarItems'
        ));
    }

    public function syncToday(DashboardSyncService $dashboardSyncService): RedirectResponse
    {
        $dashboardSyncService->syncAll();

        return redirect()
            ->route('admin.dashboard')
            ->with('success', 'Dashboard berhasil di-sync (seluruh area).');
    }
}
