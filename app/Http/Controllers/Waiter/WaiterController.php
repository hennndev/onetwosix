<?php

namespace App\Http\Controllers\Waiter;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\DisplayMessageRequest;
use App\Models\GeneralSetting;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosCategorySetting;
use App\Models\Printer;
use App\Models\TableReservation;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class WaiterController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('waiter.scanner');
    }

    public function scanner(): View
    {
        return view('waiter.scanner');
    }

    public function activeTables(): View
    {
        $waiterId = (int) Auth::id();
        $user = auth()->user();
        $activeAreaId = $user ? $user->resolveActiveAreaId() : null;

        $sessions = TableSession::with(['table.area', 'customer.profile', 'billing', 'orders.items.inventoryItem'])
            ->withSum(['orders as total_spent' => fn ($q) => $q->whereNotIn('status', ['cancelled'])], 'total')
            ->where('waiter_id', $waiterId)
            ->where('status', 'active')
            ->when($activeAreaId, fn ($q) => $q->whereHas('table', fn ($t) => $t->where('area_id', $activeAreaId)))
            ->orderByDesc('checked_in_at')
            ->get();

        $areas = $user ? $user->getAccessibleAreas() : Area::where('is_active', true)->orderBy('sort_order')->get();
        $sessionChargePreviews = $sessions->mapWithKeys(function (TableSession $session) {
            $billing = $session->billing;

            return [
                $session->id => $this->calculateSessionChargeTotals(
                    $session,
                    (float) ($billing?->discount_amount ?? 0),
                    (float) ($billing?->minimum_charge ?? 0),
                ),
            ];
        });

        return view('waiter.active-tables', compact('sessions', 'areas', 'sessionChargePreviews'));
    }

    /**
     * @return array<string, float>
     */
    protected function calculateSessionChargeTotals(TableSession $session, float $discountAmount, float $minimumCharge): array
    {
        $settings = GeneralSetting::instance();
        $orders = $session->orders->where('status', '!=', 'cancelled')->values();
        $ordersTotal = (float) $orders->sum(fn ($order) => (float) ($order->total ?? 0));
        $subtotal = $ordersTotal;

        $bases = $this->resolveChargeableBases($orders);
        $serviceCharge = round(max($bases['service_charge_base'], 0) * (((float) $settings->service_charge_percentage) / 100), 2);
        $serviceChargeTaxableAmount = round(max($bases['tax_and_service_base'], 0) * (((float) $settings->service_charge_percentage) / 100), 2);
        $tax = round((max($bases['tax_base'], 0) + $serviceChargeTaxableAmount) * (((float) $settings->tax_percentage) / 100), 2);

        $discountBaseTotal = $subtotal + $serviceCharge + $tax;
        $discountAmount = min(max($discountAmount, 0), $discountBaseTotal);
        $subtotalAfterDiscount = max($subtotal - min($discountAmount, $subtotal), 0);

        return [
            'orders_total' => $ordersTotal,
            'minimum_charge' => $minimumCharge,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'subtotal_after_discount' => $subtotalAfterDiscount,
            'discount_base_total' => $discountBaseTotal,
            'service_charge_percentage' => (float) $settings->service_charge_percentage,
            'service_charge' => $serviceCharge,
            'tax_percentage' => (float) $settings->tax_percentage,
            'tax' => $tax,
            'grand_total' => max($discountBaseTotal - $discountAmount, 0),
        ];
    }

    /**
     * @param  Collection<int, mixed>  $orders
     * @return array<string, float>
     */
    protected function resolveChargeableBases(Collection $orders): array
    {
        $serviceChargeBase = 0;
        $taxBase = 0;
        $taxAndServiceBase = 0;

        foreach ($orders as $order) {
            $orderItems = $order->items->where('status', '!=', 'cancelled')->values();
            $orderNetTotal = (float) ($order->total ?? 0);

            if ($orderItems->isEmpty()) {
                $serviceChargeBase += max($orderNetTotal, 0);
                $taxBase += max($orderNetTotal, 0);
                $taxAndServiceBase += max($orderNetTotal, 0);

                continue;
            }

            $itemsSubtotal = (float) $orderItems->sum(fn ($item) => (float) ($item->subtotal ?? 0));
            $ratio = $itemsSubtotal > 0 ? max($orderNetTotal, 0) / $itemsSubtotal : 0;

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
        }

        return [
            'service_charge_base' => $serviceChargeBase,
            'tax_base' => $taxBase,
            'tax_and_service_base' => $taxAndServiceBase,
        ];
    }

    public function updatePax(Request $request, TableSession $session): JsonResponse
    {
        if ((int) $session->waiter_id !== (int) Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak.',
            ], 403);
        }

        $validated = $request->validate([
            'pax' => 'required|integer|min:1|max:9999',
        ]);

        $session->update(['pax' => $validated['pax']]);

        return response()->json(['success' => true, 'pax' => $session->pax]);
    }

    public function pos(): View
    {
        $waiterId = (int) Auth::id();
        $settings = GeneralSetting::instance();

        $posSettings = PosCategorySetting::visibleInArea(Auth::user()?->resolveActiveAreaId());
        $allowedTypes = $posSettings->keys()->values()->all();

        $products = InventoryItem::with('printers')
            ->whereIn('category_type', $allowedTypes ?: ['__none__'])
            ->where('is_active', true)
            ->where('is_visible_in_pos', true)
            ->get()
            ->map(function ($item) use ($posSettings) {
                $setting = $posSettings->get($item->category_type);
                $isItemGroup = (bool) ($item->is_item_group ?? false);
                $displayName = filled($item->pos_name)
                    ? (string) $item->pos_name
                    : (string) $item->name;
                $assignedCheckerPrinters = $item->printers
                    ?->filter(function (Printer $printer): bool {
                        if (! $printer->is_active) {
                            return false;
                        }

                        $printerType = strtolower(trim((string) $printer->printer_type));
                        $printerLocation = strtolower(trim((string) $printer->location));

                        return $printerType === 'checker' || $printerLocation === 'checker';
                    })
                    ->map(fn (Printer $printer): array => [
                        'id' => (int) $printer->id,
                        'name' => (string) $printer->name,
                    ])
                    ->values()
                    ->all() ?? [];

                return [
                    'id' => 'item_'.$item->id,
                    'name' => $displayName,
                    'category' => $item->category_type,
                    'price' => (float) $item->price,
                    'stock' => $isItemGroup ? null : (int) ($item->stock_quantity ?? 0),
                    'is_menu' => (bool) $setting?->is_menu,
                    'is_item_group' => $isItemGroup,
                    'assigned_checker_printers' => $assignedCheckerPrinters,
                    'type' => 'item',
                ];
            })
            ->sortBy('name')
            ->values();

        $activeSessions = TableSession::with(['table.area', 'customer.profile'])
            ->where('waiter_id', $waiterId)
            ->whereNotNull('table_reservation_id')
            ->where('status', 'active')
            ->orderByDesc('checked_in_at')
            ->get();

        $rawCart = session(\App\Http\Controllers\Waiter\WaiterPosController::CART_KEY, []);
        $cart = collect($rawCart)->mapWithKeys(fn ($item, $key) => [
            $key => [
                'id' => $item['id'],
                'name' => (string) ($item['name'] ?? ''),
                'price' => (float) $item['price'],
                'qty' => (int) $item['quantity'],
                'notes' => isset($item['notes']) && trim((string) $item['notes']) !== ''
                    ? trim((string) $item['notes'])
                    : null,
                'assigned_checker_printers' => collect($item['assigned_checker_printers'] ?? [])->values()->all(),
                'assigned_checker_printer_ids' => collect($item['assigned_checker_printer_ids'] ?? [])->values()->all(),
            ],
        ])->all();

        $checkerPrinters = Printer::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->where('printer_type', 'checker')
                    ->orWhere('location', 'checker');
            })
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Printer $printer): array => [
                'id' => (int) $printer->id,
                'name' => (string) $printer->name,
            ])
            ->values();

        $selectedSession = session(\App\Http\Controllers\Waiter\WaiterPosController::SESSION_KEY);

        if ($selectedSession !== null && ! $activeSessions->contains('id', (int) $selectedSession)) {
            $selectedSession = null;
            session()->forget(\App\Http\Controllers\Waiter\WaiterPosController::SESSION_KEY);
        }

        $canChooseChecker = (bool) ($settings->can_choose_checker ?? false);

        return view('waiter.pos', compact('products', 'activeSessions', 'cart', 'selectedSession', 'canChooseChecker', 'checkerPrinters'));
    }

    /**
     * Realtime poll: stock availability per product + the waiter's active sessions.
     * Lets the waiter see sold-out items and new/closed tables without reloading.
     */
    public function posLive(): JsonResponse
    {
        $waiterId = (int) Auth::id();
        $posSettings = PosCategorySetting::visibleInArea(Auth::user()?->resolveActiveAreaId());

        $products = InventoryItem::with('printers')
            ->whereIn('category_type', $posSettings->keys()->values()->all() ?: ['__none__'])
            ->where('is_active', true)
            ->where('is_visible_in_pos', true)
            ->get()
            ->map(function ($item) {
                $isItemGroup = (bool) ($item->is_item_group ?? false);
                $isCountPortionPossible = (bool) ($item->is_count_portion_possible ?? false);
                $possiblePortions = null;
                $isAvailable = (bool) $item->is_active && ! ($isItemGroup && (bool) $item->is_group_sold_out);

                if ($isItemGroup && $isCountPortionPossible) {
                    $possiblePortions = $this->resolvePossiblePortions($item);
                    $isAvailable = $isAvailable && $possiblePortions > 0;
                } elseif (! $isItemGroup && $isCountPortionPossible) {
                    $isAvailable = $isAvailable && (int) ($item->stock_quantity ?? 0) > 0;
                }

                return [
                    'id' => 'item_'.$item->id,
                    'stock' => $isItemGroup ? null : (int) ($item->stock_quantity ?? 0),
                    'possible_portions' => $possiblePortions,
                    'is_available' => $isAvailable,
                ];
            });

        $activeSessions = TableSession::with(['table.area', 'customer.profile'])
            ->where('waiter_id', $waiterId)
            ->whereNotNull('table_reservation_id')
            ->where('status', 'active')
            ->orderByDesc('checked_in_at')
            ->get()
            ->map(fn ($session) => [
                'id' => (string) $session->id,
                'table' => (string) ($session->table?->table_number ?? '?'),
                'customer' => $session->customer?->name,
            ])
            ->values();

        return response()->json([
            'products' => $products,
            'sessions' => $activeSessions,
        ]);
    }

    protected function resolvePossiblePortions(InventoryItem $inventoryItem): int
    {
        try {
            /** @var array<int, array<string, mixed>> $components */
            $components = Cache::remember(
                "accurate_item_group_{$inventoryItem->accurate_id}",
                now()->addHour(),
                fn (): array => app(\App\Services\AccurateService::class)->getItemGroupComponents((int) $inventoryItem->accurate_id),
            );
        } catch (\Throwable) {
            return 0;
        }

        if ($components === []) {
            return 0;
        }

        $linePossiblePortions = null;

        foreach ($components as $component) {
            $componentAccurateId = (int) ($component['itemId'] ?? 0);
            $componentQuantity = (float) ($component['quantity'] ?? 0);

            if ($componentAccurateId <= 0 || $componentQuantity <= 0) {
                continue;
            }

            $ingredient = InventoryItem::query()->where('accurate_id', $componentAccurateId)->first();
            $availableStock = max((float) ($ingredient?->stock_quantity ?? 0), 0);
            $possibleByIngredient = (int) floor($availableStock / $componentQuantity);

            $linePossiblePortions = $linePossiblePortions === null
                ? $possibleByIngredient
                : min($linePossiblePortions, $possibleByIngredient);
        }

        return $linePossiblePortions ?? 0;
    }

    public function notifications(): View
    {
        $waiter = User::query()->findOrFail((int) Auth::id());

        $assignedNotifications = $waiter->unreadNotifications()
            ->where('type', \App\Notifications\WaiterAssignedNotification::class)
            ->latest()
            ->get();

        $pendingCheckIns = TableReservation::with(['table.area', 'customer.profile'])
            ->where('status', 'confirmed')
            ->orderByDesc('created_at')
            ->get();

        $recentCheckIns = TableSession::with(['table.area', 'customer.profile'])
            ->where('waiter_id', $waiter->id)
            ->where('status', 'active')
            ->whereDate('checked_in_at', today())
            ->orderByDesc('checked_in_at')
            ->take(10)
            ->get();

        // Mark assigned notifications as read when viewing this page
        $waiter->unreadNotifications()
            ->where('type', \App\Notifications\WaiterAssignedNotification::class)
            ->update(['read_at' => now()]);

        return view('waiter.notifications', compact('pendingCheckIns', 'recentCheckIns', 'assignedNotifications'));
    }

    public function transactions(Request $request): View
    {
        $waiterId = (int) Auth::id();
        $tab = $request->get('tab', 'active');

        $query = TableSession::with(['table.area', 'customer.profile', 'billing'])
            ->where('waiter_id', $waiterId);

        if ($tab === 'active') {
            $query->where('status', 'active');
        } else {
            $query->whereIn('status', ['completed', 'force_closed']);
        }

        $sessions = $query->orderByDesc('checked_in_at')->get();

        $activeCount = TableSession::where('waiter_id', $waiterId)->where('status', 'active')->count();
        $historyCount = TableSession::where('waiter_id', $waiterId)->whereIn('status', ['completed', 'force_closed'])->count();

        return view('waiter.transactions', compact('sessions', 'tab', 'activeCount', 'historyCount'));
    }

    public function transactionChecker(Request $request): View
    {
        $waiterId = (int) Auth::id();
        $tab = $request->get('tab', 'proses');

        $assignedTableIds = TableSession::where('waiter_id', $waiterId)
            ->where('status', 'active')
            ->pluck('table_id');

        $query = Order::with([
            'items.inventoryItem',
            'tableSession.table',
            'tableSession.customer.profile',
            'customer.user',
        ])->whereNotIn('status', ['cancelled'])
            ->whereHas('tableSession', fn ($q) => $q->whereIn('table_id', $assignedTableIds));

        if ($tab === 'proses') {
            $query->whereIn('status', ['pending', 'preparing', 'ready']);
        } elseif ($tab === 'selesai') {
            $query->where('status', 'completed');
        }

        $orders = $query->latest('ordered_at')->get();

        $prosesCount = Order::whereNotIn('status', ['cancelled', 'completed'])
            ->whereHas('tableSession', fn ($q) => $q->whereIn('table_id', $assignedTableIds))
            ->count();

        $selesaiCount = Order::where('status', 'completed')
            ->whereHas('tableSession', fn ($q) => $q->whereIn('table_id', $assignedTableIds))
            ->count();

        return view('waiter.transaction-checker', compact('orders', 'tab', 'prosesCount', 'selesaiCount'));
    }

    public function transactionCheckerCheckItem(OrderItem $item): JsonResponse
    {
        $session = $item->order?->tableSession;

        if (! $session || (int) $session->waiter_id !== (int) Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak.',
            ], 403);
        }

        $item->update([
            'status' => 'served',
            'served_at' => now(),
        ]);

        $item->order->updateStatus();

        $order = Order::with('items')->find($item->order_id);
        $servedCount = $order->items->where('status', 'served')->count();
        $totalCount = $order->items->where('status', '!=', 'cancelled')->count();

        return response()->json([
            'success' => true,
            'order_status' => $order->status,
            'served_count' => $servedCount,
            'total_count' => $totalCount,
        ]);
    }

    public function transactionCheckerCheckAll(Order $order): JsonResponse
    {
        $session = $order->tableSession;

        if (! $session || (int) $session->waiter_id !== (int) Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak.',
            ], 403);
        }

        $order->items()
            ->whereNotIn('status', ['cancelled', 'served'])
            ->update(['status' => 'served', 'served_at' => now()]);

        $order->updateStatus();

        return response()->json([
            'success' => true,
            'order_status' => $order->fresh()->status,
        ]);
    }

    public function settings(): View
    {
        return view('waiter.settings');
    }

    public function displayMessages(Request $request): View
    {
        $waiterId = (int) Auth::id();

        $activeSessions = TableSession::with(['customer.profile', 'table.area'])
            ->where('waiter_id', $waiterId)
            ->where('status', 'active')
            ->whereNotNull('table_reservation_id')
            ->orderByDesc('checked_in_at')
            ->get();

        $selectedSessionId = (int) ($request->integer('session_id') ?: session(WaiterPosController::SESSION_KEY));
        $selectedSession = $selectedSessionId > 0
            ? $activeSessions->firstWhere('id', $selectedSessionId)
            : null;

        if ($selectedSession === null && $activeSessions->count() === 1) {
            $selectedSession = $activeSessions->first();
            $selectedSessionId = (int) $selectedSession?->id;
        }

        if ($selectedSession !== null) {
            session()->put(WaiterPosController::SESSION_KEY, $selectedSession->id);
        }

        $messages = DisplayMessageRequest::with(['customer.profile'])
            ->whereIn('customer_id', $activeSessions->pluck('customer_id')->filter()->unique()->values())
            ->latest()
            ->get();

        return view('waiter.display-messages', [
            'messages' => $messages,
            'totalMessages' => $messages->count(),
            'pendingMessages' => $messages->where('status', 'pending')->count(),
            'displayedMessages' => $messages->where('status', 'displayed')->count(),
            'activeSessions' => $activeSessions,
            'selectedSession' => $selectedSession,
            'selectedSessionId' => $selectedSessionId > 0 ? $selectedSessionId : null,
        ]);
    }

    public function storeDisplayMessage(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|integer|exists:table_sessions,id',
            'message' => 'required|string|max:500',
            'tip' => 'nullable|integer|min:0',
        ]);

        $session = TableSession::with(['customer.profile', 'table.area'])
            ->whereKey((int) $validated['session_id'])
            ->where('waiter_id', (int) Auth::id())
            ->where('status', 'active')
            ->whereNotNull('table_reservation_id')
            ->first();

        if (! $session || ! $session->customer_id) {
            return back()
                ->withErrors(['session_id' => 'Pilih tamu aktif terlebih dahulu.'])
                ->withInput();
        }

        DisplayMessageRequest::create([
            'customer_id' => (int) $session->customer_id,
            'message' => trim((string) $validated['message']),
            'tip' => $validated['tip'] ?? null,
            'status' => 'pending',
        ]);

        return redirect()->route('waiter.display-messages.index', ['session_id' => $session->id])
            ->with('success', 'Display message berhasil dikirim.');
    }
}
