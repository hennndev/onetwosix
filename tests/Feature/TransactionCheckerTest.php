<?php

use App\Models\Area;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Tabel;
use App\Models\TableSession;
use App\Models\User;

use function Pest\Laravel\actingAs;

test('transaction checker page shows order cards for authenticated admin users', function () {
    $user = adminUser();
    $customer = User::factory()->create();

    $area = Area::create([
        'code' => 'VIP',
        'name' => 'VIP Room',
        'capacity' => 10,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $table = Tabel::create([
        'area_id' => $area->id,
        'table_number' => 'A1',
        'qr_code' => 'QR-A1',
        'capacity' => 4,
        'status' => 'available',
        'is_active' => true,
    ]);

    $session = TableSession::create([
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'session_code' => 'SESSION-001',
        'status' => 'active',
    ]);

    $inventoryItem = InventoryItem::create([
        'code' => 'BEV-001',
        'accurate_id' => 1001,
        'name' => 'Es Teh Manis',
        'category_type' => 'beverage',
        'price' => 12500,
        'stock_quantity' => 20,
        'threshold' => 5,
        'unit' => 'glass',
        'is_active' => true,
        'item_produced' => false,
        'material_produced' => false,
    ]);

    $order = Order::create([
        'table_session_id' => $session->id,
        'created_by' => $user->id,
        'order_number' => 'ORD-TRX-001',
        'status' => 'pending',
        'items_total' => 25000,
        'discount_amount' => 0,
        'total' => 25000,
        'ordered_at' => now(),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $inventoryItem->id,
        'item_name' => 'Es Teh Manis',
        'item_code' => 'BEV-001',
        'quantity' => 2,
        'price' => 12500,
        'subtotal' => 25000,
        'discount_amount' => 0,
        'preparation_location' => 'bar',
        'status' => 'pending',
    ]);

    $displayId = '#TRX-TODAY-'.$order->id;

    actingAs($user)
        ->withSession(['accurate_database' => 'test'])
        ->get(route('admin.transaction-checker.index'))
        ->assertOk()
        ->assertViewIs('transaction-checker.index')
        ->assertSee('Transaction Checker')
        ->assertSee($displayId)
        ->assertSee('Es Teh Manis')
        ->assertSee('Check All');
});

test('transaction checker filters orders by selected area', function () {
    $admin = adminUser();

    $areaA = Area::create(['code' => 'AREA-A', 'name' => 'Area A', 'is_active' => true, 'sort_order' => 1]);
    $areaB = Area::create(['code' => 'AREA-B', 'name' => 'Area B', 'is_active' => true, 'sort_order' => 2]);

    $customer = User::factory()->create();
    $tableA = Tabel::create(['area_id' => $areaA->id, 'table_number' => 'TRX-T1', 'qr_code' => 'TRX-QR1', 'capacity' => 4, 'status' => 'available', 'is_active' => true]);
    $tableB = Tabel::create(['area_id' => $areaB->id, 'table_number' => 'TRX-T2', 'qr_code' => 'TRX-QR2', 'capacity' => 4, 'status' => 'available', 'is_active' => true]);
    $sessionA = TableSession::create(['table_id' => $tableA->id, 'customer_id' => $customer->id, 'session_code' => 'TRX-SES-A', 'status' => 'active']);
    $sessionB = TableSession::create(['table_id' => $tableB->id, 'customer_id' => $customer->id, 'session_code' => 'TRX-SES-B', 'status' => 'active']);

    $orderA = Order::create([
        'table_session_id' => $sessionA->id,
        'area_id' => $areaA->id,
        'created_by' => $admin->id,
        'order_number' => 'ORD-AREA-A',
        'status' => 'pending',
        'items_total' => 10000,
        'discount_amount' => 0,
        'total' => 10000,
        'ordered_at' => now(),
    ]);

    $orderB = Order::create([
        'table_session_id' => $sessionB->id,
        'area_id' => $areaB->id,
        'created_by' => $admin->id,
        'order_number' => 'ORD-AREA-B',
        'status' => 'pending',
        'items_total' => 20000,
        'discount_amount' => 0,
        'total' => 20000,
        'ordered_at' => now(),
    ]);

    actingAs($admin)
        ->withSession(['accurate_database' => 'test'])
        ->get(route('admin.transaction-checker.index', ['area_id' => $areaA->id]))
        ->assertOk()
        ->assertViewHas('selectedAreaId', $areaA->id)
        ->assertViewHas('orders', fn ($orders) => $orders->contains('id', $orderA->id) && ! $orders->contains('id', $orderB->id));

    actingAs($admin)
        ->withSession(['accurate_database' => 'test'])
        ->get(route('admin.transaction-checker.index', ['area_id' => $areaB->id]))
        ->assertOk()
        ->assertViewHas('orders', fn ($orders) => $orders->contains('id', $orderB->id) && ! $orders->contains('id', $orderA->id));
});

test('transaction checker shows all orders when area_id is all', function () {
    $admin = adminUser();

    $areaA = Area::create(['code' => 'AREA-ALL-A', 'name' => 'Area All A', 'is_active' => true, 'sort_order' => 1]);
    $areaB = Area::create(['code' => 'AREA-ALL-B', 'name' => 'Area All B', 'is_active' => true, 'sort_order' => 2]);

    $customer = User::factory()->create();
    $tableA = Tabel::create(['area_id' => $areaA->id, 'table_number' => 'TRX-ALL-T1', 'qr_code' => 'TRX-ALL-QR1', 'capacity' => 4, 'status' => 'available', 'is_active' => true]);
    $tableB = Tabel::create(['area_id' => $areaB->id, 'table_number' => 'TRX-ALL-T2', 'qr_code' => 'TRX-ALL-QR2', 'capacity' => 4, 'status' => 'available', 'is_active' => true]);
    $sessionA = TableSession::create(['table_id' => $tableA->id, 'customer_id' => $customer->id, 'session_code' => 'TRX-SES-ALL-A', 'status' => 'active']);
    $sessionB = TableSession::create(['table_id' => $tableB->id, 'customer_id' => $customer->id, 'session_code' => 'TRX-SES-ALL-B', 'status' => 'active']);

    $orderA = Order::create(['table_session_id' => $sessionA->id, 'area_id' => $areaA->id, 'created_by' => $admin->id, 'order_number' => 'ORD-ALL-A', 'status' => 'pending', 'items_total' => 0, 'discount_amount' => 0, 'total' => 0, 'ordered_at' => now()]);
    $orderB = Order::create(['table_session_id' => $sessionB->id, 'area_id' => $areaB->id, 'created_by' => $admin->id, 'order_number' => 'ORD-ALL-B', 'status' => 'pending', 'items_total' => 0, 'discount_amount' => 0, 'total' => 0, 'ordered_at' => now()]);

    actingAs($admin)
        ->withSession(['accurate_database' => 'test'])
        ->get(route('admin.transaction-checker.index', ['area_id' => 'all']))
        ->assertOk()
        ->assertViewHas('selectedAreaId', null)
        ->assertViewHas('orders', fn ($orders) => $orders->contains('id', $orderA->id) && $orders->contains('id', $orderB->id));
});

test('transaction checker area filter and tab filter work together', function () {
    $admin = adminUser();

    $area = Area::create(['code' => 'TRX-COMBO', 'name' => 'Combo Area', 'is_active' => true, 'sort_order' => 1]);
    $customer = User::factory()->create();
    $table = Tabel::create(['area_id' => $area->id, 'table_number' => 'TRX-CMB-T1', 'qr_code' => 'TRX-CMB-QR1', 'capacity' => 4, 'status' => 'available', 'is_active' => true]);
    $session = TableSession::create(['table_id' => $table->id, 'customer_id' => $customer->id, 'session_code' => 'TRX-SES-CMB', 'status' => 'active']);

    $pendingOrder = Order::create(['table_session_id' => $session->id, 'area_id' => $area->id, 'created_by' => $admin->id, 'order_number' => 'ORD-CMB-PEND', 'status' => 'pending', 'items_total' => 0, 'discount_amount' => 0, 'total' => 0, 'ordered_at' => now()]);
    $completedOrder = Order::create(['table_session_id' => $session->id, 'area_id' => $area->id, 'created_by' => $admin->id, 'order_number' => 'ORD-CMB-DONE', 'status' => 'completed', 'items_total' => 0, 'discount_amount' => 0, 'total' => 0, 'ordered_at' => now()]);

    actingAs($admin)
        ->withSession(['accurate_database' => 'test'])
        ->get(route('admin.transaction-checker.index', ['area_id' => $area->id, 'tab' => 'selesai']))
        ->assertOk()
        ->assertViewHas('orders', fn ($orders) => $orders->contains('id', $completedOrder->id) && ! $orders->contains('id', $pendingOrder->id));
});
