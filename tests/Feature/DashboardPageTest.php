<?php

use App\Models\Area;
use App\Models\BarOrder;
use App\Models\BarOrderItem;
use App\Models\Billing;
use App\Models\Dashboard;
use App\Models\InventoryItem;
use App\Models\KitchenOrder;
use App\Models\KitchenOrderItem;
use App\Models\Tabel;
use App\Models\TableSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

test('dashboard page shows aggregated transaction metrics from dashboard table', function () {
    $admin = adminUser();

    Dashboard::query()->create([
        'total_amount' => 500000,
        'total_food' => 51000,
        'total_alcohol' => 62000,
        'total_beverage' => 73000,
        'total_cigarette' => 84000,
        'total_breakage' => 95000,
        'total_room' => 106000,
        'total_staff_meal' => 118000,
        'total_compliment_quantity' => 9,
        'total_foc_quantity' => 7,
        'total_ld' => 117000,
        'total_ld_quantity' => 12,
        'total_penjualan_rokok' => 4200,
        'total_tax' => 15000,
        'total_service_charge' => 12000,
        'total_dp' => 13000,
        'total_cash' => 100000,
        'total_transfer' => 120000,
        'total_debit' => 90000,
        'total_kredit' => 80000,
        'total_qris' => 110000,
        'total_kitchen_items' => 25,
        'total_bar_items' => 30,
        'total_transactions' => 10,
        'last_synced_at' => now(),
    ]);

    actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertSuccessful()
        ->assertSeeText('Ringkasan Transaksi Dashboard')
        ->assertSeeText('Total Food')
        ->assertSeeText('Total Alcohol')
        ->assertSeeText('Total Beverage')
        ->assertSeeText('Total Cigarette')
        ->assertSeeText('Total Breakage')
        ->assertSeeText('Total Room')
        ->assertSeeText('Total Staff Meal')
        ->assertSeeText('Total Compliment')
        ->assertSeeText('Total FOC')
        ->assertSeeText('Total LD')
        ->assertSeeText('Qty 12')
        ->assertSeeText('Total Penjualan Rokok (Qty)')
        ->assertSeeText('Total Pajak')
        ->assertSeeText('Total Service Charge')
        ->assertSeeText('Total DP')
        ->assertSeeText('Gross Sales')
        ->assertSeeText('Net Sales')
        ->assertSeeText('(booking)')
        ->assertSeeText('Total Pembayaran Tunai')
        ->assertSeeText('Total Pembayaran Transfer')
        ->assertSeeText('Total Pembayaran Debit')
        ->assertSeeText('Total Pembayaran Kredit')
        ->assertSeeText('Total Pembayaran QRIS')
        ->assertSeeText('Total Item Keluar Kitchen')
        ->assertSeeText('Total Item Keluar Bar')
        ->assertSeeText('Rp 51.000')
        ->assertSeeText('Rp 62.000')
        ->assertSeeText('Rp 73.000')
        ->assertSeeText('Rp 84.000')
        ->assertSeeText('Rp 95.000')
        ->assertSeeText('Rp 106.000')
        ->assertSeeText('Rp 117.000')
        ->assertSeeText('Rp 118.000')
        ->assertSeeText('9')
        ->assertSeeText('7')
        ->assertSeeText('Rp 15.000')
        ->assertSeeText('Rp 12.000')
        ->assertSeeText('Rp 13.000')
        ->assertSeeText('Rp 513.000')
        ->assertSeeText('Rp 486.000')
        ->assertSeeText('Rp 100.000')
        ->assertSeeText('Rp 120.000')
        ->assertSeeText('Rp 90.000')
        ->assertSeeText('Rp 80.000')
        ->assertSeeText('Rp 110.000')
        ->assertSeeText('25')
        ->assertSeeText('30')
        ->assertSeeText('4.200')
        ->assertSeeText('Sync Dashboard Hari Ini');
});

test('dashboard sync button triggers today sync and redirects back', function () {
    $admin = adminUser();

    $area = Area::create([
        'code' => 'DPG-AREA-'.uniqid(),
        'name' => 'Dashboard Page Area '.uniqid(),
        'is_active' => true,
    ]);

    $table = Tabel::create([
        'area_id' => $area->id,
        'table_number' => 'DPG-TBL-'.uniqid(),
        'qr_code' => 'DPG-QR-'.uniqid(),
        'capacity' => 4,
        'status' => 'available',
        'is_active' => true,
    ]);

    $session = TableSession::create([
        'table_reservation_id' => null,
        'table_id' => $table->id,
        'customer_id' => $admin->id,
        'session_code' => 'DPG-SES-'.uniqid(),
        'status' => 'active',
        'billing_id' => null,
    ]);

    Billing::create([
        'table_session_id' => $session->id,
        'minimum_charge' => 0,
        'orders_total' => 20000,
        'subtotal' => 20000,
        'tax' => 2200,
        'tax_percentage' => 11,
        'service_charge' => 2000,
        'service_charge_percentage' => 10,
        'discount_amount' => 0,
        'grand_total' => 24200,
        'paid_amount' => 24200,
        'billing_status' => 'paid',
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
    ]);

    actingAs($admin)
        ->post(route('admin.dashboard.sync'))
        ->assertRedirect(route('admin.dashboard'));

    $dashboard = Dashboard::query()->findOrFail(1);

    expect((float) $dashboard->total_amount)->toBe(24200.0)
        ->and((int) $dashboard->total_transactions)->toBe(1);
});

test('dashboard uses paid_at within the 09:00 operational window for totals', function () {
    $admin = adminUser();

    Carbon::setTestNow(Carbon::create(2026, 3, 27, 8, 30, 0, 'Asia/Jakarta'));

    Dashboard::query()->create([
        'total_amount' => 0,
        'total_tax' => 0,
        'total_service_charge' => 0,
        'total_cash' => 0,
        'total_transfer' => 0,
        'total_debit' => 0,
        'total_kredit' => 0,
        'total_qris' => 0,
        'total_kitchen_items' => 0,
        'total_bar_items' => 0,
        'total_transactions' => 0,
        'last_synced_at' => now(),
    ]);

    $insideBilling = Billing::query()->create([
        'table_session_id' => null,
        'minimum_charge' => 0,
        'orders_total' => 100000,
        'subtotal' => 100000,
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
        'is_booking' => true,
        'is_walk_in' => false,
        'paid_at' => Carbon::create(2026, 3, 26, 10, 0, 0, 'Asia/Jakarta'),
    ]);

    $outsideBilling = Billing::query()->create([
        'table_session_id' => null,
        'minimum_charge' => 0,
        'orders_total' => 50000,
        'subtotal' => 50000,
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
        'is_booking' => true,
        'is_walk_in' => false,
        'paid_at' => Carbon::create(2026, 3, 26, 8, 0, 0, 'Asia/Jakarta'),
    ]);

    DB::table('billings')->where('id', $insideBilling->id)->update([
        'updated_at' => Carbon::create(2026, 3, 27, 10, 0, 0, 'Asia/Jakarta'),
    ]);
    DB::table('billings')->where('id', $outsideBilling->id)->update([
        'updated_at' => Carbon::create(2026, 3, 26, 10, 0, 0, 'Asia/Jakarta'),
    ]);

    $kitchenItem = InventoryItem::query()->create([
        'code' => 'DASH-K-ITEM',
        'accurate_id' => 961001,
        'name' => 'Dashboard Kitchen Item',
        'category_type' => 'food',
        'price' => 12000,
        'stock_quantity' => 100,
        'threshold' => 10,
        'unit' => 'porsi',
        'is_active' => true,
        'item_produced' => false,
        'material_produced' => false,
    ]);

    $barItem = InventoryItem::query()->create([
        'code' => 'DASH-B-ITEM',
        'accurate_id' => 961002,
        'name' => 'Dashboard Bar Item',
        'category_type' => 'beverage',
        'price' => 9000,
        'stock_quantity' => 100,
        'threshold' => 10,
        'unit' => 'gelas',
        'is_active' => true,
        'item_produced' => false,
        'material_produced' => false,
    ]);

    $insideKitchenOrder = KitchenOrder::query()->create([
        'order_id' => null,
        'order_number' => 'DASH-K-IN',
        'customer_user_id' => null,
        'table_id' => null,
        'total_amount' => 24000,
        'payment_method' => 'cash',
        'status' => 'selesai',
        'progress' => 100,
    ]);
    DB::table('kitchen_orders')->where('id', $insideKitchenOrder->id)->update([
        'created_at' => Carbon::create(2026, 3, 26, 10, 30, 0, 'Asia/Jakarta'),
        'updated_at' => Carbon::create(2026, 3, 26, 10, 30, 0, 'Asia/Jakarta'),
    ]);
    KitchenOrderItem::query()->create([
        'kitchen_order_id' => $insideKitchenOrder->id,
        'inventory_item_id' => $kitchenItem->id,
        'quantity' => 2,
        'price' => 12000,
        'is_completed' => true,
    ]);

    $outsideKitchenOrder = KitchenOrder::query()->create([
        'order_id' => null,
        'order_number' => 'DASH-K-OUT',
        'customer_user_id' => null,
        'table_id' => null,
        'total_amount' => 36000,
        'payment_method' => 'cash',
        'status' => 'selesai',
        'progress' => 100,
    ]);
    DB::table('kitchen_orders')->where('id', $outsideKitchenOrder->id)->update([
        'created_at' => Carbon::create(2026, 3, 26, 8, 30, 0, 'Asia/Jakarta'),
        'updated_at' => Carbon::create(2026, 3, 26, 8, 30, 0, 'Asia/Jakarta'),
    ]);
    KitchenOrderItem::query()->create([
        'kitchen_order_id' => $outsideKitchenOrder->id,
        'inventory_item_id' => $kitchenItem->id,
        'quantity' => 3,
        'price' => 12000,
        'is_completed' => true,
    ]);

    $insideBarOrder = BarOrder::query()->create([
        'order_id' => null,
        'order_number' => 'DASH-B-IN',
        'customer_user_id' => null,
        'table_id' => null,
        'total_amount' => 18000,
        'payment_method' => 'cash',
        'status' => 'selesai',
        'progress' => 100,
    ]);
    DB::table('bar_orders')->where('id', $insideBarOrder->id)->update([
        'created_at' => Carbon::create(2026, 3, 26, 11, 0, 0, 'Asia/Jakarta'),
        'updated_at' => Carbon::create(2026, 3, 26, 11, 0, 0, 'Asia/Jakarta'),
    ]);
    BarOrderItem::query()->create([
        'bar_order_id' => $insideBarOrder->id,
        'inventory_item_id' => $barItem->id,
        'quantity' => 2,
        'price' => 9000,
        'is_completed' => true,
    ]);

    $outsideBarOrder = BarOrder::query()->create([
        'order_id' => null,
        'order_number' => 'DASH-B-OUT',
        'customer_user_id' => null,
        'table_id' => null,
        'total_amount' => 27000,
        'payment_method' => 'cash',
        'status' => 'selesai',
        'progress' => 100,
    ]);
    DB::table('bar_orders')->where('id', $outsideBarOrder->id)->update([
        'created_at' => Carbon::create(2026, 3, 26, 8, 20, 0, 'Asia/Jakarta'),
        'updated_at' => Carbon::create(2026, 3, 26, 8, 20, 0, 'Asia/Jakarta'),
    ]);
    BarOrderItem::query()->create([
        'bar_order_id' => $outsideBarOrder->id,
        'inventory_item_id' => $barItem->id,
        'quantity' => 3,
        'price' => 9000,
        'is_completed' => true,
    ]);

    actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertSuccessful()
        ->assertSeeText('Gross Sales')
        ->assertSeeText('Net Sales')
        ->assertSeeText('Transaksi Hari Ini')
        ->assertSeeText('4 item terjual');

    Carbon::setTestNow();
});
