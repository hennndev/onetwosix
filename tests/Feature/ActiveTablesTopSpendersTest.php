<?php

use App\Models\Area;
use App\Models\Billing;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Tabel;
use App\Models\TableReservation;
use App\Models\TableSession;
use App\Models\User;
use App\Support\RealtimeTopSpenderBanner;

use function Pest\Laravel\actingAs;

function makeTopSpenderArea(string $codePrefix, string $name): Area
{
    return Area::create([
        'code' => $codePrefix.'-'.uniqid(),
        'name' => $name,
        'capacity' => 20,
        'is_active' => true,
        'sort_order' => 1,
    ]);
}

function makeTopSpenderTable(Area $area, string $tableNumber): Tabel
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

function makeTopSpenderActiveBooking(
    Tabel $table,
    User $customer,
    User $createdBy,
    string $bookingCode,
    int $orderSubtotal,
    int $taxAmount,
    int $serviceChargeAmount,
    int $billingSubtotal,
): TableSession {
    $reservation = TableReservation::create([
        'booking_code' => $bookingCode,
        'booking_name' => 'Top Spender '.$bookingCode,
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

    $billing = Billing::create([
        'table_session_id' => $session->id,
        'is_booking' => true,
        'minimum_charge' => $billingSubtotal,
        'orders_total' => $billingSubtotal,
        'subtotal' => $billingSubtotal,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => $billingSubtotal,
        'paid_amount' => 0,
        'billing_status' => 'draft',
    ]);

    $session->update(['billing_id' => $billing->id]);

    $order = Order::create([
        'table_session_id' => $session->id,
        'created_by' => $createdBy->id,
        'order_number' => 'ORD-'.$bookingCode,
        'status' => 'pending',
        'items_total' => $billingSubtotal,
        'discount_amount' => 0,
        'total' => $billingSubtotal,
        'ordered_at' => now(),
    ]);

    $inventoryItem = InventoryItem::create([
        'code' => 'ITEM-'.$bookingCode,
        'accurate_id' => random_int(1, 999999),
        'name' => 'Item '.$bookingCode,
        'category_type' => 'beverage',
        'price' => $orderSubtotal,
        'stock_quantity' => 10,
        'threshold' => 1,
        'unit' => 'glass',
        'is_active' => true,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $inventoryItem->id,
        'item_name' => $inventoryItem->name,
        'item_code' => $inventoryItem->code,
        'quantity' => 1,
        'price' => $orderSubtotal,
        'subtotal' => $orderSubtotal,
        'discount_amount' => 0,
        'tax_amount' => $taxAmount,
        'service_charge_amount' => $serviceChargeAmount,
        'status' => 'pending',
    ]);

    return $session;
}

test('active tables page shows top 3 spenders sorted by realtime subtotal', function () {
    $admin = adminUser();
    $customerA = User::factory()->create(['name' => 'Alpha Spender']);
    $customerB = User::factory()->create(['name' => 'Beta Spender']);
    $customerC = User::factory()->create(['name' => 'Gamma Spender']);

    $area = makeTopSpenderArea('TS', 'Top Spender Area');
    $tableA = makeTopSpenderTable($area, 'TS-01');
    $tableB = makeTopSpenderTable($area, 'TS-02');
    $tableC = makeTopSpenderTable($area, 'TS-03');

    makeTopSpenderActiveBooking($tableA, $customerA, $admin, '001', 100000, 5000, 5000, 120000);
    makeTopSpenderActiveBooking($tableB, $customerB, $admin, '002', 200000, 10000, 10000, 220000);
    makeTopSpenderActiveBooking($tableC, $customerC, $admin, '003', 300000, 15000, 15000, 330000);

    expect(collect(app(RealtimeTopSpenderBanner::class)->topSpenders(3))->pluck('customer_name')->all())
        ->toBe([
            'Top Spender 003',
            'Top Spender 002',
            'Top Spender 001',
        ]);

    actingAs($admin)
        ->withSession(['accurate_database' => 'test'])
        ->get(route('admin.active-tables.index'))
        ->assertSuccessful()
        ->assertSee('Top 3 Spender')
        ->assertSee('#1')
        ->assertSee('#2')
        ->assertSee('#3')
        ->assertSee('Top Spender 003')
        ->assertSee('Top Spender 002')
        ->assertSee('Top Spender 001')
        ->assertSee('Rp 330.000')
        ->assertSee('Rp 220.000')
        ->assertSee('Rp 110.000');
});

test('active bookings tab shows subtotal column', function () {
    $admin = adminUser();
    $customer = User::factory()->create(['name' => 'Subtotal Booker']);
    $area = makeTopSpenderArea('BK', 'Booking Area');
    $table = makeTopSpenderTable($area, 'BK-01');

    makeTopSpenderActiveBooking($table, $customer, $admin, '101', 100000, 5000, 5000, 125000);

    actingAs($admin)
        ->withSession(['accurate_database' => 'test'])
        ->get(route('admin.bookings.index', ['tab' => 'active']))
        ->assertSuccessful()
        ->assertSee('Subtotal')
        ->assertSee('Rp 125.000');
});
