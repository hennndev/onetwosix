<?php

namespace App\Http\Controllers;

use App\Models\DailyKitchenItem;
use App\Models\DailyKitchenSnapshot;
use App\Models\Dashboard;
use App\Models\EndayKitchenItem;
use App\Models\GeneralSetting;
use App\Models\KitchenOrder;
use App\Models\KitchenOrderItem;
use App\Models\Printer;
use App\Models\RecapHistoryKitchen;
use App\Services\PrinterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class KitchenController extends Controller
{
    public function index(Request $request)
    {
        $query = KitchenOrder::with([
            'customer.user',
            'customer.profile',
            'table.area',
            'items.inventoryItem',
            'order.tableSession.customer.profile',
        ]);

        // Filter by status
        if ($request->has('status') && in_array($request->status, ['baru', 'proses', 'selesai'])) {
            $query->where('status', $request->status);
        } else {
            // default: exclude selesai
            $query->whereIn('status', ['baru', 'proses']);
        }

        $orders = $query->latest()->get();

        // Calculate stats
        $totalOrders = KitchenOrder::count();
        $baruOrders = KitchenOrder::where('status', 'baru')->count();
        $prosesOrders = KitchenOrder::where('status', 'proses')->count();
        $selesaiOrders = KitchenOrder::where('status', 'selesai')->count();

        [$endDay, $startAt, $endAt] = $this->resolveEndDayRange();

        $dailySnapshot = $this->rebuildDailySnapshot($endDay, $startAt, $endAt);
        $snapshotItems = collect();

        if ($dailySnapshot !== null) {
            $dailySnapshot->loadMissing(['dailyItems.inventoryItem']);

            $snapshotItems = $dailySnapshot->dailyItems
                ->map(fn (DailyKitchenItem $item): array => [
                    'name' => (string) ($item->inventoryItem?->pos_name ?? $item->inventoryItem?->name ?? 'Unknown Item'),
                    'quantity' => (int) $item->quantity,
                ])
                ->values();
        }

        $kitchenEndDayPreview = [
            'total_items' => (int) ($dailySnapshot?->total_items ?? 0),
            'last_synced_at' => $dailySnapshot?->last_synced_at,
            'items' => $snapshotItems->all(),
        ];
        $kitchenRecapHistories = RecapHistoryKitchen::query()
            ->with(['endayItems.inventoryItem'])
            ->latest('end_day')
            ->limit(10)
            ->get()
            ->map(function (RecapHistoryKitchen $history): RecapHistoryKitchen {
                $history->setAttribute('resolved_total_items', (int) $history->endayItems->sum('quantity'));

                return $history;
            });

        return view('kitchen.index', compact('orders', 'totalOrders', 'baruOrders', 'prosesOrders', 'selesaiOrders', 'kitchenEndDayPreview', 'kitchenRecapHistories'));
    }

    public function submitEndDay(PrinterService $printerService): RedirectResponse
    {
        [$endDay] = $this->resolveEndDayRange();

        $existingHistory = RecapHistoryKitchen::query()
            ->whereDate('end_day', $endDay)
            ->first();

        if ($existingHistory !== null) {
            $existingHistory->loadMissing(['endayItems.inventoryItem']);

            $printItems = $existingHistory->endayItems
                ->map(fn (EndayKitchenItem $item): array => [
                    'name' => (string) ($item->inventoryItem?->pos_name ?? $item->inventoryItem?->name ?? 'Unknown Item'),
                    'quantity' => (int) $item->quantity,
                ])
                ->values()
                ->all();

            if ($printItems === []) {
                return back()->with('error', 'End day kitchen untuk tanggal '.$endDay.' sudah ditutup, tetapi detail item tidak tersedia untuk dicetak ulang.');
            }

            $printer = $this->resolveEndDayKitchenPrinter();

            if ($printer === null) {
                return back()->with('success', 'End day kitchen tanggal '.$endDay.' sudah ada di history. Printer tidak ditemukan untuk auto print.');
            }

            try {
                $printerService->printEndDayKitchenSummary($printItems, $endDay, $printer);

                return back()->with('success', 'End day kitchen tanggal '.$endDay.' sudah ada di history. Slip berhasil dicetak ulang ke printer '.$printer->name.'.');
            } catch (\Throwable $e) {
                return back()->with('error', 'End day kitchen tanggal '.$endDay.' sudah ada di history, tapi print gagal: '.$e->getMessage());
            }
        }

        $dailySnapshot = DailyKitchenSnapshot::query()
            ->with(['dailyItems.inventoryItem'])
            ->whereDate('end_day', $endDay)
            ->latest('id')
            ->first();

        if ($dailySnapshot === null) {
            return back()->with('error', 'Tidak ada item kitchen untuk end day tanggal '.$endDay.'.');
        }

        $items = $dailySnapshot->dailyItems;

        if ($items->isEmpty()) {
            return back()->with('error', 'Tidak ada item kitchen untuk end day tanggal '.$endDay.'.');
        }

        $printItems = $items
            ->map(fn (DailyKitchenItem $item): array => [
                'name' => (string) ($item->inventoryItem?->pos_name ?? $item->inventoryItem?->name ?? 'Unknown Item'),
                'quantity' => (int) $item->quantity,
            ])
            ->values()
            ->all();

        DB::transaction(function () use ($items, $endDay): void {
            $syncedAt = now('Asia/Jakarta');

            $history = RecapHistoryKitchen::query()->create([
                'end_day' => $endDay,
                'total_items' => (int) $items->sum('quantity'),
                'last_synced_at' => $syncedAt,
            ]);

            EndayKitchenItem::query()->insert(
                $items->map(fn (DailyKitchenItem $item): array => [
                    'recap_history_kitchen_id' => $history->id,
                    'end_day' => $endDay,
                    'inventory_item_id' => (int) $item->inventory_item_id,
                    'quantity' => (int) $item->quantity,
                    'created_at' => $syncedAt,
                    'updated_at' => $syncedAt,
                ])->values()->all()
            );

            DailyKitchenSnapshot::query()->delete();

            Dashboard::query()->where('id', 1)->update([
                'total_kitchen_items' => 0,
            ]);
        });

        $printResultNote = null;
        $printer = $this->resolveEndDayKitchenPrinter();

        if ($printer !== null) {
            try {
                $printerService->printEndDayKitchenSummary($printItems, $endDay, $printer);
                $printResultNote = ' Slip End Day Kitchen berhasil dikirim ke printer '.$printer->name.'.';
            } catch (\Throwable $e) {
                $printResultNote = ' Data tersimpan, tapi print End Day Kitchen gagal: '.$e->getMessage();
            }
        } else {
            $printResultNote = ' Data tersimpan, tapi printer End Day Kitchen tidak ditemukan.';
        }

        $successMessage = 'End day kitchen tanggal '.$endDay.' berhasil disimpan.';
        if ($printResultNote !== null) {
            $successMessage .= $printResultNote;
        }

        return back()->with('success', $successMessage);
    }

    public function syncSnapshot(): RedirectResponse
    {
        [$endDay, $startAt, $endAt] = $this->resolveEndDayRange();

        $snapshot = $this->rebuildDailySnapshot($endDay, $startAt, $endAt);

        if ($snapshot === null) {
            return back()->with('success', 'Snapshot Kitchen berhasil di-sync. Tidak ada item baru pada window operasional saat ini.');
        }

        return back()->with('success', 'Snapshot Kitchen berhasil di-sync.');
    }

    public function previewEndDay(RecapHistoryKitchen $history)
    {
        $history->loadMissing(['endayItems.inventoryItem']);
        $resolvedTotalItems = (int) $history->endayItems->sum('quantity');

        return view('kitchen.end-day-preview', [
            'history' => $history,
            'items' => $history->endayItems,
            'totalItems' => $resolvedTotalItems,
        ]);
    }

    public function reprintEndDay(RecapHistoryKitchen $history, PrinterService $printerService, Request $request): JsonResponse|RedirectResponse
    {
        $history->loadMissing(['endayItems.inventoryItem']);

        $printItems = $history->endayItems
            ->map(fn (EndayKitchenItem $item): array => [
                'name' => (string) ($item->inventoryItem?->pos_name ?? $item->inventoryItem?->name ?? 'Unknown Item'),
                'quantity' => (int) $item->quantity,
            ])
            ->values()
            ->all();

        if ($printItems === []) {
            $message = 'Detail item end day kitchen tidak ditemukan untuk history ini.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 422);
            }

            return back()->with('error', $message);
        }

        $printer = $this->resolveEndDayKitchenPrinter();

        if ($printer === null) {
            $message = 'Printer end day kitchen belum dikonfigurasi.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 422);
            }

            return back()->with('error', $message);
        }

        try {
            $printerService->printEndDayKitchenSummary(
                $printItems,
                (string) ($history->end_day?->toDateString() ?? '-'),
                $printer
            );

            $message = 'Reprint End Day Kitchen berhasil dikirim ke printer '.$printer->name.'.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                ]);
            }

            return redirect()
                ->route('admin.kitchen.end-day.preview', $history)
                ->with('success', $message);
        } catch (\Throwable $e) {
            $message = 'Gagal reprint End Day Kitchen: '.$e->getMessage();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 500);
            }

            return back()->with('error', $message);
        }
    }

    private function resolveEndDayKitchenPrinter(): ?Printer
    {
        $settings = GeneralSetting::instance();
        $configuredPrinterId = (int) ($settings->end_day_kitchen_printer_id ?? 0);

        if ($configuredPrinterId > 0) {
            $configuredPrinter = Printer::active()->where('id', $configuredPrinterId)->first();

            if ($configuredPrinter !== null) {
                return $configuredPrinter;
            }
        }

        return Printer::active()->byType('kitchen')->first()
            ?? Printer::active()->byLocation('kitchen')->first()
            ?? Printer::active()->byType('cashier')->first()
            ?? Printer::active()->default()->first()
            ?? Printer::active()->first();
    }

    private function rebuildDailySnapshot(string $endDay, Carbon $startAt, Carbon $endAt): ?DailyKitchenSnapshot
    {
        $lastCloseAt = RecapHistoryKitchen::query()->latest('created_at')->value('created_at');

        $aggregatedItems = KitchenOrderItem::query()
            ->selectRaw('inventory_item_id, SUM(quantity) as total_quantity')
            ->whereNotNull('inventory_item_id')
            ->whereHas('kitchenOrder', function ($query) use ($startAt, $endAt, $lastCloseAt): void {
                $query->where('created_at', '>=', $startAt)
                    ->where('created_at', '<=', $endAt)
                    ->when($lastCloseAt, fn ($innerQuery) => $innerQuery->where('created_at', '>', $lastCloseAt));
            })
            ->groupBy('inventory_item_id')
            ->get();

        DailyKitchenSnapshot::query()->delete();

        if ($aggregatedItems->isEmpty()) {
            return null;
        }

        $syncedAt = now('Asia/Jakarta');
        $snapshot = DailyKitchenSnapshot::query()->create([
            'end_day' => $endDay,
            'total_items' => (int) $aggregatedItems->sum('total_quantity'),
            'last_synced_at' => $syncedAt,
        ]);

        DailyKitchenItem::query()->insert(
            $aggregatedItems->map(fn ($item): array => [
                'daily_kitchen_snapshot_id' => $snapshot->id,
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
    private function resolveEndDayRange(): array
    {
        $closingAt = now('Asia/Jakarta');
        $endDayDate = $closingAt->hour < 9
            ? $closingAt->copy()->subDay()->toDateString()
            : $closingAt->toDateString();

        $day = Carbon::parse($endDayDate, 'Asia/Jakarta');
        $startAt = $day->copy()->setTime(9, 0, 0);
        $endAt = $day->copy()->addDay()->setTime(8, 59, 59);

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
        $query = KitchenOrder::with([
            'customer.user',
            'customer.profile',
            'table.area',
            'items.inventoryItem',
            'order.tableSession.customer.profile',
        ])->latest();

        // Filter by status
        if ($request->has('status') && in_array($request->status, ['baru', 'proses', 'selesai'])) {
            $query->where('status', $request->status);
        } else {
            // default: exclude selesai
            $query->whereIn('status', ['baru', 'proses']);
        }

        $orders = $query->get()->map(function ($order) {
            return $this->formatOrder($order);
        });

        $stats = [
            'total' => KitchenOrder::count(),
            'baru' => KitchenOrder::where('status', 'baru')->count(),
            'proses' => KitchenOrder::where('status', 'proses')->count(),
            'selesai' => KitchenOrder::where('status', 'selesai')->count(),
        ];

        return response()->json([
            'success' => true,
            'orders' => $orders,
            'stats' => $stats,
        ]);
    }

    public function toggleItem(KitchenOrderItem $item): JsonResponse
    {
        $item->update(['is_completed' => ! $item->is_completed]);
        $item->kitchenOrder->updateProgress();

        // Refresh the order to get updated data
        $order = KitchenOrder::with([
            'customer.user',
            'customer.profile',
            'table.area',
            'items.inventoryItem',
            'order.tableSession.customer.profile',
        ])->find($item->kitchen_order_id);

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

    public function completeAll(KitchenOrder $order): JsonResponse
    {
        $order->items()->update(['is_completed' => true]);
        $order->update([
            'progress' => 100,
            'status' => 'selesai',
        ]);

        // Refresh the order to get updated data
        $order = KitchenOrder::with([
            'customer.user',
            'customer.profile',
            'table.area',
            'items.inventoryItem',
            'order.tableSession.customer.profile',
        ])->find($order->id);

        return response()->json([
            'success' => true,
            'message' => 'Semua item telah diselesaikan!',
            'order' => $this->formatOrder($order),
        ]);
    }

    /**
     * Format order data for JSON response.
     */
    protected function formatOrder(KitchenOrder $order): array
    {
        $sessionCustomer = $order->order?->tableSession?->customer;
        $customerName = $order->customer?->user?->name
            ?? $sessionCustomer?->name
            ?? 'Walk-in';
        $customerPhone = $order->customer?->profile?->phone
            ?? $sessionCustomer?->profile?->phone
            ?? null;

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'progress' => $order->progress,
            'created_at' => $order->created_at->format('d M Y H:i'),
            'customer' => [
                'id' => $order->customer?->id,
                'name' => $customerName,
                'phone' => $customerPhone,
            ],
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
            })->values()->all(),
        ];
    }
}
