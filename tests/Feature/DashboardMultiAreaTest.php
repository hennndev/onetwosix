<?php

use App\Models\Area;
use App\Models\Billing;
use App\Models\Order;
use App\Models\Tabel;
use App\Models\TableSession;

use function Pest\Laravel\actingAs;

test('dashboard page renders area selector pills and filters metrics by area', function (): void {
    $admin = adminUser();

    $roomArea = Area::create([
        'code' => 'ROOM_TEST',
        'name' => 'Test Room Area',
        'capacity' => 10,
        'is_active' => true,
        'sort_order' => 10,
    ]);

    $loungeArea = Area::create([
        'code' => 'LNG_TEST',
        'name' => 'Test Lounge Area',
        'capacity' => 20,
        'is_active' => true,
        'sort_order' => 11,
    ]);

    $roomTable = Tabel::create([
        'area_id' => $roomArea->id,
        'table_number' => 'R-01',
        'capacity' => 4,
        'status' => 'available',
        'is_active' => true,
        'qr_code' => 'QR-R-01',
    ]);

    $loungeTable = Tabel::create([
        'area_id' => $loungeArea->id,
        'table_number' => 'L-01',
        'capacity' => 4,
        'status' => 'available',
        'is_active' => true,
        'qr_code' => 'QR-L-01',
    ]);

    $roomSession = TableSession::create([
        'table_id' => $roomTable->id,
        'customer_id' => $admin->id,
        'session_code' => 'SESS-R1',
        'checked_in_at' => now(),
        'status' => 'active',
    ]);

    $roomOrder = Order::create([
        'table_session_id' => $roomSession->id,
        'order_number' => 'ORD-R1',
        'status' => 'completed',
        'items_total' => 500000,
        'total' => 500000,
        'ordered_at' => now(),
    ]);

    Billing::create([
        'table_session_id' => $roomSession->id,
        'order_id' => $roomOrder->id,
        'transaction_code' => 'BILL-R1',
        'billing_status' => 'paid',
        'payment_method' => 'cash',
        'is_walk_in' => true,
        'orders_total' => 500000,
        'grand_total' => 500000,
        'paid_amount' => 500000,
        'paid_at' => now(),
    ]);

    (new \App\Services\DashboardSyncService)->syncAll();

    actingAs($admin)
        ->get(route('admin.dashboard', ['area_id' => $roomArea->id]))
        ->assertSuccessful()
        ->assertSeeText('Test Room Area')
        ->assertSeeText('Test Lounge Area')
        ->assertSeeText('500.000');
});
