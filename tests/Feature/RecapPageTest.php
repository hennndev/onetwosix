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
use App\Models\Printer;
use App\Models\RecapHistory;
use App\Models\Tabel;
use App\Models\TableReservation;
use App\Models\TableSession;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

function makeRecapInventoryItem(array $attributes = []): InventoryItem
{
    return InventoryItem::create(array_merge([
        'code' => 'RCP-ITEM-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Recap Item '.uniqid(),
        'category_type' => 'food',
        'price' => 15000,
        'stock_quantity' => 100,
        'threshold' => 5,
        'unit' => 'unit',
        'is_active' => true,
    ], $attributes));
}

function makeRecapOrder(int $createdById, \Illuminate\Support\Carbon $orderedAt, string $orderNumber, array $attributes = []): Order
{
    return Order::create(array_merge([
        'table_session_id' => null,
        'customer_user_id' => null,
        'created_by' => $createdById,
        'order_number' => $orderNumber,
        'status' => 'served',
        'items_total' => 30000,
        'discount_amount' => 0,
        'total' => 30000,
        'ordered_at' => $orderedAt,
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
    ], $attributes));
}

function makeRecapTableSessionWithBilling(int $customerId, array $billingAttributes = []): TableSession
{
    $area = Area::create([
        'code' => 'RCP-AREA-'.uniqid(),
        'name' => 'Recap Area '.uniqid(),
        'is_active' => true,
    ]);

    $table = Tabel::create([
        'area_id' => $area->id,
        'table_number' => 'RCP-TBL-'.uniqid(),
        'qr_code' => 'RCP-QR-'.uniqid(),
        'capacity' => 4,
        'status' => 'available',
        'is_active' => true,
    ]);

    $tableSession = TableSession::create([
        'table_reservation_id' => null,
        'table_id' => $table->id,
        'customer_id' => $customerId,
        'session_code' => 'RCP-SES-'.uniqid(),
        'status' => 'active',
    ]);

    $billing = Billing::create(array_merge([
        'table_session_id' => $tableSession->id,
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
        'paid_at' => now(),
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
    ], $billingAttributes));

    $tableSession->update([
        'billing_id' => $billing->id,
    ]);

    return $tableSession->fresh();
}

function makeRecapTableSessionWithoutBillingLink(int $customerId): TableSession
{
    $area = Area::create([
        'code' => 'RCP-AREA-'.uniqid(),
        'name' => 'Recap Area '.uniqid(),
        'is_active' => true,
    ]);

    $table = Tabel::create([
        'area_id' => $area->id,
        'table_number' => 'RCP-TBL-'.uniqid(),
        'qr_code' => 'RCP-QR-'.uniqid(),
        'capacity' => 4,
        'status' => 'available',
        'is_active' => true,
    ]);

    return TableSession::create([
        'table_reservation_id' => null,
        'table_id' => $table->id,
        'customer_id' => $customerId,
        'session_code' => 'RCP-SES-'.uniqid(),
        'status' => 'active',
        'billing_id' => null,
    ]);
}

test('admin can open recap page', function () {
    $admin = adminUser();
    $start = now()->startOfDay()->addHours(8);
    $end = now()->startOfDay()->addHours(23)->addMinutes(59);

    Dashboard::query()->create([
        'total_amount' => 500000,
        'total_food' => 51000,
        'total_alcohol' => 62000,
        'total_beverage' => 73000,
        'total_cigarette' => 84000,
        'total_breakage' => 95000,
        'total_room' => 106000,
        'total_staff_meal' => 55000,
        'total_compliment_quantity' => 9,
        'total_foc_quantity' => 7,
        'total_ld' => 117000,
        'total_ld_quantity' => 12,
        'total_penjualan_rokok' => 42,
        'total_tax' => 15000,
        'total_service_charge' => 12000,
        'total_dp' => 13000,
        'total_cash' => 100000,
        'total_transfer' => 120000,
        'total_debit' => 90000,
        'total_kredit' => 80000,
        'total_qris' => 110000,
        'total_transactions' => 10,
        'last_synced_at' => now(),
    ]);

    actingAs($admin)
        ->get(route('admin.recap.index', [
            'start_datetime' => $start->format('Y-m-d\TH:i'),
            'end_datetime' => $end->format('Y-m-d\TH:i'),
        ]))
        ->assertSuccessful()
        ->assertViewIs('recap.index')
        ->assertSeeText('Rekap End Day')
        ->assertSeeText('Recap')
        ->assertSeeText('History')
        ->assertSeeText('Preview Print Struk')
        ->assertSeeText('Transaksi Kasir')
        ->assertSeeText('Metode Pembayaran')
        ->assertSeeText('Total Pembayaran Tunai')
        ->assertSeeText('Total Tunai')
        ->assertSeeText('Rp 100.000')
        ->assertSeeText('Total DP')
        ->assertSeeText('(booking)')
        ->assertSeeText('Rp 13.000')
        ->assertSeeText('Total Food')
        ->assertSeeText('Total Alcohol')
        ->assertSeeText('Total Beverage')
        ->assertSeeText('Total Cigarette')
        ->assertSeeText('Total Breakage')
        ->assertSeeText('Total Room')
        ->assertSeeText('Total Staff Meal')
        ->assertSeeText('Total Compliment (Qty)')
        ->assertSeeText('Total FOC (Qty)')
        ->assertSeeText('Total LD')
        ->assertSeeText('Qty 12')
        ->assertSeeText('Rp 51.000')
        ->assertSeeText('Rp 62.000')
        ->assertSeeText('Rp 73.000')
        ->assertSeeText('Rp 84.000')
        ->assertSeeText('Rp 95.000')
        ->assertSeeText('Rp 106.000')
        ->assertSeeText('Rp 55.000')
        ->assertSeeText('Rp 117.000')
        ->assertSeeText('9')
        ->assertSeeText('7')
        ->assertSeeText('Total Penjualan Rokok (Qty)')
        ->assertSeeText('42')
        ->assertSeeText('Item Keluar Kitchen')
        ->assertSeeText('Item Keluar Bar')
        ->assertSee(route('admin.recap.close-preview', [
            'start_datetime' => $start->format('Y-m-d\TH:i'),
            'end_datetime' => $end->format('Y-m-d\TH:i'),
        ]))
        ->assertDontSeeText('Total Diskon')
        ->assertDontSeeText('Filter Rekapan')
        ->assertDontSeeText('Timeline Kejadian');
});

test('recap close preview page shows printable a4 summary', function () {
    $admin = adminUser();
    $start = now()->startOfDay()->addHours(8);
    $end = now()->startOfDay()->addHours(23)->addMinutes(59);

    $order = makeRecapOrder($admin->id, now()->startOfDay()->addHours(10), 'RCP-PRV-001', [
        'payment_method' => 'qris',
        'payment_reference_number' => 'QR-9988',
        'items_total' => 30000,
        'total' => 30000,
    ]);

    $item = makeRecapInventoryItem([
        'name' => 'Preview Item Recap',
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $item->id,
        'item_name' => $item->name,
        'item_code' => $item->code,
        'quantity' => 2,
        'price' => 15000,
        'subtotal' => 30000,
        'discount_amount' => 0,
        'tax_amount' => 3000,
        'service_charge_amount' => 2000,
        'preparation_location' => 'kitchen',
        'status' => 'served',
    ]);

    $rokokItem = makeRecapInventoryItem([
        'name' => 'Rokok Preview',
        'category_type' => 'Rokok',
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $rokokItem->id,
        'item_name' => $rokokItem->name,
        'item_code' => $rokokItem->code,
        'quantity' => 3,
        'price' => 25000,
        'subtotal' => 75000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'service_charge_amount' => 0,
        'preparation_location' => 'bar',
        'status' => 'served',
    ]);

    Billing::create([
        'order_id' => $order->id,
        'is_walk_in' => false,
        'is_booking' => true,
        'minimum_charge' => 0,
        'orders_total' => 20000,
        'subtotal' => 23000,
        'tax' => 2000,
        'tax_percentage' => 11,
        'service_charge' => 1000,
        'service_charge_percentage' => 5,
        'discount_amount' => 5000,
        'grand_total' => 15000,
        'paid_amount' => 3000,
        'billing_status' => 'paid',
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
    ]);

    Dashboard::query()->updateOrCreate(
        ['id' => 1],
        [
            'total_amount' => 500000,
            'total_penjualan_rokok' => 42,
            'total_compliment_quantity' => 6,
            'total_foc_quantity' => 4,
            'total_tax' => 15000,
            'total_service_charge' => 12000,
            'total_cash' => 100000,
            'total_transfer' => 120000,
            'total_debit' => 90000,
            'total_kredit' => 80000,
            'total_qris' => 110000,
            'total_kitchen_items' => 2,
            'total_bar_items' => 0,
            'total_ld_quantity' => 8,
            'total_transactions' => 10,
            'last_synced_at' => now(),
        ]
    );

    actingAs($admin)
        ->get(route('admin.recap.close-preview', [
            'start_datetime' => $start->format('Y-m-d\TH:i'),
            'end_datetime' => $end->format('Y-m-d\TH:i'),
        ]))
        ->assertSuccessful()
        ->assertViewIs('recap.close-preview')
        ->assertSeeText('Preview Print Struk - End Day')
        ->assertSeeText('Preview Cetak Struk')
        ->assertSeeText('Riwayat transaksi')
        ->assertSeeText('Cetak Otomatis')
        ->assertSeeText('Save PDF')
        ->assertSeeText('Item Keluar Kitchen')
        ->assertSeeText('Item Keluar Bar')
        ->assertSeeText('Gross Sales (Included DP)')
        ->assertSeeText('Net Sales (Included DP)')
        ->assertSeeText('Total Compliment (Qty)')
        ->assertSeeText('Total FOC (Qty)')
        ->assertSeeText('Qty 8')
        ->assertSeeText('Tutup End Day')
        ->assertSeeText('INFO ROKOK')
        ->assertSeeText('Rokok Preview')
        ->assertDontSeeText('Tidak ada item rokok.')
        ->assertSeeText('3x')
        ->assertSee(route('admin.recap.close-export'));
});

test('recap close preview print endpoint triggers server print and returns log path for log printer', function () {
    $admin = adminUser();
    $start = now()->startOfDay()->addHours(8);
    $end = now()->startOfDay()->addHours(23)->addMinutes(59);
    $logPath = storage_path('logs/printer.log');

    file_put_contents($logPath, '');

    $logPrinter = Printer::create([
        'name' => 'End Day Log Printer',
        'location' => 'cashier',
        'printer_type' => 'cashier',
        'connection_type' => 'log',
        'port' => 9100,
        'timeout' => 30,
        'header' => '126 Club',
        'footer' => 'Thank you',
        'width' => 42,
        'is_default' => false,
        'is_active' => true,
    ]);

    \App\Models\GeneralSetting::instance()->update([
        'end_day_receipt_printer_id' => $logPrinter->id,
    ]);

    Dashboard::query()->updateOrCreate(
        ['id' => 1],
        [
            'total_amount' => 500000,
            'total_penjualan_rokok' => 42,
            'total_tax' => 15000,
            'total_service_charge' => 12000,
            'total_cash' => 100000,
            'total_transfer' => 120000,
            'total_debit' => 90000,
            'total_kredit' => 80000,
            'total_qris' => 110000,
            'total_kitchen_items' => 2,
            'total_bar_items' => 1,
            'total_ld_quantity' => 8,
            'total_transactions' => 10,
            'last_synced_at' => now(),
        ]
    );

    $order = makeRecapOrder(
        $admin->id,
        $start->copy()->addHour(),
        'RCP-PRINT-001',
        [
            'payment_method' => 'qris',
            'payment_mode' => 'normal',
            'payment_reference_number' => 'REF-PRINT-001',
            'total' => 15000,
        ]
    );

    $inventoryItem = makeRecapInventoryItem(['name' => 'Print Test Item']);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $inventoryItem->id,
        'item_name' => $inventoryItem->name,
        'item_code' => $inventoryItem->code,
        'quantity' => 1,
        'price' => 15000,
        'subtotal' => 15000,
        'tax_amount' => 0,
        'service_charge_amount' => 0,
        'preparation_location' => 'kitchen',
        'status' => 'served',
    ]);

    $rokokPrintItem = makeRecapInventoryItem([
        'name' => 'Rokok Print',
        'category_type' => 'Rokok',
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $rokokPrintItem->id,
        'item_name' => $rokokPrintItem->name,
        'item_code' => $rokokPrintItem->code,
        'quantity' => 2,
        'price' => 20000,
        'subtotal' => 40000,
        'tax_amount' => 0,
        'service_charge_amount' => 0,
        'preparation_location' => 'bar',
        'status' => 'served',
    ]);

    $tableSession = makeRecapTableSessionWithBilling($admin->id, [
        'order_id' => $order->id,
        'is_booking' => true,
        'minimum_charge' => 20000,
        'orders_total' => 20000,
        'subtotal' => 23000,
        'tax' => 2000,
        'tax_percentage' => 10,
        'service_charge' => 1000,
        'service_charge_percentage' => 5,
        'discount_amount' => 5000,
        'grand_total' => 18000,
        'paid_amount' => 3000,
        'billing_status' => 'paid',
        'transaction_code' => 'RCP-CALC-BILL-001',
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
    ]);

    $order->update([
        'table_session_id' => $tableSession->id,
    ]);

    expect((int) ($order->fresh()->table_session_id ?? 0))->toBe((int) $tableSession->id);
    expect(Billing::query()->where('order_id', $order->id)->exists())->toBeTrue();
    expect((float) (Billing::query()->where('order_id', $order->id)->value('paid_amount') ?? 0))->toBe(3000.0);

    actingAs($admin)
        ->postJson(route('admin.recap.close-preview.print'), [
            'start_datetime' => $start->format('Y-m-d\TH:i'),
            'end_datetime' => $end->format('Y-m-d\TH:i'),
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('connection_type', 'log')
        ->assertJsonPath('printer_name', 'End Day Log Printer')
        ->assertJsonPath('log_path', $logPath);

    $printedLog = file_get_contents($logPath);

    expect($printedLog)
        ->toContain('INFO ROKOK')
        ->toContain('Rokok Print')
        ->toContain('Qty: 2x')
        ->toContain('Item Keluar Kitchen')
        ->toContain('Item Keluar Bar')
        ->toContain('Gross Sales')
        ->toContain('Net Sales')
        ->toContain('DAFTAR TRANSAKSI')
        ->toContain('RCP-PRINT-001');
});

test('recap close preview print can skip transaction history when disabled', function () {
    $admin = adminUser();
    $start = now()->startOfDay()->addHours(8);
    $end = now()->startOfDay()->addHours(23)->addMinutes(59);
    $logPath = storage_path('logs/printer.log');

    file_put_contents($logPath, '');

    $logPrinter = Printer::create([
        'name' => 'End Day Log Printer No History',
        'location' => 'cashier',
        'printer_type' => 'cashier',
        'connection_type' => 'log',
        'port' => 9100,
        'timeout' => 30,
        'header' => '126 Club',
        'footer' => 'Thank you',
        'width' => 42,
        'is_default' => false,
        'is_active' => true,
    ]);

    \App\Models\GeneralSetting::instance()->update([
        'end_day_receipt_printer_id' => $logPrinter->id,
    ]);

    Dashboard::query()->updateOrCreate(
        ['id' => 1],
        [
            'total_amount' => 500000,
            'total_penjualan_rokok' => 42,
            'total_tax' => 15000,
            'total_service_charge' => 12000,
            'total_cash' => 100000,
            'total_transfer' => 120000,
            'total_debit' => 90000,
            'total_kredit' => 80000,
            'total_qris' => 110000,
            'total_kitchen_items' => 2,
            'total_bar_items' => 1,
            'total_ld_quantity' => 8,
            'total_transactions' => 10,
            'last_synced_at' => now(),
        ]
    );

    $order = makeRecapOrder(
        $admin->id,
        $start->copy()->addHour(),
        'RCP-NOHIST-001',
        [
            'payment_method' => 'qris',
            'payment_mode' => 'normal',
            'payment_reference_number' => 'REF-NOHIST-001',
            'total' => 15000,
        ]
    );

    $inventoryItem = makeRecapInventoryItem(['name' => 'No History Item']);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $inventoryItem->id,
        'item_name' => $inventoryItem->name,
        'item_code' => $inventoryItem->code,
        'quantity' => 1,
        'price' => 15000,
        'subtotal' => 15000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'service_charge_amount' => 0,
        'preparation_location' => 'kitchen',
        'status' => 'served',
    ]);

    $tableSession = makeRecapTableSessionWithBilling($admin->id, [
        'order_id' => $order->id,
        'is_booking' => true,
        'minimum_charge' => 20000,
        'orders_total' => 20000,
        'subtotal' => 23000,
        'tax' => 2000,
        'tax_percentage' => 10,
        'service_charge' => 1000,
        'service_charge_percentage' => 5,
        'discount_amount' => 5000,
        'grand_total' => 18000,
        'paid_amount' => 3000,
        'billing_status' => 'paid',
        'transaction_code' => 'RCP-NOHIST-BILL-001',
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
    ]);

    $order->update([
        'table_session_id' => $tableSession->id,
    ]);

    actingAs($admin)
        ->postJson(route('admin.recap.close-preview.print'), [
            'start_datetime' => $start->format('Y-m-d\TH:i'),
            'end_datetime' => $end->format('Y-m-d\TH:i'),
            'include_transaction_history' => false,
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $printedLog = file_get_contents($logPath);

    expect($printedLog)
        ->not->toContain('DAFTAR TRANSAKSI')
        ->not->toContain('RCP-NOHIST-001')
        ->toContain('Gross Sales')
        ->toContain('Net Sales');
});

test('recap close preview excludes incomplete empty transactions', function () {
    $admin = adminUser();
    $start = now()->startOfDay();
    $end = now()->endOfDay();

    $validOrder = makeRecapOrder(
        $admin->id,
        $start->copy()->addHours(12),
        'RCP-VALID-001',
        [
            'payment_method' => 'transfer',
            'payment_mode' => 'normal',
            'payment_reference_number' => 'REF-VALID-001',
            'total' => 30000,
        ]
    );

    $inventoryItem = makeRecapInventoryItem(['name' => 'Valid Item']);

    OrderItem::create([
        'order_id' => $validOrder->id,
        'inventory_item_id' => $inventoryItem->id,
        'item_name' => $inventoryItem->name,
        'item_code' => $inventoryItem->code,
        'quantity' => 2,
        'price' => 15000,
        'subtotal' => 30000,
        'tax_amount' => 0,
        'service_charge_amount' => 0,
        'preparation_location' => 'kitchen',
        'status' => 'served',
    ]);

    Order::create([
        'table_session_id' => null,
        'customer_user_id' => null,
        'created_by' => $admin->id,
        'order_number' => 'RCP-PENDING-001',
        'status' => 'pending',
        'items_total' => 0,
        'discount_amount' => 0,
        'total' => 0,
        'ordered_at' => $start->copy()->addHours(13),
        'payment_method' => null,
        'payment_mode' => null,
    ]);

    Dashboard::query()->updateOrCreate(
        ['id' => 1],
        [
            'total_amount' => 30000,
            'total_tax' => 0,
            'total_service_charge' => 0,
            'total_cash' => 0,
            'total_transfer' => 30000,
            'total_debit' => 0,
            'total_kredit' => 0,
            'total_qris' => 0,
            'total_kitchen_items' => 2,
            'total_bar_items' => 0,
            'total_transactions' => 1,
            'last_synced_at' => now(),
        ]
    );

    actingAs($admin)
        ->get(route('admin.recap.close-preview', [
            'start_datetime' => $start->format('Y-m-d\TH:i'),
            'end_datetime' => $end->format('Y-m-d\TH:i'),
        ]))
        ->assertSuccessful()
        ->assertDontSeeText('DAFTAR TRANSAKSI')
        ->assertDontSeeText('Tidak ada item.');
});

test('recap close preview print calculates total with tax service and discount', function () {
    $admin = adminUser();
    $start = now()->startOfDay();
    $end = now()->endOfDay();
    $logPath = storage_path('logs/printer.log');

    file_put_contents($logPath, '');

    $logPrinter = Printer::create([
        'name' => 'End Day Calc Printer',
        'location' => 'cashier',
        'printer_type' => 'cashier',
        'connection_type' => 'log',
        'port' => 9100,
        'timeout' => 30,
        'header' => '126 Club',
        'footer' => 'Thank you',
        'width' => 42,
        'is_default' => false,
        'is_active' => true,
    ]);

    \App\Models\GeneralSetting::instance()->update([
        'end_day_receipt_printer_id' => $logPrinter->id,
    ]);

    Dashboard::query()->updateOrCreate(
        ['id' => 1],
        [
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
        ]
    );

    $tableSession = makeRecapTableSessionWithBilling($admin->id, [
        'is_booking' => true,
        'minimum_charge' => 20000,
        'orders_total' => 20000,
        'subtotal' => 23000,
        'tax' => 2000,
        'tax_percentage' => 10,
        'service_charge' => 1000,
        'service_charge_percentage' => 5,
        'discount_amount' => 5000,
        'grand_total' => 18000,
        'paid_amount' => 3000,
        'billing_status' => 'paid',
        'transaction_code' => 'RCP-CALC-BILL-001',
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
    ]);

    $order = makeRecapOrder($admin->id, $start->copy()->addHours(10), 'RCP-CALC-001', [
        'table_session_id' => $tableSession->id,
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
        'discount_amount' => 5000,
        'items_total' => 20000,
        'total' => 20000,
    ]);

    $item = makeRecapInventoryItem(['name' => 'Calc Item']);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $item->id,
        'item_name' => $item->name,
        'item_code' => $item->code,
        'quantity' => 1,
        'price' => 20000,
        'subtotal' => 20000,
        'discount_amount' => 0,
        'tax_amount' => 2000,
        'service_charge_amount' => 1000,
        'preparation_location' => 'kitchen',
        'status' => 'served',
    ]);

    Billing::query()
        ->where('table_session_id', $tableSession->id)
        ->update(['order_id' => $order->id]);

    $reservation = TableReservation::create([
        'booking_code' => random_int(100000, 999999),
        'booking_name' => 'Recap DP Booking',
        'table_id' => $tableSession->table_id,
        'customer_id' => $tableSession->customer_id,
        'reservation_date' => $start->toDateString(),
        'reservation_time' => $start->format('H:i:s'),
        'status' => 'checked_in',
        'down_payment_amount' => 3000,
    ]);

    $tableSession->update([
        'table_reservation_id' => $reservation->id,
    ]);

    Billing::query()
        ->where('table_session_id', $tableSession->id)
        ->update(['paid_amount' => 23000]);

    actingAs($admin)
        ->postJson(route('admin.recap.close-preview.print'), [
            'start_datetime' => $start->format('Y-m-d\TH:i'),
            'end_datetime' => $end->format('Y-m-d\TH:i'),
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $printedLog = file_get_contents($logPath);

    expect($printedLog)
        ->toContain('Total Diskon')
        ->toContain('- Rp 5.000')
        ->toContain('Gross Sales')
        ->toContain('Net Sales')
        ->toContain('Total DP');
});

test('recap close preview print without explicit range only includes today transactions', function () {
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-03-28 10:00:00', 'Asia/Jakarta'));

    $admin = adminUser();
    $todayStart = now()->startOfDay();
    $logPath = storage_path('logs/printer.log');

    file_put_contents($logPath, '');

    $logPrinter = Printer::create([
        'name' => 'End Day Today Printer',
        'location' => 'cashier',
        'printer_type' => 'cashier',
        'connection_type' => 'log',
        'port' => 9100,
        'timeout' => 30,
        'header' => '126 Club',
        'footer' => 'Thank you',
        'width' => 42,
        'is_default' => false,
        'is_active' => true,
    ]);

    \App\Models\GeneralSetting::instance()->update([
        'end_day_receipt_printer_id' => $logPrinter->id,
    ]);

    Dashboard::query()->updateOrCreate(
        ['id' => 1],
        [
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
        ]
    );

    $yesterdayOrder = makeRecapOrder($admin->id, $todayStart->copy()->subDay()->addHours(10), 'RCP-OLD-001', [
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
    ]);

    $todayOrder = makeRecapOrder($admin->id, $todayStart->copy()->addHours(11), 'RCP-TODAY-ONLY-001', [
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
    ]);

    $item = makeRecapInventoryItem(['name' => 'Today Filter Item']);

    OrderItem::create([
        'order_id' => $yesterdayOrder->id,
        'inventory_item_id' => $item->id,
        'item_name' => $item->name,
        'item_code' => $item->code,
        'quantity' => 1,
        'price' => 10000,
        'subtotal' => 10000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'service_charge_amount' => 0,
        'preparation_location' => 'kitchen',
        'status' => 'served',
    ]);

    OrderItem::create([
        'order_id' => $todayOrder->id,
        'inventory_item_id' => $item->id,
        'item_name' => $item->name,
        'item_code' => $item->code,
        'quantity' => 1,
        'price' => 12000,
        'subtotal' => 12000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'service_charge_amount' => 0,
        'preparation_location' => 'kitchen',
        'status' => 'served',
    ]);

    actingAs($admin)
        ->postJson(route('admin.recap.close-preview.print'))
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $printedLog = file_get_contents($logPath);

    expect($printedLog)
        ->not->toContain('RCP-OLD-001');

    \Illuminate\Support\Carbon::setTestNow();
});

test('recap close preview print before 9am uses previous end day window', function () {
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-03-28 08:30:00', 'Asia/Jakarta'));

    $admin = adminUser();
    $logPath = storage_path('logs/printer.log');

    file_put_contents($logPath, '');

    $logPrinter = Printer::create([
        'name' => 'End Day Before 9 Printer',
        'location' => 'cashier',
        'printer_type' => 'cashier',
        'connection_type' => 'log',
        'port' => 9100,
        'timeout' => 30,
        'header' => '126 Club',
        'footer' => 'Thank you',
        'width' => 42,
        'is_default' => false,
        'is_active' => true,
    ]);

    \App\Models\GeneralSetting::instance()->update([
        'end_day_receipt_printer_id' => $logPrinter->id,
    ]);

    Dashboard::query()->updateOrCreate(
        ['id' => 1],
        [
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
        ]
    );

    $yesterdayOrder = makeRecapOrder(
        $admin->id,
        \Illuminate\Support\Carbon::parse('2026-03-27 10:00:00', 'Asia/Jakarta'),
        'RCP-BEFORE9-YDAY-001',
        [
            'payment_method' => 'cash',
            'payment_mode' => 'normal',
        ]
    );

    $todayOrder = makeRecapOrder(
        $admin->id,
        \Illuminate\Support\Carbon::parse('2026-03-28 10:00:00', 'Asia/Jakarta'),
        'RCP-BEFORE9-TODAY-001',
        [
            'payment_method' => 'cash',
            'payment_mode' => 'normal',
        ]
    );

    $item = makeRecapInventoryItem(['name' => 'Before 9 Item']);

    OrderItem::create([
        'order_id' => $yesterdayOrder->id,
        'inventory_item_id' => $item->id,
        'item_name' => $item->name,
        'item_code' => $item->code,
        'quantity' => 1,
        'price' => 10000,
        'subtotal' => 10000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'service_charge_amount' => 0,
        'preparation_location' => 'kitchen',
        'status' => 'served',
    ]);

    OrderItem::create([
        'order_id' => $todayOrder->id,
        'inventory_item_id' => $item->id,
        'item_name' => $item->name,
        'item_code' => $item->code,
        'quantity' => 1,
        'price' => 12000,
        'subtotal' => 12000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'service_charge_amount' => 0,
        'preparation_location' => 'kitchen',
        'status' => 'served',
    ]);

    actingAs($admin)
        ->postJson(route('admin.recap.close-preview.print'))
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $printedLog = file_get_contents($logPath);

    expect($printedLog)
        ->not->toContain('RCP-BEFORE9-TODAY-001');

    \Illuminate\Support\Carbon::setTestNow();
});

test('recap page filters cashier kitchen and bar events by selected datetime range', function () {
    $admin = adminUser();

    $frozenNow = \Illuminate\Support\Carbon::parse('2026-07-26 15:00:00');
    \Illuminate\Support\Carbon::setTestNow($frozenNow);

    $today = now()->startOfDay()->addHours(10);
    $yesterday = now()->subDay()->startOfDay()->addHours(11);
    $rangeStart = now()->startOfDay()->addHours(9);
    $rangeEnd = now()->startOfDay()->addHours(23);

    $todayOrder = makeRecapOrder($admin->id, $today, 'RCP-TODAY-001');
    $todayOrder->forceFill(['created_at' => $today, 'updated_at' => $today])->save();
    $yesterdayOrder = makeRecapOrder($admin->id, $yesterday, 'RCP-YEST-001');
    $yesterdayOrder->forceFill(['created_at' => $yesterday, 'updated_at' => $yesterday])->save();

    $foodToday = makeRecapInventoryItem([
        'name' => 'Nasi Goreng Recap',
        'category_type' => 'food',
    ]);
    $foodYesterday = makeRecapInventoryItem([
        'name' => 'Mie Goreng Lama',
        'category_type' => 'food',
    ]);
    $drinkToday = makeRecapInventoryItem([
        'name' => 'Es Teh Recap',
        'category_type' => 'beverage',
    ]);
    $drinkYesterday = makeRecapInventoryItem([
        'name' => 'Jus Lama',
        'category_type' => 'beverage',
    ]);

    OrderItem::create([
        'order_id' => $todayOrder->id,
        'inventory_item_id' => $foodToday->id,
        'item_name' => $foodToday->name,
        'item_code' => $foodToday->code,
        'quantity' => 2,
        'price' => 15000,
        'subtotal' => 30000,
        'discount_amount' => 0,
        'preparation_location' => 'kitchen',
        'status' => 'served',
    ]);

    $kitchenOrderToday = KitchenOrder::create([
        'order_id' => $todayOrder->id,
        'order_number' => $todayOrder->order_number,
        'customer_user_id' => null,
        'table_id' => null,
        'total_amount' => 15000,
        'status' => 'selesai',
        'progress' => 100,
    ]);
    $kitchenOrderToday->forceFill(['created_at' => $today, 'updated_at' => $today])->save();

    KitchenOrderItem::create([
        'kitchen_order_id' => $kitchenOrderToday->id,
        'inventory_item_id' => $foodToday->id,
        'quantity' => 1,
        'price' => 15000,
        'is_completed' => true,
    ]);

    $kitchenOrderYesterday = KitchenOrder::create([
        'order_id' => $yesterdayOrder->id,
        'order_number' => $yesterdayOrder->order_number,
        'customer_user_id' => null,
        'table_id' => null,
        'total_amount' => 15000,
        'status' => 'selesai',
        'progress' => 100,
    ]);
    $kitchenOrderYesterday->forceFill(['created_at' => $yesterday, 'updated_at' => $yesterday])->save();

    KitchenOrderItem::create([
        'kitchen_order_id' => $kitchenOrderYesterday->id,
        'inventory_item_id' => $foodYesterday->id,
        'quantity' => 1,
        'price' => 15000,
        'is_completed' => true,
    ]);

    $barOrderToday = BarOrder::create([
        'order_id' => $todayOrder->id,
        'order_number' => $todayOrder->order_number,
        'customer_user_id' => null,
        'table_id' => null,
        'total_amount' => 15000,
        'payment_method' => 'cash',
        'status' => 'selesai',
        'progress' => 100,
    ]);
    $barOrderToday->forceFill(['created_at' => $today, 'updated_at' => $today])->save();

    BarOrderItem::create([
        'bar_order_id' => $barOrderToday->id,
        'inventory_item_id' => $drinkToday->id,
        'quantity' => 1,
        'price' => 15000,
        'is_completed' => true,
    ]);

    $barOrderYesterday = BarOrder::create([
        'order_id' => $yesterdayOrder->id,
        'order_number' => $yesterdayOrder->order_number,
        'customer_user_id' => null,
        'table_id' => null,
        'total_amount' => 15000,
        'payment_method' => 'cash',
        'status' => 'selesai',
        'progress' => 100,
    ]);
    $barOrderYesterday->forceFill(['created_at' => $yesterday, 'updated_at' => $yesterday])->save();

    BarOrderItem::create([
        'bar_order_id' => $barOrderYesterday->id,
        'inventory_item_id' => $drinkYesterday->id,
        'quantity' => 1,
        'price' => 15000,
        'is_completed' => true,
    ]);

    actingAs($admin)
        ->get(route('admin.recap.index', [
            'start_datetime' => $rangeStart->format('Y-m-d\TH:i'),
            'end_datetime' => $rangeEnd->format('Y-m-d\TH:i'),
        ]))
        ->assertSuccessful()
        ->assertSee('RCP-TODAY-001')
        ->assertSee($today->format('d/m/Y H:i'))
        ->assertDontSee('RCP-YEST-001')
        ->assertSee('Nasi Goreng Recap')
        ->assertDontSee('Mie Goreng Lama')
        ->assertSee('Es Teh Recap')
        ->assertDontSee('Jus Lama')
        ->assertSee('Tunai')
        ->assertSee('Rp 30.000');
});

test('recap page hides live lists when selected end day already exists in history', function () {
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-03-28 10:00:00', 'Asia/Jakarta'));

    $admin = adminUser();

    Dashboard::query()->updateOrCreate(
        ['id' => 1],
        [
            'total_amount' => 0,
            'total_dp' => 0,
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
        ]
    );

    $orderedAt = \Illuminate\Support\Carbon::parse('2026-03-28 09:30:00', 'Asia/Jakarta');

    $order = makeRecapOrder($admin->id, $orderedAt, 'RCP-CLOSED-001', [
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
        'items_total' => 22000,
        'total' => 22000,
    ]);

    $foodItem = makeRecapInventoryItem([
        'name' => 'Closed Kitchen Item',
        'category_type' => 'food',
    ]);

    $drinkItem = makeRecapInventoryItem([
        'name' => 'Closed Bar Item',
        'category_type' => 'beverage',
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $foodItem->id,
        'item_name' => $foodItem->name,
        'item_code' => $foodItem->code,
        'quantity' => 1,
        'price' => 22000,
        'subtotal' => 22000,
        'discount_amount' => 0,
        'preparation_location' => 'kitchen',
        'status' => 'served',
    ]);

    $kitchenOrder = KitchenOrder::create([
        'order_id' => $order->id,
        'order_number' => $order->order_number,
        'customer_user_id' => null,
        'table_id' => null,
        'total_amount' => 22000,
        'status' => 'selesai',
        'progress' => 100,
    ]);
    $kitchenOrder->forceFill(['created_at' => $orderedAt, 'updated_at' => $orderedAt])->save();

    KitchenOrderItem::create([
        'kitchen_order_id' => $kitchenOrder->id,
        'inventory_item_id' => $foodItem->id,
        'quantity' => 2,
        'price' => 11000,
        'is_completed' => true,
    ]);

    $barOrder = BarOrder::create([
        'order_id' => $order->id,
        'order_number' => $order->order_number,
        'customer_user_id' => null,
        'table_id' => null,
        'total_amount' => 22000,
        'payment_method' => 'cash',
        'status' => 'selesai',
        'progress' => 100,
    ]);
    $barOrder->forceFill(['created_at' => $orderedAt, 'updated_at' => $orderedAt])->save();

    BarOrderItem::create([
        'bar_order_id' => $barOrder->id,
        'inventory_item_id' => $drinkItem->id,
        'quantity' => 3,
        'price' => 7333,
        'is_completed' => true,
    ]);

    RecapHistory::query()->create([
        'end_day' => '2026-03-28',
        'total_amount' => 123000,
        'total_tax' => 10000,
        'total_service_charge' => 8000,
        'total_cash' => 50000,
        'total_transfer' => 30000,
        'total_debit' => 20000,
        'total_kredit' => 10000,
        'total_qris' => 13000,
        'total_kitchen_items' => 9,
        'total_bar_items' => 7,
        'total_transactions' => 3,
        'last_synced_at' => now(),
    ]);

    $response = actingAs($admin)
        ->get(route('admin.recap.index'))
        ->assertSuccessful()
        ->assertDontSee('RCP-CLOSED-001')
        ->assertDontSee('Closed Kitchen Item')
        ->assertDontSee('Closed Bar Item')
        ->assertSeeText('Tidak ada transaksi kasir pada tanggal ini.')
        ->assertSeeText('Tidak ada item kitchen pada tanggal ini.')
        ->assertSeeText('Tidak ada item bar pada tanggal ini.');

    expect((int) $response->viewData('cashierCount'))->toBe(0)
        ->and((float) $response->viewData('totalTax'))->toBe(0.0)
        ->and((float) $response->viewData('totalServiceCharge'))->toBe(0.0)
        ->and((int) $response->viewData('kitchenQtyTotal'))->toBe(0)
        ->and((int) $response->viewData('barQtyTotal'))->toBe(0);

    \Illuminate\Support\Carbon::setTestNow();
});

test('recap page shows total tax total service charge and payment method totals', function () {
    $admin = adminUser();
    $rangeStart = now()->startOfDay()->addHours(8);
    $rangeEnd = now()->startOfDay()->addHours(23);
    $orderedAt = now()->startOfDay()->addHours(12);

    $sessionTransfer = makeRecapTableSessionWithBilling($admin->id, [
        'tax' => 3000,
        'service_charge' => 2000,
        'payment_method' => 'transfer',
        'paid_amount' => 50000,
        'grand_total' => 50000,
        'paid_at' => $orderedAt,
    ]);
    makeRecapOrder($admin->id, $orderedAt, 'RCP-PAY-TRF', [
        'table_session_id' => $sessionTransfer->id,
        'items_total' => 50000,
        'total' => 50000,
        'payment_method' => 'transfer',
    ]);

    $sessionDebit = makeRecapTableSessionWithBilling($admin->id, [
        'tax' => 2000,
        'service_charge' => 1500,
        'payment_method' => 'debit',
        'paid_amount' => 40000,
        'grand_total' => 40000,
        'paid_at' => $orderedAt,
    ]);
    makeRecapOrder($admin->id, $orderedAt, 'RCP-PAY-DBT', [
        'table_session_id' => $sessionDebit->id,
        'items_total' => 40000,
        'total' => 40000,
        'payment_method' => 'debit',
    ]);

    $sessionCredit = makeRecapTableSessionWithBilling($admin->id, [
        'tax' => 1000,
        'service_charge' => 1000,
        'payment_method' => 'kredit',
        'paid_amount' => 30000,
        'grand_total' => 30000,
        'paid_at' => $orderedAt,
    ]);
    makeRecapOrder($admin->id, $orderedAt, 'RCP-PAY-KRD', [
        'table_session_id' => $sessionCredit->id,
        'items_total' => 30000,
        'total' => 30000,
        'payment_method' => 'kredit',
    ]);

    $sessionQris = makeRecapTableSessionWithBilling($admin->id, [
        'tax' => 500,
        'service_charge' => 500,
        'payment_method' => 'qris',
        'paid_amount' => 20000,
        'grand_total' => 20000,
        'paid_at' => $orderedAt,
    ]);
    makeRecapOrder($admin->id, $orderedAt, 'RCP-PAY-QRS', [
        'table_session_id' => $sessionQris->id,
        'items_total' => 20000,
        'total' => 20000,
        'payment_method' => 'qris',
    ]);

    Dashboard::query()->updateOrCreate(
        ['id' => 1],
        [
            'total_amount' => 140000,
            'total_tax' => 6500,
            'total_service_charge' => 5000,
            'total_transfer' => 50000,
            'total_debit' => 40000,
            'total_kredit' => 30000,
            'total_qris' => 20000,
            'total_cash' => 0,
            'total_transactions' => 4,
            'last_synced_at' => now(),
        ]
    );

    actingAs($admin)
        ->get(route('admin.recap.index', [
            'start_datetime' => $rangeStart->format('Y-m-d\TH:i'),
            'end_datetime' => $rangeEnd->format('Y-m-d\TH:i'),
        ]))
        ->assertSuccessful()
        ->assertSeeText('Total Pajak')
        ->assertSeeText('Total Service Charge')
        ->assertSeeText('Total Pembayaran Transfer')
        ->assertSeeText('Total Pembayaran Debit')
        ->assertSeeText('Total Pembayaran Kredit')
        ->assertSeeText('Total Pembayaran QRIS')
        ->assertSeeText('Rp 6.500')
        ->assertSeeText('Rp 5.000')
        ->assertSeeText('Rp 50.000')
        ->assertSeeText('Rp 40.000')
        ->assertSeeText('Rp 30.000')
        ->assertSeeText('Rp 20.000');
});

test('recap page calculates total pembayaran tunai from live split billing data', function () {
    $admin = adminUser();
    $rangeStart = now()->startOfDay()->addHours(8);
    $rangeEnd = now()->startOfDay()->addHours(23);
    $orderedAt = now()->startOfDay()->addHours(12);

    $sessionSplit = makeRecapTableSessionWithBilling($admin->id, [
        'is_booking' => true,
        'is_walk_in' => false,
        'tax' => 0,
        'service_charge' => 0,
        'payment_method' => null,
        'payment_mode' => 'split',
        'paid_amount' => 40000,
        'grand_total' => 40000,
        'split_cash_amount' => 15000,
        'split_debit_amount' => 25000,
        'split_non_cash_method' => 'debit',
    ]);

    \Illuminate\Support\Facades\DB::table('billings')
        ->where('id', $sessionSplit->billing_id)
        ->update([
            'created_at' => $orderedAt,
            'updated_at' => $orderedAt,
            'paid_at' => $orderedAt,
        ]);

    makeRecapOrder($admin->id, $orderedAt, 'RCP-PAY-SPLIT-CASH', [
        'table_session_id' => $sessionSplit->id,
        'items_total' => 40000,
        'total' => 40000,
        'payment_method' => null,
        'payment_mode' => 'split',
    ]);

    Dashboard::query()->updateOrCreate(
        ['id' => 1],
        [
            'total_amount' => 0,
            'total_tax' => 0,
            'total_service_charge' => 0,
            'total_transfer' => 0,
            'total_debit' => 0,
            'total_kredit' => 0,
            'total_qris' => 0,
            'total_cash' => 0,
            'total_transactions' => 0,
            'last_synced_at' => now(),
        ]
    );

    actingAs($admin)
        ->get(route('admin.recap.index', [
            'start_datetime' => $rangeStart->format('Y-m-d\TH:i'),
            'end_datetime' => $rangeEnd->format('Y-m-d\TH:i'),
        ]))
        ->assertSuccessful()
        ->assertViewHas('paymentMethodTotals', function (array $totals): bool {
            return (float) ($totals['cash'] ?? 0) === 15000.0
                && (float) ($totals['debit'] ?? 0) === 25000.0;
        })
        ->assertSeeText('Rp 15.000')
        ->assertSeeText('Rp 25.000');
});

test('recap page includes walk-in order payment totals when billing is missing', function () {
    $admin = adminUser();
    $rangeStart = now()->startOfDay()->addHours(8);
    $rangeEnd = now()->startOfDay()->addHours(23);
    $orderedAt = now()->startOfDay()->addHours(13);

    makeRecapOrder($admin->id, $orderedAt, 'RCP-WALKIN-CASH-ONLY', [
        'table_session_id' => null,
        'customer_user_id' => null,
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
        'items_total' => 12000,
        'total' => 12000,
    ]);

    Dashboard::query()->updateOrCreate(
        ['id' => 1],
        [
            'total_amount' => 0,
            'total_tax' => 0,
            'total_service_charge' => 0,
            'total_transfer' => 0,
            'total_debit' => 0,
            'total_kredit' => 0,
            'total_qris' => 0,
            'total_cash' => 0,
            'total_transactions' => 0,
            'last_synced_at' => now(),
        ]
    );

    actingAs($admin)
        ->get(route('admin.recap.index', [
            'start_datetime' => $rangeStart->format('Y-m-d\TH:i'),
            'end_datetime' => $rangeEnd->format('Y-m-d\TH:i'),
        ]))
        ->assertSuccessful()
        ->assertViewHas('paymentMethodTotals', function (array $totals): bool {
            return (float) ($totals['cash'] ?? 0) === 12000.0;
        })
        ->assertSeeText('Rp 12.000');
});

test('recap includes booking billing by table_session_id and walk-in calculated tax service', function () {
    $admin = adminUser();
    $rangeStart = now()->startOfDay()->addHours(8);
    $rangeEnd = now()->startOfDay()->addHours(23);
    $orderedAt = now()->startOfDay()->addHours(12);

    $settings = \App\Models\GeneralSetting::instance();
    $settings->update([
        'tax_percentage' => 10,
        'service_charge_percentage' => 10,
    ]);

    $bookingSession = makeRecapTableSessionWithoutBillingLink($admin->id);
    Billing::create([
        'table_session_id' => $bookingSession->id,
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

    makeRecapOrder($admin->id, $orderedAt, 'RCP-LINKLESS-BOOKING', [
        'table_session_id' => $bookingSession->id,
        'items_total' => 50000,
        'total' => 50000,
        'payment_method' => null,
    ]);

    $walkInOrder = makeRecapOrder($admin->id, $orderedAt, 'RCP-WALKIN-001', [
        'table_session_id' => null,
        'items_total' => 100000,
        'total' => 100000,
        'payment_method' => 'debit',
        'payment_mode' => 'normal',
    ]);

    $walkInItem = makeRecapInventoryItem([
        'name' => 'Walkin Charged Item',
        'include_tax' => true,
        'include_service_charge' => true,
    ]);

    OrderItem::create([
        'order_id' => $walkInOrder->id,
        'inventory_item_id' => $walkInItem->id,
        'item_name' => $walkInItem->name,
        'item_code' => $walkInItem->code,
        'quantity' => 1,
        'price' => 100000,
        'subtotal' => 100000,
        'discount_amount' => 0,
        'preparation_location' => 'kitchen',
        'status' => 'served',
    ]);

    Dashboard::query()->updateOrCreate(
        ['id' => 1],
        [
            'total_amount' => 150000,
            'total_tax' => 14000,
            'total_service_charge' => 12000,
            'total_transfer' => 50000,
            'total_debit' => 100000,
            'total_kredit' => 0,
            'total_qris' => 0,
            'total_cash' => 0,
            'total_transactions' => 2,
            'last_synced_at' => now(),
        ]
    );

    actingAs($admin)
        ->get(route('admin.recap.index', [
            'start_datetime' => $rangeStart->format('Y-m-d\TH:i'),
            'end_datetime' => $rangeEnd->format('Y-m-d\TH:i'),
        ]))
        ->assertSuccessful()
        ->assertSeeText('Total Pajak')
        ->assertSeeText('Total Service Charge')
        ->assertSeeText('Total Pembayaran Transfer')
        ->assertSeeText('Total Pembayaran Debit')
        ->assertSeeText('Rp 14.000')
        ->assertSeeText('Rp 12.000')
        ->assertSeeText('Rp 50.000');
});

test('recap dashboard accumulates split payment totals by actual split methods', function () {
    $admin = adminUser();
    $rangeStart = now()->startOfDay()->addHours(8);
    $rangeEnd = now()->startOfDay()->addHours(23);

    $tableSession = makeRecapTableSessionWithBilling($admin->id, [
        'is_booking' => true,
        'is_walk_in' => false,
        'billing_status' => 'paid',
        'paid_at' => now()->startOfDay()->addHours(12),
        'payment_method' => 'cash',
        'payment_mode' => 'split',
        'split_cash_amount' => 10000,
        'split_debit_amount' => 15000,
        'split_non_cash_method' => 'qris',
        'split_non_cash_reference_number' => 'QR-001',
        'split_second_non_cash_amount' => 25000,
        'split_second_non_cash_method' => 'transfer',
        'split_second_non_cash_reference_number' => 'TR-002',
        'grand_total' => 50000,
        'paid_amount' => 50000,
    ]);

    $tableSession->billing->update([
        'billing_status' => 'paid',
        'paid_at' => now()->startOfDay()->addHours(12),
        'payment_method' => 'cash',
        'payment_mode' => 'split',
        'split_cash_amount' => 10000,
        'split_debit_amount' => 15000,
        'split_non_cash_method' => 'qris',
        'split_non_cash_reference_number' => 'QR-001',
        'split_second_non_cash_amount' => 25000,
        'split_second_non_cash_method' => 'transfer',
        'split_second_non_cash_reference_number' => 'TR-002',
        'grand_total' => 50000,
        'paid_amount' => 50000,
    ]);

    Dashboard::query()->updateOrCreate(
        ['id' => 1],
        [
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
        ]
    );

    actingAs($admin)
        ->get(route('admin.recap.index', [
            'start_datetime' => $rangeStart->format('Y-m-d\TH:i'),
            'end_datetime' => $rangeEnd->format('Y-m-d\TH:i'),
        ]))
        ->assertSuccessful()
        ->assertViewHas('paymentMethodTotals', function (array $totals): bool {
            return (float) ($totals['cash'] ?? 0) === 10000.0
                && (float) ($totals['qris'] ?? 0) === 15000.0
                && (float) ($totals['transfer'] ?? 0) === 25000.0
                && (float) ($totals['debit'] ?? 0) === 0.0;
        });
});

test('recap page shows dashboard preview aggregates', function () {
    $admin = adminUser();
    $start = now()->startOfDay()->addHours(8);
    $end = now()->startOfDay()->addHours(23)->addMinutes(59);

    Dashboard::query()->updateOrCreate(
        ['id' => 1],
        [
            'total_amount' => 500000,
            'total_dp' => 50000,
            'total_penjualan_rokok' => 42,
            'total_tax' => 15000,
            'total_service_charge' => 12000,
            'total_cash' => 100000,
            'total_transfer' => 120000,
            'total_debit' => 90000,
            'total_kredit' => 80000,
            'total_qris' => 110000,
            'total_transactions' => 10,
            'last_synced_at' => now(),
        ]
    );

    actingAs($admin)
        ->get(route('admin.recap.index', [
            'start_datetime' => $start->format('Y-m-d\TH:i'),
            'end_datetime' => $end->format('Y-m-d\TH:i'),
        ]))
        ->assertSuccessful()
        ->assertViewHas('dashboardPreview', function (array $preview): bool {
            return (float) ($preview['gross_sales'] ?? 0) === 550000.0
                && (float) ($preview['net_sales'] ?? 0) === 523000.0;
        })
        ->assertSeeText('Preview Dashboard (Akumulasi)')
        ->assertSeeText('Semua transaksi booking + walk-in')
        ->assertSeeText('Gross Sales')
        ->assertSeeText('Net Sales')
        ->assertSeeText('Total Penjualan Rokok (Qty)')
        ->assertSeeText('42')
        ->assertSeeText('Rp 550.000')
        ->assertSeeText('Rp 523.000')
        ->assertSeeText('Rp 15.000')
        ->assertSeeText('Rp 12.000')
        ->assertSeeText('Rp 120.000')
        ->assertSeeText('Rp 90.000')
        ->assertSeeText('Rp 80.000')
        ->assertSeeText('Rp 110.000')
        ->assertSeeText('10');
});

test('recap cashier table shows payment reference and order item details', function () {
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-05-10 14:00:00', 'Asia/Jakarta'));
    $admin = adminUser();
    $start = now()->startOfDay()->addHours(8);
    $end = now()->startOfDay()->addHours(23)->addMinutes(59);

    Dashboard::query()->updateOrCreate(
        ['id' => 1],
        [
            'total_amount' => 45000,
            'total_dp' => 0,
            'total_tax' => 0,
            'total_service_charge' => 0,
            'total_cash' => 0,
            'total_transfer' => 45000,
            'total_debit' => 0,
            'total_kredit' => 0,
            'total_qris' => 0,
            'total_transactions' => 1,
            'last_synced_at' => now(),
        ]
    );

    $order = makeRecapOrder($admin->id, now()->startOfDay()->addHours(12), 'RCP-REF-001', [
        'payment_method' => 'transfer',
        'payment_reference_number' => 'REF-TRF-9988',
        'items_total' => 45000,
        'total' => 45000,
        'status' => 'completed',
    ]);

    $item = makeRecapInventoryItem([
        'name' => 'Teh Tarik Rekap',
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $item->id,
        'item_name' => $item->name,
        'item_code' => $item->code,
        'quantity' => 2,
        'price' => 22500,
        'subtotal' => 45000,
        'discount_amount' => 0,
        'tax_amount' => 4500,
        'service_charge_amount' => 3000,
        'preparation_location' => 'bar',
        'status' => 'served',
    ]);

    actingAs($admin)
        ->get(route('admin.recap.index', [
            'start_datetime' => $start->format('Y-m-d\TH:i'),
            'end_datetime' => $end->format('Y-m-d\TH:i'),
        ]))
        ->assertSuccessful()
        ->assertSeeText('No. Referensi')
        ->assertSeeText('REF-TRF-9988')
        ->assertSeeText('Lihat Item')
        ->assertSeeText('Teh Tarik Rekap')
        ->assertSeeText('2x')
        ->assertSeeText('Harga: Rp 22.500')
        ->assertSeeText('Subtotal: Rp 45.000')
        ->assertSeeText('PB1: Rp 4.500')
        ->assertSeeText('Service: Rp 3.000');

    \Illuminate\Support\Carbon::setTestNow();
});

test('recap page shows automatic closing history list and modal content shell', function () {
    $admin = adminUser();
    $start = now()->startOfDay()->addHours(8);
    $end = now()->startOfDay()->addHours(23)->addMinutes(59);

    collect(range(1, 11))->each(function (int $offset): void {
        RecapHistory::query()->create([
            'end_day' => now()->subDays($offset)->toDateString(),
            'total_amount' => 120000,
            'total_tax' => 12000,
            'total_service_charge' => 8000,
            'total_cash' => 50000,
            'total_transfer' => 30000,
            'total_debit' => 20000,
            'total_kredit' => 10000,
            'total_qris' => 10000,
            'total_kitchen_items' => 6,
            'total_bar_items' => 4,
            'total_transactions' => 4,
            'last_synced_at' => now()->subMinutes(10),
        ]);
    });

    actingAs($admin)
        ->get(route('admin.recap.index', [
            'start_datetime' => $start->format('Y-m-d\TH:i'),
            'end_datetime' => $end->format('Y-m-d\TH:i'),
        ]))
        ->assertSuccessful()
        ->assertViewHas('recapHistories', function ($recapHistories): bool {
            return $recapHistories->hasPages()
                && $recapHistories->perPage() === 10
                && $recapHistories->total() === 11;
        })
        ->assertSeeText('History Closing')
        ->assertSeeText('List snapshot dashboard yang otomatis tersimpan setiap jam 12 malam.')
        ->assertSeeText('Detail History Closing')
        ->assertSeeText('Transactions Recap History')
        ->assertSeeText('Dari Billing')
        ->assertSeeText('Dari Walk-in')
        ->assertSeeText('Total DP (booking)')
        ->assertSeeText('Export History (.xlsx)')
        ->assertSeeText(now()->subDay()->format('d/m/Y'))
        ->assertSeeText('Snapshot recap tersimpan otomatis saat proses closing harian.')
        ->assertSeeText('Lihat Detail');
});

test('recap export returns native xlsx file', function () {
    $admin = adminUser();
    $start = now()->startOfDay()->addHours(8);
    $end = now()->startOfDay()->addHours(23)->addMinutes(59);

    $order = makeRecapOrder($admin->id, now(), 'RCP-EXPORT-001');
    $item = makeRecapInventoryItem(['name' => 'Export Item']);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $item->id,
        'item_name' => $item->name,
        'item_code' => $item->code,
        'quantity' => 1,
        'price' => 15000,
        'subtotal' => 15000,
        'discount_amount' => 0,
        'preparation_location' => 'kitchen',
        'status' => 'served',
    ]);

    $response = actingAs($admin)
        ->get(route('admin.recap.export', [
            'start_datetime' => $start->format('Y-m-d\TH:i'),
            'end_datetime' => $end->format('Y-m-d\TH:i'),
        ]));

    $response
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertHeader('content-disposition', 'attachment; filename=rekapan-'.$start->format('Ymd_Hi').'-'.$end->format('Ymd_Hi').'.xlsx');
});

test('recap history export returns native xlsx file', function () {
    $admin = adminUser();

    $history = RecapHistory::query()->create([
        'end_day' => now()->subDay()->toDateString(),
        'total_amount' => 120000,
        'total_tax' => 12000,
        'total_service_charge' => 8000,
        'total_cash' => 50000,
        'total_transfer' => 30000,
        'total_debit' => 20000,
        'total_kredit' => 10000,
        'total_qris' => 10000,
        'total_transactions' => 4,
        'last_synced_at' => now()->subMinutes(10),
    ]);

    $response = actingAs($admin)
        ->get(route('admin.recap.history.export', $history));

    $response
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertHeader('content-disposition', 'attachment; filename=rekapan-history-'.$history->end_day?->format('Ymd').'.xlsx');
});

test('recap history can be reprinted from history flow', function () {
    $admin = adminUser();
    $logPath = storage_path('logs/printer.log');
    $historyDate = now()->subDay();

    file_put_contents($logPath, '');

    $logPrinter = Printer::create([
        'name' => 'End Day Reprint Printer',
        'location' => 'cashier',
        'printer_type' => 'cashier',
        'connection_type' => 'log',
        'port' => 9100,
        'timeout' => 30,
        'header' => '126 Club',
        'footer' => 'Thank you',
        'width' => 42,
        'is_default' => false,
        'is_active' => true,
    ]);

    \App\Models\GeneralSetting::instance()->update([
        'end_day_receipt_printer_id' => $logPrinter->id,
    ]);

    $order = makeRecapOrder(
        $admin->id,
        $historyDate->copy()->startOfDay()->addHours(10),
        'RCP-HIST-REPRINT-001',
        [
            'payment_method' => 'cash',
            'payment_mode' => 'normal',
            'total' => 120000,
            'items_total' => 120000,
        ]
    );

    $inventoryItem = makeRecapInventoryItem(['name' => 'History Reprint Item']);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $inventoryItem->id,
        'item_name' => $inventoryItem->name,
        'item_code' => $inventoryItem->code,
        'quantity' => 1,
        'price' => 120000,
        'subtotal' => 120000,
        'tax_amount' => 12000,
        'service_charge_amount' => 8000,
        'discount_amount' => 0,
        'preparation_location' => 'kitchen',
        'status' => 'served',
    ]);

    $history = RecapHistory::query()->create([
        'end_day' => $historyDate->toDateString(),
        'total_amount' => 120000,
        'total_tax' => 12000,
        'total_service_charge' => 8000,
        'total_cash' => 50000,
        'total_transfer' => 30000,
        'total_debit' => 20000,
        'total_kredit' => 10000,
        'total_qris' => 10000,
        'total_kitchen_items' => 6,
        'total_bar_items' => 4,
        'total_transactions' => 4,
        'last_synced_at' => now()->subMinutes(10),
    ]);

    [$expectedStart, $expectedEnd] = \App\Models\RecapHistory::resolveWindowForDate($historyDate->copy());

    $expectedRedirectUrl = route('admin.recap.close-preview', [
        'start_datetime' => $expectedStart->format('Y-m-d\TH:i'),
        'end_datetime' => $expectedEnd->format('Y-m-d\TH:i'),
        'reprint' => 1,
        'recap_history_id' => $history->id,
    ]);

    actingAs($admin)
        ->from(route('admin.recap.index'))
        ->post(route('admin.recap.history.reprint', $history))
        ->assertRedirect($expectedRedirectUrl)
        ->assertSessionHas('success');

    $logOutput = file_get_contents($logPath);

    expect($logOutput)
        ->toContain('END DAY RECAP')
        ->toContain('DAFTAR TRANSAKSI')
        ->toContain('Status : SUCCESS (LOG MODE)');
});

test('recap history transactions endpoint returns billing and walk-in arrays', function () {
    $admin = adminUser();

    $session = makeRecapTableSessionWithBilling($admin->id, [
        'is_booking' => true,
        'is_walk_in' => false,
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
        'billing_status' => 'paid',
        'paid_at' => now(),
        'grand_total' => 30000,
        'paid_amount' => 30000,
        'transaction_code' => 'TRANSACTION-CODE-001',
    ]);

    $order = Order::create([
        'table_session_id' => $session->id,
        'customer_user_id' => null,
        'created_by' => $admin->id,
        'order_number' => 'ORDER-HISTORY-BILL-001',
        'status' => 'completed',
        'items_total' => 30000,
        'discount_amount' => 0,
        'total' => 30000,
        'ordered_at' => now(),
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
    ]);

    $item = makeRecapInventoryItem(['name' => 'History Billing Item']);
    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $item->id,
        'item_code' => $item->code,
        'item_name' => $item->name,
        'quantity' => 1,
        'price' => 30000,
        'subtotal' => 30000,
        'discount_amount' => 0,
        'preparation_location' => 'kitchen',
        'status' => 'served',
    ]);

    $session->billing->update([
        'billing_status' => 'paid',
        'paid_at' => now(),
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
        'orders_total' => 30000,
        'subtotal' => 30000,
        'grand_total' => 30000,
        'paid_amount' => 30000,
    ]);

    $history = RecapHistory::query()->create([
        'end_day' => now()->subDay()->toDateString(),
        'total_amount' => 30000,
        'total_tax' => 0,
        'total_service_charge' => 0,
        'total_cash' => 30000,
        'total_transfer' => 0,
        'total_debit' => 0,
        'total_kredit' => 0,
        'total_qris' => 0,
        'total_transactions' => 1,
        'last_synced_at' => now()->addHour(),
    ]);

    $response = actingAs($admin)
        ->getJson(route('admin.recap.history.transactions', $history));

    $response
        ->assertSuccessful()
        ->assertJsonStructure([
            'billing_transactions',
            'walkin_transactions',
        ]);

    expect($response->json('billing_transactions.0.transaction_number'))->toBe('BILLING-'.$session->billing->id);
});

test('recap transaction detail includes split payment methods', function () {
    $admin = adminUser();

    $area = Area::create([
        'code' => 'RS-AREA-01',
        'name' => 'Recap Split Area',
        'is_active' => true,
    ]);

    $table = Tabel::create([
        'area_id' => $area->id,
        'table_number' => 'RS-01',
        'qr_code' => 'RS-01-QR',
        'capacity' => 4,
        'status' => 'available',
        'is_active' => true,
    ]);

    $customer = User::factory()->create();

    $tableSession = TableSession::create([
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'session_code' => 'RS-SESSION-01',
        'status' => 'active',
        'checked_in_at' => now(),
    ]);

    $billing = Billing::create([
        'table_session_id' => $tableSession->id,
        'is_booking' => true,
        'is_walk_in' => false,
        'billing_status' => 'paid',
        'paid_at' => now(),
        'payment_method' => 'cash',
        'payment_mode' => 'split',
        'orders_total' => 50000,
        'subtotal' => 50000,
        'grand_total' => 50000,
        'paid_amount' => 50000,
        'split_cash_amount' => 20000,
        'split_debit_amount' => 15000,
        'split_non_cash_method' => 'debit',
        'split_non_cash_reference_number' => 'DB-111',
        'split_second_non_cash_amount' => 15000,
        'split_second_non_cash_method' => 'qris',
        'split_second_non_cash_reference_number' => 'QR-222',
    ]);

    $history = RecapHistory::query()->create([
        'end_day' => now()->subDay()->toDateString(),
        'total_amount' => 50000,
        'total_tax' => 0,
        'total_service_charge' => 0,
        'total_cash' => 20000,
        'total_transfer' => 0,
        'total_debit' => 15000,
        'total_kredit' => 0,
        'total_qris' => 15000,
        'total_transactions' => 1,
        'last_synced_at' => now()->addHour(),
    ]);

    $response = actingAs($admin)
        ->getJson(route('admin.recap.history.transactions', $history));

    $response
        ->assertSuccessful()
        ->assertJsonPath('billing_transactions.0.transaction_number', 'BILLING-'.$billing->id)
        ->assertJsonPath('billing_transactions.0.payment_mode', 'split')
        ->assertJsonCount(3, 'billing_transactions.0.split_payments');

    actingAs($admin)
        ->get(route('admin.recap.index'))
        ->assertSuccessful()
        ->assertSeeText('Detail Split Payment');
});

test('recap history transactions endpoint stops at last synced time', function () {
    $admin = adminUser();
    $historySyncAt = now('Asia/Jakarta')->setTime(0, 30);
    $previousDayOrderAt = $historySyncAt->copy()->subDay()->setTime(10, 0);
    $afterSyncOrderAt = $historySyncAt->copy()->setTime(7, 30);

    $previousDayOrder = makeRecapOrder($admin->id, $previousDayOrderAt, 'RCP-HIST-SYNC-001', [
        'status' => 'completed',
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
        'total' => 15000,
    ]);
    $previousDayItem = makeRecapInventoryItem(['name' => 'Previous Day Item']);
    OrderItem::create([
        'order_id' => $previousDayOrder->id,
        'inventory_item_id' => $previousDayItem->id,
        'item_code' => $previousDayItem->code,
        'item_name' => $previousDayItem->name,
        'quantity' => 1,
        'price' => 15000,
        'subtotal' => 15000,
        'discount_amount' => 0,
        'preparation_location' => 'kitchen',
        'status' => 'served',
    ]);

    $afterSyncOrder = makeRecapOrder($admin->id, $afterSyncOrderAt, 'RCP-HIST-SYNC-002', [
        'status' => 'completed',
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
        'total' => 20000,
    ]);
    $afterSyncItem = makeRecapInventoryItem(['name' => 'After Sync Item']);
    OrderItem::create([
        'order_id' => $afterSyncOrder->id,
        'inventory_item_id' => $afterSyncItem->id,
        'item_code' => $afterSyncItem->code,
        'item_name' => $afterSyncItem->name,
        'quantity' => 1,
        'price' => 20000,
        'subtotal' => 20000,
        'discount_amount' => 0,
        'preparation_location' => 'kitchen',
        'status' => 'served',
    ]);

    $history = RecapHistory::query()->create([
        'end_day' => $historySyncAt->copy()->subDay()->toDateString(),
        'total_amount' => 35000,
        'total_tax' => 0,
        'total_service_charge' => 0,
        'total_cash' => 35000,
        'total_transfer' => 0,
        'total_debit' => 0,
        'total_kredit' => 0,
        'total_qris' => 0,
        'total_transactions' => 2,
        'last_synced_at' => $historySyncAt,
    ]);

    $response = actingAs($admin)
        ->getJson(route('admin.recap.history.transactions', $history));

    $response
        ->assertSuccessful()
        ->assertJsonCount(0, 'billing_transactions')
        ->assertJsonCount(1, 'walkin_transactions')
        ->assertJsonPath('walkin_transactions.0.transaction_number', 'RCP-HIST-SYNC-001');
});

test('recap close preview hides close end day button in reprint mode', function () {
    $admin = adminUser();
    $start = now()->startOfDay();
    $end = now()->endOfDay();

    Dashboard::query()->updateOrCreate(
        ['id' => 1],
        [
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
        ]
    );

    actingAs($admin)
        ->get(route('admin.recap.close-preview', [
            'start_datetime' => $start->format('Y-m-d\TH:i'),
            'end_datetime' => $end->format('Y-m-d\TH:i'),
            'reprint' => 1,
        ]))
        ->assertSuccessful()
        ->assertDontSeeText('Tutup End Day')
        ->assertSeeText('Cetak Otomatis')
        ->assertSeeText('Save PDF');
});

test('recap kitchen summary follows dashboard aggregate after close', function () {
    $admin = adminUser();

    $frozenNow = \Illuminate\Support\Carbon::parse('2026-07-26 15:00:00');
    \Illuminate\Support\Carbon::setTestNow($frozenNow);

    $beforeCloseAt = now()->subHours(2);
    $afterCloseAt = now()->subHours(1);
    $closedAt = now()->subMinutes(10);

    $orderBeforeClose = makeRecapOrder($admin->id, $beforeCloseAt, 'RCP-BEFORE-CLOSE');
    $orderAfterClose = makeRecapOrder($admin->id, $afterCloseAt, 'RCP-AFTER-CLOSE');

    $kitchenItem = makeRecapInventoryItem([
        'name' => 'Kitchen Reset Item',
        'category_type' => 'food',
    ]);

    $kitchenOrderBeforeClose = KitchenOrder::create([
        'order_id' => $orderBeforeClose->id,
        'order_number' => $orderBeforeClose->order_number,
        'customer_user_id' => null,
        'table_id' => null,
        'total_amount' => 20000,
        'status' => 'selesai',
        'progress' => 100,
    ]);
    $kitchenOrderBeforeClose->forceFill([
        'created_at' => $beforeCloseAt,
        'updated_at' => $beforeCloseAt,
    ])->save();

    KitchenOrderItem::create([
        'kitchen_order_id' => $kitchenOrderBeforeClose->id,
        'inventory_item_id' => $kitchenItem->id,
        'quantity' => 5,
        'price' => 20000,
        'is_completed' => true,
    ]);

    $kitchenOrderAfterClose = KitchenOrder::create([
        'order_id' => $orderAfterClose->id,
        'order_number' => $orderAfterClose->order_number,
        'customer_user_id' => null,
        'table_id' => null,
        'total_amount' => 20000,
        'status' => 'selesai',
        'progress' => 100,
    ]);
    $kitchenOrderAfterClose->forceFill([
        'created_at' => $afterCloseAt,
        'updated_at' => $afterCloseAt,
    ])->save();

    KitchenOrderItem::create([
        'kitchen_order_id' => $kitchenOrderAfterClose->id,
        'inventory_item_id' => $kitchenItem->id,
        'quantity' => 3,
        'price' => 20000,
        'is_completed' => true,
    ]);

    $history = RecapHistory::query()->create([
        'end_day' => now()->toDateString(),
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
        'last_synced_at' => $closedAt,
    ]);
    $history->forceFill([
        'created_at' => $closedAt,
        'updated_at' => $closedAt,
    ])->save();

    $response = actingAs($admin)
        ->get(route('admin.recap.index'));

    $response->assertSuccessful();

    expect((int) $response->viewData('kitchenQtyTotal'))->toBe(0);
});

test('recap page shows transactions recap hari ini tabs for billing and walk-in', function () {
    $admin = adminUser();
    $windowStart = now('Asia/Jakarta')->startOfDay()->setTime(9, 0);
    $preWindowTime = $windowStart->copy()->subMinutes(30);
    $inWindowTime = $windowStart->copy()->addMinutes(30);

    Dashboard::query()->create([
        'total_amount' => 500000,
        'total_food' => 51000,
        'total_alcohol' => 62000,
        'total_beverage' => 73000,
        'total_cigarette' => 84000,
        'total_breakage' => 95000,
        'total_room' => 106000,
        'total_staff_meal' => 55000,
        'total_compliment_quantity' => 9,
        'total_foc_quantity' => 7,
        'total_ld' => 117000,
        'total_ld_quantity' => 12,
        'total_penjualan_rokok' => 42,
        'total_tax' => 15000,
        'total_service_charge' => 12000,
        'total_dp' => 13000,
        'total_cash' => 100000,
        'total_transfer' => 120000,
        'total_debit' => 90000,
        'total_kredit' => 80000,
        'total_qris' => 110000,
        'total_transactions' => 10,
        'last_synced_at' => now(),
    ]);

    $preWindowSession = makeRecapTableSessionWithBilling($admin->id, [
        'is_booking' => true,
        'is_walk_in' => false,
        'transaction_code' => 'BILL-PRE-001',
        'minimum_charge' => 50000,
        'orders_total' => 50000,
        'grand_total' => 56000,
        'paid_amount' => 56000,
        'tax' => 5000,
        'service_charge' => 1000,
        'paid_at' => $preWindowTime,
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
        'payment_reference_number' => 'BILL-PRE-REF',
    ]);

    $preWindowBillingOrder = makeRecapOrder($admin->id, $preWindowTime, 'ORDER-BILL-PRE-001', [
        'table_session_id' => $preWindowSession->id,
        'payment_method' => 'cash',
        'total' => 56000,
    ]);

    $preWindowBillingItem = makeRecapInventoryItem();
    OrderItem::create([
        'order_id' => $preWindowBillingOrder->id,
        'inventory_item_id' => $preWindowBillingItem->id,
        'item_code' => $preWindowBillingItem->code,
        'item_name' => $preWindowBillingItem->name,
        'quantity' => 1,
        'price' => 56000,
        'subtotal' => 56000,
        'status' => 'served',
    ]);

    $preWindowWalkInOrder = makeRecapOrder($admin->id, $preWindowTime, 'WALKIN-PRE-001', [
        'table_session_id' => null,
        'payment_method' => 'cash',
        'total' => 30000,
    ]);

    $preWindowWalkInItem = makeRecapInventoryItem();
    OrderItem::create([
        'order_id' => $preWindowWalkInOrder->id,
        'inventory_item_id' => $preWindowWalkInItem->id,
        'item_code' => $preWindowWalkInItem->code,
        'item_name' => $preWindowWalkInItem->name,
        'quantity' => 1,
        'price' => 30000,
        'subtotal' => 30000,
        'status' => 'served',
    ]);

    $tableSession = makeRecapTableSessionWithBilling($admin->id, [
        'is_booking' => true,
        'is_walk_in' => false,
        'transaction_code' => 'BILL-TODAY-001',
        'minimum_charge' => 50000,
        'orders_total' => 50000,
        'grand_total' => 56000,
        'paid_amount' => 56000,
        'tax' => 5000,
        'service_charge' => 1000,
        'paid_at' => now(),
        'payment_method' => 'qris',
        'payment_mode' => 'normal',
        'foc_comp_payment_method' => 'Compliment',
        'payment_reference_number' => 'BILL-REF-001',
    ]);

    $billingOrder = makeRecapOrder($admin->id, $inWindowTime, 'ORDER-BILL-001', [
        'table_session_id' => $tableSession->id,
        'payment_method' => 'qris',
        'total' => 56000,
    ]);

    $billingItem = makeRecapInventoryItem();
    OrderItem::create([
        'order_id' => $billingOrder->id,
        'inventory_item_id' => $billingItem->id,
        'item_code' => $billingItem->code,
        'item_name' => $billingItem->name,
        'quantity' => 2,
        'price' => 25000,
        'subtotal' => 50000,
        'status' => 'served',
    ]);

    $walkInOrder = makeRecapOrder($admin->id, $inWindowTime, 'WALKIN-TODAY-001', [
        'table_session_id' => null,
        'payment_method' => 'cash',
        'total' => 30000,
    ]);

    Billing::query()->create([
        'table_session_id' => null,
        'order_id' => $walkInOrder->id,
        'is_walk_in' => true,
        'is_booking' => false,
        'minimum_charge' => 0,
        'orders_total' => 30000,
        'subtotal' => 30000,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => 30000,
        'paid_amount' => 30000,
        'billing_status' => 'paid',
        'paid_at' => now(),
        'transaction_code' => 'WALK-BILL-TODAY-001',
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
        'foc_comp_payment_method' => 'FOC',
    ]);

    $walkInItem = makeRecapInventoryItem();
    OrderItem::create([
        'order_id' => $walkInOrder->id,
        'inventory_item_id' => $walkInItem->id,
        'item_code' => $walkInItem->code,
        'item_name' => $walkInItem->name,
        'quantity' => 1,
        'price' => 30000,
        'subtotal' => 30000,
        'status' => 'served',
    ]);

    actingAs($admin)
        ->get(route('admin.recap.index'))
        ->assertSuccessful()
        ->assertSeeText('Transactions Recap Hari Ini')
        ->assertSeeText('Dari Billing')
        ->assertSeeText('Dari Walk-in')
        ->assertSeeText('Filter Payment')
        ->assertSeeText('Breakage')
        ->assertSeeText('Room')
        ->assertSeeText('Staff Meal')
        ->assertSeeText('Compliment')
        ->assertSeeText('FOC')
        ->assertSeeText('DP (Booking)')
        ->assertSee('selectedTransaction?.total_bill || 0')
        ->assertSeeText('Detail Transaksi');
});

test('user without recap permission cannot access recap route', function () {
    $user = \App\Models\User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'Cashier']);
    $user->assignRole($role);

    actingAs($user)
        ->get(route('admin.recap.index'))
        ->assertForbidden();
});

test('user with recap permission can access recap route', function () {
    $user = \App\Models\User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'Cashier']);
    $permission = Permission::firstOrCreate(['name' => 'admin.recap.*', 'guard_name' => 'web']);
    $role->givePermissionTo($permission);
    $user->assignRole($role);

    actingAs($user)
        ->get(route('admin.recap.index'))
        ->assertSuccessful();
});

test('recap shows distinct per-order totals for unpaid booking transactions', function () {
    $admin = adminUser();
    $rangeStart = now()->startOfDay()->addHours(8);
    $rangeEnd = now()->startOfDay()->addHours(23);

    // Session billing exists but is NOT paid (belum closed).
    $session = makeRecapTableSessionWithBilling($admin->id, [
        'is_booking' => true,
        'billing_status' => 'draft',
        'minimum_charge' => 0,
        'orders_total' => 0,
        'subtotal' => 0,
        'tax' => 0,
        'service_charge' => 0,
        'discount_amount' => 0,
        'grand_total' => 0,
        'paid_amount' => 0,
        'payment_method' => null,
    ]);

    $t1 = makeRecapOrder($admin->id, $rangeStart->copy()->addHours(10), 'RCP-UNPAID-001', [
        'table_session_id' => $session->id,
        'items_total' => 10000,
        'total' => 10000,
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
    ]);
    $t2 = makeRecapOrder($admin->id, $rangeStart->copy()->addHours(11), 'RCP-UNPAID-002', [
        'table_session_id' => $session->id,
        'items_total' => 20000,
        'total' => 20000,
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
    ]);
    $t3 = makeRecapOrder($admin->id, $rangeStart->copy()->addHours(12), 'RCP-UNPAID-003', [
        'table_session_id' => $session->id,
        'items_total' => 30000,
        'total' => 30000,
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
    ]);

    // Each order has its own item so the per-order subtotal is computed from items.
    $item = makeRecapInventoryItem(['name' => 'Unpaid Booking Item']);

    foreach ([
        [$t1, 10000],
        [$t2, 20000],
        [$t3, 30000],
    ] as [$order, $subtotal]) {
        OrderItem::create([
            'order_id' => $order->id,
            'inventory_item_id' => $item->id,
            'item_name' => $item->name,
            'item_code' => $item->code,
            'quantity' => 1,
            'price' => $subtotal,
            'subtotal' => $subtotal,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'service_charge_amount' => 0,
            'preparation_location' => 'kitchen',
            'status' => 'served',
        ]);
    }

    Dashboard::query()->updateOrCreate(
        ['id' => 1],
        [
            'total_amount' => 0,
            'total_tax' => 0,
            'total_service_charge' => 0,
            'total_transfer' => 0,
            'total_debit' => 0,
            'total_kredit' => 0,
            'total_qris' => 0,
            'total_cash' => 0,
            'total_transactions' => 0,
            'last_synced_at' => now(),
        ]
    );

    $response = actingAs($admin)->get(route('admin.recap.index', [
        'start_datetime' => $rangeStart->format('Y-m-d\TH:i'),
        'end_datetime' => $rangeEnd->format('Y-m-d\TH:i'),
    ]));

    $response->assertSuccessful();

    // Cashier transactions render one row per order, each with its own total.
    $transactions = $response->viewData('cashierTransactions');

    expect($transactions)
        ->toHaveCount(3)
        ->each->toMatchArray(['down_payment_amount' => 0.0]);

    $byNumber = collect($transactions)->keyBy('order_number');

    expect((float) $byNumber['RCP-UNPAID-001']['total'])->toBe(10000.0)
        ->and((float) $byNumber['RCP-UNPAID-002']['total'])->toBe(20000.0)
        ->and((float) $byNumber['RCP-UNPAID-003']['total'])->toBe(30000.0);
});
