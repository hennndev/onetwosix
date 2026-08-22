<?php

use App\Models\Area;
use App\Models\BarOrder;
use App\Models\BarOrderItem;
use App\Models\Billing;
use App\Models\Dashboard;
use App\Models\InventoryItem;
use App\Models\KitchenOrder;
use App\Models\KitchenOrderItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RecapHistory;
use App\Models\Tabel;
use App\Models\TableReservation;
use App\Models\TableSession;
use App\Services\DashboardSyncService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

function makeDashboardSession(int $customerId): TableSession
{
    $area = Area::create([
        'code' => 'DSH-AREA-'.uniqid(),
        'name' => 'Dashboard Area '.uniqid(),
        'is_active' => true,
    ]);

    $table = Tabel::create([
        'area_id' => $area->id,
        'table_number' => 'DSH-TBL-'.uniqid(),
        'qr_code' => 'DSH-QR-'.uniqid(),
        'capacity' => 4,
        'status' => 'available',
        'is_active' => true,
    ]);

    return TableSession::create([
        'table_reservation_id' => null,
        'table_id' => $table->id,
        'customer_id' => $customerId,
        'session_code' => 'DSH-SES-'.uniqid(),
        'status' => 'active',
        'billing_id' => null,
    ]);
}

function createDashboardKitchenAndBarItems(int $createdById, int $kitchenQty, int $barQty): void
{
    $order = \App\Models\Order::create([
        'table_session_id' => null,
        'customer_user_id' => null,
        'created_by' => $createdById,
        'order_number' => 'DSH-ORD-'.uniqid(),
        'status' => 'pending',
        'items_total' => 30000,
        'discount_amount' => 0,
        'total' => 30000,
        'ordered_at' => now(),
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
    ]);

    $kitchenOrder = KitchenOrder::create([
        'order_id' => $order->id,
        'order_number' => $order->order_number,
        'customer_user_id' => null,
        'table_id' => null,
        'total_amount' => 10000,
        'status' => 'selesai',
        'progress' => 100,
    ]);

    KitchenOrderItem::create([
        'kitchen_order_id' => $kitchenOrder->id,
        'inventory_item_id' => null,
        'quantity' => $kitchenQty,
        'price' => 10000,
        'is_completed' => true,
    ]);

    $barOrder = BarOrder::create([
        'order_id' => $order->id,
        'order_number' => $order->order_number,
        'customer_user_id' => null,
        'table_id' => null,
        'total_amount' => 10000,
        'payment_method' => 'cash',
        'status' => 'selesai',
        'progress' => 100,
    ]);

    BarOrderItem::create([
        'bar_order_id' => $barOrder->id,
        'inventory_item_id' => null,
        'quantity' => $barQty,
        'price' => 10000,
        'is_completed' => true,
    ]);
}

test('dashboard sync aggregates totals from paid billings and walk-in orders', function () {
    $admin = adminUser();

    createDashboardKitchenAndBarItems($admin->id, 7, 5);

    $sessionTransfer = makeDashboardSession($admin->id);
    Billing::create([
        'table_session_id' => $sessionTransfer->id,
        'is_walk_in' => false,
        'is_booking' => true,
        'minimum_charge' => 0,
        'orders_total' => 50000,
        'subtotal' => 50000,
        'tax' => 3000,
        'tax_percentage' => 10,
        'service_charge' => 2000,
        'service_charge_percentage' => 10,
        'discount_amount' => 0,
        'grand_total' => 50000,
        'paid_amount' => 50000,
        'billing_status' => 'paid',
        'payment_method' => 'transfer',
        'payment_mode' => 'normal',
    ]);

    $sessionSplit = makeDashboardSession($admin->id);
    Billing::create([
        'table_session_id' => $sessionSplit->id,
        'is_walk_in' => false,
        'is_booking' => true,
        'minimum_charge' => 0,
        'orders_total' => 70000,
        'subtotal' => 70000,
        'tax' => 1400,
        'tax_percentage' => 10,
        'service_charge' => 700,
        'service_charge_percentage' => 10,
        'discount_amount' => 0,
        'grand_total' => 70000,
        'paid_amount' => 70000,
        'billing_status' => 'paid',
        'payment_method' => null,
        'payment_mode' => 'split',
        'split_cash_amount' => 30000,
        'split_debit_amount' => 20000,
        'split_non_cash_method' => 'qris',
        'split_non_cash_reference_number' => 'SPLIT-001',
        'split_second_non_cash_amount' => 20000,
        'split_second_non_cash_method' => 'kredit',
        'split_second_non_cash_reference_number' => 'SPLIT-002',
    ]);

    Billing::create([
        'table_session_id' => null,
        'order_id' => null,
        'is_walk_in' => true,
        'is_booking' => false,
        'minimum_charge' => 0,
        'orders_total' => 100000,
        'subtotal' => 100000,
        'tax' => 11000,
        'tax_percentage' => 10,
        'service_charge' => 10000,
        'service_charge_percentage' => 10,
        'discount_amount' => 0,
        'grand_total' => 121000,
        'paid_amount' => 121000,
        'billing_status' => 'paid',
        'payment_method' => 'debit',
        'payment_mode' => 'normal',
    ]);

    (new DashboardSyncService)->sync();

    $dashboard = Dashboard::query()->findOrFail(1);

    expect((float) $dashboard->total_amount)->toBe(241000.0)
        ->and((float) $dashboard->total_tax)->toBe(15400.0)
        ->and((float) $dashboard->total_service_charge)->toBe(12700.0)
        ->and((float) $dashboard->total_cash)->toBe(30000.0)
        ->and((float) $dashboard->total_transfer)->toBe(50000.0)
        ->and((float) $dashboard->total_debit)->toBe(121000.0)
        ->and((float) $dashboard->total_qris)->toBe(20000.0)
        ->and((float) $dashboard->total_kredit)->toBe(20000.0)
        ->and((int) $dashboard->total_kitchen_items)->toBe(7)
        ->and((int) $dashboard->total_bar_items)->toBe(5)
        ->and((int) $dashboard->total_transactions)->toBe(3);
});

test('dashboard sync includes walk-in split orders with null payment method', function () {
    $admin = adminUser();

    createDashboardKitchenAndBarItems($admin->id, 2, 1);

    Billing::create([
        'table_session_id' => null,
        'order_id' => null,
        'is_walk_in' => true,
        'is_booking' => false,
        'minimum_charge' => 0,
        'orders_total' => 100000,
        'subtotal' => 100000,
        'tax' => 11000,
        'tax_percentage' => 10,
        'service_charge' => 10000,
        'service_charge_percentage' => 10,
        'discount_amount' => 0,
        'grand_total' => 121000,
        'paid_amount' => 121000,
        'billing_status' => 'paid',
        'payment_method' => null,
        'payment_mode' => 'split',
        'split_cash_amount' => 21000,
        'split_debit_amount' => 100000,
        'split_non_cash_method' => 'qris',
        'split_non_cash_reference_number' => 'WALKIN-SPLIT-001',
    ]);

    (new DashboardSyncService)->sync();

    $dashboard = Dashboard::query()->findOrFail(1);

    expect((float) $dashboard->total_amount)->toBe(121000.0)
        ->and((float) $dashboard->total_tax)->toBe(11000.0)
        ->and((float) $dashboard->total_service_charge)->toBe(10000.0)
        ->and((float) $dashboard->total_cash)->toBe(21000.0)
        ->and((float) $dashboard->total_qris)->toBe(100000.0)
        ->and((int) $dashboard->total_kitchen_items)->toBe(2)
        ->and((int) $dashboard->total_bar_items)->toBe(1)
        ->and((int) $dashboard->total_transactions)->toBe(1);
});

test('dashboard sync uses paid_at instead of updated_at for paid billing window', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-11 12:00:00', 'Asia/Jakarta'));

    try {
        $admin = adminUser();
        createDashboardKitchenAndBarItems($admin->id, 1, 1);

        $staleBilling = Billing::create([
            'table_session_id' => null,
            'order_id' => null,
            'is_walk_in' => true,
            'is_booking' => false,
            'minimum_charge' => 0,
            'orders_total' => 500000,
            'subtotal' => 500000,
            'tax' => 0,
            'tax_percentage' => 0,
            'service_charge' => 0,
            'service_charge_percentage' => 0,
            'discount_amount' => 0,
            'grand_total' => 500000,
            'paid_amount' => 500000,
            'billing_status' => 'paid',
            'paid_at' => Carbon::parse('2026-04-10 08:00:00', 'Asia/Jakarta'),
            'payment_method' => 'cash',
            'payment_mode' => 'normal',
        ]);

        DB::table('billings')
            ->where('id', $staleBilling->id)
            ->update([
                'updated_at' => Carbon::parse('2026-04-11 10:00:00', 'Asia/Jakarta')->toDateTimeString(),
            ]);

        Billing::create([
            'table_session_id' => null,
            'order_id' => null,
            'is_walk_in' => true,
            'is_booking' => false,
            'minimum_charge' => 0,
            'orders_total' => 120000,
            'subtotal' => 120000,
            'tax' => 0,
            'tax_percentage' => 0,
            'service_charge' => 0,
            'service_charge_percentage' => 0,
            'discount_amount' => 0,
            'grand_total' => 120000,
            'paid_amount' => 120000,
            'billing_status' => 'paid',
            'paid_at' => Carbon::parse('2026-04-11 10:30:00', 'Asia/Jakarta'),
            'payment_method' => 'cash',
            'payment_mode' => 'normal',
        ]);

        (new DashboardSyncService)->sync();

        $dashboard = Dashboard::query()->findOrFail(1);

        expect((float) $dashboard->total_cash)->toBe(120000.0)
            ->and((float) $dashboard->total_amount)->toBe(120000.0)
            ->and((int) $dashboard->total_transactions)->toBe(1);
    } finally {
        Carbon::setTestNow();
    }
});

test('dashboard sync does not double count split second non-cash amount when first non-cash is zero', function () {
    $admin = adminUser();

    createDashboardKitchenAndBarItems($admin->id, 1, 1);

    Billing::create([
        'table_session_id' => null,
        'order_id' => null,
        'is_walk_in' => true,
        'is_booking' => false,
        'minimum_charge' => 0,
        'orders_total' => 600000,
        'subtotal' => 600000,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => 600000,
        'paid_amount' => 600000,
        'billing_status' => 'paid',
        'payment_method' => null,
        'payment_mode' => 'split',
        'split_cash_amount' => 100000,
        'split_debit_amount' => 0,
        'split_non_cash_method' => 'debit',
        'split_non_cash_reference_number' => null,
        'split_second_non_cash_amount' => 500000,
        'split_second_non_cash_method' => 'transfer',
        'split_second_non_cash_reference_number' => 'TRX-500K',
    ]);

    (new DashboardSyncService)->sync();

    $dashboard = Dashboard::query()->findOrFail(1);

    expect((float) $dashboard->total_amount)->toBe(600000.0)
        ->and((float) $dashboard->total_cash)->toBe(100000.0)
        ->and((float) $dashboard->total_transfer)->toBe(500000.0)
        ->and((float) $dashboard->total_debit)->toBe(0.0)
        ->and((int) $dashboard->total_transactions)->toBe(1);
});

test('dashboard sync aggregates category main totals from related order items', function () {
    $admin = adminUser();

    $session = makeDashboardSession($admin->id);

    Billing::create([
        'table_session_id' => $session->id,
        'is_walk_in' => false,
        'is_booking' => true,
        'minimum_charge' => 0,
        'orders_total' => 540000,
        'subtotal' => 540000,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => 540000,
        'paid_amount' => 540000,
        'billing_status' => 'paid',
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
    ]);

    $order = Order::create([
        'table_session_id' => $session->id,
        'created_by' => $admin->id,
        'order_number' => 'DSH-CAT-'.uniqid(),
        'status' => 'pending',
        'items_total' => 540000,
        'discount_amount' => 0,
        'total' => 540000,
        'ordered_at' => now(),
    ]);

    $itemsByCategory = [
        ['key' => 'food', 'subtotal' => 10000],
        ['key' => 'alcohol', 'subtotal' => 20000],
        ['key' => 'beverage', 'subtotal' => 30000],
        ['key' => 'cigarette', 'subtotal' => 40000],
        ['key' => 'breakage', 'subtotal' => 50000],
        ['key' => 'room', 'subtotal' => 60000],
        ['key' => 'staff_meal', 'subtotal' => 80000],
        ['key' => 'LD', 'subtotal' => 70000],
    ];

    foreach ($itemsByCategory as $itemCategory) {
        $inventoryItem = InventoryItem::create([
            'code' => strtoupper($itemCategory['key']).'-'.uniqid(),
            'accurate_id' => random_int(100000, 999999),
            'name' => ucfirst(strtolower((string) $itemCategory['key'])).' Item '.uniqid(),
            'category_type' => 'beverage',
            'category_main' => $itemCategory['key'],
            'price' => (float) $itemCategory['subtotal'],
            'stock_quantity' => 10,
            'threshold' => 2,
            'unit' => 'pcs',
            'is_active' => true,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'inventory_item_id' => $inventoryItem->id,
            'item_name' => $inventoryItem->name,
            'item_code' => $inventoryItem->code,
            'quantity' => $itemCategory['key'] === 'LD' ? 4 : 1,
            'price' => (float) $itemCategory['subtotal'],
            'subtotal' => (float) $itemCategory['subtotal'],
            'status' => 'served',
        ]);
    }

    $cancelledInventoryItem = InventoryItem::create([
        'code' => 'FOOD-CANCELLED-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Food Cancelled '.uniqid(),
        'category_type' => 'food',
        'category_main' => 'food',
        'price' => 99999,
        'stock_quantity' => 10,
        'threshold' => 2,
        'unit' => 'pcs',
        'is_active' => true,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $cancelledInventoryItem->id,
        'item_name' => $cancelledInventoryItem->name,
        'item_code' => $cancelledInventoryItem->code,
        'quantity' => 1,
        'price' => 99999,
        'subtotal' => 99999,
        'status' => 'cancelled',
    ]);

    (new DashboardSyncService)->sync();

    $dashboard = Dashboard::query()->findOrFail(1);

    expect((float) $dashboard->total_food)->toBe(10000.0)
        ->and((float) $dashboard->total_alcohol)->toBe(20000.0)
        ->and((float) $dashboard->total_beverage)->toBe(30000.0)
        ->and((float) $dashboard->total_cigarette)->toBe(40000.0)
        ->and((float) $dashboard->total_breakage)->toBe(50000.0)
        ->and((float) $dashboard->total_room)->toBe(60000.0)
        ->and((float) $dashboard->total_staff_meal)->toBe(80000.0)
        ->and((int) $dashboard->total_compliment_quantity)->toBe(0)
        ->and((int) $dashboard->total_foc_quantity)->toBe(0)
        ->and((float) $dashboard->total_ld)->toBe(70000.0)
        ->and((int) $dashboard->total_ld_quantity)->toBe(4);
});

test('dashboard sync aggregates compliment and foc quantities as counts', function () {
    $admin = adminUser();

    $area = Area::create([
        'code' => 'DSH-QTY-AREA-'.uniqid(),
        'name' => 'Dashboard Qty Area '.uniqid(),
        'is_active' => true,
    ]);

    $table = Tabel::create([
        'area_id' => $area->id,
        'table_number' => 'DSH-QTY-TBL-'.uniqid(),
        'qr_code' => 'DSH-QTY-QR-'.uniqid(),
        'capacity' => 4,
        'status' => 'available',
        'is_active' => true,
    ]);

    $session = TableSession::create([
        'table_reservation_id' => null,
        'table_id' => $table->id,
        'customer_id' => $admin->id,
        'session_code' => 'DSH-QTY-SES-'.uniqid(),
        'status' => 'active',
        'billing_id' => null,
    ]);

    // Billing unik per table_session → sesi terpisah utk FOC vs Compliment.
    $focSession = TableSession::create([
        'table_reservation_id' => null,
        'table_id' => $table->id,
        'customer_id' => $admin->id,
        'session_code' => 'DSH-QTY-SES-FOC-'.uniqid(),
        'status' => 'active',
        'billing_id' => null,
    ]);

    $order = Order::create([
        'table_session_id' => $session->id,
        'customer_user_id' => null,
        'created_by' => $admin->id,
        'order_number' => 'DSH-QTY-ORD-'.uniqid(),
        'status' => 'completed',
        'items_total' => 0,
        'discount_amount' => 0,
        'total' => 0,
        'ordered_at' => now(),
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
    ]);

    $complimentItem = InventoryItem::create([
        'code' => 'DSH-QTY-COMP-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Compliment Item',
        'category_type' => 'menu-qty',
        'category_main' => 'compliment',
        'price' => 0,
        'stock_quantity' => 0,
        'threshold' => 0,
        'unit' => 'pcs',
        'is_active' => true,
    ]);

    $focItem = InventoryItem::create([
        'code' => 'DSH-QTY-FOC-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'FOC Item',
        'category_type' => 'menu-qty',
        'category_main' => 'foc',
        'price' => 0,
        'stock_quantity' => 0,
        'threshold' => 0,
        'unit' => 'pcs',
        'is_active' => true,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $complimentItem->id,
        'item_name' => $complimentItem->name,
        'item_code' => $complimentItem->code,
        'quantity' => 3,
        'price' => 0,
        'subtotal' => 0,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'service_charge_amount' => 0,
        'status' => 'served',
    ]);

    // FOC/Compliment qty kini dihitung dari billing yang bertanda foc_comp_payment_method.
    // Pisahkan ke billing sendiri-sendiri agar qty-nya masuk bucket masing-masing.
    $focOrder = Order::create([
        'table_session_id' => $focSession->id,
        'customer_user_id' => null,
        'created_by' => $admin->id,
        'order_number' => 'DSH-QTY-FOC-ORD-'.uniqid(),
        'status' => 'completed',
        'items_total' => 0,
        'discount_amount' => 0,
        'total' => 0,
        'ordered_at' => now(),
        'payment_method' => 'cash',
    ]);

    OrderItem::create([
        'order_id' => $focOrder->id,
        'inventory_item_id' => $focItem->id,
        'item_name' => $focItem->name,
        'item_code' => $focItem->code,
        'quantity' => 5,
        'price' => 0,
        'subtotal' => 0,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'service_charge_amount' => 0,
        'status' => 'served',
    ]);

    Billing::create([
        'table_session_id' => $session->id,
        'order_id' => $order->id,
        'minimum_charge' => 0,
        'orders_total' => 0,
        'subtotal' => 0,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => 0,
        'paid_amount' => 0,
        'billing_status' => 'paid',
        'payment_method' => 'Compliment',
        'payment_mode' => 'normal',
        'is_booking' => true,
        'is_walk_in' => false,
        'foc_comp_payment_method' => 'Compliment',
    ]);

    Billing::create([
        'table_session_id' => $focSession->id,
        'order_id' => $focOrder->id,
        'minimum_charge' => 0,
        'orders_total' => 0,
        'subtotal' => 0,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => 0,
        'paid_amount' => 0,
        'billing_status' => 'paid',
        'payment_method' => 'FOC',
        'payment_mode' => 'normal',
        'is_booking' => true,
        'is_walk_in' => false,
        'foc_comp_payment_method' => 'FOC',
    ]);

    (new DashboardSyncService)->sync();

    $dashboard = Dashboard::query()->findOrFail(1);

    expect((int) $dashboard->total_compliment_quantity)->toBe(3)
        ->and((int) $dashboard->total_foc_quantity)->toBe(5);
});

test('dashboard sync aggregates total dp from paid booking reservations', function () {
    $admin = adminUser();

    $sessionWithDp = makeDashboardSession($admin->id);
    $sessionWithoutDp = makeDashboardSession($admin->id);
    $walkInSession = makeDashboardSession($admin->id);

    $reservation = TableReservation::create([
        'booking_code' => random_int(100000, 999999),
        'table_id' => $sessionWithDp->table_id,
        'customer_id' => $admin->id,
        'reservation_date' => now()->toDateString(),
        'reservation_time' => now()->format('H:i:s'),
        'status' => 'checked_in',
        'down_payment_amount' => 25000,
    ]);

    $sessionWithDp->update(['table_reservation_id' => $reservation->id]);

    Billing::create([
        'table_session_id' => $sessionWithDp->id,
        'is_walk_in' => false,
        'is_booking' => true,
        'minimum_charge' => 0,
        'orders_total' => 0,
        'subtotal' => 0,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => 100000,
        'paid_amount' => 100000,
        'billing_status' => 'paid',
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
    ]);

    Billing::create([
        'table_session_id' => $sessionWithoutDp->id,
        'is_walk_in' => false,
        'is_booking' => true,
        'minimum_charge' => 0,
        'orders_total' => 0,
        'subtotal' => 0,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => 50000,
        'paid_amount' => 50000,
        'billing_status' => 'paid',
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
    ]);

    Billing::create([
        'table_session_id' => $walkInSession->id,
        'is_walk_in' => true,
        'is_booking' => false,
        'minimum_charge' => 0,
        'orders_total' => 0,
        'subtotal' => 0,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => 25000,
        'paid_amount' => 25000,
        'billing_status' => 'paid',
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
    ]);

    (new DashboardSyncService)->sync();

    $dashboard = Dashboard::query()->findOrFail(1);

    expect((float) $dashboard->total_dp)->toBe(25000.0);
});

test('dashboard sync aggregates only current operational-window transactions', function () {
    Carbon::setTestNow(Carbon::create(2026, 3, 27, 2, 0, 0, 'Asia/Jakarta'));

    $admin = adminUser();
    $today = now();
    $yesterday = now()->subDay();

    $sessionYesterday = makeDashboardSession($admin->id);
    $yesterdayBilling = Billing::create([
        'table_session_id' => $sessionYesterday->id,
        'is_walk_in' => false,
        'is_booking' => true,
        'minimum_charge' => 0,
        'orders_total' => 50000,
        'subtotal' => 50000,
        'tax' => 5000,
        'tax_percentage' => 10,
        'service_charge' => 5000,
        'service_charge_percentage' => 10,
        'discount_amount' => 0,
        'grand_total' => 60000,
        'paid_amount' => 60000,
        'billing_status' => 'paid',
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
    ]);

    DB::table('billings')
        ->where('id', $yesterdayBilling->id)
        ->update([
            'created_at' => $yesterday,
            'updated_at' => $yesterday,
        ]);

    $sessionToday = makeDashboardSession($admin->id);
    Billing::create([
        'table_session_id' => $sessionToday->id,
        'is_walk_in' => false,
        'is_booking' => true,
        'minimum_charge' => 0,
        'orders_total' => 40000,
        'subtotal' => 40000,
        'tax' => 4000,
        'tax_percentage' => 10,
        'service_charge' => 2000,
        'service_charge_percentage' => 10,
        'discount_amount' => 0,
        'grand_total' => 46000,
        'paid_amount' => 46000,
        'billing_status' => 'paid',
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
        'created_at' => $today,
        'updated_at' => $today,
    ]);

    $walkInYesterdayBilling = Billing::create([
        'table_session_id' => null,
        'order_id' => null,
        'is_walk_in' => true,
        'is_booking' => false,
        'minimum_charge' => 0,
        'orders_total' => 100000,
        'subtotal' => 100000,
        'tax' => 11000,
        'tax_percentage' => 10,
        'service_charge' => 10000,
        'service_charge_percentage' => 10,
        'discount_amount' => 0,
        'grand_total' => 121000,
        'paid_amount' => 121000,
        'billing_status' => 'paid',
        'payment_method' => 'debit',
        'payment_mode' => 'normal',
    ]);

    createDashboardKitchenAndBarItems($admin->id, 4, 3);

    DB::table('billings')
        ->where('id', $walkInYesterdayBilling->id)
        ->update([
            'created_at' => $yesterday,
            'updated_at' => $yesterday,
        ]);

    Billing::create([
        'table_session_id' => null,
        'order_id' => null,
        'is_walk_in' => true,
        'is_booking' => false,
        'minimum_charge' => 0,
        'orders_total' => 100000,
        'subtotal' => 100000,
        'tax' => 11000,
        'tax_percentage' => 10,
        'service_charge' => 10000,
        'service_charge_percentage' => 10,
        'discount_amount' => 0,
        'grand_total' => 121000,
        'paid_amount' => 121000,
        'billing_status' => 'paid',
        'payment_method' => 'debit',
        'payment_mode' => 'normal',
    ]);

    (new DashboardSyncService)->sync();

    $dashboard = Dashboard::query()->findOrFail(1);

    expect((float) $dashboard->total_amount)->toBe(167000.0)
        ->and((float) $dashboard->total_tax)->toBe(15000.0)
        ->and((float) $dashboard->total_service_charge)->toBe(12000.0)
        ->and((float) $dashboard->total_cash)->toBe(46000.0)
        ->and((float) $dashboard->total_debit)->toBe(121000.0)
        ->and((int) $dashboard->total_kitchen_items)->toBe(4)
        ->and((int) $dashboard->total_bar_items)->toBe(3)
        ->and((int) $dashboard->total_transactions)->toBe(2);

    Carbon::setTestNow();
});

test('dashboard sync excludes billings and items already closed before latest recap close', function () {
    Carbon::setTestNow(Carbon::create(2026, 3, 27, 10, 0, 0, 'Asia/Jakarta'));

    $admin = adminUser();

    $closedAt = now()->setTime(8, 0, 0);

    $recapHistory = RecapHistory::query()->create([
        'end_day' => now()->subDay()->toDateString(),
        'total_amount' => 1000,
        'total_tax' => 100,
        'total_service_charge' => 100,
        'total_cash' => 1000,
        'total_transfer' => 0,
        'total_debit' => 0,
        'total_kredit' => 0,
        'total_qris' => 0,
        'total_transactions' => 1,
        'last_synced_at' => $closedAt,
    ]);

    DB::table('recap_history')
        ->where('id', $recapHistory->id)
        ->update([
            'created_at' => $closedAt,
            'updated_at' => $closedAt,
        ]);

    $sessionBeforeClose = makeDashboardSession($admin->id);
    $beforeCloseBilling = Billing::create([
        'table_session_id' => $sessionBeforeClose->id,
        'is_walk_in' => false,
        'is_booking' => true,
        'minimum_charge' => 0,
        'orders_total' => 50000,
        'subtotal' => 50000,
        'tax' => 5000,
        'tax_percentage' => 10,
        'service_charge' => 5000,
        'service_charge_percentage' => 10,
        'discount_amount' => 0,
        'grand_total' => 60000,
        'paid_amount' => 60000,
        'billing_status' => 'paid',
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
    ]);

    DB::table('billings')
        ->where('id', $beforeCloseBilling->id)
        ->update([
            'updated_at' => now()->setTime(7, 30, 0),
        ]);

    $sessionAfterClose = makeDashboardSession($admin->id);
    $afterCloseBilling = Billing::create([
        'table_session_id' => $sessionAfterClose->id,
        'is_walk_in' => false,
        'is_booking' => true,
        'minimum_charge' => 0,
        'orders_total' => 40000,
        'subtotal' => 40000,
        'tax' => 4000,
        'tax_percentage' => 10,
        'service_charge' => 2000,
        'service_charge_percentage' => 10,
        'discount_amount' => 0,
        'grand_total' => 46000,
        'paid_amount' => 46000,
        'billing_status' => 'paid',
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
    ]);

    DB::table('billings')
        ->where('id', $afterCloseBilling->id)
        ->update([
            'updated_at' => now()->setTime(9, 15, 0),
        ]);

    $order = \App\Models\Order::create([
        'table_session_id' => null,
        'customer_user_id' => null,
        'created_by' => $admin->id,
        'order_number' => 'DSH-CUTOFF-'.uniqid(),
        'status' => 'pending',
        'items_total' => 30000,
        'discount_amount' => 0,
        'total' => 30000,
        'ordered_at' => now(),
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
    ]);

    $kitchenOrderBefore = KitchenOrder::create([
        'order_id' => $order->id,
        'order_number' => $order->order_number,
        'customer_user_id' => null,
        'table_id' => null,
        'total_amount' => 10000,
        'status' => 'selesai',
        'progress' => 100,
    ]);
    $kitchenOrderBefore->forceFill(['created_at' => now()->setTime(7, 0, 0), 'updated_at' => now()->setTime(7, 0, 0)])->save();

    KitchenOrderItem::create([
        'kitchen_order_id' => $kitchenOrderBefore->id,
        'inventory_item_id' => null,
        'quantity' => 3,
        'price' => 10000,
        'is_completed' => true,
    ]);

    $barOrderAfter = BarOrder::create([
        'order_id' => $order->id,
        'order_number' => $order->order_number,
        'customer_user_id' => null,
        'table_id' => null,
        'total_amount' => 10000,
        'payment_method' => 'cash',
        'status' => 'selesai',
        'progress' => 100,
    ]);
    $barOrderAfter->forceFill(['created_at' => now()->setTime(9, 30, 0), 'updated_at' => now()->setTime(9, 30, 0)])->save();

    BarOrderItem::create([
        'bar_order_id' => $barOrderAfter->id,
        'inventory_item_id' => null,
        'quantity' => 2,
        'price' => 10000,
        'is_completed' => true,
    ]);

    (new DashboardSyncService)->sync();

    $dashboard = Dashboard::query()->findOrFail(1);

    expect((float) $dashboard->total_amount)->toBe(46000.0)
        ->and((float) $dashboard->total_tax)->toBe(4000.0)
        ->and((float) $dashboard->total_service_charge)->toBe(2000.0)
        ->and((float) $dashboard->total_cash)->toBe(46000.0)
        ->and((int) $dashboard->total_kitchen_items)->toBe(0)
        ->and((int) $dashboard->total_bar_items)->toBe(2)
        ->and((int) $dashboard->total_transactions)->toBe(1);

    Carbon::setTestNow();
});

test('dashboard sync computes total penjualan rokok from order items category rokok', function () {
    $admin = adminUser();
    $session = makeDashboardSession($admin->id);

    $billing = Billing::create([
        'table_session_id' => $session->id,
        'is_walk_in' => false,
        'is_booking' => true,
        'minimum_charge' => 0,
        'orders_total' => 300000,
        'subtotal' => 300000,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => 300000,
        'paid_amount' => 300000,
        'billing_status' => 'paid',
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
    ]);

    $rokokItem = InventoryItem::query()->create([
        'code' => 'ROKOK-'.uniqid(),
        'accurate_id' => random_int(200000, 299999),
        'name' => 'Rokok Test',
        'category_type' => 'Rokok',
        'price' => 25000,
        'stock_quantity' => 50,
        'threshold' => 5,
        'unit' => 'pack',
        'is_active' => true,
    ]);

    $nonRokokItem = InventoryItem::query()->create([
        'code' => 'NON-ROKOK-'.uniqid(),
        'accurate_id' => random_int(300000, 399999),
        'name' => 'Makanan Test',
        'category_type' => 'Makanan',
        'price' => 45000,
        'stock_quantity' => 50,
        'threshold' => 5,
        'unit' => 'portion',
        'is_active' => true,
    ]);

    $order = Order::create([
        'table_session_id' => $session->id,
        'customer_user_id' => null,
        'created_by' => $admin->id,
        'order_number' => 'DSH-ROKOK-'.uniqid(),
        'status' => 'completed',
        'items_total' => 300000,
        'discount_amount' => 0,
        'total' => 300000,
        'ordered_at' => now(),
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
    ]);

    $billing->update([
        'order_id' => $order->id,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $rokokItem->id,
        'item_name' => $rokokItem->name,
        'item_code' => $rokokItem->code,
        'quantity' => 4,
        'price' => 25000,
        'subtotal' => 100000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'service_charge_amount' => 0,
        'preparation_location' => 'bar',
        'status' => 'pending',
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $nonRokokItem->id,
        'item_name' => $nonRokokItem->name,
        'item_code' => $nonRokokItem->code,
        'quantity' => 2,
        'price' => 100000,
        'subtotal' => 200000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'service_charge_amount' => 0,
        'preparation_location' => 'kitchen',
        'status' => 'pending',
    ]);

    (new DashboardSyncService)->sync();

    $dashboard = Dashboard::query()->findOrFail(1);

    expect((float) $dashboard->total_penjualan_rokok)->toBe(4.0)
        ->and((float) $dashboard->total_amount)->toBe(300000.0);
});

test('dashboard sync aggregates compliment and foc quantities for walk in transactions', function () {
    $admin = adminUser();

    $complimentOrder = Order::create([
        'table_session_id' => null,
        'customer_user_id' => null,
        'created_by' => $admin->id,
        'order_number' => 'DSH-WALKIN-COMP-'.uniqid(),
        'status' => 'completed',
        'items_total' => 0,
        'discount_amount' => 0,
        'total' => 0,
        'ordered_at' => now(),
        'payment_method' => 'Compliment',
        'payment_mode' => 'normal',
    ]);

    $focOrder = Order::create([
        'table_session_id' => null,
        'customer_user_id' => null,
        'created_by' => $admin->id,
        'order_number' => 'DSH-WALKIN-FOC-'.uniqid(),
        'status' => 'completed',
        'items_total' => 0,
        'discount_amount' => 0,
        'total' => 0,
        'ordered_at' => now(),
        'payment_method' => 'FOC',
        'payment_mode' => 'normal',
    ]);

    $complimentItem = InventoryItem::create([
        'code' => 'DSH-WI-COMP-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Walk-In Compliment Item',
        'category_type' => 'menu-qty',
        'category_main' => 'compliment',
        'price' => 0,
        'stock_quantity' => 0,
        'threshold' => 0,
        'unit' => 'pcs',
        'is_active' => true,
    ]);

    $focItem = InventoryItem::create([
        'code' => 'DSH-WI-FOC-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Walk-In FOC Item',
        'category_type' => 'menu-qty',
        'category_main' => 'foc',
        'price' => 0,
        'stock_quantity' => 0,
        'threshold' => 0,
        'unit' => 'pcs',
        'is_active' => true,
    ]);

    OrderItem::create([
        'order_id' => $complimentOrder->id,
        'inventory_item_id' => $complimentItem->id,
        'item_name' => $complimentItem->name,
        'item_code' => $complimentItem->code,
        'quantity' => 2,
        'price' => 0,
        'subtotal' => 0,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'service_charge_amount' => 0,
        'status' => 'served',
    ]);

    OrderItem::create([
        'order_id' => $focOrder->id,
        'inventory_item_id' => $focItem->id,
        'item_name' => $focItem->name,
        'item_code' => $focItem->code,
        'quantity' => 4,
        'price' => 0,
        'subtotal' => 0,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'service_charge_amount' => 0,
        'status' => 'served',
    ]);

    Billing::create([
        'table_session_id' => null,
        'order_id' => $complimentOrder->id,
        'is_walk_in' => true,
        'is_booking' => false,
        'minimum_charge' => 0,
        'orders_total' => 0,
        'subtotal' => 0,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => 0,
        'paid_amount' => 0,
        'billing_status' => 'paid',
        'payment_method' => 'Compliment',
        'payment_mode' => 'normal',
        'foc_comp_payment_method' => 'Compliment',
    ]);

    Billing::create([
        'table_session_id' => null,
        'order_id' => $focOrder->id,
        'is_walk_in' => true,
        'is_booking' => false,
        'minimum_charge' => 0,
        'orders_total' => 0,
        'subtotal' => 0,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => 0,
        'paid_amount' => 0,
        'billing_status' => 'paid',
        'payment_method' => 'FOC',
        'payment_mode' => 'normal',
        'foc_comp_payment_method' => 'FOC',
    ]);

    (new DashboardSyncService)->sync();

    $dashboard = Dashboard::query()->findOrFail(1);

    expect((int) $dashboard->total_compliment_quantity)->toBe(2)
        ->and((int) $dashboard->total_foc_quantity)->toBe(4);
});
