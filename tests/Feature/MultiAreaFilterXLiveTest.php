<?php

use App\Models\Area;
use App\Models\BarOrder;
use App\Models\BarOrderItem;
use App\Models\Billing;
use App\Models\CustomerUser;
use App\Models\InventoryItem;
use App\Models\KitchenOrder;
use App\Models\KitchenOrderItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Tabel;
use App\Models\TableReservation;
use App\Models\TableSession;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\RealtimeTopSpenderBanner;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function makeAreaTestArea(string $code): Area
{
    return Area::create([
        'code' => $code,
        'name' => 'Area '.$code,
        'capacity' => 20,
        'is_active' => true,
        'sort_order' => 1,
    ]);
}

function makeAreaTestTable(Area $area, string $number): Tabel
{
    return Tabel::create([
        'area_id' => $area->id,
        'table_number' => $number,
        'qr_code' => 'QR-'.$number.'-'.uniqid(),
        'capacity' => 4,
        'minimum_charge' => 0,
        'status' => 'available',
        'is_active' => true,
    ]);
}

function makeAreaTestCustomer(string $name): CustomerUser
{
    $user = User::factory()->create(['name' => $name]);
    $profile = UserProfile::create(['user_id' => $user->id, 'full_name' => $name]);
    $customerUser = CustomerUser::create([
        'user_id' => $user->id,
        'user_profile_id' => $profile->id,
        'customer_code' => 'CUST-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
    ]);

    return $customerUser;
}

function makeAreaTestBarOrder(Tabel $table, CustomerUser $customer, string $orderNumber, string $status): BarOrder
{
    $barOrder = BarOrder::create([
        'area_id' => $table->area_id,
        'order_id' => null,
        'order_number' => $orderNumber,
        'customer_user_id' => $customer->id,
        'table_id' => $table->id,
        'total_amount' => 10000,
        'payment_method' => 'cash',
        'status' => $status,
        'progress' => 0,
    ]);

    BarOrderItem::create([
        'bar_order_id' => $barOrder->id,
        'inventory_item_id' => null,
        'quantity' => 1,
        'price' => 10000,
        'is_completed' => false,
    ]);

    return $barOrder;
}

function makeAreaTestKitchenOrder(Tabel $table, CustomerUser $customer, string $orderNumber, string $status): KitchenOrder
{
    $kitchenOrder = KitchenOrder::create([
        'area_id' => $table->area_id,
        'order_id' => null,
        'order_number' => $orderNumber,
        'customer_user_id' => $customer->id,
        'table_id' => $table->id,
        'total_amount' => 10000,
        'payment_method' => 'cash',
        'status' => $status,
        'progress' => 0,
    ]);

    KitchenOrderItem::create([
        'kitchen_order_id' => $kitchenOrder->id,
        'inventory_item_id' => null,
        'quantity' => 1,
        'price' => 10000,
        'is_completed' => false,
    ]);

    return $kitchenOrder;
}

test('fetchOrders filters bar orders by the resolved active area', function () {
    $admin = adminUser();

    $room = makeAreaTestArea('ROOM-T');
    $lounge = makeAreaTestArea('LOUNGE-T');
    $roomTable = makeAreaTestTable($room, 'RM-01');
    $loungeTable = makeAreaTestTable($lounge, 'LG-01');

    $roomCustomer = makeAreaTestCustomer('Room Customer');
    $loungeCustomer = makeAreaTestCustomer('Lounge Customer');

    makeAreaTestBarOrder($roomTable, $roomCustomer, 'BAR-ROOM', 'proses');
    makeAreaTestBarOrder($loungeTable, $loungeCustomer, 'BAR-LOUNGE', 'proses');

    actingAs($admin);

    // Explicit requested area_id -> only that area's orders
    get(route('admin.bar.fetch', ['area_id' => $room->id]))
        ->assertSuccessful()
        ->assertJson(fn ($json) => $json
            ->where('success', true)
            ->has('orders', 1)
            ->where('orders.0.order_number', 'BAR-ROOM')
            ->has('stats'));

    // Explicit all -> all areas
    get(route('admin.bar.fetch', ['area_id' => 'all']))
        ->assertSuccessful()
        ->assertJson(fn ($json) => $json
            ->where('success', true)
            ->has('orders', 2)
            ->has('stats'));
});

test('fetchOrders filters kitchen orders by the resolved active area', function () {
    $admin = adminUser();

    $room = makeAreaTestArea('KROOM-T');
    $lounge = makeAreaTestArea('KLOUNGE-T');
    $roomTable = makeAreaTestTable($room, 'KRM-01');
    $loungeTable = makeAreaTestTable($lounge, 'KLG-01');

    $roomCustomer = makeAreaTestCustomer('Kitchen Room Customer');
    $loungeCustomer = makeAreaTestCustomer('Kitchen Lounge Customer');

    makeAreaTestKitchenOrder($roomTable, $roomCustomer, 'KIT-ROOM', 'proses');
    makeAreaTestKitchenOrder($loungeTable, $loungeCustomer, 'KIT-LOUNGE', 'proses');

    actingAs($admin);

    get(route('admin.kitchen.fetch', ['area_id' => $room->id]))
        ->assertSuccessful()
        ->assertJson(fn ($json) => $json
            ->where('success', true)
            ->has('orders', 1)
            ->where('orders.0.order_number', 'KIT-ROOM')
            ->has('stats'));

    get(route('admin.kitchen.fetch', ['area_id' => 'all']))
        ->assertSuccessful()
        ->assertJson(fn ($json) => $json
            ->where('success', true)
            ->has('orders', 2)
            ->has('stats'));
});

test('resolveActiveAreaId falls back to the active session area', function () {
    $admin = adminUser();
    $room = makeAreaTestArea('SESS-T');

    session()->put('active_area_id', (string) $room->id);

    expect($admin->resolveActiveAreaId())->toBe($room->id);
    expect($admin->resolveActiveAreaId('all', true))->toBeNull();
    expect($admin->resolveActiveAreaId('', true))->toBeNull();
});

function makeAreaTestActiveSession(Tabel $table, User $customer, User $createdBy, string $bookingCode, string $bookingName): TableSession
{
    $reservation = TableReservation::create([
        'booking_code' => $bookingCode,
        'booking_name' => $bookingName,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'created_by' => $createdBy->id,
        'reservation_date' => now()->toDateString(),
        'reservation_time' => now()->format('H:i:s'),
        'status' => 'checked_in',
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $reservation->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'session_code' => 'SES-'.$bookingCode,
        'checked_in_at' => now()->subMinutes(25),
        'status' => 'active',
    ]);

    $inventoryItem = InventoryItem::create([
        'code' => 'ITEM-'.$bookingCode,
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Item '.$bookingCode,
        'category_type' => 'beverage',
        'price' => 100000,
        'stock_quantity' => 10,
        'threshold' => 1,
        'unit' => 'glass',
        'is_active' => true,
    ]);

    $order = Order::create([
        'table_session_id' => $session->id,
        'created_by' => $createdBy->id,
        'order_number' => 'ORD-'.$bookingCode,
        'status' => 'pending',
        'items_total' => 100000,
        'discount_amount' => 0,
        'total' => 100000,
        'ordered_at' => now(),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $inventoryItem->id,
        'item_name' => $inventoryItem->name,
        'item_code' => $inventoryItem->code,
        'quantity' => 1,
        'price' => 100000,
        'subtotal' => 100000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'service_charge_amount' => 0,
        'status' => 'pending',
    ]);

    return $session;
}

test('RealtimeTopSpenderBanner scopes top spenders by area', function () {
    $admin = adminUser();

    $room = makeAreaTestArea('BNR-ROOM');
    $lounge = makeAreaTestArea('BNR-LOUNGE');
    $roomTable = makeAreaTestTable($room, 'BR-01');
    $loungeTable = makeAreaTestTable($lounge, 'BL-01');

    $roomCustomer = User::factory()->create(['name' => 'Banner Room Spender']);
    $loungeCustomer = User::factory()->create(['name' => 'Banner Lounge Spender']);

    makeAreaTestActiveSession($roomTable, $roomCustomer, $admin, '201', 'Room Spender');
    makeAreaTestActiveSession($loungeTable, $loungeCustomer, $admin, '202', 'Lounge Spender');

    $banner = app(RealtimeTopSpenderBanner::class);

    $roomTop = $banner->topSpenders(3, $room->id);
    $loungeTop = $banner->topSpenders(3, $lounge->id);
    $allTop = $banner->topSpenders(3);

    expect(collect($roomTop)->pluck('customer_name')->all())->toBe(['Room Spender']);
    expect(collect($loungeTop)->pluck('customer_name')->all())->toBe(['Lounge Spender']);
    expect(collect($allTop)->pluck('customer_name')->all())->toContain('Room Spender', 'Lounge Spender');
});

test('X-Live header returns only the dashboard stats partial with the X-Live header', function () {
    $admin = adminUser();
    $room = makeAreaTestArea('LIVE-ROOM');
    $roomTable = makeAreaTestTable($room, 'LR-01');

    $session = TableSession::create([
        'table_id' => $roomTable->id,
        'customer_id' => $admin->id,
        'session_code' => 'LIVE-SES',
        'checked_in_at' => now(),
        'status' => 'active',
    ]);

    Billing::create([
        'table_session_id' => $session->id,
        'area_id' => $room->id,
        'is_booking' => true,
        'orders_total' => 100000,
        'subtotal' => 100000,
        'grand_total' => 100000,
        'paid_amount' => 100000,
        'billing_status' => 'paid',
        'paid_at' => now(),
    ]);

    (new \App\Services\DashboardSyncService)->sync($room->id);

    actingAs($admin);

    get(route('admin.dashboard', ['area_id' => $room->id]), ['X-Live' => '1'])
        ->assertSuccessful()
        ->assertHeader('X-Live', '1')
        ->assertSee('Rp 100.000')
        ->assertSee('Pendapatan Hari Ini')
        ->assertDontSee('Area '.$room->code);

    // Non-live full page still renders the area selector.
    get(route('admin.dashboard', ['area_id' => $room->id]))
        ->assertSuccessful()
        ->assertSee('Area '.$room->code);
});

test('X-Live header returns only the recap summary partial', function () {
    $admin = adminUser();
    $room = makeAreaTestArea('RECAP-LIVE');

    actingAs($admin);

    get(route('admin.recap.index', ['area_id' => $room->id]), ['X-Live' => '1'])
        ->assertSuccessful()
        ->assertHeader('X-Live', '1');

    // Non-live renders the full recap page.
    get(route('admin.recap.index', ['area_id' => $room->id]))
        ->assertSuccessful();
});
