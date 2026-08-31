<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\BarOrder;
use App\Models\BarOrderItem;
use App\Models\Billing;
use App\Models\CustomerUser;
use App\Models\DailyAuthCode;
use App\Models\GeneralSetting;
use App\Models\InventoryItem;
use App\Models\KitchenOrder;
use App\Models\KitchenOrderItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosCategorySetting;
use App\Models\Printer;
use App\Models\Tabel;
use App\Models\TableReservation;
use App\Models\TableSession;
use App\Models\Tier;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\AccurateService;
use App\Services\DashboardSyncService;
use App\Services\PosStockConsumer;
use App\Services\PrinterService;
use App\Services\SessionBillingCalculator;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosController extends Controller
{
    public function __construct(
        protected PrinterService $printerService,
        protected AccurateService $accurateService,
        protected DashboardSyncService $dashboardSyncService,
        protected PosStockConsumer $posStockConsumer,
        protected SessionBillingCalculator $sessionBillingCalculator,
    ) {}

    public function index(Request $request)
    {
        $generalSettings = GeneralSetting::instance();
        $posSettings = PosCategorySetting::visibleInArea(Auth::user()?->resolveActiveAreaId());

        $allTypes = $posSettings->keys()->values()->all();

        // Get inventory items for configured category types
        $inventoryQuery = InventoryItem::whereIn('category_type', $allTypes ?: ['__none__'])
            ->where('is_active', true)
            ->where('is_visible_in_pos', true);

        // Search functionality
        if ($request->filled('search')) {
            $inventoryQuery->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('pos_name', 'like', '%'.$request->search.'%');
            });
        }

        // Map inventory items to product format
        $products = $inventoryQuery->get()->map(function ($item) use ($posSettings) {
            $setting = $posSettings->get($item->category_type);
            $isItemGroup = (bool) ($item->is_item_group ?? false);
            $isGroupSoldOut = (bool) ($item->is_group_sold_out ?? false);
            $isCountPortionPossible = (bool) ($item->is_count_portion_possible ?? false);
            $possiblePortions = null;
            $isAvailable = $item->is_visible_in_pos !== false && (bool) $item->is_active && ! ($isItemGroup && $isGroupSoldOut);

            if ($isItemGroup && $isCountPortionPossible) {
                $possiblePortions = $this->resolvePossiblePortions($item);
                $isAvailable = $isAvailable && $possiblePortions > 0;
            }

            return [
                'id' => 'item_'.$item->id,
                'item_id' => $item->id,
                'name' => $item->pos_name ?: $item->name,
                'category' => $item->category_type,
                'price' => $this->resolveZeroPricedItemAmount($item),
                'stock' => $isItemGroup ? null : ($item->stock_quantity ?? 0),
                'possible_portions' => $possiblePortions,
                'is_available' => $isAvailable,
                'is_menu' => (bool) $setting?->is_menu,
                'is_item_group' => $isItemGroup,
                'include_tax' => (bool) $item->include_tax,
                'include_service_charge' => (bool) $item->include_service_charge,
                'type' => 'item',
            ];
        })->values();

        // Get cart from session
        $cart = session()->get('pos_cart', []);
        $cartItemFlags = InventoryItem::query()
            ->whereIn('id', collect($cart)->map(fn ($item) => (int) str_replace('item_', '', (string) ($item['id'] ?? '0')))->filter()->values())
            ->get(['id', 'include_tax', 'include_service_charge'])
            ->keyBy('id');

        $cartItems = collect($cart)->map(function ($item) use ($cartItemFlags) {
            $inventoryItemId = (int) str_replace('item_', '', (string) ($item['id'] ?? '0'));
            $flags = $cartItemFlags->get($inventoryItemId);

            return [
                'id' => $item['id'],
                'name' => $item['name'],
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'preparation_location' => $item['preparation_location'] ?? 'direct',
                'assigned_printer_types' => collect($item['assigned_printer_types'] ?? [])->values()->all(),
                'assigned_checker_printers' => collect($item['assigned_checker_printers'] ?? [])->values()->all(),
                'assigned_checker_printer_ids' => collect($item['assigned_checker_printer_ids'] ?? [])->values()->all(),
                'include_tax' => (bool) ($flags?->include_tax ?? $item['include_tax'] ?? true),
                'include_service_charge' => (bool) ($flags?->include_service_charge ?? $item['include_service_charge'] ?? true),
            ];
        });

        $cartTotal = $cartItems->sum(function ($item) {
            return $item['price'] * $item['quantity'];
        });

        $user = Auth::user();
        $activeAreaId = $user ? $user->resolveActiveAreaId() : null;

        // Get active table sessions for booking customers
        $tableSessions = TableSession::with(['customer.profile', 'customer.customerUser', 'table.area', 'billing', 'waiter.profile', 'reservation'])
            ->where('status', 'active')
            ->whereNotNull('checked_in_at')
            ->whereNull('checked_out_at')
            ->when($activeAreaId, fn ($q) => $q->whereHas('table', fn ($t) => $t->where('area_id', $activeAreaId)))
            ->get();

        // Get tiers for discount calculation (highest level first)
        $tiers = Tier::orderBy('level', 'desc')->get();

        // Get waiter list for assignment in checkout modal
        $waiters = User::whereHas('roles', fn ($q) => $q->where('name', 'Waiter/Server'))
            ->with('profile')
            ->get()
            ->map(fn ($w) => ['id' => $w->id, 'name' => $w->profile?->name ?? $w->name]);

        // Get printer locations for counter selection
        $printerLocations = $this->getPrinterLocations();

        // Get current counter location from session
        $currentCounter = session()->get('pos_counter_location');

        // Tables without an active session (available for walk-in)
        $activetableIds = TableSession::where('status', 'active')
            ->when($activeAreaId, fn ($q) => $q->whereHas('table', fn ($t) => $t->where('area_id', $activeAreaId)))
            ->pluck('table_id');
        $availableTables = Tabel::with('area')
            ->where('is_active', true)
            ->when($activeAreaId, fn ($q) => $q->where('area_id', $activeAreaId))
            ->whereNotIn('id', $activetableIds)
            ->orderBy('table_number')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'table_number' => $t->table_number,
                'area' => $t->area?->name ?? '',
                'capacity' => $t->capacity,
                'minimum_charge' => (float) ($t->minimum_charge ?? 0),
            ]);

        return view('pos.index', compact('products', 'cartItems', 'cartTotal', 'tableSessions', 'tiers', 'waiters', 'printerLocations', 'currentCounter', 'posSettings', 'availableTables', 'generalSettings'));
    }

    /**
     * Walk-in: search existing customers by name or phone.
     */
    public function walkInSearchCustomers(Request $request): JsonResponse
    {
        $query = $request->get('q', '');

        $customers = User::with(['profile', 'customerUser'])
            ->whereHas('customerUser')
            ->whereDoesntHave('tableSessions', fn ($q) => $q->where('status', 'active'))
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhereHas('profile', fn ($pq) => $pq->where('phone', 'like', "%{$query}%"));
            })
            ->limit(10)
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'phone' => $u->profile?->phone ?? '',
                'customer_code' => $u->customerUser?->customer_code ?? '',
            ]);

        return response()->json(['customers' => $customers]);
    }

    /**
     * Walk-in: create a new guest customer (User + UserProfile + CustomerUser).
     */
    public function walkInCreateCustomer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        DB::beginTransaction();
        try {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $this->generateWalkInCustomerEmail($validated['name']),
                'password' => Hash::make(Str::random(16)),
            ]);

            $profile = UserProfile::create([
                'user_id' => $user->id,
                'phone' => $validated['phone'] ?? null,
            ]);

            CustomerUser::create([
                'user_id' => $user->id,
                'user_profile_id' => $profile->id,
                'total_visits' => 0,
                'lifetime_spending' => 0,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'customer' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $validated['phone'] ?? '',
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get valid printer service locations.
     */
    protected function getPrinterLocations(): array
    {
        return [
            'kitchen' => 'Kitchen',
            'bar' => 'Bar',
            'cashier' => 'Cashier',
            'checker' => 'Checker',
        ];
    }

    /**
     * Get recent orders for the history modal.
     */
    public function recentOrders(): JsonResponse
    {
        $orders = Order::with([
            'items',
            'tableSession.table.area',
            'tableSession.reservation.customer.profile',
            'tableSession.reservation.customer.customerUser',
            'customer.user',
        ])
            ->latest()
            ->limit(20)
            ->get()
            ->map(function ($order) {
                $session = $order->tableSession;
                $customer = $session?->reservation?->customer;
                $customerName = $customer?->profile?->name
                  ?? $customer?->customerUser?->name
                  ?? $order->customer?->user?->name
                  ?? 'Walk-in';

                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'ordered_at' => $order->ordered_at?->format('d M Y, H:i') ?? $order->created_at->format('d M Y, H:i'),
                    'total' => (float) $order->total,
                    'items_count' => $order->items->count(),
                    'customer_name' => $customerName,
                    'table' => $session?->table?->table_number ?? '-',
                    'area' => $session?->table?->area?->name ?? '-',
                    'type' => $session?->reservation ? 'Booking' : 'Walk-in',
                ];
            });

        return response()->json(['orders' => $orders]);
    }

    /**
     * Select counter location for current session.
     */
    public function selectCounter(Request $request): JsonResponse
    {
        $request->validate([
            'counter_location' => 'required|string',
        ]);

        session()->put('pos_counter_location', $request->counter_location);

        return response()->json([
            'success' => true,
            'message' => 'Counter location set successfully.',
            'counter_location' => $request->counter_location,
        ]);
    }

    public function addToCart(Request $request, $productId): JsonResponse
    {
        $posSettings = PosCategorySetting::allKeyed();

        $itemId = str_replace('item_', '', $productId);
        $inventoryItem = InventoryItem::with('printers')->find($itemId);
        $setting = $posSettings->get($inventoryItem?->category_type);

        if (! $inventoryItem || ! $setting || ! $setting->isVisibleInArea(Auth::user()?->resolveActiveAreaId())) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        $product = [
            'id' => $productId,
            'name' => $inventoryItem->pos_name ?: $inventoryItem->name,
            'price' => $this->resolveZeroPricedItemAmount($inventoryItem),
            'type' => 'item',
            'preparation_location' => $this->resolvePreparationLocationFromPrinters($inventoryItem) ?? $setting->preparation_location,
            'assigned_printer_types' => $this->resolveAssignedPrinterTypes($inventoryItem),
            'assigned_checker_printers' => $this->resolveAssignedCheckerPrinters($inventoryItem),
            'include_tax' => (bool) $inventoryItem->include_tax,
            'include_service_charge' => (bool) $inventoryItem->include_service_charge,
        ];

        $cart = session()->get('pos_cart', []);

        $nextQuantity = (int) ($cart[$productId]['quantity'] ?? 0) + 1;

        $isItemGroup = (bool) ($inventoryItem->is_item_group ?? false);
        $detailGroupComponents = $this->resolveDetailGroupComponents($inventoryItem, $setting);

        if ($inventoryItem->is_visible_in_pos === false || $inventoryItem->is_active === false || ($isItemGroup && (bool) $inventoryItem->is_group_sold_out)) {
            return response()->json([
                'success' => false,
                'message' => 'Item ini berstatus Sold Out / tidak tersedia.',
            ], 422);
        }

        $isCountPortionPossible = (bool) ($inventoryItem->is_count_portion_possible ?? false);

        if ($detailGroupComponents !== [] && $isCountPortionPossible) {
            $possiblePortions = $this->resolvePossiblePortions($inventoryItem, $detailGroupComponents);

            if ($nextQuantity > $possiblePortions) {
                return response()->json([
                    'success' => false,
                    'message' => "Stok bahan hanya cukup {$this->formatStockNumber($possiblePortions)} porsi.",
                ], 422);
            }
        } elseif (! $isItemGroup && (int) ($inventoryItem->stock_quantity ?? 0) < $nextQuantity) {
            return response()->json([
                'success' => false,
                'message' => 'Stok tidak mencukupi untuk item ini.',
            ], 422);
        }

        if (isset($cart[$productId])) {
            $cart[$productId]['quantity']++;
            $cart[$productId]['include_tax'] = $product['include_tax'];
            $cart[$productId]['include_service_charge'] = $product['include_service_charge'];
            $cart[$productId]['assigned_printer_types'] = $product['assigned_printer_types'];
            $cart[$productId]['assigned_checker_printers'] = $product['assigned_checker_printers'];
            $cart[$productId]['assigned_checker_printer_ids'] = collect($product['assigned_checker_printers'])->pluck('id')->values()->all();
        } else {
            $cart[$productId] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => $product['price'],
                'quantity' => 1,
                'preparation_location' => $product['preparation_location'],
                'assigned_printer_types' => $product['assigned_printer_types'],
                'assigned_checker_printers' => $product['assigned_checker_printers'],
                'assigned_checker_printer_ids' => collect($product['assigned_checker_printers'])->pluck('id')->values()->all(),
                'include_tax' => $product['include_tax'],
                'include_service_charge' => $product['include_service_charge'],
            ];
        }

        session()->put('pos_cart', $cart);

        return $this->cartResponse('Product added to cart', $cart);
    }

    public function updateCartQuantity(Request $request, $productId): JsonResponse
    {
        $cart = session()->get('pos_cart', []);
        $action = $request->input('action');

        if (isset($cart[$productId])) {
            if ($action === 'increase') {
                $itemId = str_replace('item_', '', $productId);
                $inventoryItem = InventoryItem::find($itemId);
                $setting = PosCategorySetting::allKeyed()->get($inventoryItem?->category_type);
                $nextQuantity = (int) $cart[$productId]['quantity'] + 1;

                if (! $inventoryItem || ! $setting || ! $setting->isVisibleInArea(Auth::user()?->resolveActiveAreaId())) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Product not found',
                    ], 404);
                }

                $isItemGroup = (bool) ($inventoryItem->is_item_group ?? false);
                $detailGroupComponents = $this->resolveDetailGroupComponents($inventoryItem, $setting);

                if ($inventoryItem->is_visible_in_pos === false || $inventoryItem->is_active === false || ($isItemGroup && (bool) $inventoryItem->is_group_sold_out)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Item ini berstatus Sold Out / tidak tersedia.',
                    ], 422);
                }

                $isCountPortionPossible = (bool) ($inventoryItem->is_count_portion_possible ?? false);

                if ($detailGroupComponents !== [] && $isCountPortionPossible) {
                    $possiblePortions = $this->resolvePossiblePortions($inventoryItem, $detailGroupComponents);

                    if ($nextQuantity > $possiblePortions) {
                        return response()->json([
                            'success' => false,
                            'message' => "Stok bahan hanya cukup {$this->formatStockNumber($possiblePortions)} porsi.",
                        ], 422);
                    }
                } elseif (! $isItemGroup && (int) ($inventoryItem->stock_quantity ?? 0) < $nextQuantity) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Stok tidak mencukupi untuk item ini.',
                    ], 422);
                }

                $cart[$productId]['quantity']++;
            } elseif ($action === 'decrease') {
                $cart[$productId]['quantity']--;
                if ($cart[$productId]['quantity'] <= 0) {
                    unset($cart[$productId]);
                }
            }
        }

        session()->put('pos_cart', $cart);

        return $this->cartResponse('Cart updated', $cart);
    }

    public function removeFromCart($productId): JsonResponse
    {
        $cart = session()->get('pos_cart', []);

        if (isset($cart[$productId])) {
            unset($cart[$productId]);
        }

        session()->put('pos_cart', $cart);

        return $this->cartResponse('Item removed from cart', $cart);
    }

    public function clearCart(): JsonResponse
    {
        session()->forget('pos_cart');

        return $this->cartResponse('Cart cleared', []);
    }

    public function previewCheckoutAvailability(): JsonResponse
    {
        $cart = session()->get('pos_cart', []);

        if (empty($cart)) {
            return response()->json([
                'success' => false,
                'can_checkout' => false,
                'message' => 'Keranjang kosong!',
                'menu_items' => [],
                'stock_issues' => [],
            ], 400);
        }

        return response()->json([
            'success' => true,
            ...$this->resolveCartAvailability($cart),
        ]);
    }

    /**
     * Normalisasi diskon per item menjadi map id→nilai.
     * Map baru (discount_items) menang; bila kosong, fallback legacy discount_item_ids + satu nilai.
     */
    protected function normalizeDiscountItems(array $raw = [], array $legacyIds = [], ?float $legacyValue = null): array
    {
        $map = [];
        foreach ($raw as $id => $value) {
            $map[(int) $id] = (float) $value;
        }

        if ($map === [] && $legacyIds !== []) {
            $v = (float) $legacyValue;
            foreach ($legacyIds as $id) {
                $map[(int) $id] = $v;
            }
        }

        return $map;
    }

    /**
     * Hitung diskon per-item dari cart — semua mode diskon menandai item.
     * FOC/Compliment → semua item ikut pct setting (bulk).
     * Per-item biasa → hanya item terpilih (map id→nilai, nilai beda per item).
     * Global % → semua item pct sama. Global nominal → pro-rata proporsional, sisa cents di item terakhir.
     *
     * @return array{pct_by_id: array<int, float>, amount_by_id: array<int, float>, total: float}
     */
    protected function resolveItemDiscounts(array $cart, string $mode, float $value = 0.0, ?array $itemValues = null, ?string $itemType = null): array
    {
        $pctById = [];
        $amountById = [];
        $total = 0.0;

        // Kumpulkan subtotal per id (2 pass dibutuhkan untuk pro-rata nominal global).
        $subtotalById = [];
        $grossTotal = 0.0;
        foreach ($cart as $productId => $cartItem) {
            $itemId = (int) str_replace('item_', '', (string) $productId);
            $subtotal = round((float) $cartItem['price'] * (int) $cartItem['quantity'], 2);
            $subtotalById[$itemId] = $subtotal;
            $grossTotal += $subtotal;
        }

        $selectedAll = in_array($mode, ['foc', 'global-percentage', 'global-nominal'], true);
        $remainingCents = $mode === 'global-nominal' ? (int) round($value * 100) : 0;
        $lastSelectedId = null;

        // Tentukan id yang didiskon + id terakhir (untuk menampung sisa cents).
        foreach ($subtotalById as $itemId => $_) {
            if ($selectedAll || ($itemValues !== null && array_key_exists($itemId, $itemValues))) {
                $lastSelectedId = $itemId;
            }
        }

        foreach ($subtotalById as $itemId => $subtotal) {
            $selected = $selectedAll || ($itemValues !== null && array_key_exists($itemId, $itemValues));
            if (! $selected) {
                $pctById[$itemId] = 0.0;
                $amountById[$itemId] = 0.0;

                continue;
            }

            if ($mode === 'foc' || $mode === 'global-percentage') {
                $pct = max((float) $value, 0.0);
                $amount = $pct > 0 ? round($subtotal * $pct / 100, 2) : 0.0;
            } elseif ($mode === 'global-nominal') {
                if ($itemId === $lastSelectedId) {
                    $amount = round(max($remainingCents, 0) / 100, 2);
                } else {
                    $shareCents = min((int) round($subtotal * 100), (int) floor($value * 100 * ($grossTotal > 0 ? $subtotal / $grossTotal : 0)));
                    $shareCents = min($shareCents, max($remainingCents, 0));
                    $amount = $shareCents / 100;
                    $remainingCents -= $shareCents;
                }
                $amount = min($amount, $subtotal);
                $pct = $subtotal > 0 ? round($amount / $subtotal * 100, 2) : 0.0;
            } else {
                // mode 'item': nilai per item dari map.
                $itemValue = (float) ($itemValues[$itemId] ?? 0);
                if ($itemType === 'nominal') {
                    $amount = min($itemValue, $subtotal);
                    $pct = $subtotal > 0 ? round($amount / $subtotal * 100, 2) : 0.0;
                } else {
                    $pct = max($itemValue, 0.0);
                    $amount = $pct > 0 ? round($subtotal * $pct / 100, 2) : 0.0;
                }
            }

            $pctById[$itemId] = $pct;
            $amountById[$itemId] = $amount;
            $total += $amount;
        }

        return ['pct_by_id' => $pctById, 'amount_by_id' => $amountById, 'total' => round($total, 2)];
    }

    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_type' => 'required|in:booking,walk-in',
            'customer_user_id' => 'required_if:customer_type,booking|nullable|exists:users,id',
            'table_id' => 'nullable|exists:tables,id',
            'discount_percentage' => 'nullable|integer|min:0|max:100',
            'discount_type' => 'nullable|in:none,percentage,nominal,item',
            'discount_nominal' => 'nullable|numeric|min:0',
            'discount_auth_code' => 'nullable|digits:4',
            'foc_comp_auth_code' => 'nullable|digits:4',
            'discount_item_ids' => 'nullable|array',
            'discount_item_ids.*' => 'integer',
            'discount_items' => 'nullable|array',
            'discount_items.*' => 'numeric|min:0',
            'discount_item_type' => 'nullable|in:percentage,nominal',
            'discount_item_value' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|in:cash,debit,kredit,qris,transfer,FOC,Compliment',
            'foc_comp_payment_method' => 'nullable|in:FOC,Compliment',
            'payment_mode' => 'nullable|in:normal,split,partial,debt',
            'partial_paid_amount' => 'nullable|numeric|min:0',
            'payment_reference_number' => 'nullable|string|max:100',
            'split_cash_amount' => 'nullable|numeric|min:0',
            'split_non_cash_amount' => 'nullable|numeric|min:0',
            'split_non_cash_method' => 'nullable|in:debit,kredit,qris,transfer,ewallet,lainnya',
            'split_non_cash_reference_number' => 'nullable|string|max:100',
            'split_second_non_cash_amount' => 'nullable|numeric|min:0',
            'split_second_non_cash_method' => 'nullable|in:debit,kredit,qris,transfer,ewallet,lainnya',
            'split_second_non_cash_reference_number' => 'nullable|string|max:100',
            'checker_printer_ids' => 'nullable|array',
            'checker_printer_ids.*' => 'integer|exists:printers,id',
            'auto_print_receipt' => 'nullable|boolean',
            'idempotency_key' => 'nullable|uuid',
        ]);

        $cartNotes = $request->input('cart_notes', []);
        $selectedCheckerPrinterIds = collect($request->input('checker_printer_ids', []))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $cart = session()->get('pos_cart', []);

        if (! empty($validated['idempotency_key'])) {
            $existingOrder = Order::query()
                ->where('idempotency_key', $validated['idempotency_key'])
                ->where('created_by', Auth::id())
                ->first();
            if ($existingOrder) {
                return $this->idempotentOrderResponse($existingOrder);
            }
        }

        if (empty($cart)) {
            return response()->json([
                'success' => false,
                'message' => 'Keranjang kosong!',
            ], 400);
        }

        $availability = $this->resolveCartAvailability($cart);

        if (! $availability['can_checkout']) {
            return response()->json([
                'success' => false,
                'message' => $availability['message'],
                'menu_items' => $availability['menu_items'],
                'stock_issues' => $availability['stock_issues'],
            ], 422);
        }

        $stockRequirements = $this->posStockConsumer->requirements($cart);

        DB::beginTransaction();
        try {
            // Only booking for now
            if ($validated['customer_type'] === 'booking') {
                // Find active table session
                $tableSession = TableSession::where('customer_id', $validated['customer_user_id'])
                    ->where('table_id', $validated['table_id'])
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();

                if (! $tableSession) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Table session tidak ditemukan atau tidak aktif!',
                    ], 404);
                }

                if ($tableSession->table_reservation_id && ! $tableSession->waiter_id) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Pilih waiter terlebih dahulu sebelum menyelesaikan transaksi.',
                    ], 422);
                }

                if ($tableSession->billing) {
                    Billing::query()->whereKey($tableSession->billing->id)->lockForUpdate()->first();
                }

                $this->posStockConsumer->consume($stockRequirements);

                // Generate order number
                $order = $this->createOrderWithRetry([
                    'table_session_id' => $tableSession->id,
                    'created_by' => Auth::id(),
                    'status' => 'pending',
                    'items_total' => 0,
                    'discount_amount' => 0,
                    'total' => 0,
                    'ordered_at' => now(),
                    'notes' => null,
                    'idempotency_key' => $validated['idempotency_key'] ?? null,
                ], 'ORD', 'booking');

                $orderNumber = (string) $order->order_number;
                $focCompPaymentMethod = $validated['foc_comp_payment_method'] ?? null;
                $discountType = $validated['discount_type'] ?? 'percentage';
                $discountPercentage = (int) ($validated['discount_percentage'] ?? 0);
                $discountNominal = (float) ($validated['discount_nominal'] ?? 0);
                $discountAuthCode = (string) ($validated['discount_auth_code'] ?? '');
                $focCompAuthCode = (string) ($validated['foc_comp_auth_code'] ?? '');
                $discountItemIds = collect($validated['discount_item_ids'] ?? [])->map(fn ($id): int => (int) $id)->unique()->values()->all();
                $discountItemType = $validated['discount_item_type'] ?? null;
                $discountItemValue = (float) ($validated['discount_item_value'] ?? 0);
                // Map id→nilai (nilai berbeda per item); fallback legacy bila map kosong.
                $discountItemValues = $this->normalizeDiscountItems($validated['discount_items'] ?? [], $discountItemIds, $discountItemValue);
                $generalSettings = GeneralSetting::instance();

                // Diskon FOC/Compliment dari setting (default: Compliment 100%, FOC 0%).
                if ($focCompPaymentMethod === 'Compliment') {
                    $discountPercentage = $generalSettings->complimentDiscountPercentage();
                } elseif ($focCompPaymentMethod === 'FOC') {
                    $discountPercentage = $generalSettings->focDiscountPercentage();
                }

                // Mutex: diskon per item tidak bisa digabung dengan FOC/Compliment atau diskon transaksi.
                if (count($discountItemValues) > 0 && ($focCompPaymentMethod === 'FOC' || $focCompPaymentMethod === 'Compliment' || $discountPercentage > 0)) {
                    throw ValidationException::withMessages([
                        'discount_item_ids' => 'Diskon per item tidak dapat digabung dengan diskon transaksi atau FOC/Compliment.',
                    ]);
                }

                // Auth code: regular discount selalu; FOC/Compliment sesuai setting (tidak otomatis).
                $isFocComp = in_array($focCompPaymentMethod, ['FOC', 'Compliment'], true);
                $focTypeRequiresAuth = ($focCompPaymentMethod === 'FOC' && $generalSettings->focRequiresAuthCode())
                    || ($focCompPaymentMethod === 'Compliment' && $generalSettings->complimentRequiresAuthCode());

                $requiresAuthCode = $focTypeRequiresAuth || (! $isFocComp && ($discountPercentage > 0 || count($discountItemValues) > 0));

                if ($requiresAuthCode) {
                    // FOC/Compliment pakai field auth sendiri; diskon biasa pakai discount_auth_code.
                    $authCode = $isFocComp ? $focCompAuthCode : $discountAuthCode;

                    if ($authCode === '') {
                        throw ValidationException::withMessages([
                            $isFocComp ? 'foc_comp_auth_code' : 'discount_auth_code' => $isFocComp
                                ? 'Auth code wajib diisi untuk FOC, Compliment.'
                                : 'Auth code wajib diisi untuk diskon.',
                        ]);
                    }

                    $today = now()->format('Y-m-d');
                    $authRecord = DailyAuthCode::forDate($today);

                    if ($authCode !== $authRecord->active_code) {
                        throw ValidationException::withMessages([
                            $isFocComp ? 'foc_comp_auth_code' : 'discount_auth_code' => $isFocComp
                                ? 'Auth code FOC / Compliment tidak valid.'
                                : 'Auth code diskon tidak valid.',
                        ]);
                    }
                }

                $itemsTotal = 0;
                $serviceChargeBase = 0;
                $taxBase = 0;
                $taxAndServiceBase = 0;
                $selectedDiscountTotal = 0.0;
                $generalSettings = GeneralSetting::instance();
                $taxPercentage = (float) $generalSettings->tax_percentage;
                $serviceChargePercentage = (float) $generalSettings->service_charge_percentage;

                // Semua mode menandai item: FOC → semua; per-item → terpilih; global %/nominal → semua (pro-rata).
                $discountMode = $isFocComp
                    ? 'foc'
                    : (count($discountItemValues) > 0
                        ? 'item'
                        : ($discountType === 'nominal' ? 'global-nominal' : 'global-percentage'));
                $discountValue = $isFocComp
                    ? (float) $discountPercentage
                    : (count($discountItemValues) > 0
                        ? 0.0
                        : ($discountType === 'nominal' ? (float) $discountNominal : (float) $discountPercentage));
                $itemDiscounts = $this->resolveItemDiscounts($cart, $discountMode, $discountValue, $discountItemValues, $discountItemType);
                $discountPctById = $itemDiscounts['pct_by_id'];
                $discountAmountById = $itemDiscounts['amount_by_id'];

                // Create Order Items from cart
                foreach ($cart as $productId => $cartItem) {
                    $itemId = str_replace('item_', '', $productId);
                    $inventoryItem = InventoryItem::with('printers')->find($itemId);

                    if (! $inventoryItem) {
                        continue;
                    }

                    $inventoryItemId = $inventoryItem->id;
                    $itemName = filled($inventoryItem->pos_name)
                        ? (string) $inventoryItem->pos_name
                        : (string) $inventoryItem->name;
                    $itemCode = $inventoryItem->code;
                    $price = $this->resolveZeroPricedItemAmount($inventoryItem);
                    $preparationLocation = $this->resolvePreparationLocationFromPrinters($inventoryItem);

                    $quantity = $cartItem['quantity'];
                    $subtotal = $price * $quantity;
                    $itemsTotal += $subtotal;
                    // FOC → semua item; per-item → terpilih; global %/nominal → semua item (pro-rata).
                    $linePct = (float) ($discountPctById[$inventoryItemId] ?? 0);
                    $lineDiscount = (float) ($discountAmountById[$inventoryItemId] ?? 0);
                    $netSubtotal = max($subtotal - $lineDiscount, 0);
                    $selectedDiscountTotal += $lineDiscount;
                    $includeTax = (bool) $inventoryItem->include_tax;
                    $includeServiceCharge = (bool) $inventoryItem->include_service_charge;

                    if ($includeServiceCharge) {
                        $serviceChargeBase += $netSubtotal;
                    }

                    if ($includeTax) {
                        $taxBase += $netSubtotal;
                    }

                    if ($includeTax && $includeServiceCharge) {
                        $taxAndServiceBase += $netSubtotal;
                    }

                    $itemTaxAmount = $includeTax
                        ? round($netSubtotal * ($taxPercentage / 100), 2)
                        : 0;
                    $itemServiceChargeAmount = $includeServiceCharge
                        ? round(($netSubtotal + ($includeTax ? $itemTaxAmount : 0)) * ($serviceChargePercentage / 100), 2)
                        : 0;

                    // Create Order Item
                    OrderItem::create([
                        'order_id' => $order->id,
                        'inventory_item_id' => $inventoryItemId,
                        'item_name' => $itemName,
                        'item_code' => $itemCode,
                        'quantity' => $quantity,
                        'price' => $price,
                        'subtotal' => $subtotal,
                        'discount_amount' => $lineDiscount,
                        'discount_reason' => $lineDiscount > 0 ? 'Diskon item' : null,
                        'discount_approval_id' => null,
                        'is_discount' => $lineDiscount > 0,
                        'discount_pct' => $lineDiscount > 0 ? $linePct : 0,
                        'tax_amount' => $itemTaxAmount,
                        'service_charge_amount' => $itemServiceChargeAmount,
                        'preparation_location' => $preparationLocation,
                        'status' => 'pending',
                        'notes' => $cartNotes[$productId] ?? null,
                    ]);

                }

                // Semua mode menandai item → diskon transaksi = jumlah rupiah per item (tanpa double count).
                $orderTotals = $this->calculateWalkInTotals($itemsTotal, 0, $selectedDiscountTotal, [
                    'service_charge_base' => $serviceChargeBase,
                    'tax_base' => $taxBase,
                    'tax_and_service_base' => $taxAndServiceBase,
                ], true);
                $discountAmount = (float) $orderTotals['discount_amount'];
                $finalTotal = max($itemsTotal - $discountAmount, 0);

                // Update Order totals
                $order->update([
                    'items_total' => $itemsTotal,
                    'discount_amount' => $discountAmount,
                    'total' => $finalTotal,
                ]);

                // Route items to Kitchen/Bar and print tickets
                $this->routeOrderToPreparation($order, $tableSession, $orderNumber, null, $selectedCheckerPrinterIds);

                // Update Billing
                if ($tableSession->billing) {
                    $billing = $tableSession->billing;
                    $tableSession->loadMissing('orders.items.inventoryItem');

                    $sessionTotals = $this->calculateSessionBillingTotals(
                        $tableSession,
                        (float) $billing->discount_amount,
                        (float) $billing->minimum_charge,
                    );

                    $billing->update([
                        'area_id' => $billing->area_id ?? $tableSession->table?->area_id ?? auth()->user()?->resolveActiveArea()?->id,
                        'orders_total' => (float) $sessionTotals['orders_total'],
                        'subtotal' => (float) $sessionTotals['subtotal'],
                        'tax_percentage' => (float) $sessionTotals['tax_percentage'],
                        'tax' => (float) $sessionTotals['tax'],
                        'service_charge_percentage' => (float) $sessionTotals['service_charge_percentage'],
                        'service_charge' => (float) $sessionTotals['service_charge'],
                        'grand_total' => (float) $sessionTotals['grand_total'],
                        'foc_comp_payment_method' => $focCompPaymentMethod,
                    ]);
                }

                DB::commit();

                try {
                    $this->dashboardSyncService->sync();
                } catch (\Throwable $e) {
                }

                // Clear cart
                session()->forget('pos_cart');

                return response()->json([
                    'success' => true,
                    'message' => "Order #{$orderNumber} berhasil dibuat!",
                    'order_number' => $orderNumber,
                    'order_id' => $order->id,
                    'items_total' => $itemsTotal,
                    'discount_amount' => $discountAmount,
                    'service_charge_percentage' => (float) $orderTotals['service_charge_percentage'],
                    'service_charge' => (float) $orderTotals['service_charge'],
                    'tax_percentage' => (float) $orderTotals['tax_percentage'],
                    'tax' => (float) $orderTotals['tax'],
                    'total' => (float) $orderTotals['grand_total'],
                    'formatted_total' => 'Rp '.number_format((float) $orderTotals['grand_total'], 0, ',', '.'),
                    'receipt_printed' => false,
                ]);
            }

            // Walk-in: no table session, immediate payment + receipt
            if ($validated['customer_type'] === 'walk-in') {
                $autoPrintReceipt = array_key_exists('auto_print_receipt', $validated)
                    ? (bool) $validated['auto_print_receipt']
                    : true;

                $request->validate([
                    'walk_in_customer_id' => 'required|exists:users,id',
                ]);

                $customerId = (int) $request->input('walk_in_customer_id');

                // Resolve CustomerUser for kitchen/bar checker
                $customerUser = CustomerUser::where('user_id', $customerId)->first();

                $orderNumber = $this->generateDailyOrderNumber('WALKIN', 'walk-in');

                $paymentMode = $validated['payment_mode'] ?? 'normal';
                $focCompPaymentMethod = $validated['foc_comp_payment_method'] ?? null;
                $discountType = $validated['discount_type'] ?? 'none';
                $discountPercentage = 0;
                $discountNominal = 0;
                $discountAuthCode = (string) ($validated['discount_auth_code'] ?? '');
                $focCompAuthCode = (string) ($validated['foc_comp_auth_code'] ?? '');
                $discountItemIds = collect($validated['discount_item_ids'] ?? [])->map(fn ($id): int => (int) $id)->unique()->values()->all();
                $discountItemType = $validated['discount_item_type'] ?? null;
                $discountItemValue = (float) ($validated['discount_item_value'] ?? 0);
                // Map id→nilai (nilai berbeda per item); fallback legacy bila map kosong.
                $discountItemValues = $this->normalizeDiscountItems($validated['discount_items'] ?? [], $discountItemIds, $discountItemValue);
                $generalSettings = GeneralSetting::instance();
                $isFocComp = in_array($focCompPaymentMethod, ['FOC', 'Compliment'], true);

                // Mutex: diskon per item tidak bisa digabung dengan FOC/Compliment atau diskon transaksi global.
                if (count($discountItemValues) > 0 && ($isFocComp || in_array($discountType, ['percentage', 'nominal'], true))) {
                    throw ValidationException::withMessages([
                        'discount_item_ids' => 'Diskon per item tidak dapat digabung dengan diskon transaksi atau FOC/Compliment.',
                    ]);
                }

                // Diskon FOC/Compliment dari setting (default: Compliment 100%, FOC 0%).
                if ($focCompPaymentMethod === 'Compliment') {
                    $discountType = 'percentage';
                    $discountPercentage = $generalSettings->complimentDiscountPercentage();
                } elseif ($focCompPaymentMethod === 'FOC') {
                    $discountType = $generalSettings->focDiscountPercentage() > 0 ? 'percentage' : 'none';
                    $discountPercentage = $generalSettings->focDiscountPercentage();
                }

                if ($discountType === 'percentage') {
                    // FOC/Compliment boleh 0% (dari setting); diskon manual wajib > 0.
                    if (! $isFocComp && $discountPercentage <= 0) {
                        $discountPercentage = (float) ($validated['discount_percentage'] ?? 0);
                    }

                    if ($discountPercentage < 0 || $discountPercentage > 100) {
                        throw ValidationException::withMessages([
                            'discount_percentage' => 'Diskon persentase harus antara 0 dan 100.',
                        ]);
                    }

                    if (! $isFocComp && $discountPercentage <= 0) {
                        throw ValidationException::withMessages([
                            'discount_percentage' => 'Diskon persentase harus lebih dari 0 dan maksimal 100.',
                        ]);
                    }
                }

                if ($discountType === 'nominal') {
                    $discountNominal = (float) ($validated['discount_nominal'] ?? 0);

                    if ($discountNominal <= 0) {
                        throw ValidationException::withMessages([
                            'discount_nominal' => 'Diskon nominal harus lebih dari 0.',
                        ]);
                    }
                }

                // Auth code: regular discount selalu; FOC/Compliment sesuai setting (tidak otomatis).
                $focTypeRequiresAuth = ($focCompPaymentMethod === 'FOC' && $generalSettings->focRequiresAuthCode())
                    || ($focCompPaymentMethod === 'Compliment' && $generalSettings->complimentRequiresAuthCode());

                $requiresAuthCode = $focTypeRequiresAuth || (! $isFocComp && ($discountType !== 'none' || count($discountItemValues) > 0));

                if ($requiresAuthCode) {
                    // FOC/Compliment pakai field auth sendiri; diskon biasa pakai discount_auth_code.
                    $authCode = $isFocComp ? $focCompAuthCode : $discountAuthCode;

                    if ($authCode === '') {
                        throw ValidationException::withMessages([
                            $isFocComp ? 'foc_comp_auth_code' : 'discount_auth_code' => $isFocComp
                                ? 'Auth code wajib diisi untuk FOC, Compliment.'
                                : 'Auth code wajib diisi untuk diskon.',
                        ]);
                    }

                    $today = now()->format('Y-m-d');
                    $authRecord = DailyAuthCode::forDate($today);

                    if ($authCode !== $authRecord->active_code) {
                        throw ValidationException::withMessages([
                            $isFocComp ? 'foc_comp_auth_code' : 'discount_auth_code' => $isFocComp
                                ? 'Auth code FOC / Compliment tidak valid.'
                                : 'Auth code diskon tidak valid.',
                        ]);
                    }
                }

                $paymentMethod = $paymentMode === 'split'
                  ? null
                  : ($validated['payment_method'] ?? null);

                // FOC/Compliment → payment_method otomatis, tanpa metode normal.
                if (in_array($focCompPaymentMethod, ['FOC', 'Compliment'], true)) {
                    $paymentMethod = $focCompPaymentMethod;
                }

                $paymentReferenceNumber = $paymentMode === 'normal'
                  ? (in_array($paymentMethod, ['cash', 'FOC', 'Compliment'], true) ? null : ($validated['payment_reference_number'] ?? null))
                  : null;

                if ($paymentMode === 'normal' && ! in_array($paymentMethod, ['cash', 'FOC', 'Compliment'], true) && blank($paymentReferenceNumber)) {
                    throw ValidationException::withMessages([
                        'payment_reference_number' => 'Nomor referensi pembayaran non-cash wajib diisi.',
                    ]);
                }

                $splitCashAmount = null;
                $splitNonCashAmount = null;
                $splitNonCashMethod = null;
                $splitNonCashReferenceNumber = null;
                $splitSecondNonCashAmount = null;
                $splitSecondNonCashMethod = null;
                $splitSecondNonCashReferenceNumber = null;

                if ($paymentMode === 'split') {
                    $splitCashAmount = (float) ($validated['split_cash_amount'] ?? 0);
                    $splitNonCashAmount = (float) ($validated['split_non_cash_amount'] ?? 0);
                    $splitNonCashMethod = $validated['split_non_cash_method'] ?? null;
                    $splitNonCashReferenceNumber = $validated['split_non_cash_reference_number'] ?? null;
                    $splitSecondNonCashAmount = (float) ($validated['split_second_non_cash_amount'] ?? 0);
                    $splitSecondNonCashMethod = $validated['split_second_non_cash_method'] ?? null;
                    $splitSecondNonCashReferenceNumber = $validated['split_second_non_cash_reference_number'] ?? null;
                    $activeNonCashCount = collect([
                        ['amount' => $splitNonCashAmount, 'method' => $splitNonCashMethod, 'reference' => $splitNonCashReferenceNumber],
                        ['amount' => $splitSecondNonCashAmount, 'method' => $splitSecondNonCashMethod, 'reference' => $splitSecondNonCashReferenceNumber],
                    ])->filter(fn (array $entry): bool => (float) $entry['amount'] > 0)->count();

                    $hasCash = $splitCashAmount > 0;

                    if ($splitCashAmount < 0 || $splitNonCashAmount < 0 || $splitSecondNonCashAmount < 0) {
                        throw ValidationException::withMessages([
                            'split_total' => 'Nominal split bill tidak boleh minus.',
                        ]);
                    }

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

                    if ($splitNonCashAmount > 0 && blank($splitNonCashMethod)) {
                        throw ValidationException::withMessages([
                            'split_non_cash_method' => 'Metode non-cash pertama untuk split bill wajib dipilih.',
                        ]);
                    }

                    if ($splitNonCashAmount > 0 && blank($splitNonCashReferenceNumber)) {
                        throw ValidationException::withMessages([
                            'split_non_cash_reference_number' => 'Nomor referensi non-cash pertama untuk split bill wajib diisi.',
                        ]);
                    }

                    if ($splitSecondNonCashAmount > 0 && blank($splitSecondNonCashMethod)) {
                        throw ValidationException::withMessages([
                            'split_second_non_cash_method' => 'Metode non-cash kedua untuk split bill wajib dipilih.',
                        ]);
                    }

                    if ($splitSecondNonCashAmount > 0 && blank($splitSecondNonCashReferenceNumber)) {
                        throw ValidationException::withMessages([
                            'split_second_non_cash_reference_number' => 'Nomor referensi non-cash kedua untuk split bill wajib diisi.',
                        ]);
                    }
                }

                $order = $this->createOrderWithRetry([
                    'table_session_id' => null,
                    'customer_user_id' => $customerUser?->id,
                    'created_by' => Auth::id(),
                    'status' => 'pending',
                    'items_total' => 0,
                    'discount_amount' => 0,
                    'total' => 0,
                    'ordered_at' => now(),
                    'payment_method' => $paymentMethod,
                    'payment_mode' => $paymentMode,
                    'payment_reference_number' => $paymentReferenceNumber,
                    'foc_comp_payment_method' => $focCompPaymentMethod,
                    'idempotency_key' => $validated['idempotency_key'] ?? null,
                ], 'WALKIN', 'walk-in');

                $this->posStockConsumer->consume($stockRequirements);

                $orderNumber = (string) $order->order_number;

                $itemsTotal = 0;
                $serviceChargeBase = 0;
                $taxBase = 0;
                $taxAndServiceBase = 0;
                $selectedDiscountTotal = 0.0;
                $generalSettings = GeneralSetting::instance();
                $taxPercentage = (float) $generalSettings->tax_percentage;
                $serviceChargePercentage = (float) $generalSettings->service_charge_percentage;

                // Semua mode menandai item: FOC → semua; per-item → terpilih; global %/nominal → semua (pro-rata).
                $discountMode = $isFocComp
                    ? 'foc'
                    : (count($discountItemValues) > 0
                        ? 'item'
                        : ($discountType === 'nominal' ? 'global-nominal' : 'global-percentage'));
                $discountValue = $isFocComp
                    ? (float) $discountPercentage
                    : (count($discountItemValues) > 0
                        ? 0.0
                        : ($discountType === 'nominal' ? (float) $discountNominal : (float) $discountPercentage));
                $itemDiscounts = $this->resolveItemDiscounts($cart, $discountMode, $discountValue, $discountItemValues, $discountItemType);
                $discountPctById = $itemDiscounts['pct_by_id'];
                $discountAmountById = $itemDiscounts['amount_by_id'];
                foreach ($cart as $productId => $cartItem) {
                    $itemId = str_replace('item_', '', $productId);
                    $inventoryItem = InventoryItem::with('printers')->find($itemId);
                    if (! $inventoryItem) {
                        continue;
                    }
                    $inventoryItemId = $inventoryItem->id;
                    $itemName = filled($inventoryItem->pos_name)
                        ? (string) $inventoryItem->pos_name
                        : (string) $inventoryItem->name;
                    $itemCode = $inventoryItem->code;
                    $price = $this->resolveZeroPricedItemAmount($inventoryItem);
                    $preparationLocation = $this->resolvePreparationLocationFromPrinters($inventoryItem);
                    $quantity = $cartItem['quantity'];
                    $subtotal = $price * $quantity;
                    $itemsTotal += $subtotal;
                    // FOC → semua item; per-item → terpilih; global %/nominal → semua item (pro-rata).
                    $linePct = (float) ($discountPctById[$inventoryItemId] ?? 0);
                    $lineDiscount = (float) ($discountAmountById[$inventoryItemId] ?? 0);
                    $netSubtotal = max($subtotal - $lineDiscount, 0);
                    $selectedDiscountTotal += $lineDiscount;
                    $includeTax = (bool) $inventoryItem->include_tax;
                    $includeServiceCharge = (bool) $inventoryItem->include_service_charge;

                    if ($includeServiceCharge) {
                        $serviceChargeBase += $netSubtotal;
                    }

                    if ($includeTax) {
                        $taxBase += $netSubtotal;
                    }

                    if ($includeTax && $includeServiceCharge) {
                        $taxAndServiceBase += $netSubtotal;
                    }

                    $itemTaxAmount = $includeTax
                        ? round($netSubtotal * ($taxPercentage / 100), 2)
                        : 0;
                    $itemServiceChargeAmount = $includeServiceCharge
                        ? round(($netSubtotal + ($includeTax ? $itemTaxAmount : 0)) * ($serviceChargePercentage / 100), 2)
                        : 0;

                    OrderItem::create([
                        'order_id' => $order->id,
                        'inventory_item_id' => $inventoryItemId,
                        'item_name' => $itemName,
                        'item_code' => $itemCode,
                        'quantity' => $quantity,
                        'price' => $price,
                        'subtotal' => $subtotal,
                        'discount_amount' => $lineDiscount,
                        'discount_reason' => $lineDiscount > 0 ? 'Diskon item' : null,
                        'discount_approval_id' => null,
                        'is_discount' => $lineDiscount > 0,
                        'discount_pct' => $lineDiscount > 0 ? $linePct : 0,
                        'tax_amount' => $itemTaxAmount,
                        'service_charge_amount' => $itemServiceChargeAmount,
                        'preparation_location' => $preparationLocation,
                        'status' => 'pending',
                        'notes' => $cartNotes[$productId] ?? null,
                    ]);

                }

                // Base diskon dari gross itemsTotal (bukan net) agar clamp tidak memotong diskon per-item.
                $baseTotalsForDiscount = $this->calculateWalkInTotals($itemsTotal, 0, 0, [
                    'service_charge_base' => $serviceChargeBase,
                    'tax_base' => $taxBase,
                    'tax_and_service_base' => $taxAndServiceBase,
                ], true);

                $discountBaseTotal = (float) ($baseTotalsForDiscount['discount_base_total'] ?? $itemsTotal);

                // Semua mode menandai item → diskon transaksi = jumlah rupiah per item.
                $requestedDiscountAmount = $selectedDiscountTotal;

                $requestedDiscountAmount = min(max($requestedDiscountAmount, 0), $discountBaseTotal);

                $totals = $this->calculateWalkInTotals($itemsTotal, 0, $requestedDiscountAmount, [
                    'service_charge_base' => $serviceChargeBase,
                    'tax_base' => $taxBase,
                    'tax_and_service_base' => $taxAndServiceBase,
                ], true);

                if ($paymentMode === 'split') {
                    $grandTotal = round((float) $totals['grand_total'], 0);
                    $splitTotal = round((float) ($splitCashAmount ?? 0) + (float) ($splitNonCashAmount ?? 0) + (float) ($splitSecondNonCashAmount ?? 0), 0);

                    if (abs($splitTotal - $grandTotal) > 0.01) {
                        throw ValidationException::withMessages([
                            'split_total' => 'Total split harus sama dengan grand total.',
                        ]);
                    }
                }

                $order->update([
                    'items_total' => $itemsTotal,
                    'discount_amount' => $totals['discount_amount'],
                    'total' => $totals['grand_total'],
                ]);

                $isPartialPayment = in_array($paymentMode, ['partial', 'debt'], true);
                $partialPaidAmount = (float) ($validated['partial_paid_amount'] ?? 0);

                if ($isPartialPayment) {
                    if ($partialPaidAmount <= 0 || $partialPaidAmount >= (float) $totals['grand_total']) {
                        throw ValidationException::withMessages([
                            'partial_paid_amount' => 'Nominal bayar sebagian/DP harus lebih besar dari 0 dan kurang dari total tagihan.',
                        ]);
                    }
                    $paidAmount = $partialPaidAmount;
                    $remainingBalance = max(0, (float) $totals['grand_total'] - $partialPaidAmount);
                    $billingStatus = 'partial_paid';
                    $isDebt = true;
                } else {
                    $paidAmount = (float) $totals['grand_total'];
                    $remainingBalance = 0;
                    $billingStatus = 'paid';
                    $isDebt = false;
                }

                $transactionCode = $this->createWalkInBillingWithRetry([
                    'table_session_id' => null,
                    'order_id' => $order->id,
                    'is_walk_in' => true,
                    'is_booking' => false,
                    'minimum_charge' => 0,
                    'orders_total' => (float) $itemsTotal,
                    'subtotal' => (float) $totals['subtotal_after_discount'],
                    'tax' => (float) $totals['tax'],
                    'tax_percentage' => (float) $totals['tax_percentage'],
                    'service_charge' => (float) $totals['service_charge'],
                    'service_charge_percentage' => (float) $totals['service_charge_percentage'],
                    'discount_amount' => (float) $totals['discount_amount'],
                    'grand_total' => (float) $totals['grand_total'],
                    'paid_amount' => $paidAmount,
                    'remaining_balance' => $remainingBalance,
                    'is_debt' => $isDebt,
                    'billing_status' => $billingStatus,
                    'paid_at' => now('Asia/Jakarta'),
                    'payment_method' => $paymentMethod ?? 'cash',
                    'foc_comp_payment_method' => $focCompPaymentMethod,
                    'payment_reference_number' => $paymentReferenceNumber,
                    'payment_mode' => $paymentMode,
                    'split_cash_amount' => $splitCashAmount,
                    'split_debit_amount' => $splitNonCashAmount,
                    'split_non_cash_method' => $splitNonCashMethod,
                    'split_non_cash_reference_number' => $splitNonCashReferenceNumber,
                    'split_second_non_cash_amount' => $splitSecondNonCashAmount,
                    'split_second_non_cash_method' => $splitSecondNonCashMethod,
                    'split_second_non_cash_reference_number' => $splitSecondNonCashReferenceNumber,
                ]);

                $createdBilling = Billing::where('transaction_code', $transactionCode)->first();
                if ($createdBilling) {
                    $payments = $paymentMode === 'split'
                        ? collect([
                            ['amount' => $splitCashAmount, 'method' => 'cash', 'reference' => null],
                            ['amount' => $splitNonCashAmount, 'method' => $splitNonCashMethod, 'reference' => $splitNonCashReferenceNumber],
                            ['amount' => $splitSecondNonCashAmount, 'method' => $splitSecondNonCashMethod, 'reference' => $splitSecondNonCashReferenceNumber],
                        ])->filter(fn (array $payment): bool => (float) $payment['amount'] > 0)
                        : collect([['amount' => $paidAmount, 'method' => $paymentMethod ?? 'cash', 'reference' => $paymentReferenceNumber]]);

                    foreach ($payments as $payment) {
                        \App\Models\BillingPayment::create([
                            'billing_id' => $createdBilling->id,
                            'amount_paid' => $payment['amount'],
                            'payment_method' => $payment['method'],
                            'payment_reference_number' => $payment['reference'],
                            'payment_type' => $isDebt ? 'initial_partial' : 'full_payment',
                            'notes' => $isDebt ? 'Pembayaran DP/Parsial saat checkout POS' : 'Pembayaran saat checkout POS',
                            'created_by' => Auth::id(),
                            'paid_at' => now('Asia/Jakarta'),
                        ]);
                    }
                }

                if ($customerUser) {
                    $customerUser->increment('total_visits');
                    // FOC/Compliment tidak masuk spending (bukan revenue).
                    if (! in_array((string) ($focCompPaymentMethod ?? ''), ['FOC', 'Compliment'], true)) {
                        $customerUser->increment('lifetime_spending', (float) $totals['grand_total']);
                    }
                }

                // Route to kitchen/bar checkers (no table session)
                $this->routeOrderToPreparation($order, null, $orderNumber, $customerUser?->id, $selectedCheckerPrinterIds);

                DB::commit();

                try {
                    $this->dashboardSyncService->sync();
                } catch (\Throwable $e) {
                }

                session()->forget('pos_cart');

                // Push to Accurate: Sales Order + Sales Invoice (non-blocking)
                $this->pushOrderToAccurate($order, $customerUser, $totals['grand_total']);

                $receiptPrinted = $autoPrintReceipt
                    ? $this->printOrderReceipt($order, 'walk_in')
                    : false;

                return response()->json([
                    'success' => true,
                    'message' => "Order #{$orderNumber} (Walk-in) berhasil dibuat!",
                    'order_number' => $orderNumber,
                    'order_id' => $order->id,
                    'items_total' => $itemsTotal,
                    'discount_amount' => $totals['discount_amount'],
                    'service_charge_percentage' => $totals['service_charge_percentage'],
                    'service_charge' => $totals['service_charge'],
                    'tax_percentage' => $totals['tax_percentage'],
                    'tax' => $totals['tax'],
                    'total' => $totals['grand_total'],
                    'formatted_total' => 'Rp '.number_format($totals['grand_total'], 0, ',', '.'),
                    'receipt_printed' => $receiptPrinted,
                    'receipt_url' => route('admin.pos.order-receipt', $order),
                ]);
            }

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Jenis customer tidak valid.',
            ], 422);
        } catch (ValidationException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->validator->errors()->first() ?: 'Data checkout tidak valid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();

            if (! empty($validated['idempotency_key'])) {
                $existingOrder = Order::query()
                    ->where('idempotency_key', $validated['idempotency_key'])
                    ->where('created_by', Auth::id())
                    ->first();
                if ($existingOrder) {
                    return $this->idempotentOrderResponse($existingOrder);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show the receipt preview page for a POS order.
     */
    public function orderReceipt(Order $order): \Illuminate\View\View
    {
        $order->load(['items.inventoryItem', 'customer.user', 'customer.profile', 'tableSession.table']);

        $billing = Billing::query()
            ->where('order_id', $order->id)
            ->latest('id')
            ->first();

        if (! $billing) {
            $billing = new Billing([
                'transaction_code' => $order->order_number,
                'updated_at' => $order->ordered_at,
                'minimum_charge' => 0,
                'orders_total' => (float) $order->items_total,
                'subtotal' => (float) $order->items_total,
                'discount_amount' => (float) $order->discount_amount,
                'service_charge' => 0,
                'service_charge_percentage' => 0,
                'tax' => 0,
                'tax_percentage' => 0,
                'grand_total' => (float) $order->total,
                'payment_mode' => $order->payment_mode,
                'payment_method' => $order->payment_method,
                'payment_reference_number' => $order->payment_reference_number,
            ]);
        }

        $allItems = $order->items->map(function ($item): array {
            return [
                'name' => $item->item_name,
                'qty' => (int) $item->quantity,
                'price' => (float) $item->price,
                'subtotal' => (float) $item->subtotal,
                'discount_amount' => (float) $item->discount_amount,
            ];
        })->values();

        $customerName = $order->customer?->user?->name
          ?? $order->customer?->profile?->name
          ?? 'Walk-in';

        $tableDisplay = $order->tableSession?->table?->table_number ?? '-';
        $receiptType = $order->table_session_id ? 'BOOKING' : 'WALK-IN';

        return view('bookings.receipt', [
            'booking' => null,
            'billing' => $billing,
            'allItems' => $allItems,
            'customerName' => $customerName,
            'receiptType' => $receiptType,
            'tableDisplay' => $tableDisplay,
            'cashierName' => auth()->user()?->name ?? 'Admin',
            'printedAt' => ($billing->updated_at ?? $order->ordered_at ?? now())?->format('d M Y H:i') ?? now()->format('d M Y H:i'),
        ]);
    }

    /**
     * @return array<string, float>
     */
    protected function calculateSessionBillingTotals(TableSession $session, float $discountAmount, float $minimumCharge): array
    {
        $totals = $this->sessionBillingCalculator->calculate($session, $discountAmount, $minimumCharge);

        return $totals + [
            'minimum_charge' => $minimumCharge,
            'subtotal_after_discount' => max($totals['subtotal'] - min($totals['discount_amount'], $totals['subtotal']), 0),
            'discount_base_total' => $totals['subtotal'] + $totals['service_charge'] + $totals['tax'],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $orders
     * @return array<string, float>
     */
    protected function resolveSessionChargeableBases($orders): array
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

    protected function calculateWalkInTotals(
        float|int $itemsTotal,
        int $discountPercentage = 0,
        ?float $discountAmountOverride = null,
        ?array $chargeableBases = null,
        bool $applyServiceChargeOnTaxBase = false,
    ): array {
        $settings = GeneralSetting::instance();
        $itemsTotalFloat = (float) $itemsTotal;

        $serviceChargeBase = (float) ($chargeableBases['service_charge_base'] ?? $itemsTotalFloat);
        $taxBase = (float) ($chargeableBases['tax_base'] ?? $itemsTotalFloat);
        $taxAndServiceBase = (float) ($chargeableBases['tax_and_service_base'] ?? $serviceChargeBase);

        $taxRate = (float) $settings->tax_percentage / 100;
        $serviceChargeRate = (float) $settings->service_charge_percentage / 100;

        if ($applyServiceChargeOnTaxBase) {
            $taxAmount = round(max($taxBase, 0) * $taxRate, 2);

            $serviceChargeBaseWithTax = max($serviceChargeBase, 0);
            if ($taxRate > 0) {
                $serviceChargeBaseWithTax += max($taxAndServiceBase, 0) * $taxRate;
            }

            $serviceChargeAmount = round($serviceChargeBaseWithTax * $serviceChargeRate, 2);
        } else {
            $serviceChargeAmount = round(max($serviceChargeBase, 0) * $serviceChargeRate, 2);
            $serviceChargeTaxableAmount = round(max($taxAndServiceBase, 0) * $serviceChargeRate, 2);
            $taxAmount = round((max($taxBase, 0) + $serviceChargeTaxableAmount) * $taxRate, 2);
        }

        $discountBaseTotal = $itemsTotalFloat + $serviceChargeAmount + $taxAmount;
        $discountAmount = $discountAmountOverride ?? (float) round($discountBaseTotal * $discountPercentage / 100);
        $discountAmount = min(max((float) $discountAmount, 0), $discountBaseTotal);

        $subtotalAfterDiscount = $applyServiceChargeOnTaxBase
            ? $itemsTotalFloat
            : max($itemsTotalFloat - min($discountAmount, $itemsTotalFloat), 0);
        $grandTotal = max($discountBaseTotal - $discountAmount, 0);

        return [
            'discount_amount' => (float) $discountAmount,
            'subtotal_after_discount' => $subtotalAfterDiscount,
            'discount_base_total' => $discountBaseTotal,
            'service_charge_percentage' => (float) $settings->service_charge_percentage,
            'service_charge' => $serviceChargeAmount,
            'tax_percentage' => (float) $settings->tax_percentage,
            'tax' => $taxAmount,
            'grand_total' => $grandTotal,
        ];
    }

    protected function resolveChargeableBasesFromOrderItems($orderItems): array
    {
        $serviceChargeBase = 0;
        $taxBase = 0;
        $taxAndServiceBase = 0;

        foreach ($orderItems as $orderItem) {
            $subtotal = (float) ($orderItem->subtotal ?? ((float) $orderItem->price * (int) $orderItem->quantity));
            $includeTax = (bool) ($orderItem->inventoryItem?->include_tax ?? true);
            $includeServiceCharge = (bool) ($orderItem->inventoryItem?->include_service_charge ?? true);

            if ($includeServiceCharge) {
                $serviceChargeBase += $subtotal;
            }

            if ($includeTax) {
                $taxBase += $subtotal;
            }

            if ($includeTax && $includeServiceCharge) {
                $taxAndServiceBase += $subtotal;
            }
        }

        return [
            'service_charge_base' => $serviceChargeBase,
            'tax_base' => $taxBase,
            'tax_and_service_base' => $taxAndServiceBase,
        ];
    }

    /**
     * Area yang menentukan printer untuk sebuah cetakan.
     *
     * Prioritas: area meja order (struk harus keluar dekat meja yang dilayani) →
     * area order → area aktif user (walk-in / tanpa meja).
     */
    protected function resolvePrintAreaId(?Order $order = null): ?int
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        return $order?->tableSession?->table?->area_id
            ?? $order?->area_id
            ?? $user?->resolveActiveAreaId();
    }

    /**
     * Print receipt for a specific order.
     */
    public function printReceipt(Request $request, ?Order $order = null): JsonResponse
    {
        try {
            $type = strtolower((string) $request->input('type', 'cashier'));

            // If no order provided, try to get from request or session
            if (! $order) {
                $orderId = $request->input('order_id');
                if ($orderId) {
                    $order = Order::with([
                        'items.inventoryItem',
                        'tableSession.table',
                        'kitchenOrder.items.inventoryItem',
                        'kitchenOrder.table',
                        'barOrder.items.inventoryItem',
                        'barOrder.table',
                    ])->find($orderId);
                }
            } else {
                $order->load([
                    'items.inventoryItem',
                    'tableSession.table',
                    'kitchenOrder.items.inventoryItem',
                    'kitchenOrder.table',
                    'barOrder.items.inventoryItem',
                    'barOrder.table',
                ]);
            }

            if (! $order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found.',
                ], 404);
            }

            if (in_array($type, ['kitchen', 'bar', 'checker'], true)) {
                $printAreaId = $this->resolvePrintAreaId($order);

                if ($type === 'kitchen') {
                    $printer = null;
                    if ($request->filled('printer_id')) {
                        $printer = Printer::active()->find($request->input('printer_id'));
                    }
                    if (! $printer) {
                        $printer = Printer::getForService($type, $printAreaId) ?? Printer::getDefault($printAreaId);
                    }

                    if (! $printer) {
                        return response()->json([
                            'success' => false,
                            'message' => 'No active printer configured for this print type.',
                        ], 400);
                    }

                    if (! $order->kitchenOrder) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Order ini tidak memiliki kitchen ticket.',
                        ], 422);
                    }

                    $this->printerService->printKitchenTicket($order->kitchenOrder, $printer);

                    return response()->json([
                        'success' => true,
                        'message' => 'Kitchen ticket berhasil dikirim ke printer.',
                    ]);
                }

                if ($type === 'bar') {
                    $printer = null;
                    if ($request->filled('printer_id')) {
                        $printer = Printer::active()->find($request->input('printer_id'));
                    }
                    if (! $printer) {
                        $printer = Printer::getForService($type, $printAreaId) ?? Printer::getDefault($printAreaId);
                    }

                    if (! $printer) {
                        return response()->json([
                            'success' => false,
                            'message' => 'No active printer configured for this print type.',
                        ], 400);
                    }

                    if (! $order->barOrder) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Order ini tidak memiliki bar ticket.',
                        ], 422);
                    }

                    $this->printerService->printBarTicket($order->barOrder, $printer);

                    return response()->json([
                        'success' => true,
                        'message' => 'Bar ticket berhasil dikirim ke printer.',
                    ]);
                }

                $availableCheckerPrinters = $this->resolveCheckerPrintersForOrder($order);
                $canChooseChecker = (bool) (GeneralSetting::instance()->can_choose_checker ?? false);
                $selectedCheckerPrinters = collect();

                if ($canChooseChecker && $availableCheckerPrinters->count() > 1) {
                    $selectedCheckerIds = collect($request->input('checker_printer_ids', []))
                        ->map(fn ($id): int => (int) $id)
                        ->filter(fn (int $id): bool => $id > 0)
                        ->unique()
                        ->values();

                    if ($selectedCheckerIds->isEmpty()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Pilih minimal satu printer checker.',
                        ], 422);
                    }

                    $invalidPrinterIds = $selectedCheckerIds->diff($availableCheckerPrinters->pluck('id'));

                    if ($invalidPrinterIds->isNotEmpty()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Printer checker yang dipilih tidak sesuai assignment menu.',
                        ], 422);
                    }

                    $selectedCheckerPrinters = $availableCheckerPrinters
                        ->whereIn('id', $selectedCheckerIds)
                        ->values();
                }

                if ($selectedCheckerPrinters->isEmpty() && $availableCheckerPrinters->isNotEmpty()) {
                    $selectedCheckerPrinters = collect([$availableCheckerPrinters->first()]);
                }

                if ($selectedCheckerPrinters->isEmpty()) {
                    $fallbackPrinter = null;
                    if ($request->filled('printer_id')) {
                        $fallbackPrinter = Printer::active()->find((int) $request->input('printer_id'));
                    }
                    if (! $fallbackPrinter) {
                        $fallbackPrinter = Printer::getForService('checker', $printAreaId) ?? Printer::getDefault($printAreaId);
                    }

                    if (! $fallbackPrinter) {
                        return response()->json([
                            'success' => false,
                            'message' => 'No active printer configured for this print type.',
                        ], 400);
                    }

                    $selectedCheckerPrinters = collect([$fallbackPrinter]);
                }

                $printed = false;

                foreach ($selectedCheckerPrinters as $checkerPrinter) {
                    if ($order->kitchenOrder) {
                        $this->printerService->printCheckerTicket($order->kitchenOrder, $checkerPrinter);
                        $printed = true;
                    }

                    if ($order->barOrder) {
                        $this->printerService->printCheckerTicket($order->barOrder, $checkerPrinter);
                        $printed = true;
                    }
                }

                if (! $printed) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Order ini tidak memiliki checker ticket.',
                    ], 422);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Checker ticket berhasil dikirim ke printer.',
                ]);
            }

            // Get printer (specific or default)
            $printer = null;
            if ($request->filled('printer_id')) {
                $printer = Printer::active()->find($request->input('printer_id'));
            }
            if (! $printer) {
                $receiptType = $order->table_session_id ? 'closed_billing' : 'walk_in';
                $printer = $this->resolveReceiptPrinter($receiptType);
            }

            if (! $printer) {
                return response()->json([
                    'success' => false,
                    'message' => 'No default printer configured.',
                ], 400);
            }

            $billing = Billing::query()
                ->where('order_id', $order->id)
                ->latest('id')
                ->first();

            if ($billing && ! $order->table_session_id && (bool) $billing->is_walk_in) {
                $this->printerService->printWalkInBillingReceipt($order, $billing, $printer);
            } else {
                $this->printerService->printReceipt($order, $printer);
            }

            return response()->json([
                'success' => true,
                'message' => "Receipt for order {$order->order_number} printed successfully.",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to print receipt: '.$e->getMessage(),
            ], 500);
        }
    }

    protected function generateDailyOrderNumber(string $prefix, string $scope): string
    {
        $date = today()->toDateString();
        $sequence = Order::query()
            ->whereDate('created_at', $date)
            ->when($scope === 'walk-in', fn ($query) => $query->whereNull('table_session_id'))
            ->count() + 1;

        return sprintf('%s-%s-%04d', $prefix, today()->format('Ymd'), $sequence);
    }

    protected function generateWalkInTransactionCode(int $offset = 0): string
    {
        $sequence = Billing::query()
            ->where('is_walk_in', true)
            ->whereDate('created_at', today())
            ->count() + 1 + $offset;

        return 'WALKIN-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    protected function createOrderWithRetry(array $attributes, string $prefix, string $scope): Order
    {
        $offset = 0;
        $maxAttempts = 10;

        if (empty($attributes['area_id'])) {
            if (! empty($attributes['table_session_id'])) {
                $attributes['area_id'] = TableSession::with('table')->find($attributes['table_session_id'])?->table?->area_id;
            }

            if (empty($attributes['area_id']) && Auth::check()) {
                $attributes['area_id'] = Auth::user()->getAssignedArea()?->id ?? Auth::user()->resolveActiveArea()?->id;
            }
        }

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $attributes['order_number'] = $attempt === 1
                ? $this->generateDailyOrderNumberWithOffset($prefix, $scope, $offset)
                : $this->generateFallbackDailyOrderNumber($prefix, $attempt);

            try {
                return Order::create($attributes);
            } catch (QueryException $exception) {
                if (str_contains(strtolower($exception->getMessage()), 'idempotency_key')) {
                    throw $exception;
                }

                if (! $this->isDuplicateEntryException($exception) || $attempt === $maxAttempts) {
                    throw $exception;
                }

                $offset++;
            }
        }

        throw new \RuntimeException('Gagal membuat order number unik.');
    }

    protected function generateFallbackDailyOrderNumber(string $prefix, int $attempt): string
    {
        $sequence = random_int(1, 9999) + $attempt;

        if ($sequence > 9999) {
            $sequence -= 9999;
        }

        return sprintf('%s-%s-%04d', $prefix, today()->format('Ymd'), $sequence);
    }

    protected function createWalkInBillingWithRetry(array $attributes): string
    {
        $offset = 0;
        $maxAttempts = 5;

        if (empty($attributes['area_id'])) {
            if (! empty($attributes['order_id'])) {
                $attributes['area_id'] = Order::find($attributes['order_id'])?->area_id;
            } elseif (! empty($attributes['table_session_id'])) {
                $attributes['area_id'] = TableSession::with('table')->find($attributes['table_session_id'])?->table?->area_id;
            }

            if (empty($attributes['area_id']) && Auth::check()) {
                $attributes['area_id'] = Auth::user()->getAssignedArea()?->id ?? Auth::user()->resolveActiveArea()?->id;
            }
        }

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $transactionCode = $this->generateWalkInTransactionCode($offset);
            $attributes['transaction_code'] = $transactionCode;

            try {
                Billing::create($attributes);

                return $transactionCode;
            } catch (QueryException $exception) {
                if (! $this->isDuplicateEntryException($exception) || $attempt === $maxAttempts) {
                    throw $exception;
                }

                $offset += 2;
            }
        }

        throw new \RuntimeException('Gagal membuat kode transaksi walk-in unik.');
    }

    protected function generateDailyOrderNumberWithOffset(string $prefix, string $scope, int $offset = 0): string
    {
        $date = today()->toDateString();
        $sequence = Order::query()
            ->whereDate('created_at', $date)
            ->when($scope === 'walk-in', fn ($query) => $query->whereNull('table_session_id'))
            ->count() + 1 + $offset;

        return sprintf('%s-%s-%04d', $prefix, today()->format('Ymd'), $sequence);
    }

    protected function isDuplicateEntryException(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $message = strtolower($exception->getMessage());

        return in_array($sqlState, ['23000', '23505'], true)
            || str_contains($message, 'duplicate entry')
            || str_contains($message, 'unique constraint');
    }

    public function printWalkInDraftReceipt(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => 'nullable|string|max:100',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string|max:200',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.subtotal' => 'required|numeric|min:0',
            'subtotal' => 'required|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'tax_percentage' => 'nullable|numeric|min:0|max:100',
            'service_charge' => 'nullable|numeric|min:0',
            'service_charge_percentage' => 'nullable|numeric|min:0|max:100',
            'grand_total' => 'required|numeric|min:0',
            'payment_mode' => 'nullable|in:normal,split',
            'payment_method' => 'nullable|string|max:50',
            'foc_comp_payment_method' => 'nullable|in:FOC,Compliment',
            'payment_reference_number' => 'nullable|string|max:100',
            'split_cash_amount' => 'nullable|numeric|min:0',
            'split_non_cash_amount' => 'nullable|numeric|min:0',
            'split_non_cash_method' => 'nullable|string|max:50',
            'split_non_cash_reference_number' => 'nullable|string|max:100',
            'split_second_non_cash_amount' => 'nullable|numeric|min:0',
            'split_second_non_cash_method' => 'nullable|string|max:50',
            'split_second_non_cash_reference_number' => 'nullable|string|max:100',
        ]);

        $printer = $this->resolveReceiptPrinter('walk_in');

        if (! $printer) {
            return response()->json([
                'success' => false,
                'message' => 'No default printer configured.',
            ], 400);
        }

        $draftNumber = 'DRAFT-'.now()->format('Ymd-His');
        $paymentMode = strtolower((string) ($validated['payment_mode'] ?? 'normal'));
        $paymentMethod = strtoupper((string) ($validated['payment_method'] ?? 'CASH'));

        $payload = [
            'transaction_code' => $draftNumber,
            'date' => now()->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
            'cashier' => (string) (Auth::user()?->name ?? 'Admin'),
            'customer_name' => (string) ($validated['customer_name'] ?? 'Walk-in'),
            'type' => 'WALK-IN (DRAFT)',
            'table' => 'WALK-IN',
            'items' => collect($validated['items'])
                ->map(fn (array $item): array => [
                    'name' => (string) ($item['name'] ?? '-'),
                    'qty' => (int) ($item['qty'] ?? 0),
                    'price' => (float) ($item['price'] ?? 0),
                    'subtotal' => (float) ($item['subtotal'] ?? 0),
                ])
                ->values()
                ->all(),
            'down_payment_amount' => 0,
            'subtotal' => (float) ($validated['subtotal'] ?? 0),
            'discount_amount' => (float) ($validated['discount_amount'] ?? 0),
            'tax' => (float) ($validated['tax'] ?? 0),
            'tax_percentage' => (float) ($validated['tax_percentage'] ?? 0),
            'service_charge' => (float) ($validated['service_charge'] ?? 0),
            'service_charge_percentage' => (float) ($validated['service_charge_percentage'] ?? 0),
            'grand_total' => (float) ($validated['grand_total'] ?? 0),
            'payment_mode' => $paymentMode,
            'payment_method' => $paymentMode === 'split' ? 'SPLIT BILL' : $paymentMethod,
            'foc_comp_payment_method' => (string) ($validated['foc_comp_payment_method'] ?? ''),
            'payment_reference_number' => (string) ($validated['payment_reference_number'] ?? ''),
            'split_cash_amount' => (float) ($validated['split_cash_amount'] ?? 0),
            'split_non_cash_amount' => (float) ($validated['split_non_cash_amount'] ?? 0),
            'split_non_cash_method' => strtoupper((string) ($validated['split_non_cash_method'] ?? 'NON-CASH 1')),
            'split_non_cash_reference_number' => (string) ($validated['split_non_cash_reference_number'] ?? ''),
            'split_second_non_cash_amount' => (float) ($validated['split_second_non_cash_amount'] ?? 0),
            'split_second_non_cash_method' => strtoupper((string) ($validated['split_second_non_cash_method'] ?? 'NON-CASH 2')),
            'split_second_non_cash_reference_number' => (string) ($validated['split_second_non_cash_reference_number'] ?? ''),
        ];

        $printed = $this->printerService->printWalkInDraftReceipt($payload, $printer);

        if (! $printed) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim draft struk ke printer.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Draft struk berhasil dikirim ke printer.',
        ]);
    }

    /**
     * Test print with dummy data for internal testing.
     */
    public function testPrint(Request $request): JsonResponse
    {
        try {
            $printer = null;
            if ($request->filled('printer_id')) {
                $printer = Printer::active()->find($request->input('printer_id'));
            }
            $printer = $printer ?? $this->resolveReceiptPrinter('closed_billing');

            if (! $printer) {
                return response()->json([
                    'success' => false,
                    'message' => 'No default printer configured.',
                ], 400);
            }

            $this->printerService->testPrint($printer);

            $modeMessage = $printer->connection_type === 'log'
              ? 'Mode LOG (simulasi), kertas tidak akan keluar.'
              : 'Perintah test print sudah dikirim ke printer fisik.';

            return response()->json([
                'success' => true,
                'message' => "Test print ke {$printer->name} ({$printer->printer_type}/{$printer->location}) berhasil. {$modeMessage}",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Test print failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Print receipt for an order (internal method).
     */
    protected function printOrderReceipt(Order $order, string $receiptType): bool
    {
        try {
            $printer = $this->resolveReceiptPrinter($receiptType);

            if (! $printer) {
                return false;
            }

            $order->load(['items.inventoryItem', 'tableSession.table']);
            $billing = Billing::query()
                ->where('order_id', $order->id)
                ->latest('id')
                ->first();

            if ($billing && ! $order->table_session_id && (bool) $billing->is_walk_in) {
                $this->printerService->printWalkInBillingReceipt($order, $billing, $printer);
            } else {
                $this->printerService->printReceipt($order, $printer);
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function resolveReceiptPrinter(string $receiptType, ?int $areaId = null): ?Printer
    {
        $settings = GeneralSetting::instance();
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        // resolveActiveAreaId(): assigned area bila user terikat satu area, jika tidak
        // pakai area aktif di session. Tanpa ini user multi-area (admin) selalu null
        // sehingga mapping printer per area terlewat.
        $contextAreaId = $areaId ?: $user?->resolveActiveAreaId();

        $targetType = match ($receiptType) {
            'walk_in' => 'walk_in',
            default => 'closed_billing',
        };

        $configuredPrinterId = $settings->getPrinterIdForArea($contextAreaId, $targetType);

        if ($configuredPrinterId && $configuredPrinterId > 0) {
            $configuredPrinter = Printer::active()->find($configuredPrinterId);

            if ($configuredPrinter) {
                return $configuredPrinter;
            }
        }

        return Printer::getForService('cashier', $contextAreaId) ?? Printer::getDefault($contextAreaId);
    }

    /**
     * Route order items to Kitchen/Bar preparation queues and print tickets.
     */
    protected function routeOrderToPreparation(
        Order $order,
        ?TableSession $tableSession,
        string $orderNumber,
        ?int $walkInCustomerUserId = null,
        ?Collection $selectedCheckerPrinterIds = null
    ): void {
        $order->loadMissing(['items.inventoryItem.printers']);

        $kitchenItems = collect();
        $barItems = collect();
        $checkerCashierItems = collect();

        // Prioritize explicit Kitchen / Bar destination, fallback to prep location or category type
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

            // Fallback: If no explicit printer type assigned, route based on preparation_location or category type
            $location = strtolower(trim((string) ($item->preparation_location ?? '')));
            if ($location === 'bar') {
                $barItems->push($item);

                continue;
            }

            if ($location === 'kitchen') {
                $kitchenItems->push($item);

                continue;
            }

            $categoryType = strtolower(trim((string) ($item->inventoryItem?->category_type ?? '')));
            if (in_array($categoryType, ['beverage', 'bar', 'minuman', 'drink', 'cocktail', 'mocktail', 'liquor', 'spirit', 'wine', 'beer', 'softdrink'], true)) {
                $barItems->push($item);

                continue;
            }

            if (in_array($categoryType, ['food', 'kitchen', 'makanan', 'snack', 'dessert', 'main course', 'appetizer'], true)) {
                $kitchenItems->push($item);

                continue;
            }

            $checkerCashierItems->push($item);
        }

        // Resolve customer_user_id: from session (booking) or from walk-in param
        if ($tableSession !== null) {
            $customerUser = CustomerUser::where('user_id', $tableSession->customer_id)->first();
            $customerUserId = $customerUser?->id;
        } else {
            $customerUserId = $walkInCustomerUserId;
        }

        $tableId = $tableSession?->table_id;

        $resolvedAreaId = $order->area_id
            ?? $tableSession?->table?->area_id
            ?? (session('active_area_id') && session('active_area_id') !== 'all' ? (int) session('active_area_id') : null);

        // Create Kitchen Order if there are kitchen items
        if ($kitchenItems->isNotEmpty()) {
            $kitchenOrder = KitchenOrder::create([
                'area_id' => $resolvedAreaId,
                'order_id' => $order->id,
                'order_number' => $orderNumber,
                'customer_user_id' => $customerUserId,
                'table_id' => $tableId,
                'total_amount' => $kitchenItems->sum('subtotal'),
                'status' => 'baru',
                'progress' => 0,
            ]);

            // Create kitchen order items
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

            // Auto-print kitchen ticket safely
            try {
                $this->printKitchenTicket($kitchenOrder, $kitchenItems, $selectedCheckerPrinterIds);
            } catch (\Throwable $e) {
                logger()->error('Failed auto-printing kitchen ticket: '.$e->getMessage());
            }
        }

        // Create Bar Order if there are bar items
        if ($barItems->isNotEmpty()) {
            $barOrder = BarOrder::create([
                'area_id' => $resolvedAreaId,
                'order_id' => $order->id,
                'order_number' => $orderNumber,
                'customer_user_id' => $customerUserId,
                'table_id' => $tableId,
                'total_amount' => $barItems->sum('subtotal'),
                'payment_method' => 'cash',
                'status' => 'baru',
                'progress' => 0,
            ]);

            // Create bar order items
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

            // Auto-print bar ticket safely
            try {
                $this->printBarTicket($barOrder, $barItems, $selectedCheckerPrinterIds);
            } catch (\Throwable $e) {
                logger()->error('Failed auto-printing bar ticket: '.$e->getMessage());
            }
        }

        if ($checkerCashierItems->isNotEmpty()) {
            try {
                $this->printCheckerCashierItemsWithoutPreparationOrder(
                    $order,
                    $checkerCashierItems,
                    $orderNumber,
                    $tableId,
                    $selectedCheckerPrinterIds
                );
            } catch (\Throwable $e) {
                logger()->error('Failed auto-printing checker ticket: '.$e->getMessage());
            }
        }
    }

    protected function printCheckerCashierItemsWithoutPreparationOrder(
        Order $order,
        Collection $checkerCashierItems,
        string $orderNumber,
        ?int $tableId,
        ?Collection $selectedCheckerPrinterIds = null
    ): bool {
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

            return $this->printItemsToAssignedPrinters(
                $virtualOrder,
                $checkerCashierItems,
                fn (KitchenOrder|BarOrder $preparationOrder, Printer $printer): bool => match ($printer->printer_type) {
                    'checker' => $this->queuePreparationTicket($preparationOrder, $printer, 'checker'),
                    'cashier' => $this->queuePreparationTicket($preparationOrder, $printer, 'cashier'),
                    default => false,
                },
                $selectedCheckerPrinterIds
            );
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Print kitchen order ticket.
     */
    protected function printKitchenTicket(KitchenOrder $kitchenOrder, Collection $items, ?Collection $selectedCheckerPrinterIds = null): bool
    {
        try {
            $kitchenOrder->loadMissing(['table']);

            return $this->printItemsToAssignedPrinters(
                $kitchenOrder,
                $items,
                fn (KitchenOrder|BarOrder $order, Printer $printer): bool => match ($printer->printer_type) {
                    'checker' => $this->queuePreparationTicket($order, $printer, 'checker'),
                    'cashier' => $this->queuePreparationTicket($order, $printer, 'cashier'),
                    'bar' => $this->queuePreparationTicket($order, $printer, 'bar'),
                    default => $this->queuePreparationTicket($order, $printer, 'kitchen'),
                },
                $selectedCheckerPrinterIds
            );
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Print bar order ticket.
     */
    protected function printBarTicket(BarOrder $barOrder, Collection $items, ?Collection $selectedCheckerPrinterIds = null): bool
    {
        try {
            $barOrder->loadMissing(['table']);

            return $this->printItemsToAssignedPrinters(
                $barOrder,
                $items,
                fn (KitchenOrder|BarOrder $order, Printer $printer): bool => match ($printer->printer_type) {
                    'checker' => $this->queuePreparationTicket($order, $printer, 'checker'),
                    'cashier' => $this->queuePreparationTicket($order, $printer, 'cashier'),
                    'kitchen' => $this->queuePreparationTicket($order, $printer, 'kitchen'),
                    default => $this->queuePreparationTicket($order, $printer, 'bar'),
                },
                $selectedCheckerPrinterIds
            );
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function printItemsToAssignedPrinters(
        object $order,
        Collection $items,
        callable $callback,
        ?Collection $selectedCheckerPrinterIds = null
    ): bool {
        $user = Auth::user();
        $contextArea = $user?->getAssignedArea() ?? $order->tableSession?->table?->area ?? $order->table?->area;
        $contextAreaId = $contextArea?->id;
        $contextAreaCode = $contextArea ? strtoupper(trim((string) $contextArea->code)) : null;

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

            // Filter printers by user/table area context if area-specific printers exist
            if ($contextAreaId !== null && $targetPrinters->count() > 1) {
                $areaMatchingPrinters = $targetPrinters->filter(function (Printer $printer) use ($contextAreaId, $contextAreaCode): bool {
                    if ($printer->area_id !== null) {
                        return (int) $printer->area_id === (int) $contextAreaId;
                    }
                    if ($printer->location !== null && $contextAreaCode !== null) {
                        return strtoupper(trim((string) $printer->location)) === $contextAreaCode;
                    }

                    return false;
                });

                if ($areaMatchingPrinters->isNotEmpty()) {
                    $targetPrinters = $areaMatchingPrinters;
                }
            }

            if ($targetPrinters->isEmpty()) {
                continue;
            }

            foreach ($targetPrinters as $printer) {
                $groupedByPrinter[$printer->id]['printer'] = $printer;
                $groupedByPrinter[$printer->id]['items'][$item->id] = $item;
            }
        }

        $printed = false;

        foreach ($groupedByPrinter as $group) {
            try {
                $orderForPrinter = clone $order;
                $orderForPrinter->setRelation('items', collect($group['items'])->values());
                $printed = (bool) $callback($orderForPrinter, $group['printer']) || $printed;
            } catch (\Exception $e) {
                Log::warning('Assigned printer failed during POS checkout print fan-out', [
                    'printer_id' => $group['printer']->id ?? null,
                    'printer_name' => $group['printer']->name ?? null,
                    'connection_type' => $group['printer']->connection_type ?? null,
                    'order_number' => $order->order_number ?? null,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $printed;
    }

    /**
     * Cetak tiket persiapan langsung (sinkron, tanpa queue).
     *
     * Sengaja TIDAK lewat queue: deploy ini tidak menjalankan worker, jadi job yang
     * di-dispatch hanya menumpuk di tabel `jobs` dan tiket tak pernah keluar.
     * Nilai balik = hasil cetak sebenarnya, supaya pemanggil bisa tahu bila gagal.
     */
    protected function queuePreparationTicket(object $order, Printer $printer, string $ticketType): bool
    {
        return match ($ticketType) {
            'bar' => $this->printerService->printBarTicket($order, $printer),
            'checker' => $this->printerService->printCheckerTicket($order, $printer),
            'cashier' => $this->printerService->printCashierTicket($order, $printer),
            default => $this->printerService->printKitchenTicket($order, $printer),
        };
    }

    protected function idempotentOrderResponse(Order $order): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => "Order #{$order->order_number} sudah diproses.",
            'order_number' => $order->order_number,
            'order_id' => $order->id,
            'items_total' => (float) $order->items_total,
            'discount_amount' => (float) $order->discount_amount,
            'total' => (float) $order->total,
            'formatted_total' => 'Rp '.number_format((float) $order->total, 0, ',', '.'),
            'receipt_printed' => false,
            'idempotent_replay' => true,
        ]);
    }

    /**
     * Get printer for a service location, considering counter location from session.
     * Priority: counter location > service location > default.
     */
    protected function getPrinterForLocation(string $serviceLocation, ?int $areaId = null): ?Printer
    {
        $counterLocation = session()->get('pos_counter_location');
        $contextAreaId = $areaId ?: $this->resolvePrintAreaId();

        // 1. Try counter location first (area-specific printer)
        if ($counterLocation) {
            $printer = Printer::getByLocation($counterLocation, $contextAreaId);
            if ($printer) {
                return $printer;
            }
        }

        // 2. Fallback to service location printer (kitchen/bar) — prefer printer_type match
        $printer = Printer::getForService($serviceLocation, $contextAreaId);
        if ($printer) {
            return $printer;
        }

        // 3. Final fallback to default printer
        return Printer::getDefault($contextAreaId);
    }

    /**
     * Decrement stock for an inventory item, respecting Accurate item group components.
     *
     * If the item has an `accurate_id` and Accurate returns group components (ingredients),
     * each ingredient's stock is decremented by (component_quantity × sold_quantity).
     * Falls back to decrementing the item's own stock when no components are found.
     */
    protected function decrementInventoryStock(InventoryItem $inventoryItem, int $quantity): void
    {
        $setting = PosCategorySetting::allKeyed()->get($inventoryItem->category_type);
        $isItemGroup = (bool) ($inventoryItem->is_item_group ?? false);

        if (! $inventoryItem->accurate_id) {
            if (! $isItemGroup) {
                $this->decrementSingleItemStock($inventoryItem->id, $quantity);
            }

            return;
        }

        $components = $this->getItemGroupComponents($inventoryItem);

        if (empty($components)) {
            if (! $isItemGroup) {
                $this->decrementSingleItemStock($inventoryItem->id, $quantity);
            }

            return;
        }

        foreach ($components as $component) {
            $componentAccurateId = $component['itemId'] ?? null;
            $componentQty = (float) ($component['quantity'] ?? 0);

            if (! $componentAccurateId || $componentQty <= 0) {
                continue;
            }

            $ingredient = InventoryItem::where('accurate_id', $componentAccurateId)->first();

            if (! $ingredient) {
                continue;
            }

            $this->decrementSingleItemStock($ingredient->id, (int) round($componentQty * $quantity));
        }
    }

    protected function resolvePreparationLocationFromPrinters(InventoryItem $inventoryItem): ?string
    {
        $assignedTypes = $this->resolveAssignedPrinterTypes($inventoryItem);

        if ($assignedTypes->contains('bar')) {
            return 'bar';
        }

        if ($assignedTypes->contains('kitchen')) {
            return 'kitchen';
        }

        if ($assignedTypes->contains('cashier') || $assignedTypes->contains('checker')) {
            return null;
        }

        $setting = PosCategorySetting::allKeyed()->get($inventoryItem->category_type);
        if (! empty($setting?->preparation_location) && in_array($setting->preparation_location, ['kitchen', 'bar'], true)) {
            return $setting->preparation_location;
        }

        $categoryType = strtolower(trim((string) $inventoryItem->category_type));
        if (in_array($categoryType, ['food', 'kitchen', 'makanan'], true)) {
            return 'kitchen';
        }

        if (in_array($categoryType, ['beverage', 'bar', 'minuman', 'drink'], true)) {
            return 'bar';
        }

        return null;
    }

    protected function resolveZeroPricedItemAmount(InventoryItem $inventoryItem): float
    {
        return (float) $inventoryItem->price;
    }

    protected function resolveAssignedPrinterTypes(InventoryItem $inventoryItem): \Illuminate\Support\Collection
    {
        return $inventoryItem->printers
            ?->filter(fn (Printer $printer): bool => $printer->is_active)
            ->map(fn (Printer $printer): ?string => $this->resolvePrinterServiceType($printer))
            ->filter()
            ->values() ?? collect();
    }

    protected function resolveAssignedCheckerPrinters(InventoryItem $inventoryItem): Collection
    {
        return $inventoryItem->printers
            ?->filter(fn (Printer $printer): bool => $printer->is_active && $this->resolvePrinterServiceType($printer) === 'checker')
            ->map(fn (Printer $printer): array => [
                'id' => (int) $printer->id,
                'name' => (string) $printer->name,
            ])
            ->values() ?? collect();
    }

    protected function resolveCheckerPrintersForOrder(Order $order): Collection
    {
        $order->loadMissing([
            'kitchenOrder.items.inventoryItem.printers',
            'barOrder.items.inventoryItem.printers',
        ]);

        return collect([$order->kitchenOrder?->items ?? collect(), $order->barOrder?->items ?? collect()])
            ->flatten(1)
            ->flatMap(fn ($item): Collection => $item->inventoryItem?->printers ?? collect())
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

    protected function decrementSingleItemStock(int $itemId, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        InventoryItem::query()
            ->whereKey($itemId)
            ->lockForUpdate()
            ->first()
            ?->decrement('stock_quantity', $quantity);
    }

    protected function resolveCartAvailability(array $cart): array
    {
        $posSettings = PosCategorySetting::allKeyed();
        $menuItems = [];
        $stockIssues = [];
        $ingredientRequirements = [];

        foreach ($cart as $productId => $cartItem) {
            $itemId = (int) str_replace('item_', '', (string) $productId);
            $inventoryItem = InventoryItem::find($itemId);
            $requestedQuantity = (int) ($cartItem['quantity'] ?? 0);

            if (! $inventoryItem || $requestedQuantity <= 0) {
                continue;
            }

            $setting = $posSettings->get($inventoryItem->category_type);
            $isItemGroup = (bool) ($inventoryItem->is_item_group ?? false);
            $isCountPortionPossible = (bool) ($inventoryItem->is_count_portion_possible ?? false);
            $detailGroupComponents = $this->resolveDetailGroupComponents($inventoryItem, $setting);

            if ($detailGroupComponents !== [] && $isCountPortionPossible) {
                $possiblePortions = $this->resolvePossiblePortions($inventoryItem, $detailGroupComponents);
                $isAvailable = $possiblePortions >= $requestedQuantity;

                $menuItems[] = [
                    'product_id' => $productId,
                    'item_id' => $inventoryItem->id,
                    'name' => $inventoryItem->name,
                    'requested_quantity' => $requestedQuantity,
                    'possible_portions' => $possiblePortions,
                    'is_available' => $isAvailable,
                ];

                if (! $isAvailable) {
                    $stockIssues[] = [
                        'type' => 'detail_group_shortage',
                        'product_id' => $productId,
                        'name' => $inventoryItem->name,
                        'possible_portions' => $possiblePortions,
                        'requested_quantity' => $requestedQuantity,
                        'message' => "Stok bahan {$inventoryItem->name} hanya cukup {$this->formatStockNumber($possiblePortions)} porsi.",
                    ];
                }

                continue;
            }

            if ($isItemGroup) {
                $isAvailable = $inventoryItem->is_visible_in_pos !== false && (bool) $inventoryItem->is_active && ! (bool) $inventoryItem->is_group_sold_out;

                $menuItems[] = [
                    'product_id' => $productId,
                    'item_id' => $inventoryItem->id,
                    'name' => $inventoryItem->name,
                    'requested_quantity' => $requestedQuantity,
                    'possible_portions' => null,
                    'is_available' => $isAvailable,
                ];

                if (! $isAvailable) {
                    $stockIssues[] = [
                        'type' => 'manual_sold_out',
                        'product_id' => $productId,
                        'name' => $inventoryItem->name,
                        'message' => "Item {$inventoryItem->name} berstatus Sold Out / tidak tersedia.",
                    ];
                }

                continue;
            }

            if (! $isItemGroup) {
                $availableStock = (float) ($inventoryItem->stock_quantity ?? 0);

                if ($availableStock < $requestedQuantity) {
                    $stockIssues[] = [
                        'type' => 'stock',
                        'product_id' => $productId,
                        'name' => $inventoryItem->name,
                        'available_stock' => $availableStock,
                        'requested_quantity' => $requestedQuantity,
                        'message' => "Stok {$inventoryItem->name} hanya tersisa {$this->formatStockNumber($availableStock)}.",
                    ];
                }

                continue;
            }

            continue;
        }

        foreach ($ingredientRequirements as $ingredientAccurateId => $ingredientRequirement) {
            if ($ingredientRequirement['required_total'] <= $ingredientRequirement['available_stock']) {
                continue;
            }

            $stockIssues[] = [
                'type' => 'ingredient_shortage',
                'ingredient_accurate_id' => $ingredientAccurateId,
                'ingredient_name' => $ingredientRequirement['ingredient_name'],
                'available_stock' => $ingredientRequirement['available_stock'],
                'required_total' => $ingredientRequirement['required_total'],
                'menus' => array_keys($ingredientRequirement['menus']),
                'message' => "Stok bahan {$ingredientRequirement['ingredient_name']} tidak cukup. Butuh {$this->formatStockNumber($ingredientRequirement['required_total'])}, tersedia {$this->formatStockNumber($ingredientRequirement['available_stock'])}.",
            ];
        }

        return [
            'can_checkout' => $stockIssues === [],
            'message' => $stockIssues[0]['message'] ?? 'Stok menu siap untuk checkout.',
            'menu_items' => $menuItems,
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
            fn () => $this->accurateService->getItemGroupComponents((int) $inventoryItem->accurate_id)
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

    protected function formatStockNumber(float|int $value): string
    {
        $formatted = number_format((float) $value, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    /**
     * Push a walk-in order to Accurate as Sales Order + Sales Invoice.
     * Failures are logged but do not interrupt the checkout response.
     */
    protected function pushOrderToAccurate(Order $order, ?CustomerUser $customerUser, int|float $finalTotal): void
    {
        $billing = Billing::query()->where('order_id', $order->id)->latest('id')->first();

        try {
            $order->load(['items.inventoryItem']);

            if (! $customerUser) {
                $billing?->update([
                    'error_message' => 'Customer walk-in tidak ditemukan untuk sinkronisasi Accurate.',
                ]);

                return;
            }

            $customerNo = $this->ensureAccurateCustomer($customerUser);

            if (! $customerNo) {
                $billing?->update([
                    'error_message' => 'Customer Accurate tidak ditemukan untuk transaksi walk-in ini.',
                ]);

                return;
            }

            $transDate = $order->ordered_at->format('d/m/Y');
            $taxAmount = (float) ($billing?->tax ?? 0);
            $serviceChargeAmount = (float) ($billing?->service_charge ?? 0);

            $warehouseName = GeneralSetting::instance()->getAccurateWarehouseName();

            $detailItem = $order->items->map(function ($item) use ($warehouseName) {
                $gross = (float) $item->subtotal;

                return [
                    'itemNo' => $item->inventoryItem?->code ?? $item->item_code,
                    'quantity' => $item->quantity,
                    'unitPrice' => (float) $item->price,
                    'discountPercent' => $gross > 0 ? round((float) $item->discount_amount / $gross * 100, 6) : 0,
                    'warehouseName' => $warehouseName,
                ];
            })->values()->toArray();

            // 1. Save Sales Order — retry with suffix on duplicate number conflict.
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

            $soNumber = $order->accurate_so_number;
            $activeArea = auth()->user()?->resolveActiveArea();
            $areaPrefix = $activeArea ? $activeArea->so_prefix : 'ROOM-';
            if (! $soNumber) {
                $soNumber = sprintf('%sWALKIN-%s-%05d', $areaPrefix, $order->ordered_at->format('Ymd'), $order->id % 100000);
                $this->accurateService->saveSalesOrder(array_merge($soBasePayload, ['number' => $soNumber]));
                $order->update(['accurate_so_number' => $soNumber]);
                $billing?->update(['accurate_so_number' => $soNumber]);
            }

            // 2. Save Sales Invoice
            $invPayload = [
                'customerNo' => $customerNo,
                'transDate' => $transDate,
                'memo' => 'Walk-in POS — '.$order->order_number,
                'number' => $soNumber,
                'detailItem' => $detailItem,
            ];

            if ($soNumber) {
                $invPayload['detailItem'] = array_map(
                    fn (array $item): array => array_merge($item, ['salesOrderNumber' => $soNumber]),
                    $detailItem
                );
            }

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

            $invNumber = $order->accurate_inv_number;
            if (! $invNumber) {
                $invResult = $this->accurateService->saveSalesInvoice($invPayload);
                $invNumber = $invResult['r']['number'] ?? $invResult['d']['number'] ?? $soNumber;
                $order->update(['accurate_inv_number' => $invNumber]);
                $billing?->update(['accurate_inv_number' => $invNumber]);
            }

            // 3. Save Sales Receipt (Penerimaan Penjualan) for single or split payments
            $this->syncSalesReceipts($customerNo, $transDate, $soNumber, $invNumber, $order->order_number, $billing, $order);

            // 4. Persist Accurate numbers on the order record
            $order->update([
                'accurate_so_number' => $soNumber,
                'accurate_inv_number' => $invNumber,
            ]);

            $billing?->update([
                'accurate_so_number' => $soNumber,
                'accurate_inv_number' => $invNumber,
                'error_message' => null,
            ]);
        } catch (\Exception $e) {
            $billing?->update([
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Accurate walk-in sync failed', [
                'order_id' => $order->id,
                'billing_id' => $billing?->id,
                'message' => $e->getMessage(),
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
                    'description' => "Pembayaran Walk-in POS ({$paymentItem['method_label']}) — {$reference}",
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
                Log::warning('Accurate Walk-in Sales Receipt Sync: FAILED', [
                    'reference' => $reference,
                    'method' => $paymentItem['method_label'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function generateWalkInCustomerEmail(string $name): string
    {
        $domain = '126club.local';
        $baseName = Str::of($name)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->value();

        $baseName = $baseName !== '' ? $baseName : 'walkin';
        $baseName = Str::limit($baseName, 24, '');

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $email = sprintf(
                '%s%06d@%s',
                $baseName,
                random_int(0, 999999),
                $domain,
            );

            if (! User::query()->where('email', $email)->exists()) {
                return $email;
            }
        }

        return sprintf('walkin%s@%s', Str::uuid()->toString(), $domain);
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

    /**
     * Build a standardized cart response for AJAX requests.
     */
    protected function cartResponse(string $message, array $cart): JsonResponse
    {
        $cartItemFlags = InventoryItem::query()
            ->whereIn('id', collect($cart)->map(fn ($item) => (int) str_replace('item_', '', (string) ($item['id'] ?? '0')))->filter()->values())
            ->get(['id', 'include_tax', 'include_service_charge', 'category_main', 'price'])
            ->keyBy('id');

        $cartItems = collect($cart)->values()->map(function ($item) use ($cartItemFlags) {
            $inventoryItemId = (int) str_replace('item_', '', (string) ($item['id'] ?? '0'));
            $flags = $cartItemFlags->get($inventoryItemId);
            $price = $flags ? $this->resolveZeroPricedItemAmount($flags) : (float) $item['price'];

            return [
                'id' => $item['id'],
                'name' => $item['name'],
                'price' => $price,
                'quantity' => (int) $item['quantity'],
                'subtotal' => $price * (int) $item['quantity'],
                'preparation_location' => $item['preparation_location'] ?? 'direct',
                'assigned_printer_types' => collect($item['assigned_printer_types'] ?? [])->values()->all(),
                'assigned_checker_printers' => collect($item['assigned_checker_printers'] ?? [])->values()->all(),
                'assigned_checker_printer_ids' => collect($item['assigned_checker_printer_ids'] ?? [])->values()->all(),
                'include_tax' => (bool) ($flags?->include_tax ?? $item['include_tax'] ?? true),
                'include_service_charge' => (bool) ($flags?->include_service_charge ?? $item['include_service_charge'] ?? true),
            ];
        });

        $cartTotal = $cartItems->sum('subtotal');

        return response()->json([
            'success' => true,
            'message' => $message,
            'cart' => $cartItems,
            'cartTotal' => $cartTotal,
            'itemCount' => $cartItems->count(),
        ]);
    }

    public function assignWaiterFromPos(Request $request, TableReservation $booking): JsonResponse
    {
        $validated = $request->validate([
            'waiter_id' => 'required|exists:users,id',
        ]);

        $session = $booking->tableSession;

        if (! $session) {
            return response()->json(['success' => false, 'message' => 'Sesi aktif tidak ditemukan.'], 422);
        }

        $previousWaiterId = $session->waiter_id;
        $newWaiterId = (int) $validated['waiter_id'];

        $session->update(['waiter_id' => $newWaiterId]);

        if ($newWaiterId !== $previousWaiterId) {
            $waiter = User::find($newWaiterId);
            $waiter?->notify(new \App\Notifications\WaiterAssignedNotification(
                $booking->load(['table.area', 'customer.profile', 'customer.customerUser'])
            ));
        }

        $waiter = User::find($newWaiterId);
        $waiterName = $waiter?->profile?->name ?? $waiter?->name ?? '-';

        return response()->json(['success' => true, 'waiterName' => $waiterName]);
    }
}
