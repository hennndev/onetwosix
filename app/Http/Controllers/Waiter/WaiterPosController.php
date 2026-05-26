<?php

namespace App\Http\Controllers\Waiter;

use App\Http\Controllers\Controller;
use App\Models\BarOrder;
use App\Models\BarOrderItem;
use App\Models\CustomerUser;
use App\Models\GeneralSetting;
use App\Models\InventoryItem;
use App\Models\KitchenOrder;
use App\Models\KitchenOrderItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosCategorySetting;
use App\Models\Printer;
use App\Models\TableSession;
use App\Services\AccurateService;
use App\Services\PrinterService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WaiterPosController extends Controller
{
    public const CART_KEY = 'waiter_pos_cart';

    public const SESSION_KEY = 'waiter_pos_selected_session';

    public function __construct(
        protected PrinterService $printerService,
        protected AccurateService $accurateService,
    ) {}

    public function addToCart(Request $request, string $productId): JsonResponse
    {
        $posSettings = PosCategorySetting::allKeyed();

        $itemId = str_replace('item_', '', $productId);
        $inventoryItem = InventoryItem::with('printers')->find($itemId);
        $setting = $posSettings->get($inventoryItem?->category_type);

        if (! $inventoryItem || ! $setting || ! $setting->show_in_pos || ! $inventoryItem->is_visible_in_pos) {
            return response()->json(['success' => false, 'message' => 'Produk tidak ditemukan.'], 404);
        }

        $cart = session()->get(self::CART_KEY, []);
        $nextQty = (int) ($cart[$productId]['quantity'] ?? 0) + 1;
        $isCountPortionPossible = (bool) ($inventoryItem->is_count_portion_possible ?? false);
        $detailGroupComponents = $this->resolveDetailGroupComponents($inventoryItem, $setting);

        if ($detailGroupComponents !== []) {
            $possiblePortions = $this->resolvePossiblePortions($inventoryItem, $detailGroupComponents);

            if ($nextQty > $possiblePortions) {
                return response()->json(['success' => false, 'message' => "Stok bahan hanya cukup {$possiblePortions} porsi."], 422);
            }
        } elseif ($isCountPortionPossible && (int) ($inventoryItem->stock_quantity ?? 0) < $nextQty) {
            return response()->json(['success' => false, 'message' => 'Stok tidak mencukupi.'], 422);
        }

        if (isset($cart[$productId])) {
            $cart[$productId]['quantity']++;
        } else {
            $displayName = filled($inventoryItem->pos_name)
                ? (string) $inventoryItem->pos_name
                : (string) $inventoryItem->name;
            $assignedCheckerPrinters = $this->resolveAssignedCheckerPrinters($inventoryItem);
            $assignedCheckerPrinterIds = collect($assignedCheckerPrinters)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();

            $cart[$productId] = [
                'id' => $productId,
                'name' => $displayName,
                'price' => $this->resolveZeroPricedItemAmount($inventoryItem),
                'quantity' => 1,
                'preparation_location' => $this->resolvePreparationLocationFromPrinters($inventoryItem) ?? $setting->preparation_location ?? 'direct',
                'assigned_checker_printers' => $assignedCheckerPrinters,
                'assigned_checker_printer_ids' => $assignedCheckerPrinterIds,
            ];
        }

        session()->put(self::CART_KEY, $cart);

        return $this->cartResponse($cart);
    }

    public function updateCart(Request $request, string $productId): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $cart = session()->get(self::CART_KEY, []);

        if ($validated['quantity'] <= 0) {
            unset($cart[$productId]);
        } elseif (isset($cart[$productId])) {
            $itemId = (int) str_replace('item_', '', $productId);
            $inventoryItem = InventoryItem::with('printers')->find($itemId);
            $setting = PosCategorySetting::allKeyed()->get($inventoryItem?->category_type);

            if (! $inventoryItem || ! $setting || ! $setting->show_in_pos || ! $inventoryItem->is_visible_in_pos) {
                return response()->json(['success' => false, 'message' => 'Produk tidak ditemukan.'], 404);
            }

            $isCountPortionPossible = (bool) ($inventoryItem->is_count_portion_possible ?? false);
            $detailGroupComponents = $this->resolveDetailGroupComponents($inventoryItem, $setting);

            if ($detailGroupComponents !== []) {
                $possiblePortions = $this->resolvePossiblePortions($inventoryItem, $detailGroupComponents);

                if ($validated['quantity'] > $possiblePortions) {
                    return response()->json(['success' => false, 'message' => "Stok bahan hanya cukup {$possiblePortions} porsi."], 422);
                }
            } elseif ($isCountPortionPossible && (int) ($inventoryItem->stock_quantity ?? 0) < $validated['quantity']) {
                return response()->json(['success' => false, 'message' => 'Stok tidak mencukupi.'], 422);
            }

            $cart[$productId]['quantity'] = $validated['quantity'];

            $assignedCheckerPrinters = $this->resolveAssignedCheckerPrinters($inventoryItem);
            $cart[$productId]['assigned_checker_printers'] = $assignedCheckerPrinters;
            $cart[$productId]['assigned_checker_printer_ids'] = collect($assignedCheckerPrinters)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();

            if ($request->has('notes')) {
                $notes = trim((string) ($validated['notes'] ?? ''));
                $cart[$productId]['notes'] = $notes !== '' ? $notes : null;
            }
        }

        session()->put(self::CART_KEY, $cart);

        return $this->cartResponse($cart);
    }

    public function removeFromCart(string $productId): JsonResponse
    {
        $cart = session()->get(self::CART_KEY, []);
        unset($cart[$productId]);
        session()->put(self::CART_KEY, $cart);

        return $this->cartResponse($cart);
    }

    public function selectSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|integer|exists:table_sessions,id',
        ]);

        $session = TableSession::query()
            ->whereKey($validated['session_id'])
            ->where('waiter_id', (int) Auth::id())
            ->whereNotNull('table_reservation_id')
            ->where('status', 'active')
            ->first();

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya meja booking aktif yang di-assign ke Anda yang bisa dipilih.',
            ], 422);
        }

        session()->put(self::SESSION_KEY, $session->id);

        return response()->json(['success' => true]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|exists:table_sessions,id',
            'checker_printer_ids' => 'nullable|array',
            'checker_printer_ids.*' => 'integer|exists:printers,id',
        ]);

        $selectedCheckerPrinterIds = collect($request->input('checker_printer_ids', []))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $waiterId = (int) Auth::id();

        $tableSession = TableSession::with(['table', 'billing', 'orders'])
            ->where('id', $validated['session_id'])
            ->where('waiter_id', $waiterId)
            ->whereNotNull('table_reservation_id')
            ->where('status', 'active')
            ->first();

        if (! $tableSession) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya meja booking aktif yang di-assign ke Anda yang bisa checkout.',
            ], 422);
        }

        $cart = session()->get(self::CART_KEY, []);

        if (empty($cart)) {
            return response()->json(['success' => false, 'message' => 'Keranjang kosong.'], 400);
        }

        $availableCheckerPrinters = $this->resolveCheckerPrintersFromCart($cart);
        $canChooseChecker = (bool) (GeneralSetting::instance()->can_choose_checker ?? false);

        if ($canChooseChecker && $availableCheckerPrinters->count() > 1) {
            if ($selectedCheckerPrinterIds->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pilih minimal satu printer checker.',
                ], 422);
            }

            $invalidPrinterIds = $selectedCheckerPrinterIds->diff($availableCheckerPrinters->pluck('id'));

            if ($invalidPrinterIds->isNotEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Printer checker yang dipilih tidak sesuai assignment menu.',
                ], 422);
            }
        }

        $availability = $this->resolveCartAvailability($cart);

        if (! $availability['can_checkout']) {
            return response()->json([
                'success' => false,
                'message' => $availability['message'],
                'stock_issues' => $availability['stock_issues'],
            ], 422);
        }

        DB::beginTransaction();
        try {
            $order = $this->createOrderWithRetry([
                'table_session_id' => $tableSession->id,
                'created_by' => Auth::id(),
                'status' => 'pending',
                'items_total' => 0,
                'discount_amount' => 0,
                'total' => 0,
                'ordered_at' => now(),
                'notes' => null,
            ]);

            $orderNumber = (string) $order->order_number;

            $itemsTotal = 0;
            $generalSettings = GeneralSetting::instance();
            $taxPercentage = (float) $generalSettings->tax_percentage;
            $serviceChargePercentage = (float) $generalSettings->service_charge_percentage;

            foreach ($cart as $productId => $cartItem) {
                $itemId = str_replace('item_', '', $productId);
                $inventoryItem = InventoryItem::with('printers')->find($itemId);

                if (! $inventoryItem) {
                    continue;
                }

                $preparationLocation = $this->resolvePreparationLocationFromPrinters($inventoryItem);

                $price = $this->resolveZeroPricedItemAmount($inventoryItem);
                $quantity = (int) $cartItem['quantity'];
                $subtotal = $price * $quantity;
                $itemsTotal += $subtotal;
                $includeTax = (bool) $inventoryItem->include_tax;
                $includeServiceCharge = (bool) $inventoryItem->include_service_charge;
                $itemServiceChargeAmount = $includeServiceCharge
                    ? round($subtotal * ($serviceChargePercentage / 100), 2)
                    : 0;
                $itemTaxAmount = $includeTax
                    ? round(($subtotal + ($includeServiceCharge ? $itemServiceChargeAmount : 0)) * ($taxPercentage / 100), 2)
                    : 0;

                OrderItem::create([
                    'order_id' => $order->id,
                    'inventory_item_id' => $inventoryItem->id,
                    'item_name' => filled($inventoryItem->pos_name)
                        ? (string) $inventoryItem->pos_name
                        : (string) $inventoryItem->name,
                    'item_code' => $inventoryItem->code,
                    'quantity' => $quantity,
                    'price' => $price,
                    'subtotal' => $subtotal,
                    'discount_amount' => 0,
                    'tax_amount' => $itemTaxAmount,
                    'service_charge_amount' => $itemServiceChargeAmount,
                    'preparation_location' => $preparationLocation,
                    'status' => 'pending',
                    'notes' => isset($cartItem['notes']) && trim((string) $cartItem['notes']) !== ''
                        ? trim((string) $cartItem['notes'])
                        : null,
                ]);
            }

            $order->update([
                'items_total' => $itemsTotal,
                'total' => $itemsTotal,
            ]);

            $order->load('items');
            $this->routeOrderToPreparation($order, $tableSession, $orderNumber, $selectedCheckerPrinterIds);

            if ($tableSession->billing) {
                $billing = $tableSession->billing;
                $billing->orders_total = $tableSession->orders()->sum('total');
                $billing->subtotal = max((float) $billing->minimum_charge, (float) $billing->orders_total);
                $billing->tax = 0;
                $billing->grand_total = $billing->subtotal - $billing->discount_amount;
                $billing->save();
            }

            session()->forget(self::CART_KEY);
            session()->forget(self::SESSION_KEY);

            DB::commit();

            return response()->json(['success' => true, 'order_number' => $orderNumber]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan: '.$e->getMessage()], 500);
        }
    }

    protected function routeOrderToPreparation(
        Order $order,
        TableSession $tableSession,
        string $orderNumber,
        ?Collection $selectedCheckerPrinterIds = null
    ): void {
        $order->loadMissing(['items.inventoryItem.printers']);

        $kitchenItems = collect();
        $barItems = collect();
        $checkerCashierItems = collect();

        foreach ($order->items as $item) {
            $assignedTypes = $item->inventoryItem?->printers
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
                ->values() ?? collect();

            if ($assignedTypes->contains('bar')) {
                $barItems->push($item);

                continue;
            }

            if ($assignedTypes->contains('kitchen')) {
                $kitchenItems->push($item);

                continue;
            }

            if ($assignedTypes->contains('cashier') || $assignedTypes->contains('checker')) {
                $checkerCashierItems->push($item);

                continue;
            }

            // Unassigned items go straight to transaction checker (no production order)
        }

        $customerUserId = CustomerUser::where('user_id', $tableSession->customer_id)
            ->value('id');

        $tableId = $tableSession->table_id;

        if ($kitchenItems->isNotEmpty()) {
            $kitchenOrder = KitchenOrder::create([
                'order_id' => $order->id,
                'order_number' => $orderNumber,
                'customer_user_id' => $customerUserId,
                'table_id' => $tableId,
                'total_amount' => $kitchenItems->sum('subtotal'),
                'status' => 'baru',
                'progress' => 0,
            ]);

            foreach ($kitchenItems as $item) {
                KitchenOrderItem::create([
                    'kitchen_order_id' => $kitchenOrder->id,
                    'inventory_item_id' => $item->inventory_item_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'is_completed' => false,
                    'notes' => $item->notes,
                ]);
            }

            $this->printKitchenTicket($kitchenOrder, $kitchenItems, $selectedCheckerPrinterIds);
        }

        if ($barItems->isNotEmpty()) {
            $barOrder = BarOrder::create([
                'order_id' => $order->id,
                'order_number' => $orderNumber,
                'customer_user_id' => $customerUserId,
                'table_id' => $tableId,
                'total_amount' => $barItems->sum('subtotal'),
                'status' => 'baru',
                'progress' => 0,
            ]);

            foreach ($barItems as $item) {
                BarOrderItem::create([
                    'bar_order_id' => $barOrder->id,
                    'inventory_item_id' => $item->inventory_item_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'is_completed' => false,
                    'notes' => $item->notes,
                ]);
            }

            $this->printBarTicket($barOrder, $barItems, $selectedCheckerPrinterIds);
        }

        if ($checkerCashierItems->isNotEmpty()) {
            $this->printCheckerCashierItemsWithoutPreparationOrder(
                $order,
                $checkerCashierItems,
                $orderNumber,
                $tableId,
                $selectedCheckerPrinterIds
            );
        }

    }

    protected function printCheckerCashierItemsWithoutPreparationOrder(
        Order $order,
        Collection $checkerCashierItems,
        string $orderNumber,
        ?int $tableId,
        ?Collection $selectedCheckerPrinterIds = null
    ): void {
        try {
            $virtualOrder = new KitchenOrder([
                'order_id' => $order->id,
                'order_number' => $orderNumber,
                'customer_user_id' => $order->customer_user_id,
                'table_id' => $tableId,
                'total_amount' => $checkerCashierItems->sum('subtotal'),
                'status' => 'baru',
                'progress' => 0,
            ]);

            $virtualOrder->setRelation('order', $order);
            $virtualOrder->setRelation('table', $order->tableSession?->table);

            $this->printItemsToAssignedPrinters(
                $virtualOrder,
                $checkerCashierItems,
                fn (KitchenOrder|BarOrder $preparationOrder, Printer $printer): bool => match ($printer->printer_type) {
                    'checker' => $this->printerService->printCheckerTicket($preparationOrder, $printer),
                    'cashier' => $this->printerService->printCashierTicket($preparationOrder, $printer),
                    default => false,
                },
                $selectedCheckerPrinterIds
            );
        } catch (\Exception $e) {
            // Silent fail — don't block checkout
        }
    }

    protected function printKitchenTicket(KitchenOrder $kitchenOrder, Collection $items, ?Collection $selectedCheckerPrinterIds = null): void
    {
        try {
            $kitchenOrder->loadMissing(['table']);
            $this->printItemsToAssignedPrinters(
                $kitchenOrder,
                $items,
                fn (KitchenOrder|BarOrder $order, Printer $printer): bool => match ($printer->printer_type) {
                    'checker' => $this->printerService->printCheckerTicket($order, $printer),
                    'cashier' => $this->printerService->printCashierTicket($order, $printer),
                    'bar' => $this->printerService->printBarTicket($order, $printer),
                    default => $this->printerService->printKitchenTicket($order, $printer),
                },
                $selectedCheckerPrinterIds
            );
        } catch (\Exception $e) {
            // Silent fail — don't block checkout
        }
    }

    protected function printBarTicket(BarOrder $barOrder, Collection $items, ?Collection $selectedCheckerPrinterIds = null): void
    {
        try {
            $barOrder->loadMissing(['table']);
            $this->printItemsToAssignedPrinters(
                $barOrder,
                $items,
                fn (KitchenOrder|BarOrder $order, Printer $printer): bool => match ($printer->printer_type) {
                    'checker' => $this->printerService->printCheckerTicket($order, $printer),
                    'cashier' => $this->printerService->printCashierTicket($order, $printer),
                    'kitchen' => $this->printerService->printKitchenTicket($order, $printer),
                    default => $this->printerService->printBarTicket($order, $printer),
                },
                $selectedCheckerPrinterIds
            );
        } catch (\Exception $e) {
            // Silent fail — don't block checkout
        }
    }

    protected function printItemsToAssignedPrinters(
        object $order,
        Collection $items,
        callable $callback,
        ?Collection $selectedCheckerPrinterIds = null
    ): void {
        $groupedByPrinter = [];

        foreach ($items as $item) {
            $targetPrinters = $item->inventoryItem?->printers?->filter(fn (Printer $printer): bool => $printer->is_active) ?? collect();

            if ($selectedCheckerPrinterIds?->isNotEmpty()) {
                $targetPrinters = $targetPrinters->filter(function (Printer $printer) use ($selectedCheckerPrinterIds): bool {
                    if ($this->resolvePrinterServiceType($printer) !== 'checker') {
                        return true;
                    }

                    return $selectedCheckerPrinterIds->contains((int) $printer->id);
                });
            }

            if ($targetPrinters->isEmpty()) {
                continue;
            }

            foreach ($targetPrinters as $printer) {
                $groupedByPrinter[$printer->id]['printer'] = $printer;
                $groupedByPrinter[$printer->id]['items'][$item->id] = $item;
            }
        }

        foreach ($groupedByPrinter as $group) {
            try {
                $orderForPrinter = clone $order;
                $orderForPrinter->setRelation('items', collect($group['items'])->values());
                $callback($orderForPrinter, $group['printer']);
            } catch (\Exception $e) {
                Log::warning('Assigned printer failed during waiter checkout print fan-out', [
                    'printer_id' => $group['printer']->id ?? null,
                    'printer_name' => $group['printer']->name ?? null,
                    'connection_type' => $group['printer']->connection_type ?? null,
                    'order_number' => $order->order_number ?? null,
                    'message' => $e->getMessage(),
                ]);
            }
        }

    }

    protected function resolveCheckerPrintersFromCart(array $cart): Collection
    {
        $inventoryItemIds = collect($cart)
            ->map(fn ($item, $productId): int => (int) str_replace('item_', '', (string) $productId))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($inventoryItemIds->isEmpty()) {
            return collect();
        }

        return InventoryItem::query()
            ->with('printers')
            ->whereIn('id', $inventoryItemIds)
            ->get()
            ->flatMap(fn (InventoryItem $inventoryItem): Collection => $inventoryItem->printers ?? collect())
            ->filter(fn (Printer $printer): bool => $printer->is_active && $this->resolvePrinterServiceType($printer) === 'checker')
            ->unique('id')
            ->values();
    }

    protected function resolvePrinterServiceType(Printer $printer): ?string
    {
        $type = strtolower(trim((string) $printer->printer_type));

        if (in_array($type, ['kitchen', 'bar', 'cashier', 'checker'], true)) {
            return $type;
        }

        $location = strtolower(trim((string) $printer->location));

        return in_array($location, ['kitchen', 'bar', 'cashier', 'checker'], true) ? $location : null;
    }

    protected function resolvePreparationLocationFromPrinters(InventoryItem $inventoryItem): ?string
    {
        $assignedTypes = $inventoryItem->printers
            ?->filter(fn (Printer $printer): bool => $printer->is_active)
            ->map(function (Printer $printer): ?string {
                $type = strtolower(trim((string) $printer->printer_type));

                if (in_array($type, ['kitchen', 'bar'], true)) {
                    return $type;
                }

                $location = strtolower(trim((string) $printer->location));

                return in_array($location, ['kitchen', 'bar'], true) ? $location : null;
            })
            ->filter()
            ->values() ?? collect();

        if ($assignedTypes->contains('bar')) {
            return 'bar';
        }

        if ($assignedTypes->contains('kitchen')) {
            return 'kitchen';
        }

        return null;
    }

    protected function resolveZeroPricedItemAmount(InventoryItem $inventoryItem): float
    {
        $categoryMain = strtolower(trim((string) $inventoryItem->category_main));

        if (in_array($categoryMain, ['compliment', 'foc'], true)) {
            return 0.0;
        }

        return (float) $inventoryItem->price;
    }

    protected function cartResponse(array $cart): JsonResponse
    {
        $inventoryItems = InventoryItem::query()
            ->whereIn('id', collect($cart)->map(fn ($item) => (int) str_replace('item_', '', (string) ($item['id'] ?? '0')))->filter()->values())
            ->get(['id', 'category_main', 'price'])
            ->keyBy('id');

        $formatted = collect($cart)->mapWithKeys(function ($item, $key) use ($inventoryItems): array {
            $inventoryItemId = (int) str_replace('item_', '', (string) ($item['id'] ?? '0'));
            $inventoryItem = $inventoryItems->get($inventoryItemId);
            $price = $inventoryItem ? $this->resolveZeroPricedItemAmount($inventoryItem) : (float) $item['price'];

            return [
                $key => [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'price' => $price,
                    'qty' => (int) $item['quantity'],
                    'notes' => isset($item['notes']) && trim((string) $item['notes']) !== ''
                        ? trim((string) $item['notes'])
                        : null,
                    'assigned_checker_printers' => collect($item['assigned_checker_printers'] ?? [])->values()->all(),
                    'assigned_checker_printer_ids' => collect($item['assigned_checker_printer_ids'] ?? [])->values()->all(),
                ],
            ];
        })->all();

        return response()->json(['success' => true, 'cart' => $formatted]);
    }

    protected function resolveCartAvailability(array $cart): array
    {
        $posSettings = PosCategorySetting::allKeyed();
        $stockIssues = [];

        foreach ($cart as $productId => $cartItem) {
            $itemId = (int) str_replace('item_', '', (string) $productId);
            $inventoryItem = InventoryItem::find($itemId);
            $requestedQuantity = (int) ($cartItem['quantity'] ?? 0);

            if (! $inventoryItem || $requestedQuantity <= 0) {
                continue;
            }

            $setting = $posSettings->get($inventoryItem->category_type);
            $isCountPortionPossible = (bool) ($inventoryItem->is_count_portion_possible ?? false);
            $detailGroupComponents = $this->resolveDetailGroupComponents($inventoryItem, $setting);

            if ($detailGroupComponents !== []) {
                $possiblePortions = $this->resolvePossiblePortions($inventoryItem, $detailGroupComponents);

                if ($possiblePortions < $requestedQuantity) {
                    $stockIssues[] = [
                        'type' => 'detail_group_shortage',
                        'product_id' => $productId,
                        'name' => $inventoryItem->name,
                        'possible_portions' => $possiblePortions,
                        'requested_quantity' => $requestedQuantity,
                        'message' => "Stok bahan {$inventoryItem->name} hanya cukup {$possiblePortions} porsi.",
                    ];
                }

                continue;
            }

            if ($isCountPortionPossible) {
                $availableStock = (float) ($inventoryItem->stock_quantity ?? 0);

                if ($availableStock < $requestedQuantity) {
                    $stockIssues[] = [
                        'type' => 'stock',
                        'product_id' => $productId,
                        'name' => $inventoryItem->name,
                        'available_stock' => $availableStock,
                        'requested_quantity' => $requestedQuantity,
                        'message' => "Stok {$inventoryItem->name} hanya tersisa {$availableStock}.",
                    ];
                }

                continue;
            }

            continue;
        }

        return [
            'can_checkout' => $stockIssues === [],
            'message' => $stockIssues[0]['message'] ?? 'Stok menu siap untuk checkout.',
            'stock_issues' => $stockIssues,
        ];
    }

    protected function getItemGroupComponents(InventoryItem $inventoryItem): array
    {
        if (! $inventoryItem->accurate_id) {
            return [];
        }

        $cacheKey = "accurate_item_group_{$inventoryItem->accurate_id}";

        return Cache::remember(
            $cacheKey,
            now()->addHour(),
            function () use ($inventoryItem): array {
                try {
                    return $this->accurateService->getItemGroupComponents((int) $inventoryItem->accurate_id);
                } catch (\Throwable $exception) {
                    return [];
                }
            }
        );
    }

    protected function resolveDetailGroupComponents(InventoryItem $inventoryItem, ?PosCategorySetting $setting = null): array
    {
        if (! (bool) ($inventoryItem->is_item_group ?? false) || ! (bool) ($inventoryItem->is_count_portion_possible ?? false)) {
            return [];
        }

        return $this->getItemGroupComponents($inventoryItem);
    }

    protected function resolvePossiblePortions(InventoryItem $inventoryItem, ?array $components = null): int
    {
        $components ??= $this->getItemGroupComponents($inventoryItem);

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

            $ingredient = InventoryItem::query()
                ->where('accurate_id', $componentAccurateId)
                ->first();

            $availableStock = max((float) ($ingredient?->stock_quantity ?? 0), 0);
            $possibleByIngredient = (int) floor($availableStock / $componentQuantity);

            $linePossiblePortions = $linePossiblePortions === null
                ? $possibleByIngredient
                : min($linePossiblePortions, $possibleByIngredient);
        }

        return $linePossiblePortions ?? 0;
    }

    protected function generateDailyOrderNumber(int $offset = 0): string
    {
        $date = today()->toDateString();
        $sequence = Order::query()
            ->whereDate('created_at', $date)
            ->count() + 1 + $offset;

        return sprintf('ORD-%s-%04d', today()->format('Ymd'), $sequence);
    }

    protected function createOrderWithRetry(array $attributes): Order
    {
        $offset = 0;
        $maxAttempts = 10;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $attributes['order_number'] = $attempt === 1
                ? $this->generateDailyOrderNumber($offset)
                : $this->generateFallbackDailyOrderNumber($attempt);

            try {
                return Order::create($attributes);
            } catch (QueryException $exception) {
                if (! $this->isDuplicateEntryException($exception) || $attempt === $maxAttempts) {
                    throw $exception;
                }

                $offset++;
            }
        }

        throw new \RuntimeException('Gagal membuat order number unik.');
    }

    protected function generateFallbackDailyOrderNumber(int $attempt): string
    {
        $sequence = random_int(1, 9999) + $attempt;

        if ($sequence > 9999) {
            $sequence -= 9999;
        }

        return sprintf('ORD-%s-%04d', today()->format('Ymd'), $sequence);
    }

    protected function isDuplicateEntryException(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $message = strtolower($exception->getMessage());

        return in_array($sqlState, ['23000', '23505'], true)
            || str_contains($message, 'duplicate entry')
            || str_contains($message, 'unique constraint');
    }

    /**
     * @return array<int, array{id:int,name:string}>
     */
    protected function resolveAssignedCheckerPrinters(InventoryItem $inventoryItem): array
    {
        $inventoryItem->loadMissing('printers');

        return $inventoryItem->printers
            ?->filter(fn (Printer $printer): bool => $printer->is_active && $this->resolvePrinterServiceType($printer) === 'checker')
            ->map(fn (Printer $printer): array => [
                'id' => (int) $printer->id,
                'name' => (string) $printer->name,
            ])
            ->values()
            ->all() ?? [];
    }
}
