<?php

namespace App\Http\Controllers;

use App\Models\BarOrderItem;
use App\Models\Billing;
use App\Models\Dashboard;
use App\Models\GeneralSetting;
use App\Models\KitchenOrderItem;
use App\Models\Order;
use App\Models\Printer;
use App\Models\RecapHistory;
use App\Models\TableSession;
use App\Services\PrinterService;
use App\Services\RecapClosingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RecapController extends Controller
{
    public function index(Request $request): \Illuminate\View\View|\Illuminate\Http\Response
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'start_datetime' => ['nullable', 'date'],
            'end_datetime' => ['nullable', 'date', 'after_or_equal:start_datetime'],
            'area_id' => ['nullable'],
        ]);

        $user = auth()->user();
        $areas = $user ? $user->getAccessibleAreas() : \App\Models\Area::where('is_active', true)->orderBy('sort_order')->get();
        $selectedAreaId = $user ? $user->resolveActiveAreaId($request->input('area_id'), $request->has('area_id')) : ($request->filled('area_id')
            ? ($request->input('area_id') === 'all' ? null : (int) $request->input('area_id'))
            : (session('active_area_id') && session('active_area_id') !== 'all' ? (int) session('active_area_id') : null));

        [$startAt, $endAt] = $this->resolveRange($validated, $selectedAreaId);
        $recapData = $this->buildRecapData($startAt, $endAt, (bool) ($validated['reprint'] ?? false), $selectedAreaId);

        if ($request->headers->get('X-Live')) {
            return response(
                view('recap._partials.summary', $recapData)
            )->withHeaders(['X-Live' => '1']);
        }

        $recapHistoryTransactionRecaps = RecapHistory::query()
            ->when($selectedAreaId, fn ($q) => $q->where('area_id', $selectedAreaId))
            ->latest('end_day')
            ->limit(10)
            ->get()
            ->mapWithKeys(function (RecapHistory $history): array {
                $historyRecapData = $this->buildRecapDataFromHistory($history);

                return [
                    $history->id => [
                        'cashier_transactions' => $historyRecapData['cashierTransactions'] ?? [],
                    ],
                ];
            })
            ->all();

        return view('recap.index', array_merge($recapData, [
            'recapHistoryTransactionRecaps' => $recapHistoryTransactionRecaps,
            'areas' => $areas,
            'selectedAreaId' => $selectedAreaId,
        ]));
    }

    public function export(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'start_datetime' => ['nullable', 'date'],
            'end_datetime' => ['nullable', 'date', 'after_or_equal:start_datetime'],
            'area_id' => ['nullable'],
        ]);

        $user = auth()->user();
        $selectedAreaId = $user ? $user->resolveActiveAreaId($request->input('area_id')) : ($request->filled('area_id')
            ? ($request->input('area_id') === 'all' ? null : (int) $request->input('area_id'))
            : (session('active_area_id') && session('active_area_id') !== 'all' ? (int) session('active_area_id') : null));

        [$startAt, $endAt] = $this->resolveRange($validated, $selectedAreaId);
        $recapData = $this->buildRecapData($startAt, $endAt, false, $selectedAreaId);

        return $this->downloadRows(
            $this->buildLiveRecapExportRows($recapData),
            'rekapan-'.$startAt->format('Ymd_Hi').'-'.$endAt->format('Ymd_Hi').'.xlsx'
        );
    }

    public function closePreview(Request $request): View
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'start_datetime' => ['nullable', 'date'],
            'end_datetime' => ['nullable', 'date', 'after_or_equal:start_datetime'],
            'reprint' => ['nullable', 'boolean'],
            'include_transaction_history' => ['nullable', 'boolean'],
            'recap_history_id' => ['nullable', 'integer', 'exists:recap_history,id'],
            'area_id' => ['nullable'],
        ]);

        $user = auth()->user();
        $areas = $user ? $user->getAccessibleAreas() : \App\Models\Area::where('is_active', true)->orderBy('sort_order')->get();
        $selectedAreaId = $user ? $user->resolveActiveAreaId($request->input('area_id')) : ($request->filled('area_id')
            ? ($request->input('area_id') === 'all' ? null : (int) $request->input('area_id'))
            : (session('active_area_id') && session('active_area_id') !== 'all' ? (int) session('active_area_id') : null));

        [$startAt, $endAt] = $this->resolveRange($validated, $selectedAreaId);
        $recapHistory = ! empty($validated['recap_history_id'])
            ? RecapHistory::query()->find((int) $validated['recap_history_id'])
            : null;

        $recapData = $recapHistory
            ? $this->buildRecapDataFromHistory($recapHistory)
            : $this->buildRecapData($startAt, $endAt, (bool) ($validated['reprint'] ?? false), $selectedAreaId);

        return view('recap.close-preview', array_merge($recapData, [
            'printedAt' => now(),
            'isReprintPreview' => (bool) ($validated['reprint'] ?? false),
            'includeTransactionHistory' => (bool) ($validated['include_transaction_history'] ?? true),
            'reprintHistoryId' => (int) ($validated['recap_history_id'] ?? 0),
            'areas' => $areas,
            'selectedAreaId' => $selectedAreaId,
        ]));
    }

    public function printClosePreview(Request $request, PrinterService $printerService): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'start_datetime' => ['nullable', 'date'],
            'end_datetime' => ['nullable', 'date', 'after_or_equal:start_datetime'],
            'include_transaction_history' => ['nullable', 'boolean'],
            'recap_history_id' => ['nullable', 'integer', 'exists:recap_history,id'],
            'area_id' => ['nullable'],
        ]);

        $user = auth()->user();
        $selectedAreaId = $user ? $user->resolveActiveAreaId($request->input('area_id')) : ($request->filled('area_id')
            ? ($request->input('area_id') === 'all' ? null : (int) $request->input('area_id'))
            : (session('active_area_id') && session('active_area_id') !== 'all' ? (int) session('active_area_id') : null));

        [$startAt, $endAt] = $this->resolveRange($validated, $selectedAreaId);
        $recapHistory = ! empty($validated['recap_history_id'])
            ? RecapHistory::query()->find((int) $validated['recap_history_id'])
            : null;

        $recapData = $recapHistory
            ? $this->buildRecapDataFromHistory($recapHistory)
            : $this->buildRecapData($startAt, $endAt, false, $selectedAreaId);

        $includeTransactionHistory = (bool) ($validated['include_transaction_history'] ?? true);

        $printer = $this->resolveEndDayPrinter($selectedAreaId);

        if (! $printer) {
            return response()->json([
                'success' => false,
                'message' => 'Printer End Day belum dikonfigurasi atau tidak aktif.',
            ], 422);
        }

        try {
            $printerService->printEndDayRecap($recapData, $printer, $includeTransactionHistory);

            $message = "Print End Day berhasil dikirim ke printer {$printer->name}.";

            if ($printer->connection_type === 'log') {
                $message .= ' (LOG MODE)';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'printer_name' => $printer->name,
                'connection_type' => $printer->connection_type,
                'log_path' => $printer->connection_type === 'log' ? storage_path('logs/printer.log') : null,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal print End Day: '.$e->getMessage(),
            ], 500);
        }
    }

    public function closeAndExport(Request $request, RecapClosingService $recapClosingService): BinaryFileResponse|RedirectResponse
    {
        $user = auth()->user();
        $areaId = $user ? $user->resolveActiveAreaId($request->input('area_id')) : ($request->filled('area_id') && $request->input('area_id') !== 'all'
            ? (int) $request->input('area_id')
            : (session('active_area_id') && session('active_area_id') !== 'all' ? (int) session('active_area_id') : null));

        $result = $recapClosingService->closeDay(null, $areaId);

        if ($result['status'] === 'no_data') {
            return back()->with('error', 'Tidak ada data dashboard untuk ditutup.');
        }

        $recapHistory = $result['recap_history'];

        if (! $recapHistory) {
            return back()->with('error', 'Gagal menyiapkan data end day.');
        }

        return $this->downloadRows(
            $this->buildHistoryExportRows($recapHistory),
            'rekapan-history-'.$recapHistory->end_day?->format('Ymd').'.xlsx'
        );
    }

    public function exportHistory(RecapHistory $recapHistory): BinaryFileResponse
    {
        return $this->downloadRows(
            $this->buildHistoryExportRows($recapHistory),
            'rekapan-history-'.$recapHistory->end_day?->format('Ymd').'.xlsx'
        );
    }

    public function historyTransactions(RecapHistory $recapHistory): JsonResponse
    {
        $historyRecapData = $this->buildRecapDataFromHistory($recapHistory);

        return response()->json([
            'billing_transactions' => $historyRecapData['todayBillingTransactions'] ?? [],
            'walkin_transactions' => $historyRecapData['todayWalkInTransactions'] ?? [],
        ]);
    }

    public function reprintHistory(RecapHistory $recapHistory, PrinterService $printerService): RedirectResponse
    {
        if (! $recapHistory->end_day) {
            return back()->with('error', 'Tanggal end day history tidak valid.');
        }

        [$startAt, $endAt] = $this->resolveEndDayWindow($recapHistory->end_day->copy());

        $printer = $this->resolveEndDayPrinter();

        if (! $printer) {
            return back()->with('error', 'Printer End Day belum dikonfigurasi atau tidak aktif.');
        }

        $recapData = $this->buildRecapDataFromHistory($recapHistory);

        try {
            $printerService->printEndDayRecap($recapData, $printer);

            $message = "Reprint history end day berhasil dikirim ke printer {$printer->name}.";

            if ($printer->connection_type === 'log') {
                $message .= ' (LOG MODE)';
            }

            return redirect()->route('admin.recap.close-preview', [
                'start_datetime' => $startAt->format('Y-m-d\TH:i'),
                'end_datetime' => $endAt->format('Y-m-d\TH:i'),
                'reprint' => 1,
                'recap_history_id' => $recapHistory->id,
            ])->with('success', $message);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal reprint history End Day: '.$e->getMessage());
        }
    }

    private function buildRecapData(Carbon $startAt, Carbon $endAt, bool $includeClosedEndDayData = false, ?int $areaId = null): array
    {
        $isSelectedEndDayClosed = ! $includeClosedEndDayData
            && $this->isSelectedEndDayClosed($startAt, $areaId);

        $orders = Order::query()
            ->with(['items.inventoryItem', 'tableSession.table.area', 'tableSession.customer.profile', 'tableSession.reservation', 'customer.user'])
            ->where('status', '!=', 'cancelled')
            ->when($areaId, fn ($q) => $q->where(fn ($sub) => $sub->where('area_id', $areaId)->orWhereHas('tableSession.table', fn ($t) => $t->where('area_id', $areaId))))
            ->where(function ($query) use ($startAt, $endAt): void {
                $query->whereBetween('ordered_at', [$startAt, $endAt])
                    ->orWhere(function ($fallbackQuery) use ($startAt, $endAt): void {
                        $fallbackQuery->whereNull('ordered_at')
                            ->whereBetween('created_at', [$startAt, $endAt]);
                    });
            })
            ->orderByRaw('COALESCE(ordered_at, created_at) ASC')
            ->get();

        if ($isSelectedEndDayClosed) {
            $orders = collect();
        }

        $billingsBySessionId = Billing::query()
            ->whereIn('table_session_id', $orders->pluck('table_session_id')->filter()->unique()->values())
            ->get()
            ->keyBy('table_session_id');

        $billingsByOrderId = Billing::query()
            ->whereIn('order_id', $orders->pluck('id')->filter()->unique()->values())
            ->get()
            ->keyBy('order_id');

        $cashierTransactions = $orders
            ->map(function (Order $order) use ($billingsBySessionId, $billingsByOrderId): array {
                $eventTime = $order->ordered_at ?? $order->created_at;
                $customerName = $order->tableSession?->customer?->profile?->name
                    ?? $order->tableSession?->customer?->name
                    ?? $order->customer?->user?->name
                    ?? 'Walk-in';
                $sessionBilling = $order->table_session_id ? $billingsBySessionId->get($order->table_session_id) : null;
                $billing = $billingsByOrderId->get($order->id)
                    ?? (($sessionBilling?->billing_status ?? '') === 'paid' ? $sessionBilling : null);
                $paymentMode = $order->payment_mode ?? $billing?->payment_mode;
                $paymentMethod = $order->payment_method ?? $billing?->payment_method;
                $orderItems = $order->items
                    ->where('status', '!=', 'cancelled')
                    ->values();
                $paymentReferenceNumber = $order->payment_reference_number
                    ?? $billing?->payment_reference_number
                    ?? $billing?->split_non_cash_reference_number;

                $itemsSubtotal = (float) $orderItems->sum(fn ($item) => (float) ($item->subtotal ?? 0));
                $itemTaxTotal = (float) $orderItems->sum(fn ($item) => (float) ($item->tax_amount ?? 0));
                $itemServiceChargeTotal = (float) $orderItems->sum(fn ($item) => (float) ($item->service_charge_amount ?? 0));

                $totalBill = $billing
                    ? max((float) ($billing->minimum_charge ?? 0), (float) ($billing->orders_total ?? 0))
                    : $itemsSubtotal;
                $taxTotal = $billing ? (float) ($billing->tax ?? 0) : $itemTaxTotal;
                $serviceChargeTotal = $billing ? (float) ($billing->service_charge ?? 0) : $itemServiceChargeTotal;
                $subTotal = $totalBill + $taxTotal + $serviceChargeTotal;

                $discountAmount = (float) ($billing?->discount_amount ?? $order->discount_amount ?? 0);
                // DP (down payment) hanya relevan saat billing closed/paid — bukan per transaksi
                // booking yang belum closed. Untuk order per-order (belum paid), DP tidak dikurangkan.
                $downPaymentAmount = $billing
                    ? (float) ($order->tableSession?->reservation?->down_payment_amount ?? 0)
                    : 0.0;

                $remainingTotal = max($subTotal - $discountAmount - $downPaymentAmount, 0);

                return [
                    'timestamp' => $eventTime,
                    'datetime' => $eventTime?->format('d/m/Y H:i') ?? '-',
                    'order_number' => $order->order_number
                        ?? $billing?->transaction_code
                        ?? ('TRX-'.$order->id),
                    'customer_name' => $customerName,
                    'payment_method' => $this->formatPaymentMethod($paymentMethod, $paymentMode),
                    'payment_reference_number' => $paymentReferenceNumber,
                    'items_count' => $orderItems->count(),
                    'total_bill' => $totalBill,
                    'tax_total' => $taxTotal,
                    'service_charge_total' => $serviceChargeTotal,
                    'sub_total' => $subTotal,
                    'discount_amount' => $discountAmount,
                    'down_payment_amount' => $downPaymentAmount,
                    'order_status' => (string) $order->status,
                    'billing_status' => (string) ($billing?->billing_status ?? ''),
                    'items' => $orderItems
                        ->map(fn ($item): array => [
                            'name' => (string) ($item->inventoryItem?->pos_name ?? $item->inventoryItem?->name ?? $item->item_name ?? '-'),
                            'quantity' => (int) ($item->quantity ?? 0),
                            'price' => (float) ($item->price ?? 0),
                            'subtotal' => (float) ($item->subtotal ?? 0),
                            'tax_amount' => (float) ($item->tax_amount ?? 0),
                            'service_charge_amount' => (float) ($item->service_charge_amount ?? 0),
                        ])
                        ->values()
                        ->all(),
                    'total' => $remainingTotal,
                ];
            })
            ->filter(function (array $transaction): bool {
                $isPaidByBilling = $transaction['billing_status'] === 'paid';
                $isCompletedOrder = $transaction['order_status'] === 'completed';
                // Transaksi booking yang belum closed (billing belum paid) tetap ditampilkan
                // per-order selama transaksi tersebut punya nilai/barang.
                $isMeaningfulTransaction = $transaction['items_count'] > 0
                    || (float) $transaction['total'] > 0
                    || filled($transaction['payment_reference_number'])
                    || ($transaction['payment_method'] ?? '-') !== '-';

                if (! $isMeaningfulTransaction) {
                    return false;
                }

                // Hanya tampilkan order yang relevan: completed, billing paid, atau
                // transaksi bermakna dari sesi yang belum ditutup.
                return $isPaidByBilling || $isCompletedOrder || $transaction['billing_status'] !== 'paid';
            })
            ->map(function (array $transaction): array {
                unset($transaction['order_status'], $transaction['billing_status']);

                return $transaction;
            })
            ->values();

        // Item FOC/Compliment keluar (category_main = foc/compliment), dikelompokkan sendiri.
        $focItems = $orders
            ->flatMap(function (Order $order) {
                return $order->items
                    ->where('status', '!=', 'cancelled')
                    ->filter(function ($item): bool {
                        $categoryMain = strtolower(trim((string) ($item->inventoryItem?->category_main ?? '')));

                        return in_array($categoryMain, ['foc', 'compliment'], true);
                    })
                    ->map(function ($item): array {
                        return [
                            'name' => (string) ($item->inventoryItem?->pos_name ?? $item->inventoryItem?->name ?? $item->item_name ?? '-'),
                            'quantity' => (int) ($item->quantity ?? 0),
                        ];
                    });
            })
            ->groupBy('name')
            ->map(function ($group, $name): array {
                return [
                    'name' => (string) $name,
                    'quantity' => (int) collect($group)->sum('quantity'),
                ];
            })
            ->sortBy('name')
            ->values();

        $rokokItems = $orders
            ->flatMap(function (Order $order) {
                return $order->items
                    ->where('status', '!=', 'cancelled')
                    ->filter(function ($item): bool {
                        $categoryType = strtolower(trim((string) ($item->inventoryItem?->category_type ?? '')));

                        return $categoryType !== '' && str_contains($categoryType, 'rokok');
                    })
                    ->map(function ($item): array {
                        return [
                            'name' => (string) ($item->inventoryItem?->pos_name ?? $item->inventoryItem?->name ?? $item->item_name ?? '-'),
                            'quantity' => (int) ($item->quantity ?? 0),
                        ];
                    });
            })
            ->groupBy('name')
            ->map(function ($group, $name): array {
                return [
                    'name' => (string) $name,
                    'quantity' => (int) collect($group)->sum('quantity'),
                ];
            })
            ->sortBy('name')
            ->values();

        $totalDiscount = (float) $cashierTransactions->sum('discount_amount');
        $liveTotalDownPayment = $this->resolveLiveTotalDownPayment($startAt, $endAt, $areaId);

        $dashboardAggregate = Dashboard::query()
            ->when($areaId, fn ($q) => $q->where('area_id', $areaId), fn ($q) => $q->whereNull('area_id'))
            ->first() ?? Dashboard::query()->find(1);
        $dashboardTotalDp = $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_dp ?? 0);
        $dashboardTotalLdQuantity = $isSelectedEndDayClosed ? 0 : (int) ($dashboardAggregate?->total_ld_quantity ?? 0);
        $dashboardTotalComplimentQuantity = $isSelectedEndDayClosed ? 0 : (int) ($dashboardAggregate?->total_compliment_quantity ?? 0);
        $dashboardTotalFocQuantity = $isSelectedEndDayClosed ? 0 : (int) ($dashboardAggregate?->total_foc_quantity ?? 0);
        $dashboardTotalFocAmount = $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_foc_amount ?? 0);
        $dashboardTotalComplimentAmount = $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_compliment_amount ?? 0);
        $resolvedTotalDp = $isSelectedEndDayClosed
            ? 0.0
            : ($dashboardTotalDp > 0 ? $dashboardTotalDp : $liveTotalDownPayment);
        $totalAmount = $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_amount ?? 0);
        $grossSales = $isSelectedEndDayClosed ? 0.0 : $totalAmount + $resolvedTotalDp;
        $netSales = $isSelectedEndDayClosed
            ? 0.0
            : max(0.0, $grossSales - (float) ($dashboardAggregate?->total_tax ?? 0) - (float) ($dashboardAggregate?->total_service_charge ?? 0));

        $recapHistories = RecapHistory::query()
            ->when($areaId, fn ($q) => $q->where('area_id', $areaId))
            ->latest('end_day')
            ->paginate(10)
            ->withQueryString();

        $dashboardPaymentMethodTotals = [
            'cash' => $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_cash ?? 0),
            'transfer' => $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_transfer ?? 0),
            'debit' => $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_debit ?? 0),
            'kredit' => $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_kredit ?? 0),
            'qris' => $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_qris ?? 0),
        ];

        $paymentMethodTotals = $dashboardPaymentMethodTotals;

        if (! $isSelectedEndDayClosed) {
            $livePaymentMethodSummary = $this->resolveLivePaymentMethodTotals($startAt, $endAt, $areaId);

            if ($livePaymentMethodSummary['has_paid_billings']) {
                foreach (['cash', 'transfer', 'debit', 'kredit', 'qris'] as $method) {
                    if (($paymentMethodTotals[$method] ?? 0) == 0 && ($livePaymentMethodSummary['totals'][$method] ?? 0) > 0) {
                        $paymentMethodTotals[$method] = $livePaymentMethodSummary['totals'][$method];
                    }
                }
            }
        }

        $kitchenItems = $isSelectedEndDayClosed
            ? collect()
            : KitchenOrderItem::query()
                ->with(['inventoryItem', 'kitchenOrder.order'])
                ->whereHas('kitchenOrder', function ($query) use ($startAt, $endAt, $areaId): void {
                    $query->whereBetween('created_at', [$startAt, $endAt])
                        ->when($areaId, fn ($q) => $q->where(fn ($sub) => $sub->where('area_id', $areaId)->orWhereHas('order.tableSession.table', fn ($t) => $t->where('area_id', $areaId))));
                })
                ->get()
                ->map(function (KitchenOrderItem $item): array {
                    $eventTime = $item->kitchenOrder?->order?->ordered_at
                        ?? $item->kitchenOrder?->created_at
                        ?? $item->created_at;

                    return [
                        'timestamp' => $eventTime,
                        'datetime' => $eventTime?->format('d/m/Y H:i') ?? '-',
                        'order_number' => $item->kitchenOrder?->order_number ?? '-',
                        'item_name' => $item->inventoryItem?->pos_name ?? $item->inventoryItem?->name ?? 'Unknown Item',
                        'qty' => (int) $item->quantity,
                    ];
                })
                ->sortBy(fn (array $event) => $event['timestamp'] ?? now())
                ->values();

        $barItems = $isSelectedEndDayClosed
            ? collect()
            : BarOrderItem::query()
                ->with(['inventoryItem', 'barOrder.order'])
                ->whereHas('barOrder', function ($query) use ($startAt, $endAt, $areaId): void {
                    $query->whereBetween('created_at', [$startAt, $endAt])
                        ->when($areaId, fn ($q) => $q->where(fn ($sub) => $sub->where('area_id', $areaId)->orWhereHas('order.tableSession.table', fn ($t) => $t->where('area_id', $areaId))));
                })
                ->get()
                ->map(function (BarOrderItem $item): array {
                    $eventTime = $item->barOrder?->order?->ordered_at
                        ?? $item->barOrder?->created_at
                        ?? $item->created_at;

                    return [
                        'timestamp' => $eventTime,
                        'datetime' => $eventTime?->format('d/m/Y H:i') ?? '-',
                        'order_number' => $item->barOrder?->order_number ?? '-',
                        'item_name' => $item->inventoryItem?->pos_name ?? $item->inventoryItem?->name ?? 'Unknown Item',
                        'qty' => (int) $item->quantity,
                    ];
                })
                ->sortBy(fn (array $event) => $event['timestamp'] ?? now())
                ->values();

        [$todayBillingTransactions, $todayWalkInTransactions] = $this->buildTodayTransactionsRecap();

        return [
            'selectedDate' => $startAt->toDateString(),
            'selectedStartDatetime' => $startAt->format('Y-m-d\TH:i'),
            'selectedEndDatetime' => $endAt->format('Y-m-d\TH:i'),
            'cashierTransactions' => $cashierTransactions,
            'cashierCount' => $isSelectedEndDayClosed ? 0 : (int) ($dashboardAggregate?->total_transactions ?? 0),
            'cashierRevenue' => $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_amount ?? 0),
            'grossSales' => $grossSales,
            'netSales' => $netSales,
            'totalPenjualanRokok' => $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_penjualan_rokok ?? 0),
            'totalFood' => $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_food ?? 0),
            'totalAlcohol' => $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_alcohol ?? 0),
            'totalBeverage' => $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_beverage ?? 0),
            'totalCigarette' => $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_cigarette ?? 0),
            'totalBreakage' => $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_breakage ?? 0),
            'totalRoom' => $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_room ?? 0),
            'totalStaffMeal' => $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_staff_meal ?? 0),
            'totalComplimentQuantity' => $dashboardTotalComplimentQuantity,
            'totalFocQuantity' => $dashboardTotalFocQuantity,
            'totalComplimentAmount' => $dashboardTotalComplimentAmount,
            'totalFocAmount' => $dashboardTotalFocAmount,
            'totalLd' => $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_ld ?? 0),
            'totalLdQuantity' => $dashboardTotalLdQuantity,
            'totalTax' => $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_tax ?? 0),
            'totalServiceCharge' => $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_service_charge ?? 0),
            'totalDiscount' => $totalDiscount,
            'totalDownPayment' => $resolvedTotalDp,
            'totalCash' => $isSelectedEndDayClosed ? 0.0 : (float) $paymentMethodTotals['cash'],
            'paymentMethodTotals' => $paymentMethodTotals,
            'kitchenItems' => $kitchenItems,
            'kitchenQtyTotal' => $isSelectedEndDayClosed ? 0 : (int) $kitchenItems->sum('qty'),
            'barItems' => $barItems,
            'barQtyTotal' => $isSelectedEndDayClosed ? 0 : (int) $barItems->sum('qty'),
            'rokokItems' => $rokokItems,
            'focItems' => $focItems,
            'todayBillingTransactions' => $todayBillingTransactions,
            'todayWalkInTransactions' => $todayWalkInTransactions,
            'dashboardPreview' => [
                'total_food' => $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_food ?? 0),
                'total_alcohol' => $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_alcohol ?? 0),
                'total_beverage' => $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_beverage ?? 0),
                'total_cigarette' => $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_cigarette ?? 0),
                'total_breakage' => $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_breakage ?? 0),
                'total_room' => $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_room ?? 0),
                'total_staff_meal' => $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_staff_meal ?? 0),
                'total_compliment_quantity' => $dashboardTotalComplimentQuantity,
                'total_foc_quantity' => $dashboardTotalFocQuantity,
                'total_compliment_amount' => $dashboardTotalComplimentAmount,
                'total_foc_amount' => $dashboardTotalFocAmount,
                'total_ld' => $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_ld ?? 0),
                'total_ld_quantity' => $dashboardTotalLdQuantity,
                'total_penjualan_rokok' => $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_penjualan_rokok ?? 0),
                'gross_sales' => $grossSales,
                'net_sales' => $netSales,
                'total_tax' => $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_tax ?? 0),
                'total_service_charge' => $isSelectedEndDayClosed ? 0.0 : (float) ($dashboardAggregate?->total_service_charge ?? 0),
                'total_discount' => $totalDiscount,
                'total_down_payment' => $resolvedTotalDp,
                'total_cash' => $isSelectedEndDayClosed ? 0.0 : (float) $paymentMethodTotals['cash'],
                'total_transfer' => $isSelectedEndDayClosed ? 0.0 : (float) $paymentMethodTotals['transfer'],
                'total_debit' => $isSelectedEndDayClosed ? 0.0 : (float) $paymentMethodTotals['debit'],
                'total_kredit' => $isSelectedEndDayClosed ? 0.0 : (float) $paymentMethodTotals['kredit'],
                'total_qris' => $isSelectedEndDayClosed ? 0.0 : (float) $paymentMethodTotals['qris'],
                'total_kitchen_items' => $isSelectedEndDayClosed ? 0 : (int) ($dashboardAggregate?->total_kitchen_items ?? 0),
                'total_bar_items' => $isSelectedEndDayClosed ? 0 : (int) ($dashboardAggregate?->total_bar_items ?? 0),
                'total_transactions' => $isSelectedEndDayClosed ? 0 : (int) ($dashboardAggregate?->total_transactions ?? 0),
            ],
            'recapHistories' => $recapHistories,
        ];
    }

    /**
     * @return array{has_paid_billings: bool, totals: array{cash: float, transfer: float, debit: float, kredit: float, qris: float}}
     */
    private function resolveLivePaymentMethodTotals(Carbon $startAt, Carbon $endAt, ?int $areaId = null): array
    {
        $paymentMethodTotals = [
            'cash' => 0.0,
            'transfer' => 0.0,
            'debit' => 0.0,
            'kredit' => 0.0,
            'qris' => 0.0,
        ];

        $paidBillings = Billing::query()
            ->where('billing_status', 'paid')
            ->when($areaId, fn ($q) => $q->where(fn ($sub) => $sub->where('area_id', $areaId)->orWhereHas('tableSession.table', fn ($t) => $t->where('area_id', $areaId))))
            ->where(function ($query): void {
                $query->where('is_booking', true)
                    ->orWhere('is_walk_in', true);
            })
            ->where(function ($query) use ($startAt, $endAt): void {
                $query->where(function ($paidAtQuery) use ($startAt, $endAt): void {
                    $paidAtQuery->whereNotNull('paid_at')
                        ->whereBetween('paid_at', [$startAt, $endAt]);
                })->orWhere(function ($fallbackQuery) use ($startAt, $endAt): void {
                    $fallbackQuery->whereNull('paid_at')
                        ->whereBetween('updated_at', [$startAt, $endAt]);
                });
            })
            ->get();

        foreach ($paidBillings as $billing) {
            $paidAmount = (float) ($billing->paid_amount ?? $billing->grand_total ?? 0);

            // FOC/Compliment tidak masuk bucket metode pembayaran (bukan revenue).
            if (in_array((string) ($billing->foc_comp_payment_method ?? ''), ['FOC', 'Compliment'], true)) {
                continue;
            }

            if (strtolower((string) ($billing->payment_mode ?? 'normal')) === 'split') {
                $paymentMethodTotals['cash'] += (float) ($billing->split_cash_amount ?? 0);

                $this->addPaymentMethodAmount(
                    $paymentMethodTotals,
                    $this->normalizePaymentMethod($billing->split_non_cash_method),
                    (float) ($billing->split_debit_amount ?? 0)
                );

                $this->addPaymentMethodAmount(
                    $paymentMethodTotals,
                    $this->normalizePaymentMethod($billing->split_second_non_cash_method),
                    (float) ($billing->split_second_non_cash_amount ?? 0)
                );

                continue;
            }

            if (strtolower((string) ($billing->payment_method ?? '')) === 'cash') {
                $paymentMethodTotals['cash'] += $paidAmount;

                continue;
            }

            $this->addPaymentMethodAmount(
                $paymentMethodTotals,
                $this->normalizePaymentMethod($billing->payment_method),
                $paidAmount
            );
        }

        $walkInOrders = Order::query()
            ->whereNull('table_session_id')
            ->where('status', '!=', 'cancelled')
            ->when($areaId, fn ($q) => $q->where('area_id', $areaId))
            ->where(function ($query) use ($startAt, $endAt): void {
                $query->whereBetween('ordered_at', [$startAt, $endAt])
                    ->orWhere(function ($fallbackQuery) use ($startAt, $endAt): void {
                        $fallbackQuery->whereNull('ordered_at')
                            ->whereBetween('created_at', [$startAt, $endAt]);
                    });
            })
            ->get(['id', 'payment_method', 'payment_mode', 'total']);

        $paidBillingOrderIds = Billing::query()
            ->where('billing_status', 'paid')
            ->when($areaId, fn ($q) => $q->where(fn ($sub) => $sub->where('area_id', $areaId)->orWhereHas('tableSession.table', fn ($t) => $t->where('area_id', $areaId))))
            ->whereIn('order_id', $walkInOrders->pluck('id')->filter()->values())
            ->pluck('order_id')
            ->filter()
            ->unique();

        $walkInOrdersWithoutPaidBilling = $walkInOrders
            ->reject(fn (Order $order): bool => $paidBillingOrderIds->contains($order->id));

        foreach ($walkInOrdersWithoutPaidBilling as $order) {
            $paidAmount = (float) ($order->total ?? 0);

            if (strtolower((string) ($order->payment_method ?? '')) === 'cash') {
                $paymentMethodTotals['cash'] += $paidAmount;

                continue;
            }

            $this->addPaymentMethodAmount(
                $paymentMethodTotals,
                $this->normalizePaymentMethod($order->payment_method),
                $paidAmount
            );
        }

        return [
            'has_paid_billings' => $paidBillings->isNotEmpty() || $walkInOrdersWithoutPaidBilling->isNotEmpty(),
            'totals' => $paymentMethodTotals,
        ];
    }

    private function resolveLiveTotalDownPayment(Carbon $startAt, Carbon $endAt, ?int $areaId = null): float
    {
        $bookingSessionIds = Billing::query()
            ->where('billing_status', 'paid')
            ->where('is_booking', true)
            ->whereNotNull('table_session_id')
            ->when($areaId, fn ($q) => $q->where(fn ($sub) => $sub->where('area_id', $areaId)->orWhereHas('tableSession.table', fn ($t) => $t->where('area_id', $areaId))))
            ->where(function ($query) use ($startAt, $endAt): void {
                $query->where(function ($paidAtQuery) use ($startAt, $endAt): void {
                    $paidAtQuery->whereNotNull('paid_at')
                        ->whereBetween('paid_at', [$startAt, $endAt]);
                })->orWhere(function ($fallbackQuery) use ($startAt, $endAt): void {
                    $fallbackQuery->whereNull('paid_at')
                        ->whereBetween('updated_at', [$startAt, $endAt]);
                });
            })
            ->pluck('table_session_id')
            ->filter()
            ->unique()
            ->values();

        if ($bookingSessionIds->isEmpty()) {
            return 0.0;
        }

        return (float) TableSession::query()
            ->with('reservation:id,down_payment_amount')
            ->whereIn('id', $bookingSessionIds->all())
            ->get()
            ->sum(fn (TableSession $session): float => (float) ($session->reservation?->down_payment_amount ?? 0));
    }

    /**
     * @return array{0: \Illuminate\Support\Collection<int, array<string, mixed>>, 1: \Illuminate\Support\Collection<int, array<string, mixed>>}
     */
    private function buildTodayTransactionsRecap(): array
    {
        [$todayStart, $todayEnd] = $this->resolveOperationalWindow();

        return [
            $this->buildTodayBillingTransactions($todayStart, $todayEnd),
            $this->buildTodayWalkInTransactions($todayStart, $todayEnd),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function buildTodayBillingTransactions(Carbon $todayStart, Carbon $todayEnd)
    {
        return Billing::query()
            ->with([
                'tableSession.customer.profile',
                'tableSession.reservation.customer.profile',
                'tableSession.orders.items.inventoryItem',
            ])
            ->where('billing_status', 'paid')
            ->where('is_booking', true)
            ->where(function ($query) use ($todayStart, $todayEnd): void {
                $query->where(function ($paidAtQuery) use ($todayStart, $todayEnd): void {
                    $paidAtQuery->whereNotNull('paid_at')
                        ->whereBetween('paid_at', [$todayStart, $todayEnd]);
                })->orWhere(function ($fallbackQuery) use ($todayStart, $todayEnd): void {
                    $fallbackQuery->whereNull('paid_at')
                        ->whereBetween('updated_at', [$todayStart, $todayEnd]);
                });
            })
            ->orderByDesc('paid_at')
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (Billing $billing): array {
                $tableSession = $billing->tableSession;
                $orderItems = $tableSession?->orders
                    ? $tableSession->orders
                        ->flatMap(fn (Order $order) => $order->items->where('status', '!=', 'cancelled')->values())
                        ->values()
                    : collect();
                $categoryFlags = $this->extractTransactionCategoryFlags($orderItems);

                $paidAt = $billing->paid_at ?? $billing->updated_at;
                $paymentReferenceNumber = $billing->payment_reference_number
                    ?? $billing->split_non_cash_reference_number
                    ?? $billing->split_second_non_cash_reference_number;
                $focCompPaymentMethod = (string) ($billing->foc_comp_payment_method ?? '');
                $downPaymentAmount = (float) ($tableSession?->reservation?->down_payment_amount ?? 0);
                $customerName = $tableSession?->reservation?->customer?->profile?->name
                    ?? $tableSession?->reservation?->customer?->name
                    ?? $tableSession?->customer?->profile?->name
                    ?? $tableSession?->customer?->name
                    ?? 'Customer Booking';
                $splitPayments = $this->resolveSplitPayments($billing);

                return [
                    'timestamp' => $paidAt,
                    'datetime' => $paidAt?->format('d/m/Y H:i') ?? '-',
                    'transaction_number' => 'BILLING-'.$billing->id,
                    'billing_id' => (int) $billing->id,
                    'billing_status' => (string) ($billing->billing_status ?? '-'),
                    'customer_name' => (string) $customerName,
                    'payment_mode' => (string) ($billing->payment_mode ?? 'normal'),
                    'payment_method_raw' => (string) ($billing->payment_method ?? ''),
                    'payment_method' => $this->formatPaymentMethod((string) ($billing->payment_method ?? ''), (string) ($billing->payment_mode ?? 'normal')).($focCompPaymentMethod !== '' ? ' / '.$focCompPaymentMethod : ''),
                    'payment_method_key' => $this->resolvePaymentFilterKey((string) ($billing->payment_method ?? ''), (string) ($billing->payment_mode ?? 'normal')),
                    'foc_comp_payment_method' => $focCompPaymentMethod,
                    'payment_reference_number' => (string) ($paymentReferenceNumber ?? ''),
                    'split_payments' => $splitPayments,
                    'items_count' => (int) $orderItems->count(),
                    'total_bill' => (float) max((float) ($billing->minimum_charge ?? 0), (float) ($billing->orders_total ?? 0)),
                    'tax_total' => (float) ($billing->tax ?? 0),
                    'service_charge_total' => (float) ($billing->service_charge ?? 0),
                    'discount_amount' => (float) ($billing->discount_amount ?? 0),
                    'down_payment_amount' => $downPaymentAmount,
                    'has_down_payment' => $downPaymentAmount > 0,
                    'contains_food' => $categoryFlags['food'],
                    'contains_alcohol' => $categoryFlags['alcohol'],
                    'contains_beverage' => $categoryFlags['beverage'],
                    'contains_cigarette' => $categoryFlags['cigarette'],
                    'contains_breakage' => $categoryFlags['breakage'],
                    'contains_room' => $categoryFlags['room'],
                    'contains_staff_meal' => $categoryFlags['staff_meal'],
                    'contains_compliment' => $categoryFlags['compliment'],
                    'contains_foc' => $categoryFlags['foc'],
                    'contains_ld' => $categoryFlags['ld'],
                    'total' => (float) ($billing->paid_amount ?? $billing->grand_total ?? 0),
                    'items' => $orderItems
                        ->map(fn ($item): array => [
                            'name' => (string) ($item->inventoryItem?->pos_name ?? $item->inventoryItem?->name ?? $item->item_name ?? '-'),
                            'category_main' => (string) ($item->inventoryItem?->category_main ?? ''),
                            'quantity' => (int) ($item->quantity ?? 0),
                            'price' => (float) ($item->price ?? 0),
                            'subtotal' => (float) ($item->subtotal ?? 0),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function buildTodayWalkInTransactions(Carbon $todayStart, Carbon $todayEnd)
    {
        $walkInOrders = Order::query()
            ->with(['items.inventoryItem', 'customer.user'])
            ->whereNull('table_session_id')
            ->where('status', '!=', 'cancelled')
            ->where(function ($query) use ($todayStart, $todayEnd): void {
                $query->whereBetween('ordered_at', [$todayStart, $todayEnd])
                    ->orWhere(function ($fallbackQuery) use ($todayStart, $todayEnd): void {
                        $fallbackQuery->whereNull('ordered_at')
                            ->whereBetween('created_at', [$todayStart, $todayEnd]);
                    });
            })
            ->orderByRaw('COALESCE(ordered_at, created_at) DESC')
            ->get();

        $billingsByOrderId = Billing::query()
            ->whereIn('order_id', $walkInOrders->pluck('id')->filter()->values())
            ->where('billing_status', 'paid')
            ->get()
            ->keyBy('order_id');

        return $walkInOrders
            ->map(function (Order $order) use ($billingsByOrderId): array {
                $billing = $billingsByOrderId->get($order->id);
                $eventTime = $order->ordered_at ?? $order->created_at;
                $orderItems = $order->items->where('status', '!=', 'cancelled')->values();
                $categoryFlags = $this->extractTransactionCategoryFlags($orderItems);
                $paymentMethod = (string) ($billing?->payment_method ?? $order->payment_method ?? '');
                $paymentMode = (string) ($billing?->payment_mode ?? $order->payment_mode ?? 'normal');
                $focCompPaymentMethod = (string) ($billing?->foc_comp_payment_method ?? '');
                $paymentReferenceNumber = $billing?->payment_reference_number
                    ?? $billing?->split_non_cash_reference_number
                    ?? $billing?->split_second_non_cash_reference_number
                    ?? $order->payment_reference_number;
                $splitPayments = $this->resolveSplitPayments($billing);

                return [
                    'timestamp' => $eventTime,
                    'datetime' => $eventTime?->format('d/m/Y H:i') ?? '-',
                    'transaction_number' => (string) ($order->order_number ?? ($billing?->transaction_code ?? ('WALKIN-'.$order->id))),
                    'billing_id' => (int) ($billing?->id ?? 0),
                    'billing_status' => (string) ($billing?->billing_status ?? '-'),
                    'customer_name' => (string) ($order->customer?->user?->name ?? 'Walk-in'),
                    'payment_mode' => $paymentMode,
                    'payment_method_raw' => $paymentMethod,
                    'payment_method' => $this->formatPaymentMethod($paymentMethod, $paymentMode).($focCompPaymentMethod !== '' ? ' / '.$focCompPaymentMethod : ''),
                    'payment_method_key' => $this->resolvePaymentFilterKey($paymentMethod, $paymentMode),
                    'foc_comp_payment_method' => $focCompPaymentMethod,
                    'payment_reference_number' => (string) ($paymentReferenceNumber ?? ''),
                    'split_payments' => $splitPayments,
                    'items_count' => (int) $orderItems->count(),
                    'total_bill' => (float) ($billing?->orders_total ?? $orderItems->sum(fn ($item) => (float) ($item->subtotal ?? 0))),
                    'tax_total' => (float) ($billing?->tax ?? $orderItems->sum(fn ($item) => (float) ($item->tax_amount ?? 0))),
                    'service_charge_total' => (float) ($billing?->service_charge ?? $orderItems->sum(fn ($item) => (float) ($item->service_charge_amount ?? 0))),
                    'discount_amount' => (float) ($billing?->discount_amount ?? $order->discount_amount ?? 0),
                    'down_payment_amount' => 0.0,
                    'has_down_payment' => false,
                    'contains_food' => $categoryFlags['food'],
                    'contains_alcohol' => $categoryFlags['alcohol'],
                    'contains_beverage' => $categoryFlags['beverage'],
                    'contains_cigarette' => $categoryFlags['cigarette'],
                    'contains_breakage' => $categoryFlags['breakage'],
                    'contains_room' => $categoryFlags['room'],
                    'contains_staff_meal' => $categoryFlags['staff_meal'],
                    'contains_compliment' => $categoryFlags['compliment'],
                    'contains_foc' => $categoryFlags['foc'],
                    'contains_ld' => $categoryFlags['ld'],
                    'total' => (float) ($billing?->paid_amount ?? $billing?->grand_total ?? $order->total ?? 0),
                    'items' => $orderItems
                        ->map(fn ($item): array => [
                            'name' => (string) ($item->inventoryItem?->pos_name ?? $item->inventoryItem?->name ?? $item->item_name ?? '-'),
                            'category_main' => (string) ($item->inventoryItem?->category_main ?? ''),
                            'quantity' => (int) ($item->quantity ?? 0),
                            'price' => (float) ($item->price ?? 0),
                            'subtotal' => (float) ($item->subtotal ?? 0),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->filter(function (array $transaction): bool {
                return $transaction['items_count'] > 0 || (float) $transaction['total'] > 0;
            })
            ->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $orderItems
     * @return array{food: bool, alcohol: bool, beverage: bool, cigarette: bool, breakage: bool, room: bool, staff_meal: bool, compliment: bool, foc: bool, ld: bool}
     */
    private function extractTransactionCategoryFlags($orderItems): array
    {
        $categories = $orderItems
            ->map(fn ($item): string => $this->normalizeTransactionCategoryMain((string) ($item->inventoryItem?->category_main ?? '')))
            ->filter()
            ->unique()
            ->values();

        return [
            'food' => $categories->contains('food'),
            'alcohol' => $categories->contains('alcohol'),
            'beverage' => $categories->contains('beverage'),
            'cigarette' => $categories->contains('cigarette'),
            'breakage' => $categories->contains('breakage'),
            'room' => $categories->contains('room'),
            'staff_meal' => $categories->contains('staff meal'),
            'compliment' => $categories->contains('compliment'),
            'foc' => $categories->contains('foc'),
            'ld' => $categories->contains('ld'),
        ];
    }

    private function normalizeTransactionCategoryMain(string $categoryMain): string
    {
        $normalized = strtolower(trim($categoryMain));
        $normalized = str_replace(['_', '-'], ' ', $normalized);

        return match ($normalized) {
            'staff meal', 'staffmeal', 'staff meal menu', 'meal staff' => 'staff meal',
            'break age' => 'breakage',
            default => $normalized,
        };
    }

    private function resolvePaymentFilterKey(?string $paymentMethod, ?string $paymentMode): string
    {
        if (strtolower((string) $paymentMode) === 'split') {
            return 'split';
        }

        $normalizedPaymentMethod = strtolower(trim((string) $paymentMethod));

        return match ($normalizedPaymentMethod) {
            'cash' => 'cash',
            'transfer' => 'transfer',
            'debit', 'debit-card' => 'debit',
            'kredit', 'credit-card' => 'kredit',
            'qris' => 'qris',
            default => 'other',
        };
    }

    /**
     * @return array<int, array{label: string, method: string, reference_number: string, amount: float}>
     */
    private function resolveSplitPayments(?Billing $billing): array
    {
        if (! $billing || strtolower((string) ($billing->payment_mode ?? 'normal')) !== 'split') {
            return [];
        }

        $payments = [
            [
                'label' => 'Cash',
                'method' => 'Cash',
                'reference_number' => '',
                'amount' => (float) ($billing->split_cash_amount ?? 0),
            ],
            [
                'label' => 'Non-Cash',
                'method' => (string) ($billing->split_non_cash_method ?? '-'),
                'reference_number' => (string) ($billing->split_non_cash_reference_number ?? ''),
                'amount' => (float) ($billing->split_debit_amount ?? 0),
            ],
            [
                'label' => 'Second Non-Cash',
                'method' => (string) ($billing->split_second_non_cash_method ?? '-'),
                'reference_number' => (string) ($billing->split_second_non_cash_reference_number ?? ''),
                'amount' => (float) ($billing->split_second_non_cash_amount ?? 0),
            ],
        ];

        return collect($payments)
            ->filter(fn (array $payment): bool => $payment['amount'] > 0 || filled($payment['method']) || filled($payment['reference_number']))
            ->values()
            ->all();
    }

    private function resolveEndDayPrinter(?int $areaId = null): ?Printer
    {
        $settings = GeneralSetting::instance();
        $sessionArea = session('active_area_id') && session('active_area_id') !== 'all' ? (int) session('active_area_id') : null;
        $contextAreaId = $areaId ?: ($sessionArea ?: auth()->user()?->getAssignedArea()?->id);
        $configuredPrinterId = $settings->getPrinterIdForArea($contextAreaId, 'end_day_receipt');

        if ($configuredPrinterId && $configuredPrinterId > 0) {
            $configuredPrinter = Printer::active()->where('id', $configuredPrinterId)->first();

            if ($configuredPrinter) {
                return $configuredPrinter;
            }
        }

        return Printer::active()->byType('cashier')->first()
            ?? Printer::active()->default()->first()
            ?? Printer::active()->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRecapDataFromHistory(RecapHistory $recapHistory): array
    {
        if (! $recapHistory->end_day) {
            return [
                'selectedStartDatetime' => '-',
                'selectedEndDatetime' => '-',
                'cashierCount' => 0,
                'cashierRevenue' => 0.0,
                'totalPenjualanRokok' => 0.0,
                'totalTax' => 0.0,
                'totalServiceCharge' => 0.0,
                'totalDiscount' => 0.0,
                'totalDownPayment' => 0.0,
                'paymentMethodTotals' => [
                    'cash' => 0.0,
                    'transfer' => 0.0,
                    'debit' => 0.0,
                    'kredit' => 0.0,
                    'qris' => 0.0,
                ],
                'rokokItems' => [],
                'focItems' => $liveRecapData['focItems'] ?? [],
                'kitchenQtyTotal' => 0,
                'barQtyTotal' => 0,
                'dashboardPreview' => [
                    'total_food' => 0.0,
                    'total_alcohol' => 0.0,
                    'total_beverage' => 0.0,
                    'total_cigarette' => 0.0,
                    'total_breakage' => 0.0,
                    'total_room' => 0.0,
                    'total_staff_meal' => 0.0,
                    'total_compliment_quantity' => 0,
                    'total_foc_quantity' => 0,
                    'total_ld' => 0.0,
                    'total_ld_quantity' => 0,
                    'total_penjualan_rokok' => 0.0,
                    'total_kitchen_items' => 0,
                    'total_bar_items' => 0,
                ],
                'cashierTransactions' => [],
            ];
        }

        [$startAt, $endAt] = $this->resolveHistoryTransactionWindow($recapHistory);
        $liveRecapData = $this->buildRecapData($startAt, $endAt, true);
        $historyBillingTransactions = $this->buildTodayBillingTransactions($startAt, $endAt);
        $historyWalkInTransactions = $this->buildTodayWalkInTransactions($startAt, $endAt);
        $historyTotalDp = (float) ($recapHistory->total_dp ?? 0);
        $historyGrossSales = (float) $recapHistory->total_amount + $historyTotalDp;
        $historyNetSales = max(0.0, $historyGrossSales - (float) $recapHistory->total_tax - (float) $recapHistory->total_service_charge);

        return array_merge($liveRecapData, [
            'selectedDate' => $recapHistory->end_day->toDateString(),
            'selectedStartDatetime' => $startAt->format('d/m/Y H:i'),
            'selectedEndDatetime' => $endAt->format('d/m/Y H:i'),
            'cashierCount' => (int) $recapHistory->total_transactions,
            'cashierRevenue' => (float) $recapHistory->total_amount,
            'grossSales' => $historyGrossSales,
            'netSales' => $historyNetSales,
            'totalPenjualanRokok' => (float) $recapHistory->total_penjualan_rokok,
            'totalFood' => (float) ($recapHistory->total_food ?? 0),
            'totalAlcohol' => (float) ($recapHistory->total_alcohol ?? 0),
            'totalBeverage' => (float) ($recapHistory->total_beverage ?? 0),
            'totalCigarette' => (float) ($recapHistory->total_cigarette ?? 0),
            'totalBreakage' => (float) ($recapHistory->total_breakage ?? 0),
            'totalRoom' => (float) ($recapHistory->total_room ?? 0),
            'totalStaffMeal' => (float) ($recapHistory->total_staff_meal ?? 0),
            'totalComplimentQuantity' => (int) ($recapHistory->total_compliment_quantity ?? 0),
            'totalFocQuantity' => (int) ($recapHistory->total_foc_quantity ?? 0),
            'totalComplimentAmount' => (float) ($recapHistory->total_compliment_amount ?? 0),
            'totalFocAmount' => (float) ($recapHistory->total_foc_amount ?? 0),
            'totalLd' => (float) ($recapHistory->total_ld ?? 0),
            'totalTax' => (float) $recapHistory->total_tax,
            'totalServiceCharge' => (float) $recapHistory->total_service_charge,
            'totalDiscount' => (float) ($liveRecapData['totalDiscount'] ?? 0),
            'totalDownPayment' => $historyTotalDp,
            'paymentMethodTotals' => [
                'cash' => (float) $recapHistory->total_cash,
                'transfer' => (float) $recapHistory->total_transfer,
                'debit' => (float) $recapHistory->total_debit,
                'kredit' => (float) $recapHistory->total_kredit,
                'qris' => (float) $recapHistory->total_qris,
            ],
            'rokokItems' => $liveRecapData['rokokItems'] ?? [],
            'focItems' => $liveRecapData['focItems'] ?? [],
            'kitchenQtyTotal' => (int) $recapHistory->total_kitchen_items,
            'barQtyTotal' => (int) $recapHistory->total_bar_items,
            'todayBillingTransactions' => $historyBillingTransactions,
            'todayWalkInTransactions' => $historyWalkInTransactions,
            'dashboardPreview' => [
                'total_food' => (float) ($recapHistory->total_food ?? 0),
                'total_alcohol' => (float) ($recapHistory->total_alcohol ?? 0),
                'total_beverage' => (float) ($recapHistory->total_beverage ?? 0),
                'total_cigarette' => (float) ($recapHistory->total_cigarette ?? 0),
                'total_breakage' => (float) ($recapHistory->total_breakage ?? 0),
                'total_room' => (float) ($recapHistory->total_room ?? 0),
                'total_staff_meal' => (float) ($recapHistory->total_staff_meal ?? 0),
                'total_compliment_quantity' => (int) ($recapHistory->total_compliment_quantity ?? 0),
                'total_foc_quantity' => (int) ($recapHistory->total_foc_quantity ?? 0),
                'total_compliment_amount' => (float) ($recapHistory->total_compliment_amount ?? 0),
                'total_foc_amount' => (float) ($recapHistory->total_foc_amount ?? 0),
                'total_ld' => (float) ($recapHistory->total_ld ?? 0),
                'total_penjualan_rokok' => (float) $recapHistory->total_penjualan_rokok,
                'gross_sales' => $historyGrossSales,
                'net_sales' => $historyNetSales,
                'total_tax' => (float) $recapHistory->total_tax,
                'total_service_charge' => (float) $recapHistory->total_service_charge,
                'total_discount' => (float) ($liveRecapData['totalDiscount'] ?? 0),
                'total_down_payment' => $historyTotalDp,
                'total_cash' => (float) $recapHistory->total_cash,
                'total_transfer' => (float) $recapHistory->total_transfer,
                'total_debit' => (float) $recapHistory->total_debit,
                'total_kredit' => (float) $recapHistory->total_kredit,
                'total_qris' => (float) $recapHistory->total_qris,
                'total_kitchen_items' => (int) $recapHistory->total_kitchen_items,
                'total_bar_items' => (int) $recapHistory->total_bar_items,
                'total_transactions' => (int) $recapHistory->total_transactions,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $recapData
     * @return array<int, array<int, string|int|float>>
     */
    private function buildLiveRecapExportRows(array $recapData): array
    {
        $rows = [
            ['Rekapan End Day'],
            ['Rentang', $recapData['selectedStartDatetime'].' - '.$recapData['selectedEndDatetime']],
            [],
            ['Ringkasan'],
            ['Transaksi Kasir', $recapData['cashierCount']],
            ['Total Penjualan Kasir', $recapData['cashierRevenue']],
            ['Total Food', (float) ($recapData['dashboardPreview']['total_food'] ?? 0)],
            ['Total Alcohol', (float) ($recapData['dashboardPreview']['total_alcohol'] ?? 0)],
            ['Total Beverage', (float) ($recapData['dashboardPreview']['total_beverage'] ?? 0)],
            ['Total Cigarette', (float) ($recapData['dashboardPreview']['total_cigarette'] ?? 0)],
            ['Total Breakage', (float) ($recapData['dashboardPreview']['total_breakage'] ?? 0)],
            ['Total Room', (float) ($recapData['dashboardPreview']['total_room'] ?? 0)],
            ['Total Staff Meal', (float) ($recapData['dashboardPreview']['total_staff_meal'] ?? 0)],
            ['Gross Sales', (float) ($recapData['dashboardPreview']['gross_sales'] ?? 0)],
            ['Net Sales', (float) ($recapData['dashboardPreview']['net_sales'] ?? 0)],
            ['Total Compliment (Qty)', (int) ($recapData['dashboardPreview']['total_compliment_quantity'] ?? 0)],
            ['Total FOC (Qty)', (int) ($recapData['dashboardPreview']['total_foc_quantity'] ?? 0)],
            ['Total LD', (float) ($recapData['dashboardPreview']['total_ld'] ?? 0)],
            ['Total LD Qty', (int) ($recapData['dashboardPreview']['total_ld_quantity'] ?? 0)],
            ['Total Pajak', $recapData['totalTax']],
            ['Total Service Charge', $recapData['totalServiceCharge']],
            ['Total DP (Booking)', (float) ($recapData['dashboardPreview']['total_down_payment'] ?? 0)],
            ['Total Pembayaran Tunai', $recapData['paymentMethodTotals']['cash']],
            ['Total Pembayaran Transfer', $recapData['paymentMethodTotals']['transfer']],
            ['Total Pembayaran Debit', $recapData['paymentMethodTotals']['debit']],
            ['Total Pembayaran Kredit', $recapData['paymentMethodTotals']['kredit']],
            ['Total Pembayaran QRIS', $recapData['paymentMethodTotals']['qris']],
            ['Item Keluar Kitchen', $recapData['kitchenQtyTotal']],
            ['Item Keluar Bar', $recapData['barQtyTotal']],
            [],
            ['Kasir (Harga Ditampilkan)'],
            ['Tanggal & Jam', 'No. Transaksi', 'Customer', 'Metode Pembayaran', 'Qty Item', 'Total'],
        ];

        foreach ($recapData['cashierTransactions'] as $transaction) {
            $rows[] = [
                $transaction['datetime'],
                $transaction['order_number'],
                $transaction['customer_name'],
                $transaction['payment_method'],
                $transaction['items_count'],
                $transaction['total'],
            ];
        }

        $rows[] = [];
        $rows[] = ['Item Keluar Kitchen'];
        $rows[] = ['Tanggal & Jam', 'Order', 'Item', 'Qty'];
        foreach ($recapData['kitchenItems'] as $item) {
            $rows[] = [
                $item['datetime'],
                $item['order_number'],
                $item['item_name'],
                $item['qty'],
            ];
        }

        $rows[] = [];
        $rows[] = ['Item Keluar Bar'];
        $rows[] = ['Tanggal & Jam', 'Order', 'Item', 'Qty'];
        foreach ($recapData['barItems'] as $item) {
            $rows[] = [
                $item['datetime'],
                $item['order_number'],
                $item['item_name'],
                $item['qty'],
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, string|int|float>>
     */
    private function buildHistoryExportRows(RecapHistory $recapHistory): array
    {
        return [
            ['History Rekapan End Day'],
            ['Tanggal End Day', $recapHistory->end_day?->format('d/m/Y') ?? '-'],
            ['Last Sync', $recapHistory->last_synced_at?->format('d/m/Y H:i') ?? '-'],
            [],
            ['Ringkasan Snapshot'],
            ['Transaksi Kasir', (int) $recapHistory->total_transactions],
            ['Total Penjualan Kasir', (float) $recapHistory->total_amount],
            ['Total Food', (float) ($recapHistory->total_food ?? 0)],
            ['Total Alcohol', (float) ($recapHistory->total_alcohol ?? 0)],
            ['Total Beverage', (float) ($recapHistory->total_beverage ?? 0)],
            ['Total Cigarette', (float) ($recapHistory->total_cigarette ?? 0)],
            ['Total Breakage', (float) ($recapHistory->total_breakage ?? 0)],
            ['Total Room', (float) ($recapHistory->total_room ?? 0)],
            ['Total Staff Meal', (float) ($recapHistory->total_staff_meal ?? 0)],
            ['Gross Sales', (float) $recapHistory->total_amount],
            ['Net Sales', max(0.0, (float) $recapHistory->total_amount - (float) $recapHistory->total_tax - (float) $recapHistory->total_service_charge)],
            ['Total Compliment (Qty)', (int) ($recapHistory->total_compliment_quantity ?? 0)],
            ['Total FOC (Qty)', (int) ($recapHistory->total_foc_quantity ?? 0)],
            ['Total LD', (float) ($recapHistory->total_ld ?? 0)],
            ['Total LD Qty', (int) ($recapHistory->total_ld_quantity ?? 0)],
            ['Total Penjualan Rokok', (float) $recapHistory->total_penjualan_rokok],
            ['Total Pajak', (float) $recapHistory->total_tax],
            ['Total Service Charge', (float) $recapHistory->total_service_charge],
            ['Total DP (Booking)', (float) ($recapHistory->total_dp ?? 0)],
            ['Total Pembayaran Tunai', (float) $recapHistory->total_cash],
            ['Total Pembayaran Transfer', (float) $recapHistory->total_transfer],
            ['Total Pembayaran Debit', (float) $recapHistory->total_debit],
            ['Total Pembayaran Kredit', (float) $recapHistory->total_kredit],
            ['Total Pembayaran QRIS', (float) $recapHistory->total_qris],
            ['Item Keluar Kitchen', (int) $recapHistory->total_kitchen_items],
            ['Item Keluar Bar', (int) $recapHistory->total_bar_items],
        ];
    }

    /**
     * @param  array<int, array<int, string|int|float>>  $rows
     */
    private function downloadRows(array $rows, string $filename): BinaryFileResponse
    {
        $export = new class($rows) implements FromArray
        {
            public function __construct(private array $rows) {}

            public function array(): array
            {
                return $this->rows;
            }
        };

        return Excel::download($export, $filename);
    }

    /**
     * @return array{service_charge: float, tax: float}
     */
    private function calculateOrderChargeTotals(Order $order, GeneralSetting $settings): array
    {
        $serviceChargeBase = 0.0;
        $taxBase = 0.0;
        $taxAndServiceBase = 0.0;

        $orderItems = $order->items
            ->where('status', '!=', 'cancelled')
            ->values();

        $itemsSubtotal = (float) $orderItems->sum(fn ($item) => (float) ($item->subtotal ?? 0));
        $orderNetTotal = max((float) ($order->total ?? 0), 0);
        $ratio = $itemsSubtotal > 0 ? $orderNetTotal / $itemsSubtotal : 0;

        foreach ($orderItems as $orderItem) {
            $itemNetSubtotal = (float) ($orderItem->subtotal ?? 0) * $ratio;
            $includeTax = (bool) ($orderItem->inventoryItem?->include_tax ?? true);
            $includeServiceCharge = (bool) ($orderItem->inventoryItem?->include_service_charge ?? true);

            if ($includeServiceCharge) {
                $serviceChargeBase += $itemNetSubtotal;
            }

            if ($includeTax) {
                $taxBase += $itemNetSubtotal;
            }

            if ($includeTax && $includeServiceCharge) {
                $taxAndServiceBase += $itemNetSubtotal;
            }
        }

        $serviceCharge = round($serviceChargeBase * (((float) $settings->service_charge_percentage) / 100), 2);
        $serviceChargeTaxableAmount = round($taxAndServiceBase * (((float) $settings->service_charge_percentage) / 100), 2);
        $tax = round(($taxBase + $serviceChargeTaxableAmount) * (((float) $settings->tax_percentage) / 100), 2);

        return [
            'service_charge' => $serviceCharge,
            'tax' => $tax,
        ];
    }

    private function formatPaymentMethod(?string $paymentMethod, ?string $paymentMode): string
    {
        if ($paymentMode === 'split') {
            return 'Split Bill';
        }

        return match (strtolower((string) $paymentMethod)) {
            'cash' => 'Tunai',
            'debit' => 'Debit',
            'kredit' => 'Kredit',
            'transfer' => 'Transfer',
            'qris' => 'QRIS',
            'credit-card' => 'Kredit',
            'debit-card' => 'Debit',
            default => filled($paymentMethod) ? strtoupper((string) $paymentMethod) : '-',
        };
    }

    private function normalizePaymentMethod(?string $paymentMethod): ?string
    {
        return match (strtolower(trim((string) $paymentMethod))) {
            'debit', 'debit-card' => 'debit',
            'kredit', 'credit-card' => 'kredit',
            'transfer' => 'transfer',
            'qris' => 'qris',
            default => null,
        };
    }

    /**
     * @param  array{cash: float, transfer: float, debit: float, kredit: float, qris: float}  $paymentMethodTotals
     */
    private function addPaymentMethodAmount(array &$paymentMethodTotals, ?string $method, float $amount): void
    {
        if ($method === null || ! array_key_exists($method, $paymentMethodTotals)) {
            return;
        }

        $paymentMethodTotals[$method] += $amount;
    }

    /**
     * @param  array{date?: string|null, start_datetime?: string|null, end_datetime?: string|null}  $validated
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(array $validated, ?int $areaId = null): array
    {
        if (! empty($validated['start_datetime']) && ! empty($validated['end_datetime'])) {
            return [
                Carbon::parse($validated['start_datetime'])->seconds(0),
                Carbon::parse($validated['end_datetime'])->seconds(59),
            ];
        }

        if (! empty($validated['date'])) {
            return $this->resolveEndDayWindow(Carbon::parse($validated['date'], 'Asia/Jakarta'), $areaId);
        }

        return $this->resolveOperationalWindow($areaId);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveOperationalWindow(?int $areaId = null): array
    {
        return RecapHistory::resolveActiveWindow($areaId);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveEndDayWindow(Carbon $endDay, ?int $areaId = null): array
    {
        return RecapHistory::resolveWindowForDate($endDay, $areaId);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveHistoryTransactionWindow(RecapHistory $recapHistory): array
    {
        [$startAt, $endAt] = $this->resolveEndDayWindow($recapHistory->end_day?->copy() ?? now('Asia/Jakarta'), $recapHistory->area_id);

        if ($recapHistory->last_synced_at) {
            return [
                $startAt,
                $recapHistory->last_synced_at->copy()->timezone('Asia/Jakarta'),
            ];
        }

        return [$startAt, $endAt];
    }

    private function isSelectedEndDayClosed(Carbon $startAt, ?int $areaId = null): bool
    {
        $selectedEndDay = $startAt->copy()->timezone('Asia/Jakarta')->toDateString();

        return RecapHistory::query()
            ->when($areaId, fn ($q) => $q->where('area_id', $areaId), fn ($q) => $q->whereNull('area_id'))
            ->whereDate('end_day', $selectedEndDay)
            ->exists();
    }
}
