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

        if ($request->headers->get('X-Live')) {
            return response(
                view('_partials.dashboard-stats', $this->liveStats($selectedAreaId))
            )->withHeaders(['X-Live' => '1']);
        }

        // Kartu live "hari ini" mengikuti siklus area masing-masing; scope
        // "Semua Area" = gabungan statistik live tiap area (bukan window
        // timeline global yang tidak pernah maju lagi sejak end-day
        // per-area — tanpa ini kartu Semua Area menghisap transaksi yang
        // sudah ter-seal ke rekap area).
        $liveTodayStats = $this->resolveLiveStatsForScope($selectedAreaId);
        $revenueToday = $liveTodayStats['revenue'];
        $transactionsToday = $liveTodayStats['transactions'];
        $itemsSoldToday = $liveTodayStats['items'];

        // Batas segel untuk guard tampilan agregat tetap dari timeline recap
        // global (perilaku lama dipertahankan).
        $lastCloseAt = RecapHistory::query()
            ->whereNull('area_id')
            ->latest('created_at')
            ->value('created_at');

        // --- Bookings ---
        $bookingPending = TableReservation::where('status', 'pending')->count();
        $bookingConfirmed = TableReservation::where('status', 'confirmed')->count();
        [$bookingWindowStart, $bookingWindowEnd] = $this->resolveBookingWindowForScope($selectedAreaId);
        $bookingCompleted = TableReservation::where('status', 'completed')
            ->where('updated_at', '>=', $bookingWindowStart)
            ->where('updated_at', '<', $bookingWindowEnd)
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
        $dashboardAggregate = $this->resolveDashboardAggregate($selectedAreaId, $lastCloseAt);

        $dashboardTotalFood = (float) ($dashboardAggregate?->total_food ?? 0);
        $dashboardTotalAlcohol = (float) ($dashboardAggregate?->total_alcohol ?? 0);
        $dashboardTotalBeverage = (float) ($dashboardAggregate?->total_beverage ?? 0);
        $dashboardTotalCigarette = (float) ($dashboardAggregate?->total_cigarette ?? 0);
        $dashboardTotalBreakage = (float) ($dashboardAggregate?->total_breakage ?? 0);
        $dashboardTotalRoom = (float) ($dashboardAggregate?->total_room ?? 0);
        $dashboardTotalStaffMeal = (float) ($dashboardAggregate?->total_staff_meal ?? 0);
        $dashboardTotalComplimentQuantity = (int) ($dashboardAggregate?->total_compliment_quantity ?? 0);
        $dashboardTotalFocQuantity = (int) ($dashboardAggregate?->total_foc_quantity ?? 0);
        $dashboardTotalFocAmount = (float) ($dashboardAggregate?->total_foc_amount ?? 0);
        $dashboardTotalComplimentAmount = (float) ($dashboardAggregate?->total_compliment_amount ?? 0);
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
            'dashboardTotalFocAmount',
            'dashboardTotalComplimentAmount',
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

    private function liveStats(?int $selectedAreaId): array
    {
        $liveTodayStats = $this->resolveLiveStatsForScope($selectedAreaId);
        $transactionsToday = $liveTodayStats['transactions'];
        $itemsSoldToday = $liveTodayStats['items'];

        // Batas segel untuk guard tampilan agregat tetap dari timeline recap
        // global (perilaku lama dipertahankan).
        $lastCloseAt = RecapHistory::query()
            ->whereNull('area_id')
            ->latest('created_at')
            ->value('created_at');

        $bookingPending = TableReservation::where('status', 'pending')->count();
        $bookingConfirmed = TableReservation::where('status', 'confirmed')->count();

        $totalTables = Tabel::where('is_active', true)
            ->when($selectedAreaId, fn ($q) => $q->where('area_id', $selectedAreaId))
            ->count();
        $availableTables = Tabel::where('is_active', true)
            ->when($selectedAreaId, fn ($q) => $q->where('area_id', $selectedAreaId))
            ->where('status', 'available')
            ->count();

        $dashboardAggregate = $this->resolveDashboardAggregate($selectedAreaId, $lastCloseAt);

        $dashboardTotalDp = (float) ($dashboardAggregate?->total_dp ?? 0);
        $dashboardGrossSales = (float) ($dashboardAggregate?->total_amount ?? 0) + $dashboardTotalDp;
        $dashboardNetSales = max(0.0, $dashboardGrossSales
            - (float) ($dashboardAggregate?->total_tax ?? 0)
            - (float) ($dashboardAggregate?->total_service_charge ?? 0));

        return compact(
            'transactionsToday',
            'itemsSoldToday',
            'bookingPending',
            'bookingConfirmed',
            'totalTables',
            'availableTables',
            'dashboardGrossSales',
            'dashboardNetSales'
        );
    }

    /**
     * Statistik live "hari ini" untuk satu area: revenue (non FOC/Compliment),
     * jumlah transaksi, dan item keluar (bar + kitchen).
     *
     * Window dan batas seal diambil dari siklus AREA ITU SENDIRI (rekap area
     * sendiri ATAU rekap global, terbaru menang) — semantik sama dengan
     * DashboardSyncService::sync. Timeline global saja tidak dipakai lagi
     * karena end-day kini hanya per-area, sehingga timeline global tidak
     * pernah maju dan kartu Semua Area akan menghisap transaksi ter-seal.
     *
     * @return array{revenue: float, transactions: int, items: int}
     */
    private function resolveLiveTodayStats(?int $areaId): array
    {
        [$windowStart, $windowEnd] = RecapHistory::resolveActiveWindow($areaId);
        $lastCloseAt = RecapHistory::resolveLatestCycleRecap($areaId)?->created_at?->timezone('Asia/Jakarta');

        $todayBillings = Billing::query()
            ->where('billing_status', 'paid')
            ->when($areaId, fn ($query) => $query->where(fn ($sub) => $sub
                ->where('area_id', $areaId)
                ->orWhereHas('tableSession.table', fn ($t) => $t->where('area_id', $areaId))))
            ->where(fn ($query) => $query->where('is_booking', true)->orWhere('is_walk_in', true))
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
                        $paidAtQuery->whereNotNull('paid_at')->where('paid_at', '>', $lastCloseAt);
                    })->orWhere(function ($fallbackQuery) use ($lastCloseAt) {
                        $fallbackQuery->whereNull('paid_at')->where('updated_at', '>', $lastCloseAt);
                    });
                });
            });

        $revenueToday = (float) (clone $todayBillings)
            ->where(fn ($query) => $query->whereNull('foc_comp_payment_method')
                ->orWhereNotIn('foc_comp_payment_method', ['FOC', 'Compliment']))
            ->sum('grand_total');

        $areaFilter = fn ($query) => $query->where(fn ($sub) => $sub
            ->where('area_id', $areaId)
            ->orWhereHas('order.tableSession.table', fn ($t) => $t->where('area_id', $areaId)));

        $itemsSoldToday = BarOrderItem::whereHas(
            'barOrder',
            fn ($q) => $q->where('created_at', '>=', $windowStart)
                ->where('created_at', '<', $windowEnd)
                ->when($areaId, $areaFilter)
                ->when($lastCloseAt, fn ($innerQuery) => $innerQuery->where('created_at', '>', $lastCloseAt))
        )->sum('quantity')
            + KitchenOrderItem::whereHas(
                'kitchenOrder',
                fn ($q) => $q->where('created_at', '>=', $windowStart)
                    ->where('created_at', '<', $windowEnd)
                    ->when($areaId, $areaFilter)
                    ->when($lastCloseAt, fn ($innerQuery) => $innerQuery->where('created_at', '>', $lastCloseAt))
            )->sum('quantity');

        return [
            'revenue' => $revenueToday,
            'transactions' => (int) (clone $todayBillings)->count(),
            'items' => (int) $itemsSoldToday,
        ];
    }

    /**
     * Statistik live untuk scope yang dipilih. Scope "Semua Area" (null) =
     * gabungan statistik live tiap area aktif — konsisten dengan arsitektur
     * "Semua Area = hasil merging area-area".
     *
     * @return array{revenue: float, transactions: int, items: int}
     */
    private function resolveLiveStatsForScope(?int $areaId): array
    {
        if ($areaId !== null) {
            return $this->resolveLiveTodayStats($areaId);
        }

        $areas = \App\Models\Area::query()->where('is_active', true)->get();

        if ($areas->isEmpty()) {
            return $this->resolveLiveTodayStats(null);
        }

        $totals = ['revenue' => 0.0, 'transactions' => 0, 'items' => 0];

        foreach ($areas as $area) {
            $stats = $this->resolveLiveTodayStats($area->id);
            $totals['revenue'] += $stats['revenue'];
            $totals['transactions'] += $stats['transactions'];
            $totals['items'] += $stats['items'];
        }

        return $totals;
    }

    /**
     * Window untuk hitungan booking selesai. Scope per-area memakai siklus
     * areanya; scope "Semua Area" memakai union window semua area aktif
     * (min start, max end) — query booking tidak difilter area, jadi window
     * digabung alih-alih dijumlah agar tidak dobel-hitung.
     *
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}
     */
    private function resolveBookingWindowForScope(?int $areaId): array
    {
        if ($areaId !== null) {
            return RecapHistory::resolveActiveWindow($areaId);
        }

        $areas = \App\Models\Area::query()->where('is_active', true)->get();

        if ($areas->isEmpty()) {
            return RecapHistory::resolveActiveWindow(null);
        }

        $startAt = null;
        $endAt = null;

        foreach ($areas as $area) {
            [$areaStart, $areaEnd] = RecapHistory::resolveActiveWindow($area->id);
            $startAt = $startAt === null || $areaStart->lt($startAt) ? $areaStart : $startAt;
            $endAt = $endAt === null || $areaEnd->gt($endAt) ? $areaEnd : $endAt;
        }

        return [$startAt, $endAt];
    }

    /**
     * GUARD end-day: baris dashboard yang terakhir di-sync SEBELUM close
     * terakhir berisi angka siklus yang sudah ter-seal — tampilkan nol.
     * Angka area muncul kembali setelah ada transaksi baru (sync berjalan).
     */
    private function resolveDashboardAggregate(?int $areaId, ?\Illuminate\Support\Carbon $lastCloseAt): ?Dashboard
    {
        $aggregate = Dashboard::query()
            ->when($areaId, fn ($q) => $q->where('area_id', $areaId), fn ($q) => $q->whereNull('area_id'))
            ->first();

        if (! $aggregate) {
            return null;
        }

        if ($lastCloseAt && ($aggregate->last_synced_at === null || $aggregate->last_synced_at->lt($lastCloseAt))) {
            return null;
        }

        return $aggregate;
    }

    public function syncToday(\Illuminate\Http\Request $request, DashboardSyncService $dashboardSyncService): RedirectResponse
    {
        $requestedAreaId = $request->input('area_id');

        if ($requestedAreaId && $requestedAreaId !== 'all') {
            $areaId = (int) $requestedAreaId;
            $dashboardSyncService->sync($areaId);
            $message = 'Dashboard berhasil di-sync untuk area terpilih.';
        } else {
            $dashboardSyncService->syncAll();
            $message = 'Dashboard berhasil di-sync (seluruh area).';
        }

        return redirect()
            ->route('admin.dashboard', isset($areaId) ? ['area_id' => $areaId] : [])
            ->with('success', $message);
    }
}
