<?php

use App\Models\Area;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Tabel;
use App\Models\TableReservation;
use App\Models\TableSession;
use App\Models\User;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

function makeBannerArea(): Area
{
    return Area::create([
        'code' => 'BNR',
        'name' => 'Banner Lounge',
        'capacity' => 20,
        'is_active' => true,
        'sort_order' => 1,
    ]);
}

function makeBannerTable(Area $area, string $tableNumber): Tabel
{
    return Tabel::create([
        'area_id' => $area->id,
        'table_number' => $tableNumber,
        'qr_code' => 'QR-'.$tableNumber.'-'.uniqid(),
        'capacity' => 6,
        'minimum_charge' => 0,
        'status' => 'occupied',
        'is_active' => true,
    ]);
}

function makeWaiterUserForBanner(): User
{
    Role::firstOrCreate(['name' => 'Waiter/Server', 'guard_name' => 'web']);

    $waiter = User::factory()->create();
    $waiter->assignRole('Waiter/Server');

    return $waiter;
}

function makeBannerInventoryItem(array $attributes = []): InventoryItem
{
    return InventoryItem::create(array_merge([
        'code' => 'BNR-ITEM-'.uniqid(),
        'accurate_id' => random_int(1, 999999),
        'name' => 'Banner Item '.uniqid(),
        'category_type' => 'beverage',
        'price' => 10000,
        'stock_quantity' => 50,
        'threshold' => 5,
        'unit' => 'glass',
        'is_active' => true,
    ], $attributes));
}

function makeActiveBookingSession(Tabel $table, User $customer, User $createdBy, ?User $waiter = null, string $bookingName = 'Realtime Booker'): TableSession
{
    $reservation = TableReservation::create([
        'booking_code' => random_int(100000, 999999),
        'booking_name' => $bookingName,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'created_by' => $createdBy->id,
        'reservation_date' => now()->toDateString(),
        'reservation_time' => now()->format('H:i:s'),
        'status' => 'checked_in',
    ]);

    return TableSession::create([
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'table_reservation_id' => $reservation->id,
        'waiter_id' => $waiter?->id,
        'session_code' => 'SESSION-'.uniqid(),
        'checked_in_at' => now()->subMinutes(20),
        'status' => 'active',
    ]);
}

test('app layout shows realtime top spender banner from active booking orders', function () {
    $admin = adminUser();
    $area = makeBannerArea();
    $customer = User::factory()->create(['name' => 'Realtime Alpha']);
    $table = makeBannerTable($area, 'VIP-01');
    $session = makeActiveBookingSession($table, $customer, $admin, null, 'Realtime Alpha');
    $inventoryItem = makeBannerInventoryItem();

    $firstOrder = Order::create([
        'table_session_id' => $session->id,
        'created_by' => $admin->id,
        'order_number' => 'ORD-REALTIME-1',
        'status' => 'pending',
        'items_total' => 175000,
        'discount_amount' => 0,
        'total' => 175000,
        'ordered_at' => now(),
    ]);

    $secondOrder = Order::create([
        'table_session_id' => $session->id,
        'created_by' => $admin->id,
        'order_number' => 'ORD-REALTIME-2',
        'status' => 'pending',
        'items_total' => 225000,
        'discount_amount' => 0,
        'total' => 225000,
        'ordered_at' => now(),
    ]);

    OrderItem::create([
        'order_id' => $firstOrder->id,
        'inventory_item_id' => $inventoryItem->id,
        'item_name' => $inventoryItem->name,
        'item_code' => $inventoryItem->code,
        'quantity' => 1,
        'price' => 175000,
        'subtotal' => 175000,
        'discount_amount' => 0,
        'tax_amount' => 17500,
        'service_charge_amount' => 8750,
        'status' => 'pending',
    ]);

    OrderItem::create([
        'order_id' => $secondOrder->id,
        'inventory_item_id' => $inventoryItem->id,
        'item_name' => $inventoryItem->name,
        'item_code' => $inventoryItem->code,
        'quantity' => 1,
        'price' => 225000,
        'subtotal' => 225000,
        'discount_amount' => 0,
        'tax_amount' => 22500,
        'service_charge_amount' => 11250,
        'status' => 'pending',
    ]);

    actingAs($admin)
        ->withSession(['accurate_database' => 'test'])
        ->get(route('admin.dashboard'))
        ->assertSuccessful()
        ->assertSee('Realtime Top Spender')
        ->assertSee('Realtime Alpha')
        ->assertSee('Active booking table VIP-01')
        ->assertSee('Rp 460.000');
});

test('waiter layout shows realtime top spender banner from active booking orders', function () {
    $waiter = makeWaiterUserForBanner();
    $area = makeBannerArea();
    $customer = User::factory()->create(['name' => 'Realtime Bravo']);
    $table = makeBannerTable($area, 'VIP-02');
    $session = makeActiveBookingSession($table, $customer, $waiter, $waiter, 'Realtime Bravo');
    $inventoryItem = makeBannerInventoryItem();

    $order = Order::create([
        'table_session_id' => $session->id,
        'created_by' => $waiter->id,
        'order_number' => 'ORD-WAITER-REALTIME',
        'status' => 'pending',
        'items_total' => 310000,
        'discount_amount' => 0,
        'total' => 310000,
        'ordered_at' => now(),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $inventoryItem->id,
        'item_name' => $inventoryItem->name,
        'item_code' => $inventoryItem->code,
        'quantity' => 1,
        'price' => 310000,
        'subtotal' => 310000,
        'discount_amount' => 0,
        'tax_amount' => 31000,
        'service_charge_amount' => 15500,
        'status' => 'pending',
    ]);

    actingAs($waiter)
        ->withSession(['accurate_database' => 'test'])
        ->get(route('waiter.scanner'))
        ->assertSuccessful()
        ->assertSee('Realtime Top Spender')
        ->assertSee('Realtime Bravo')
        ->assertSee('Active booking table VIP-02')
        ->assertSee('Rp 356.500');
});
