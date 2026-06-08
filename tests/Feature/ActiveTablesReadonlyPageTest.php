<?php

use App\Models\Area;
use App\Models\Billing;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Tabel;
use App\Models\TableSession;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

function readonlyActiveTablesUser(): User
{
    $user = User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'Readonly Active Tables', 'guard_name' => 'web']);
    $permission = Permission::firstOrCreate(['name' => 'admin.active-tables.readonly', 'guard_name' => 'web']);
    $role->syncPermissions([$permission]);
    $user->assignRole($role);

    return $user;
}

test('user with readonly active tables permission can access readonly page from sidebar route', function () {
    $user = readonlyActiveTablesUser();

    actingAs($user)
        ->withSession(['accurate_database' => 'test'])
        ->get(route('admin.active-tables.readonly'))
        ->assertSuccessful()
        ->assertViewIs('active-tables.readonly')
        ->assertSee('Readonly')
        ->assertSee('Active Tables');
});

test('readonly active tables page does not show edit controls', function () {
    $user = readonlyActiveTablesUser();
    $area = Area::create([
        'code' => 'READ',
        'name' => 'Readonly Area',
        'capacity' => 20,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $table = Tabel::create([
        'area_id' => $area->id,
        'table_number' => 'R-01',
        'qr_code' => 'QR-READ-01',
        'capacity' => 4,
        'minimum_charge' => 0,
        'status' => 'occupied',
        'is_active' => true,
    ]);

    $customer = User::factory()->create(['name' => 'Readonly Guest']);
    $inventoryItem = InventoryItem::create([
        'code' => 'READ-ITEM-'.uniqid(),
        'accurate_id' => random_int(1, 999999),
        'name' => 'Readonly Item',
        'category_type' => 'beverage',
        'price' => 210000,
        'stock_quantity' => 10,
        'threshold' => 1,
        'unit' => 'glass',
        'is_active' => true,
    ]);

    $session = TableSession::create([
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'session_code' => 'READONLY-SESSION-1',
        'checked_in_at' => now()->subMinutes(30),
        'status' => 'active',
        'pax' => 4,
    ]);

    $order = Order::create([
        'table_session_id' => $session->id,
        'created_by' => $user->id,
        'order_number' => 'READ-ORDER-1',
        'status' => 'pending',
        'items_total' => 210000,
        'discount_amount' => 0,
        'total' => 210000,
        'ordered_at' => now(),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $inventoryItem->id,
        'item_name' => $inventoryItem->name,
        'item_code' => $inventoryItem->code,
        'quantity' => 1,
        'price' => 210000,
        'subtotal' => 210000,
        'discount_amount' => 0,
        'tax_amount' => 15000,
        'service_charge_amount' => 5000,
        'status' => 'pending',
    ]);

    $billing = Billing::create([
        'table_session_id' => $session->id,
        'minimum_charge' => 0,
        'orders_total' => 210000,
        'subtotal' => 999999,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => 888888,
        'paid_amount' => 0,
        'billing_status' => 'draft',
    ]);

    $session->update(['billing_id' => $billing->id]);

    actingAs($user)
        ->withSession(['accurate_database' => 'test'])
        ->get(route('admin.active-tables.readonly'))
        ->assertSuccessful()
        ->assertSee('Readonly Guest')
        ->assertSee('Meja')
        ->assertSee('Customer')
        ->assertSee('Waiter')
        ->assertSee('Check-in')
        ->assertSee('Min. Charge')
        ->assertSee('DP')
        ->assertSee('Event')
        ->assertSee('Orders')
        ->assertSee('Subtotal')
        ->assertSee('Service Charge')
        ->assertSee('PB1')
        ->assertSee('Rp 230.000')
        ->assertDontSee('Aksi')
        ->assertDontSee('Remove')
        ->assertDontSee('Edit pax')
        ->assertDontSee('admin.active-tables.updatePax', false)
        ->assertDontSee('x-data="paxEditor', false);
});

test('user without readonly active tables permission cannot access readonly page', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->withSession(['accurate_database' => 'test'])
        ->get(route('admin.active-tables.readonly'))
        ->assertForbidden();
});
