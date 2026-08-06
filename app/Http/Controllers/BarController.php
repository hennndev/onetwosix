<?php

namespace App\Http\Controllers;

use App\Models\BarOrder;
use App\Models\DailyBarItem;
use App\Models\DailyBarSnapshot;
use App\Models\Dashboard;
use App\Models\EndayBarItem;
use App\Models\GeneralSetting;
use App\Models\OrderItem;
use App\Models\Printer;
use App\Models\RecapHistoryBar;
use App\Services\PrinterService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BarController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        $areas = $user ? $user->getAccessibleAreas() : \App\Models\Area::where('is_active', true)->orderBy('sort_order')->get();
        $selectedAreaId = $user ? $user->resolveActiveAreaId($request->input('area_id'), $request->has('area_id')) : ($request->filled('area_id')
            ? ($request->input('area_id') === 'all' ? null : (int) $request->input('area_id'))
            : (session('active_area_id') && session('active_area_id') !== 'all' ? (int) session('active_area_id') : null));

        $query = BarOrder::with([
            'customer.user',
            'customer.profile',
            'table.area',
            'items.inventoryItem',
            'order.tableSession.customer.profile',
        ]);

        if ($selectedAreaId) {
            $query->where(fn ($sub) => $sub->where('area_id', $selectedAreaId)->orWhereHas('table.area', fn ($t) => $t->where('id', $selectedAreaId)));
        }

        // Filter by status
        if ($request->has('status') && in_array($request->status, ['baru', 'proses', 'selesai'])) {
            $query->where('status', $request->status);
        } else {
            // default: exclude selesai
            $query->whereIn('status', ['baru', 'proses']);
        }

        $orders = $query->latest()->get();

        // Calculate stats
        $stats = [
            'total' => BarOrder::query()
                ->when($selectedAreaId, fn ($q) => $q->where(fn ($sub) => $sub->where('area_id', $selectedAreaId)->orWhereHas('table.area', fn ($t) => $t->where('id', $selectedAreaId))))
                ->count(),
            'baru' => BarOrder::query()
                ->when($selectedAreaId, fn ($q) => $q->where(fn ($sub) => $sub->where('area_id', $selectedAreaId)->orWhereHas('table.area', fn ($t) => $t->where('id', $selectedAreaId))))
                ->where('status', 'baru')
                ->count(),
            'proses' => BarOrder::query()
                ->when($selectedAreaId, fn ($q) => $q->where(fn ($sub) => $sub->where('area_id', $selectedAreaId)->orWhereHas('table.area', fn ($t) => $t->where('id', $selectedAreaId))))
                ->where('status', 'proses')
                ->count(),
            'selesai' => BarOrder::query()
                ->when($selectedAreaId, fn ($q) => $q->where(fn ($sub) => $sub->where('area_id', $selectedAreaId)->orWhereHas('table.area', fn ($t) => $t->where('id', $selectedAreaId))))
                ->where('status', 'selesai')
                ->count(),
        ];

        [$endDay, $startAt, $endAt] = $this->resolveEndDayRange($selectedAreaId);

        $dailySnapshot = $this->rebuildDailySnapshot($endDay, $startAt, $endAt, $selectedAreaId);
        $snapshotItems = collect();

        if ($dailySnapshot !== null) {
            $dailySnapshot->loadMissing(['dailyItems.inventoryItem']);

            $snapshotItems = $dailySnapshot->dailyItems
                ->map(fn (DailyBarItem $item): array => [
                    'name' => (string) ($item->inventoryItem?->pos_name ?? $item->inventoryItem?->name ?? 'Unknown Item'),
                    'quantity' => (int) $item->quantity,
                ])
                ->values();
        }

        $barEndDayPreview = [
            'total_items' => (int) ($dailySnapshot?->total_items ?? 0),
            'last_synced_at' => $dailySnapshot?->last_synced_at,
            'items' => $snapshotItems->all(),
        ];
        $barRecapHistories = RecapHistoryBar::query()
            ->with(['endayItems.inventoryItem'])
            ->when($selectedAreaId, fn ($q) => $q->where('area_id', $selectedAreaId))
            ->latest('end_day')
            ->limit(10)
            ->get()
            ->map(function (RecapHistoryBar $history): RecapHistoryBar {
                $history->setAttribute('resolved_total_items', (int) $history->endayItems->sum('quantity'));

                return $history;
            });

        return view('bar.index', compact('orders', 'stats', 'barEndDayPreview', 'barRecapHistories', 'areas', 'selectedAreaId'));
    }

    public function submitEndDay(Request $request, PrinterService $printerService): RedirectResponse
    {
        $user = auth()->user();
        $areaId = $user ? $user->resolveActiveAreaId($request->input('area_id')) : ($request->filled('area_id') && $request->input('area_id') !== 'all'
            ? (int) $request->input('area_id')
            : (session('active_area_id') && session('active_area_id') !== 'all' ? (int) session('active_area_id') : null));

        [$endDay, $startAt, $endAt] = $this->resolveEndDayRange($areaId);

        $existingHistory = RecapHistoryBar::query()
            ->whereDate('end_day', $endDay)
            ->when($areaId, fn ($q) => $q->where('area_id', $areaId), fn ($q) => $q->whereNull('area_id'))
            ->first();

        if ($existingHistory !== null) {
            return back()->with('error', 'End day bar untuk tanggal '.$endDay.' sudah ditutup.');
        }

        $this->rebuildDailySnapshot($endDay, $startAt, $endAt, $areaId);

        $dailySnapshot = DailyBarSnapshot::query()
            ->with(['dailyItems.inventoryItem'])
            ->whereDate('end_day', $endDay)
            ->when($areaId, fn ($q) => $q->where('area_id', $areaId), fn ($q) => $q->whereNull('area_id'))
            ->latest('id')
            ->first();

        if ($dailySnapshot === null) {
            return back()->with('error', 'Tidak ada item bar untuk end day tanggal '.$endDay.'.');
        }

        $items = $dailySnapshot->dailyItems;

        if ($items->isEmpty()) {
            return back()->with('error', 'Tidak ada item bar untuk end day tanggal '.$endDay.'.');
        }

        $printItems = $items
            ->map(fn (DailyBarItem $item): array => [
                'name' => (string) ($item->inventoryItem?->pos_name ?? $item->inventoryItem?->name ?? 'Unknown Item'),
                'quantity' => (int) $item->quantity,
            ])
            ->values()
            ->all();

        DB::transaction(function () use ($items, $endDay, $areaId): void {
            $syncedAt = now('Asia/Jakarta');

            $history = RecapHistoryBar::query()->create([
                'end_day' => $endDay,
                'area_id' => $areaId,
                'total_items' => (int) $items->sum('quantity'),
                'last_synced_at' => $syncedAt,
            ]);

            EndayBarItem::query()->insert(
                $items->map(fn (DailyBarItem $item): array => [
                    'recap_history_bar_id' => $history->id,
                    'end_day' => $endDay,
                    'inventory_item_id' => (int) $item->inventory_item_id,
                    'quantity' => (int) $item->quantity,
                    'created_at' => $syncedAt,
                    'updated_at' => $syncedAt,
                ])->values()->all()
            );

            DailyBarSnapshot::query()
                ->when($areaId, fn ($q) => $q->where('area_id', $areaId), fn ($q) => $q->whereNull('area_id'))
                ->delete();

            Dashboard::query()
                ->when($areaId, fn ($q) => $q->where('area_id', $areaId), fn ($q) => $q->whereNull('area_id'))
                ->update([
                    'total_bar_items' => 0,
                ]);
        });

        $printResultNote = null;
        $printer = $this->resolveEndDayBarPrinter($areaId);

        if ($printer !== null) {
            try {
                $printerService->printEndDayBarSummary($printItems, $endDay, $printer);
                $printResultNote = ' Slip End Day Bar berhasil dikirim ke printer '.$printer->name.'.';
            } catch (\Throwable $e) {
                $printResultNote = ' Data tersimpan, tapi print End Day Bar gagal: '.$e->getMessage();
            }
        }

        $successMessage = 'End day bar tanggal '.$endDay.' berhasil disimpan.';
        if ($printResultNote !== null) {
            $successMessage .= $printResultNote;
        }

        return back()->with('success', $successMessage);
    }

    public function syncSnapshot(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $areaId = $user ? $user->resolveActiveAreaId($request->input('area_id')) : ($request->filled('area_id') && $request->input('area_id') !== 'all'
            ? (int) $request->input('area_id')
            : (session('active_area_id') && session('active_area_id') !== 'all' ? (int) session('active_area_id') : null));

        [$endDay, $startAt, $endAt] = $this->resolveEndDayRange();

        $snapshot = $this->rebuildDailySnapshot($endDay, $startAt, $endAt, $areaId);

        if ($snapshot === null) {
            return back()->with('success', 'Snapshot Bar berhasil di-sync. Tidak ada item baru pada window operasional saat ini.');
        }

        return back()->with('success', 'Snapshot Bar berhasil di-sync.');
    }

    public function previewEndDay(RecapHistoryBar $history)
    {
        $history->loadMissing(['endayItems.inventoryItem']);
        $resolvedTotalItems = (int) $history->endayItems->sum('quantity');

        return view('bar.end-day-preview', [
            'history' => $history,
            'items' => $history->endayItems,
            'totalItems' => $resolvedTotalItems,
        ]);
    }

    public function reprintEndDay(RecapHistoryBar $history, PrinterService $printerService, Request $request): JsonResponse|RedirectResponse
    {
        $history->loadMissing(['endayItems.inventoryItem']);

        $printItems = $history->endayItems
            ->map(fn (EndayBarItem $item): array => [
                'name' => (string) ($item->inventoryItem?->pos_name ?? $item->inventoryItem?->name ?? 'Unknown Item'),
                'quantity' => (int) $item->quantity,
            ])
            ->values()
            ->all();

        if ($printItems === []) {
            $message = 'Detail item end day bar tidak ditemukan untuk history ini.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 422);
            }

            return back()->with('error', $message);
        }

        $printer = $this->resolveEndDayBarPrinter();

        if ($printer === null) {
            $message = 'Printer end day bar belum dikonfigurasi.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 422);
            }

            return back()->with('error', $message);
        }

        try {
            $printerService->printEndDayBarSummary(
                $printItems,
                (string) ($history->end_day?->toDateString() ?? '-'),
                $printer
            );

            $message = 'Reprint End Day Bar berhasil dikirim ke printer '.$printer->name.'.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                ]);
            }

            return redirect()
                ->route('admin.bar.end-day.preview', $history)
                ->with('success', $message);
        } catch (\Throwable $e) {
            $message = 'Gagal reprint End Day Bar: '.$e->getMessage();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 500);
            }

            return back()->with('error', $message);
        }
    }

    private function resolveEndDayBarPrinter(?int $areaId = null): ?Printer
    {
        $settings = GeneralSetting::instance();
        $contextAreaId = $areaId ?: (session('active_area_id') ?: auth()->user()?->getAssignedArea()?->id);
        $configuredPrinterId = $settings->getPrinterIdForArea($contextAreaId, 'end_day_bar');

        if ($configuredPrinterId && $configuredPrinterId > 0) {
            $configuredPrinter = Printer::active()->where('id', $configuredPrinterId)->first();

            if ($configuredPrinter !== null) {
                return $configuredPrinter;
            }
        }

        return Printer::active()->byType('bar')->first()
            ?? Printer::active()->byLocation('bar')->first()
            ?? Printer::active()->byType('cashier')->first()
            ?? Printer::active()->default()->first()
            ?? Printer::active()->first();
    }

    private function rebuildDailySnapshot(string $endDay, Carbon $startAt, Carbon $endAt, ?int $areaId = null): ?DailyBarSnapshot
    {
        $lastCloseAt = RecapHistoryBar::query()
            ->when($areaId, fn ($q) => $q->where('area_id', $areaId))
            ->latest('created_at')
            ->value('created_at');

        $aggregatedItems = OrderItem::query()
            ->selectRaw('order_items.inventory_item_id, SUM(order_items.quantity) as total_quantity')
            ->whereNotNull('order_items.inventory_item_id')
            ->where(function ($query): void {
                $query->whereNull('order_items.status')
                    ->orWhere('order_items.status', '!=', 'cancelled');
            })
            ->whereHas('order', function ($query) use ($startAt, $endAt, $lastCloseAt, $areaId): void {
                $query->where('created_at', '>=', $startAt)
                    ->where('created_at', '<=', $endAt)
                    ->when($lastCloseAt, fn ($innerQuery) => $innerQuery->where('created_at', '>', $lastCloseAt))
                    ->when($areaId, fn ($q) => $q->where(fn ($sub) => $sub->where('area_id', $areaId)->orWhereHas('tableSession.table', fn ($t) => $t->where('area_id', $areaId))));
            })
            ->whereHas('inventoryItem.printers', function ($query): void {
                $query->where('printers.is_active', true)
                    ->where(function ($printerQuery): void {
                        $printerQuery->where('printers.printer_type', 'bar')
                            ->orWhere('printers.location', 'bar');
                    });
            })
            ->groupBy('order_items.inventory_item_id')
            ->get();

        DailyBarSnapshot::query()
            ->when($areaId, fn ($q) => $q->where('area_id', $areaId), fn ($q) => $q->whereNull('area_id'))
            ->delete();

        if ($aggregatedItems->isEmpty()) {
            return null;
        }

        $syncedAt = now('Asia/Jakarta');
        $snapshot = DailyBarSnapshot::query()->create([
            'end_day' => $endDay,
            'area_id' => $areaId,
            'total_items' => (int) $aggregatedItems->sum('total_quantity'),
            'last_synced_at' => $syncedAt,
        ]);

        DailyBarItem::query()->insert(
            $aggregatedItems->map(fn ($item): array => [
                'daily_bar_snapshot_id' => $snapshot->id,
                'end_day' => $endDay,
                'inventory_item_id' => (int) $item->inventory_item_id,
                'quantity' => (int) $item->total_quantity,
                'created_at' => $syncedAt,
                'updated_at' => $syncedAt,
            ])->values()->all()
        );

        return $snapshot;
    }

    /**
     * @return array{0: string, 1: Carbon, 2: Carbon}
     */
    private function resolveEndDayRange(?int $areaId = null): array
    {
        $endDayDate = \App\Models\RecapHistory::resolveNextEndDay($areaId);

        $day = Carbon::parse($endDayDate, 'Asia/Jakarta');
        [$startAt, $endAt] = \App\Models\RecapHistory::resolveWindowForDate($day, $areaId);

        return [
            $endDayDate,
            $startAt,
            $endAt,
        ];
    }

    /**
     * Fetch orders as JSON for real-time updates.
     */
    public function fetchOrders(Request $request): JsonResponse
    {
        $status = $request->get('status');

        $query = BarOrder::with([
            'customer.user',
            'customer.profile',
            'table.area',
            'items.inventoryItem',
        ])->orderBy('created_at', 'desc');

        if ($status === 'proses') {
            $query->where('status', 'proses');
        } elseif ($status === 'selesai') {
            $query->where('status', 'selesai');
        } else {
            // default: exclude selesai
            $query->whereIn('status', ['baru', 'proses']);
        }

        $user = auth()->user();
        $areaId = $user ? $user->resolveActiveAreaId($request->input('area_id'), $request->has('area_id')) : null;

        if ($areaId) {
            $query->where(fn ($q) => $q->where('area_id', $areaId)->orWhereHas('table.area', fn ($t) => $t->where('id', $areaId)));
        }

        $orders = $query->get()->map(function ($order) {
            return $this->formatOrder($order);
        });

        $stats = [
            'total' => BarOrder::count(),
            'baru' => BarOrder::where('status', 'baru')->count(),
            'proses' => BarOrder::where('status', 'proses')->count(),
            'selesai' => BarOrder::where('status', 'selesai')->count(),
        ];

        return response()->json([
            'success' => true,
            'orders' => $orders,
            'stats' => $stats,
        ]);
    }

    public function toggleItem($itemId): JsonResponse
    {
        $item = BarOrderItem::with('barOrder')->findOrFail($itemId);
        $item->is_completed = ! $item->is_completed;
        $item->save();

        // Update order progress
        $item->barOrder->updateProgress();

        // Refresh the order to get updated data
        $order = BarOrder::with([
            'customer.user',
            'customer.profile',
            'table.area',
            'items.inventoryItem',
        ])->find($item->bar_order_id);

        return response()->json([
            'success' => true,
            'message' => 'Item status updated',
            'item' => [
                'id' => $item->id,
                'is_completed' => $item->is_completed,
            ],
            'order' => $this->formatOrder($order),
        ]);
    }

    public function completeAll($orderId): JsonResponse
    {
        $order = BarOrder::with('items')->findOrFail($orderId);

        // Mark all items as completed
        $order->items()->update(['is_completed' => true]);

        // Explicitly set progress and status
        $order->update([
            'progress' => 100,
            'status' => 'selesai',
        ]);

        // Refresh the order to get updated data
        $order = BarOrder::with([
            'customer.user',
            'customer.profile',
            'table.area',
            'items.inventoryItem',
        ])->find($orderId);

        return response()->json([
            'success' => true,
            'message' => 'All items marked as completed',
            'order' => $this->formatOrder($order),
        ]);
    }

    /**
     * Format order data for JSON response.
     */
    protected function formatOrder(BarOrder $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'progress' => $order->progress,
            'created_at' => $order->created_at->format('d M Y H:i'),
            'customer' => $order->customer ? [
                'id' => $order->customer->id,
                'name' => $order->customer->user?->name ?? 'N/A',
                'phone' => $order->customer->profile?->phone ?? null,
            ] : null,
            'table' => $order->table ? [
                'id' => $order->table->id,
                'table_number' => $order->table->table_number,
                'area' => $order->table->area ? [
                    'id' => $order->table->area->id,
                    'name' => $order->table->area->name,
                ] : null,
            ] : null,
            'items' => $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'item_name' => $item->inventoryItem?->pos_name ?? $item->inventoryItem?->name ?? 'Unknown',
                    'quantity' => $item->quantity,
                    'is_completed' => $item->is_completed,
                    'notes' => $item->notes,
                ];
            }),
        ];
    }
}
