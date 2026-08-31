<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Billing;
use App\Models\BillingPayment;
use App\Models\CustomerUser;
use App\Models\GeneralSetting;
use App\Models\Order;
use App\Models\Printer;
use App\Models\TableReservation;
use App\Services\AccurateService;
use App\Services\PrinterService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TransactionHistoryController extends Controller
{
    public function __construct(
        protected PrinterService $printerService,
        protected AccurateService $accurateService
    ) {}

    public function index(Request $request): \Illuminate\View\View|\Illuminate\Http\Response
    {
        $user = auth()->user();
        $areas = $user ? $user->getAccessibleAreas() : Area::where('is_active', true)->orderBy('sort_order')->get();
        $selectedAreaId = $user ? $user->resolveActiveAreaId($request->input('area_id'), $request->has('area_id')) : ($request->filled('area_id')
            ? ($request->input('area_id') === 'all' ? null : (int) $request->input('area_id'))
            : (session('active_area_id') && session('active_area_id') !== 'all' ? (int) session('active_area_id') : null));

        $transactionMode = $request->get('transaction_mode') === 'walk_in' ? 'walk_in' : 'all';
        $dateFrom = $request->filled('date_from') ? $request->date('date_from')->toDateString() : null;
        $dateTo = $request->filled('date_to') ? $request->date('date_to')->toDateString() : null;

        $areaFilter = fn ($q) => $q->when(
            $selectedAreaId,
            fn ($sq) => $sq->where(
                fn ($sub) => $sub
                    ->where('area_id', $selectedAreaId)
                    ->orWhereHas('tableSession.table', fn ($t) => $t->where('area_id', $selectedAreaId))
            )
        );

        $query = Order::with([
            'items.inventoryItem.printers',
            'tableSession.table',
            'tableSession.reservation',
            'tableSession.billing.payments',
            'tableSession.customer.profile',
            'customer.user.profile',
            'billing.payments',
            'customer.user',
        ])
            ->whereNotIn('status', ['cancelled'])
            ->tap($areaFilter);

        if ($transactionMode === 'walk_in') {
            $query->whereNull('table_session_id');
        }

        if ($dateFrom) {
            $query->whereDate('ordered_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('ordered_at', '<=', $dateTo);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('order_number', 'like', '%'.$request->search.'%')
                    ->orWhereHas('tableSession.customer', function ($q2) use ($request) {
                        $q2->where('name', 'like', '%'.$request->search.'%');
                    })
                    ->orWhereHas('customer.user', function ($q2) use ($request) {
                        $q2->where('name', 'like', '%'.$request->search.'%');
                    });
            });
        }

        $perPage = in_array((int) $request->get('per_page'), [10, 25, 50, 100]) ? (int) $request->get('per_page') : 25;
        $orders = $query->latest('ordered_at')->paginate($perPage)->withQueryString();

        $orders->getCollection()->transform(function (Order $order) {
            $assignedPrinterTypes = $this->resolveOrderAssignedPrinterTypes($order);
            $hasKitchenItems = $assignedPrinterTypes->contains('kitchen');
            $hasBarItems = $assignedPrinterTypes->contains('bar');
            $hasCheckerItems = $assignedPrinterTypes->contains('checker');

            $order->setAttribute('print_types', [
                'resmi' => true,
                'kitchen' => $hasKitchenItems,
                'bar' => $hasBarItems,
                'checker' => $hasCheckerItems,
            ]);

            $order->setAttribute('print_counts', [
                'resmi' => (int) ($order->receipt_print_count ?? 0),
                'kitchen' => (int) ($order->kitchen_print_count ?? 0),
                'bar' => (int) ($order->bar_print_count ?? 0),
                'checker' => (int) ($order->checker_print_count ?? 0),
            ]);

            return $order;
        });

        $orderPrintPayloads = $this->buildPrintPayloads($orders->getCollection());
        $orderDetailPayloads = $this->buildDetailPayloads($orders->getCollection());

        $statsQuery = Order::query()->whereNotIn('status', ['cancelled'])->tap($areaFilter)
            // FOC/Compliment bukan revenue — exclude order dengan billing FOC.
            ->whereDoesntHave('billing', function ($query): void {
                $query->whereIn('foc_comp_payment_method', ['FOC', 'Compliment']);
            });

        if ($transactionMode === 'walk_in') {
            $statsQuery->whereNull('table_session_id');
        }

        if ($dateFrom) {
            $statsQuery->whereDate('ordered_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $statsQuery->whereDate('ordered_at', '<=', $dateTo);
        }

        $totalOrders = (clone $statsQuery)->count();
        $todayOrders = (clone $statsQuery)
            ->whereDate('ordered_at', today())
            ->count();
        $todayRevenue = (clone $statsQuery)
            ->whereDate('ordered_at', today())
            ->sum('total');
        $totalRevenue = (clone $statsQuery)->sum('total');
        $averageOrderTotal = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
        $todayBookingDownPayment = (float) TableReservation::query()
            ->whereDate('reservation_date', today())
            ->whereNotIn('status', ['cancelled', 'rejected', 'force_closed'])
            ->when($selectedAreaId, fn ($q) => $q->whereHas('table', fn ($t) => $t->where('area_id', $selectedAreaId)))
            ->sum('down_payment_amount');

        $activePrinters = Printer::active()->get(['id', 'name', 'location', 'printer_type']);

        $printerLocations = $activePrinters
            ->pluck('location')
            ->filter()
            ->map(fn ($location) => strtolower(trim((string) $location)))
            ->unique()
            ->values()
            ->toArray();

        $hasAnyActivePrinter = $activePrinters->isNotEmpty();

        $activePrinterOptions = $activePrinters
            ->map(fn (Printer $printer): array => [
                'id' => (int) $printer->id,
                'name' => (string) $printer->name,
                'location' => (string) ($printer->location ?? '-'),
                'printer_type' => (string) ($printer->printer_type ?? '-'),
            ])
            ->values()
            ->toArray();

        $viewName = $transactionMode === 'walk_in' ? 'transaction-history.walk-in.index' : 'transaction-history.index';

        if ($request->headers->get('X-Live')) {
            return response(
                view('transaction-history._partials.stats', compact('totalOrders', 'todayOrders', 'todayRevenue', 'todayBookingDownPayment'))
            )->withHeaders(['X-Live' => '1']);
        }

        return view($viewName, compact(
            'orders',
            'totalOrders',
            'todayOrders',
            'todayRevenue',
            'todayBookingDownPayment',
            'totalRevenue',
            'printerLocations',
            'hasAnyActivePrinter',
            'activePrinterOptions',
            'orderPrintPayloads',
            'orderDetailPayloads',
            'perPage',
            'transactionMode',
            'averageOrderTotal',
            'areas',
            'selectedAreaId',
        ));
    }

    /**
     * Realtime poll: re-render stats + list partials and return fresh detail/print payloads.
     */
    public function refresh(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        $selectedAreaId = $user ? $user->resolveActiveAreaId($request->input('area_id'), $request->has('area_id')) : ($request->filled('area_id')
            ? ($request->input('area_id') === 'all' ? null : (int) $request->input('area_id'))
            : (session('active_area_id') && session('active_area_id') !== 'all' ? (int) session('active_area_id') : null));

        $transactionMode = $request->get('transaction_mode') === 'walk_in' ? 'walk_in' : 'all';
        $dateFrom = $request->filled('date_from') ? $request->date('date_from')->toDateString() : null;
        $dateTo = $request->filled('date_to') ? $request->date('date_to')->toDateString() : null;

        $areaFilter = fn ($q) => $q->when(
            $selectedAreaId,
            fn ($sq) => $sq->where(
                fn ($sub) => $sub
                    ->where('area_id', $selectedAreaId)
                    ->orWhereHas('tableSession.table', fn ($t) => $t->where('area_id', $selectedAreaId))
            )
        );

        $query = Order::with([
            'items.inventoryItem.printers',
            'tableSession.table',
            'tableSession.reservation',
            'tableSession.billing.payments',
            'tableSession.customer.profile',
            'customer.user.profile',
            'billing.payments',
            'customer.user',
        ])
            ->whereNotIn('status', ['cancelled'])
            ->tap($areaFilter);

        if ($transactionMode === 'walk_in') {
            $query->whereNull('table_session_id');
        }

        if ($dateFrom) {
            $query->whereDate('ordered_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('ordered_at', '<=', $dateTo);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('order_number', 'like', '%'.$request->search.'%')
                    ->orWhereHas('tableSession.customer', function ($q2) use ($request) {
                        $q2->where('name', 'like', '%'.$request->search.'%');
                    })
                    ->orWhereHas('customer.user', function ($q2) use ($request) {
                        $q2->where('name', 'like', '%'.$request->search.'%');
                    });
            });
        }

        $perPage = in_array((int) $request->get('per_page'), [10, 25, 50, 100]) ? (int) $request->get('per_page') : 25;
        $orders = $query->latest('ordered_at')->paginate($perPage)->withQueryString();

        $orders->getCollection()->transform(function (Order $order) {
            $assignedPrinterTypes = $this->resolveOrderAssignedPrinterTypes($order);
            $order->setAttribute('print_types', [
                'resmi' => true,
                'kitchen' => $assignedPrinterTypes->contains('kitchen'),
                'bar' => $assignedPrinterTypes->contains('bar'),
                'checker' => $assignedPrinterTypes->contains('checker'),
            ]);
            $order->setAttribute('print_counts', [
                'resmi' => (int) ($order->receipt_print_count ?? 0),
                'kitchen' => (int) ($order->kitchen_print_count ?? 0),
                'bar' => (int) ($order->bar_print_count ?? 0),
                'checker' => (int) ($order->checker_print_count ?? 0),
            ]);

            return $order;
        });

        $statsQuery = Order::query()->whereNotIn('status', ['cancelled'])->tap($areaFilter)
            // FOC/Compliment bukan revenue — exclude order dengan billing FOC.
            ->whereDoesntHave('billing', function ($query): void {
                $query->whereIn('foc_comp_payment_method', ['FOC', 'Compliment']);
            });

        if ($transactionMode === 'walk_in') {
            $statsQuery->whereNull('table_session_id');
        }

        if ($dateFrom) {
            $statsQuery->whereDate('ordered_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $statsQuery->whereDate('ordered_at', '<=', $dateTo);
        }

        $totalOrders = (clone $statsQuery)->count();
        $todayOrders = (clone $statsQuery)->whereDate('ordered_at', today())->count();
        $todayRevenue = (clone $statsQuery)->whereDate('ordered_at', today())->sum('total');
        $todayBookingDownPayment = (float) \App\Models\TableReservation::query()
            ->whereDate('reservation_date', today())
            ->whereNotIn('status', ['cancelled', 'rejected', 'force_closed'])
            ->when($selectedAreaId, fn ($q) => $q->whereHas('table', fn ($t) => $t->where('area_id', $selectedAreaId)))
            ->sum('down_payment_amount');

        return response()->json([
            'success' => true,
            'statsHtml' => view('transaction-history._partials.stats', compact('totalOrders', 'todayOrders', 'todayRevenue', 'todayBookingDownPayment'))->render(),
            'listHtml' => view('transaction-history._partials.list', ['orders' => $orders])->render(),
            'totalCount' => $orders->total(),
            'detailPayloads' => $this->buildDetailPayloads($orders->getCollection()),
            'printPayloads' => $this->buildPrintPayloads($orders->getCollection()),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function buildPrintPayloads(\Illuminate\Support\Collection $orders): array
    {
        return $orders->mapWithKeys(function (Order $order) {
            $customerName = $order->tableSession?->customer?->name ?? $order->customer?->user?->name;

            return [
                $order->id => [
                    'id' => $order->id,
                    'displayId' => $order->order_number,
                    'total' => 'Rp '.number_format((float) $order->total, 0, ',', '.'),
                    'customer' => $customerName ?? 'Walk-in',
                    'time' => $order->ordered_at?->format('H:i') ?? '—',
                    'printTypes' => $order->print_types,
                    'printCounts' => $order->print_counts,
                ],
            ];
        })->toArray();
    }

    /** @return array<int, array<string, mixed>> */
    private function buildDetailPayloads(\Illuminate\Support\Collection $orders): array
    {
        return $orders->mapWithKeys(function (Order $order) {
            $displayId = $order->order_number;
            $customerName = $order->tableSession?->customer?->name ?? $order->customer?->user?->name;
            $tableName = $order->tableSession?->table?->table_number;
            $areaName = $order->tableSession?->table?->area?->name;
            $taxTotal = $order->items->sum(fn ($item) => (float) $item->tax_amount);
            $serviceChargeTotal = $order->items->sum(fn ($item) => (float) $item->service_charge_amount);
            $billing = $order->billing ?? $order->tableSession?->billing;
            $paymentModeLabel = strtoupper((string) ($billing?->payment_mode ?? 'normal'));
            $paymentMethodDisplay = $paymentModeLabel === 'SPLIT'
                ? 'SPLIT'
                : strtoupper((string) ($billing?->payment_method ?? 'cash'));

            return [
                $order->id => [
                    'id' => $order->id,
                    'displayId' => $displayId,
                    'customer' => $customerName ?? 'Walk-in',
                    'time' => $order->ordered_at?->format('d M Y H:i') ?? '—',
                    'table' => $tableName ? trim(($areaName ? $areaName.' ' : '').$tableName) : 'Walk-in',
                    'total' => 'Rp '.number_format((float) $order->total, 0, ',', '.'),
                    'items' => $order->items->map(fn ($item) => [
                        'name' => $item->item_name,
                        'qty' => (int) $item->quantity,
                        'subtotal' => 'Rp '.number_format((float) $item->subtotal, 0, ',', '.'),
                    ])->values(),
                    'taxTotal' => $taxTotal,
                    'taxTotalFormatted' => 'Rp '.number_format($taxTotal, 0, ',', '.'),
                    'serviceChargeTotal' => $serviceChargeTotal,
                    'serviceChargeTotalFormatted' => 'Rp '.number_format($serviceChargeTotal, 0, ',', '.'),
                    'billing' => $billing ? [
                        'id' => (int) $billing->id,
                        'billingStatus' => (string) ($billing->billing_status ?? '-'),
                        'paymentMode' => (string) ($billing->payment_mode ?? 'normal'),
                        'paymentMethod' => (string) ($billing->payment_method ?? 'cash'),
                        'paymentMethodDisplay' => $paymentMethodDisplay,
                        'paymentReferenceNumber' => (string) ($billing->payment_reference_number ?? ''),
                        'splitCashAmount' => (float) ($billing->split_cash_amount ?? 0),
                        'splitNonCashAmount' => (float) ($billing->split_debit_amount ?? 0),
                        'splitNonCashMethod' => (string) ($billing->split_non_cash_method ?? ''),
                        'splitNonCashReferenceNumber' => (string) ($billing->split_non_cash_reference_number ?? ''),
                        'splitSecondNonCashAmount' => (float) ($billing->split_second_non_cash_amount ?? 0),
                        'splitSecondNonCashMethod' => (string) ($billing->split_second_non_cash_method ?? ''),
                        'splitSecondNonCashReferenceNumber' => (string) ($billing->split_second_non_cash_reference_number ?? ''),
                        'grandTotal' => (float) ($billing->grand_total ?? $order->total),
                        'grandTotalFormatted' => 'Rp '.number_format((float) ($billing->grand_total ?? $order->total), 0, ',', '.'),
                        'paidAmount' => (float) ($billing->paid_amount ?? $order->total),
                        'paidAmountFormatted' => 'Rp '.number_format((float) ($billing->paid_amount ?? $order->total), 0, ',', '.'),
                        'remainingBalance' => (float) ($billing->remaining_balance ?? 0),
                        'remainingBalanceFormatted' => 'Rp '.number_format((float) ($billing->remaining_balance ?? 0), 0, ',', '.'),
                        'isDebt' => (bool) ($billing->is_debt ?? false),
                        'transactionCode' => (string) ($billing->transaction_code ?? '-'),
                        'accurateSoNumber' => (string) ($billing->accurate_so_number ?? $order->accurate_so_number ?? ''),
                        'accurateInvNumber' => (string) ($billing->accurate_inv_number ?? $order->accurate_inv_number ?? ''),
                        'errorMessage' => (string) ($billing->error_message ?? ''),
                        'updatePaymentUrl' => route('admin.transaction-history.update-payment', $order),
                        'settleDebtUrl' => route('admin.transaction-history.settle-debt', $order),
                        'payments' => $billing->payments->map(fn ($p) => [
                            'id' => $p->id,
                            'amountPaid' => (float) $p->amount_paid,
                            'amountPaidFormatted' => 'Rp '.number_format((float) $p->amount_paid, 0, ',', '.'),
                            'paymentMethod' => strtoupper($p->payment_method),
                            'paymentType' => $p->payment_type,
                            'paidAt' => $p->paid_at?->format('d M Y H:i'),
                        ])->values(),
                    ] : null,
                ],
            ];
        })->toArray();
    }

    public function print(Request $request, Order $order): JsonResponse
    {
        $type = $request->input('type', 'resmi');
        $isReprint = $request->boolean('is_reprint');
        $selectedPrinterId = (int) $request->input('printer_id', 0);

        try {
            $order->load([
                'items.inventoryItem.printers',
                'tableSession.table',
                'tableSession.customer',
                'kitchenOrder.items.inventoryItem',
                'kitchenOrder.table',
                'barOrder.items.inventoryItem',
                'barOrder.table',
            ]);

            if (! in_array($type, ['resmi', 'kitchen', 'bar', 'checker'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jenis cetak tidak valid.',
                ], 422);
            }

            $counterColumn = match ($type) {
                'kitchen' => 'kitchen_print_count',
                'bar' => 'bar_print_count',
                'checker' => 'checker_print_count',
                default => 'receipt_print_count',
            };

            $currentCount = (int) ($order->{$counterColumn} ?? 0);

            if ($currentCount > 0 && ! $isReprint) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dokumen ini sudah pernah dicetak. Silakan otorisasi kode harian untuk cetak ulang.',
                ], 422);
            }

            $location = match ($type) {
                'kitchen' => 'kitchen',
                'bar' => 'bar',
                'checker' => 'checker',
                default => 'cashier',
            };

            if ($selectedPrinterId > 0) {
                $printer = Printer::active()->find($selectedPrinterId);
            } else {
                /** @var \App\Models\User|null $user */
                $user = auth()->user();
                // Cetak ulang harus keluar di area transaksinya, bukan area printer
                // ber-id terkecil (repo ini menggabungkan beberapa area).
                $printAreaId = $order->tableSession?->table?->area_id
                    ?? $order->area_id
                    ?? $user?->resolveActiveAreaId();

                if ($type === 'resmi') {
                    $printer = Printer::getForService('cashier', $printAreaId);
                } else {
                    $printer = Printer::getByLocation($location, $printAreaId);

                    if (! $printer) {
                        $printer = Printer::getDefault($printAreaId);
                    }
                }
            }

            if (! $printer) {
                $locationLabel = match ($location) {
                    'kitchen' => 'Kitchen',
                    'bar' => 'Bar',
                    default => 'Kasir',
                };

                return response()->json([
                    'success' => false,
                    'message' => "Tidak ada printer aktif untuk lokasi {$locationLabel}.",
                ], 400);
            }

            if ($type === 'kitchen') {
                if ($order->kitchenOrder) {
                    $this->printerService->printKitchenTicket($order->kitchenOrder, $printer);
                } else {
                    $this->printerService->printReceipt($order, $printer);
                }
            } elseif ($type === 'bar') {
                if ($order->barOrder) {
                    $this->printerService->printBarTicket($order->barOrder, $printer);
                } elseif ($order->kitchenOrder) {
                    $this->printerService->printBarTicket($order->kitchenOrder, $printer);
                } else {
                    $this->printerService->printReceipt($order, $printer);
                }
            } elseif ($type === 'checker') {
                $printed = false;

                if ($order->kitchenOrder) {
                    $this->printerService->printCheckerTicket($order->kitchenOrder, $printer);
                    $printed = true;
                }

                if ($order->barOrder) {
                    $this->printerService->printCheckerTicket($order->barOrder, $printer);
                    $printed = true;
                }

                if (! $printed) {
                    $this->printerService->printReceipt($order, $printer);
                }
            } else {
                $billing = Billing::query()
                    ->where('order_id', $order->id)
                    ->latest('id')
                    ->first();

                if ($billing && ! $order->table_session_id && (bool) $billing->is_walk_in) {
                    $this->printerService->printWalkInBillingReceipt($order, $billing, $printer);
                } else {
                    $this->printerService->printReceipt($order, $printer);
                }
            }

            $order->increment($counterColumn);

            $typeLabel = match ($type) {
                'kitchen' => 'Kitchen',
                'bar' => 'Bar',
                'checker' => 'Checker',
                default => 'Struk Resmi',
            };

            return response()->json([
                'success' => true,
                'message' => "Cetak {$typeLabel} berhasil dikirim ke printer.",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mencetak: '.$e->getMessage(),
            ], 500);
        }
    }

    public function updatePayment(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'payment_mode' => 'required|in:normal,split',
            'payment_method' => 'required_if:payment_mode,normal|nullable|in:cash,kredit,debit,qris,transfer,FOC,Compliment',
            'payment_reference_number' => 'nullable|string|max:100',
            'split_cash_amount' => 'nullable|numeric|min:0',
            'split_non_cash_amount' => 'nullable|numeric|min:0',
            'split_non_cash_method' => 'nullable|in:debit,kredit,qris,transfer,ewallet,lainnya',
            'split_non_cash_reference_number' => 'nullable|string|max:100',
            'split_second_non_cash_amount' => 'nullable|numeric|min:0',
            'split_second_non_cash_method' => 'nullable|in:debit,kredit,qris,transfer,ewallet,lainnya',
            'split_second_non_cash_reference_number' => 'nullable|string|max:100',
        ]);

        $billing = $order->billing()->first() ?? $order->tableSession?->billing;

        if (! $billing) {
            return response()->json([
                'success' => false,
                'message' => 'Billing tidak ditemukan.',
            ], 404);
        }

        if ($billing->billing_status !== 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Payment hanya bisa diedit untuk billing yang sudah dibayar.',
            ], 422);
        }

        $paymentMode = (string) $validated['payment_mode'];
        $paymentMethod = null;
        $paymentReferenceNumber = null;
        $splitCashAmount = null;
        $splitDebitAmount = null;
        $splitNonCashMethod = null;
        $splitNonCashReferenceNumber = null;
        $splitSecondNonCashAmount = null;
        $splitSecondNonCashMethod = null;
        $splitSecondNonCashReferenceNumber = null;

        if ($paymentMode === 'normal') {
            // FOC/Compliment billing → payment_method dipertahankan (auto-set).
            $paymentMethod = (string) ($billing->foc_comp_payment_method
                ?? $validated['payment_method']
                ?? '');
            $paymentReferenceNumber = in_array($paymentMethod, ['cash', 'FOC', 'Compliment'], true)
                ? null
                : ((string) ($validated['payment_reference_number'] ?? ''));

            if (! in_array($paymentMethod, ['cash', 'FOC', 'Compliment'], true) && blank($paymentReferenceNumber)) {
                throw ValidationException::withMessages([
                    'payment_reference_number' => 'Nomor referensi pembayaran non-cash wajib diisi.',
                ]);
            }
        }

        if ($paymentMode === 'split') {
            $splitCashAmount = (float) ($validated['split_cash_amount'] ?? 0);
            $splitDebitAmount = (float) ($validated['split_non_cash_amount'] ?? 0);
            $splitNonCashMethod = $validated['split_non_cash_method'] ?? null;
            $splitNonCashReferenceNumber = $validated['split_non_cash_reference_number'] ?? null;
            $splitSecondNonCashAmount = (float) ($validated['split_second_non_cash_amount'] ?? 0);
            $splitSecondNonCashMethod = $validated['split_second_non_cash_method'] ?? null;
            $splitSecondNonCashReferenceNumber = $validated['split_second_non_cash_reference_number'] ?? null;

            $requiresReferenceNumber = static function (?string $method): bool {
                $normalizedMethod = strtolower(trim((string) $method));

                return $normalizedMethod !== '' && ! in_array($normalizedMethod, ['cash', 'tunai'], true);
            };

            $grandTotal = round((float) $billing->grand_total, 2);
            $splitTotal = round($splitCashAmount + $splitDebitAmount + $splitSecondNonCashAmount, 2);

            $activeNonCashCount = collect([
                ['amount' => $splitDebitAmount, 'method' => $splitNonCashMethod, 'reference' => $splitNonCashReferenceNumber],
                ['amount' => $splitSecondNonCashAmount, 'method' => $splitSecondNonCashMethod, 'reference' => $splitSecondNonCashReferenceNumber],
            ])->filter(fn (array $entry): bool => (float) $entry['amount'] > 0)->count();

            $hasCash = $splitCashAmount > 0;

            if (! $hasCash && $activeNonCashCount < 2) {
                throw ValidationException::withMessages([
                    'split_total' => 'Untuk split non-cash + non-cash, isi dua nominal non-cash lebih dari 0.',
                ]);
            }

            if ($hasCash && $activeNonCashCount < 1) {
                throw ValidationException::withMessages([
                    'split_total' => 'Untuk split cash + non-cash, minimal satu nominal non-cash harus lebih dari 0.',
                ]);
            }

            if ($activeNonCashCount === 0) {
                throw ValidationException::withMessages([
                    'split_total' => 'Split bill memerlukan minimal satu pembayaran non-cash.',
                ]);
            }

            if (abs($splitTotal - $grandTotal) > 0.01) {
                throw ValidationException::withMessages([
                    'split_total' => 'Total pembayaran split harus sama dengan grand total.',
                ]);
            }

            if ($splitDebitAmount > 0 && blank($splitNonCashMethod)) {
                throw ValidationException::withMessages([
                    'split_non_cash_method' => 'Metode non-cash pertama untuk split bill wajib dipilih.',
                ]);
            }

            if ($splitDebitAmount > 0 && $requiresReferenceNumber($splitNonCashMethod) && blank($splitNonCashReferenceNumber)) {
                throw ValidationException::withMessages([
                    'split_non_cash_reference_number' => 'Nomor referensi non-cash pertama untuk split bill wajib diisi.',
                ]);
            }

            if ($splitSecondNonCashAmount > 0 && blank($splitSecondNonCashMethod)) {
                throw ValidationException::withMessages([
                    'split_second_non_cash_method' => 'Metode non-cash kedua untuk split bill wajib dipilih.',
                ]);
            }

            if ($splitSecondNonCashAmount > 0 && $requiresReferenceNumber($splitSecondNonCashMethod) && blank($splitSecondNonCashReferenceNumber)) {
                throw ValidationException::withMessages([
                    'split_second_non_cash_reference_number' => 'Nomor referensi non-cash kedua untuk split bill wajib diisi.',
                ]);
            }
        }

        $billing->update([
            'payment_mode' => $paymentMode,
            'payment_method' => $paymentMethod,
            'payment_reference_number' => $paymentReferenceNumber,
            'split_cash_amount' => $splitCashAmount,
            'split_debit_amount' => $splitDebitAmount,
            'split_non_cash_method' => $splitNonCashMethod,
            'split_non_cash_reference_number' => $splitNonCashReferenceNumber,
            'split_second_non_cash_amount' => $splitSecondNonCashAmount,
            'split_second_non_cash_method' => $splitSecondNonCashMethod,
            'split_second_non_cash_reference_number' => $splitSecondNonCashReferenceNumber,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment berhasil diperbarui.',
        ]);
    }

    public function reSyncAccurate(Order $order)
    {
        $order->loadMissing([
            'items.inventoryItem',
            'customer.user.profile',
            'billing',
            'tableSession.billing',
        ]);

        $billing = $order->billing ?? $order->tableSession?->billing;

        if (! $billing) {
            return back()->with('error', 'Billing tidak ditemukan untuk transaksi ini.');
        }

        if (($billing->accurate_so_number || $order->accurate_so_number) && ($billing->accurate_inv_number || $order->accurate_inv_number)) {
            return back()->with('success', 'SO dan Invoice Accurate sudah tersedia.');
        }

        $this->pushOrderToAccurate($order, $billing);

        $billing->refresh();
        $order->refresh();

        $soNumber = $billing->accurate_so_number ?: $order->accurate_so_number;
        $invNumber = $billing->accurate_inv_number ?: $order->accurate_inv_number;

        if (! $soNumber || ! $invNumber) {
            return back()->with('error', $billing->error_message ?: 'Re-sync ke Accurate gagal. Silakan coba lagi.');
        }

        return back()->with('success', 'Re-sync Accurate berhasil.');
    }

    protected function pushOrderToAccurate(Order $order, $billing): void
    {
        try {
            $order->loadMissing([
                'items.inventoryItem',
                'customer.user.profile',
            ]);

            $customerUser = $order->customer;

            if (! $customerUser) {
                $billing->update([
                    'error_message' => 'Customer transaksi tidak ditemukan untuk sinkronisasi Accurate.',
                ]);

                return;
            }

            $customerNo = $this->ensureAccurateCustomer($customerUser);

            if (! $customerNo) {
                $billing->update([
                    'error_message' => 'Customer Accurate tidak ditemukan untuk transaksi ini.',
                ]);

                return;
            }

            $transDate = now()->format('d/m/Y');
            $warehouseName = GeneralSetting::instance()->getAccurateWarehouseName();
            $taxAmount = (float) ($billing->tax ?? 0);
            $serviceChargeAmount = (float) ($billing->service_charge ?? 0);

            $detailItem = $order->items
                ->groupBy('inventory_item_id')
                ->map(function ($group) use ($warehouseName) {
                    $first = $group->first();
                    $gross = (float) $group->sum('subtotal');
                    $discountAmount = (float) $group->sum('discount_amount');

                    return [
                        'itemNo' => $first->inventoryItem?->code ?? $first->item_code,
                        'quantity' => $group->sum('quantity'),
                        'unitPrice' => (float) $first->price,
                        'discountPercent' => $gross > 0 ? round($discountAmount / $gross * 100, 6) : 0,
                        'warehouseName' => $warehouseName,
                    ];
                })
                ->values()
                ->toArray();

            if (empty($detailItem)) {
                $billing->update([
                    'error_message' => 'Item order tidak ditemukan untuk dikirim ke Accurate.',
                ]);

                return;
            }

            $soBasePayload = [
                'customerNo' => $customerNo,
                'transDate' => $transDate,
                'memo' => 'Walk-in POS — '.$order->order_number,
                'detailItem' => $detailItem,
            ];

            if ($serviceChargeAmount > 0) {
                $soBasePayload['detailExpense'][] = [
                    'accountNo' => GeneralSetting::instance()->accurate_service_charge_account_no ?? '210202',
                    'expenseAmount' => $serviceChargeAmount,
                    'expenseName' => 'Service Charge',
                ];
            }

            if ($taxAmount > 0) {
                $soBasePayload['detailExpense'][] = [
                    'accountNo' => GeneralSetting::instance()->accurate_tax_account_no ?? '210201',
                    'expenseAmount' => $taxAmount,
                    'expenseName' => 'PB 1',
                ];
            }

            $soNumber = null;
            $maxAttempts = 3;
            $activeArea = $order->tableSession?->table?->area ?? auth()->user()?->resolveActiveArea();
            $areaPrefix = $activeArea ? $activeArea->so_prefix : 'ROOM-';
            $soPrefix = "{$areaPrefix}WALKIN-".now()->format('Ymd').'-';

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $randomNumber = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
                $soNumberWithPrefix = $soPrefix.$randomNumber;

                try {
                    $this->accurateService->saveSalesOrder(
                        array_merge($soBasePayload, ['number' => $soNumberWithPrefix])
                    );
                    $soNumber = $soNumberWithPrefix;
                    break;
                } catch (\Exception $e) {
                    $isDuplicate = str_contains($e->getMessage(), 'Sudah ada data');

                    if (! $isDuplicate || $attempt === $maxAttempts) {
                        throw $e;
                    }
                }
            }

            $invPayload = [
                'customerNo' => $customerNo,
                'transDate' => $transDate,
                'memo' => 'Walk-in POS — '.$order->order_number,
                'number' => $soNumber,
                'detailItem' => array_map(
                    fn (array $item): array => array_merge($item, ['salesOrderNumber' => $soNumber]),
                    $detailItem
                ),
            ];

            if ($taxAmount > 0) {
                $invPayload['detailExpense'][] = [
                    'accountNo' => GeneralSetting::instance()->accurate_tax_account_no ?? '210201',
                    'expenseAmount' => $taxAmount,
                    'expenseName' => 'PB 1',
                ];
            }

            if ($serviceChargeAmount > 0) {
                $invPayload['detailExpense'][] = [
                    'accountNo' => GeneralSetting::instance()->accurate_service_charge_account_no ?? '210202',
                    'expenseAmount' => $serviceChargeAmount,
                    'expenseName' => 'Service Charge',
                ];
            }

            $invResult = $this->accurateService->saveSalesInvoice($invPayload);
            $invNumber = $invResult['r']['number'] ?? $invResult['d']['number'] ?? $soNumber;

            // Save Sales Receipt (Penerimaan Penjualan) for single or split payments
            $this->syncSalesReceipts($customerNo, $transDate, $soNumber, $invNumber, $order->order_number, $billing, $order);

            $order->update([
                'accurate_so_number' => $soNumber,
                'accurate_inv_number' => $invNumber,
            ]);

            $billing->update([
                'accurate_so_number' => $soNumber,
                'accurate_inv_number' => $invNumber,
                'error_message' => null,
            ]);
        } catch (\Exception $e) {
            $billing->update([
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Accurate Billing Sync: FAILED', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    protected function syncSalesReceipts(string $customerNo, string $transDate, string $soNumber, ?string $invNumber, string $reference, $billing, $order = null): void
    {
        $settings = GeneralSetting::instance();
        $cashAccountNo = $settings->accurate_cash_account_no ?: '110101';
        $bankAccountNo = $settings->accurate_bank_account_no ?: '110102';

        $paymentsToSync = [];

        $billingPayments = $billing?->payments ? $billing->payments : collect();

        if ($billingPayments->count() > 1) {
            foreach ($billingPayments as $paymentRecord) {
                $amount = (float) $paymentRecord->amount_paid;
                if ($amount <= 0) {
                    continue;
                }
                $method = strtolower((string) $paymentRecord->payment_method);
                $isCash = in_array($method, ['cash', 'tunai'], true);
                $bankNo = $isCash ? $cashAccountNo : $bankAccountNo;
                $methodLabel = $isCash ? 'Tunai' : strtoupper($method);

                $paymentsToSync[] = [
                    'amount' => $amount,
                    'bankNo' => $bankNo,
                    'method_label' => $methodLabel,
                ];
            }
        } elseif (($billing?->payment_mode ?? null) === 'split') {
            $splitCash = (float) ($billing->split_cash_amount ?? 0);
            $splitNonCash1Amount = (float) ($billing->split_debit_amount ?? 0);
            $splitNonCash1Method = strtolower((string) ($billing->split_non_cash_method ?? 'non_cash_1'));

            $splitNonCash2Amount = (float) ($billing->split_second_non_cash_amount ?? 0);
            $splitNonCash2Method = strtolower((string) ($billing->split_second_non_cash_method ?? 'non_cash_2'));

            if ($splitCash > 0) {
                $paymentsToSync[] = [
                    'amount' => $splitCash,
                    'bankNo' => $cashAccountNo,
                    'method_label' => 'Tunai',
                ];
            }

            if ($splitNonCash1Amount > 0) {
                $isCash1 = in_array($splitNonCash1Method, ['cash', 'tunai'], true);
                $bankNo1 = $isCash1 ? $cashAccountNo : $bankAccountNo;
                $methodLabel1 = $isCash1 ? 'Tunai' : strtoupper((string) ($billing->split_non_cash_method ?: 'NON-CASH 1'));

                $paymentsToSync[] = [
                    'amount' => $splitNonCash1Amount,
                    'bankNo' => $bankNo1,
                    'method_label' => $methodLabel1,
                ];
            }

            if ($splitNonCash2Amount > 0) {
                $isCash2 = in_array($splitNonCash2Method, ['cash', 'tunai'], true);
                $bankNo2 = $isCash2 ? $cashAccountNo : $bankAccountNo;
                $methodLabel2 = $isCash2 ? 'Tunai' : strtoupper((string) ($billing->split_second_non_cash_method ?: 'NON-CASH 2'));

                $paymentsToSync[] = [
                    'amount' => $splitNonCash2Amount,
                    'bankNo' => $bankNo2,
                    'method_label' => $methodLabel2,
                ];
            }
        } else {
            $paidAmount = (float) ($billing?->paid_amount ?? $billing?->grand_total ?? $order?->total ?? 0);
            if ($paidAmount > 0) {
                $method = strtolower((string) ($billing?->payment_method ?? $order?->payment_method ?? 'cash'));
                $isCash = in_array($method, ['cash', 'tunai'], true);
                $bankNo = $isCash ? $cashAccountNo : $bankAccountNo;
                $methodLabel = $isCash ? 'Tunai' : strtoupper($method);

                $paymentsToSync[] = [
                    'amount' => $paidAmount,
                    'bankNo' => $bankNo,
                    'method_label' => $methodLabel,
                ];
            }
        }

        $targetInvoiceNo = $invNumber ?: $soNumber ?: $billing?->accurate_inv_number ?: $billing?->accurate_so_number ?: $order?->accurate_inv_number ?: $order?->accurate_so_number;

        foreach ($paymentsToSync as $paymentItem) {
            if ($paymentItem['amount'] <= 0) {
                continue;
            }

            try {
                $receiptPayload = [
                    'customerNo' => $customerNo,
                    'transDate' => $transDate,
                    'bankNo' => $paymentItem['bankNo'],
                    'chequeAmount' => $paymentItem['amount'],
                    'description' => "Pembayaran POS ({$paymentItem['method_label']}) — {$reference}",
                    'detailInvoice' => [
                        [
                            'invoiceNo' => $targetInvoiceNo,
                            'paymentAmount' => $paymentItem['amount'],
                        ],
                    ],
                ];
                $response = $this->accurateService->saveSalesReceipt($receiptPayload);
                $receiptNo = $response['r']['number'] ?? $response['d']['number'] ?? null;

                if ($receiptNo && $billing) {
                    $methodStr = strtolower((string) $paymentItem['method_label']);
                    $paymentRecord = \App\Models\BillingPayment::query()
                        ->where('billing_id', $billing->id)
                        ->where(function ($q) use ($methodStr, $paymentItem) {
                            $q->where('payment_method', $methodStr)
                                ->orWhere('amount_paid', $paymentItem['amount']);
                        })
                        ->whereNull('accurate_sales_receipt_number')
                        ->first();

                    if (! $paymentRecord) {
                        $paymentRecord = \App\Models\BillingPayment::query()
                            ->where('billing_id', $billing->id)
                            ->whereNull('accurate_sales_receipt_number')
                            ->first();
                    }

                    if ($paymentRecord) {
                        $paymentRecord->update([
                            'accurate_sales_receipt_number' => $receiptNo,
                            'accurate_sync_status' => 'synced',
                        ]);
                    } else {
                        \App\Models\BillingPayment::create([
                            'billing_id' => $billing->id,
                            'amount_paid' => $paymentItem['amount'],
                            'payment_method' => strtolower((string) $paymentItem['method_label']),
                            'payment_type' => 'full_payment',
                            'accurate_sales_receipt_number' => $receiptNo,
                            'accurate_sync_status' => 'synced',
                            'paid_at' => now('Asia/Jakarta'),
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Accurate Transaction History Sales Receipt Sync: FAILED', [
                    'reference' => $reference,
                    'method' => $paymentItem['method_label'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function ensureAccurateCustomer(CustomerUser $customerUser): ?string
    {
        $customerUser->loadMissing(['user', 'profile']);

        if ($customerUser->customer_code && $customerUser->accurate_id) {
            return $customerUser->customer_code;
        }

        $user = $customerUser->user;

        if (! $user) {
            return null;
        }

        $payload = [
            'name' => $user->name,
            'email' => $user->email,
        ];

        if ($customerUser->accurate_id) {
            $payload['id'] = $customerUser->accurate_id;
        }

        $response = $this->accurateService->saveCustomer($payload);
        $accurateId = $response['r']['id'] ?? $response['d']['id'] ?? null;
        $customerNo = $response['r']['customerNo'] ?? $response['d']['customerNo'] ?? null;

        if (! $customerNo) {
            throw new \RuntimeException('Accurate customer number was not returned.');
        }

        $customerUser->update([
            'accurate_id' => $accurateId,
            'customer_code' => $customerNo,
        ]);

        return $customerNo;
    }

    protected function resolveOrderAssignedPrinterTypes(Order $order): \Illuminate\Support\Collection
    {
        return $order->items
            ->flatMap(function ($item) {
                return $item->inventoryItem?->printers
                    ?->filter(fn (Printer $printer): bool => $printer->is_active)
                    ->map(function (Printer $printer): ?string {
                        $type = strtolower(trim((string) $printer->printer_type));

                        if (in_array($type, ['kitchen', 'bar', 'cashier', 'checker'], true)) {
                            return $type;
                        }

                        $location = strtolower(trim((string) $printer->location));

                        return in_array($location, ['kitchen', 'bar', 'cashier', 'checker'], true) ? $location : null;
                    })
                    ->filter()
                    ->values()
                    ?? collect();
            })
            ->unique()
            ->values();
    }

    public function settleDebt(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'amount_paid' => 'required|numeric|gt:0',
            'payment_method' => 'required|in:cash,kredit,debit,qris,transfer',
            'payment_reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:255',
        ]);

        $billing = $order->billing()->first() ?? $order->tableSession?->billing;

        if (! $billing) {
            return response()->json(['success' => false, 'message' => 'Billing tidak ditemukan.'], 404);
        }

        if (! $billing->is_debt && $billing->billing_status !== 'partial_paid') {
            return response()->json(['success' => false, 'message' => 'Billing ini tidak memiliki sisa hutang/piutang.'], 422);
        }

        $amountPaid = (float) $validated['amount_paid'];
        $remainingBalance = (float) $billing->remaining_balance;

        if ($amountPaid > $remainingBalance + 0.01) {
            return response()->json([
                'success' => false,
                'message' => 'Jumlah pelunasan (Rp '.number_format($amountPaid, 0, ',', '.').') melebihi sisa hutang (Rp '.number_format($remainingBalance, 0, ',', '.').').',
            ], 422);
        }

        $payment = BillingPayment::create([
            'billing_id' => $billing->id,
            'amount_paid' => $amountPaid,
            'payment_method' => $validated['payment_method'],
            'payment_reference_number' => $validated['payment_reference_number'] ?? null,
            'payment_type' => 'debt_settlement',
            'notes' => $validated['notes'] ?? 'Pelunasan sisa piutang',
            'created_by' => auth()->id(),
            'paid_at' => now('Asia/Jakarta'),
        ]);

        $billing->recalculatePaymentStatus();

        // Push Sales Receipt to Accurate Online if SO number exists
        $accurateSoNumber = $billing->accurate_so_number ?? $order->accurate_so_number;
        $accurateInvNumber = $billing->accurate_inv_number ?? $order->accurate_inv_number ?? $accurateSoNumber;
        if ($accurateSoNumber) {
            try {
                $customerNo = $this->ensureAccurateCustomer((int) ($billing->customer_id ?? $order?->customer_id));
                $settlementMethod = strtolower(trim((string) ($validated['payment_method'] ?? 'cash')));
                $isCash = in_array($settlementMethod, ['cash', 'tunai'], true);
                $settings = GeneralSetting::instance();
                $bankNo = $isCash
                    ? ($settings->accurate_cash_account_no ?: '110101')
                    : ($settings->accurate_bank_account_no ?: '110102');

                $receiptData = [
                    'customerNo' => $customerNo,
                    'transDate' => now('Asia/Jakarta')->format('d/m/Y'),
                    'bankNo' => $bankNo,
                    'chequeAmount' => $amountPaid,
                    'description' => 'Pelunasan Piutang POS #'.$billing->transaction_code,
                    'detailInvoice' => [
                        [
                            'invoiceNo' => $accurateInvNumber,
                            'paymentAmount' => $amountPaid,
                        ],
                    ],
                ];
                $response = $this->accurateService->saveSalesReceipt($receiptData);
                if (! empty($response['r']['number']) || ! empty($response['d']['number'])) {
                    $receiptNo = $response['r']['number'] ?? $response['d']['number'];
                    $payment->update([
                        'accurate_sales_receipt_number' => $receiptNo,
                        'accurate_sync_status' => 'synced',
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Accurate sales receipt sync error on debt settlement', ['error' => $e->getMessage()]);
                $payment->update(['accurate_sync_status' => 'failed']);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Pelunasan piutang sebesar Rp '.number_format($amountPaid, 0, ',', '.').' berhasil dicatat!',
            'billing' => [
                'billing_status' => $billing->billing_status,
                'paid_amount' => (float) $billing->paid_amount,
                'remaining_balance' => (float) $billing->remaining_balance,
                'is_debt' => (bool) $billing->is_debt,
            ],
        ]);
    }
}
