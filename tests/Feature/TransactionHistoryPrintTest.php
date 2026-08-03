<?php

use App\Models\Area;
use App\Models\Billing;
use App\Models\InventoryItem;
use App\Models\KitchenOrder;
use App\Models\KitchenOrderItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Printer;
use App\Models\Tabel;
use App\Models\TableSession;
use App\Models\User;
use App\Services\PrinterService;
use Mockery\MockInterface;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

function makeTransactionHistoryInventoryItem(array $attributes = []): InventoryItem
{
    return InventoryItem::create(array_merge([
        'code' => 'TH-ITEM-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'TH Item '.uniqid(),
        'category_type' => 'beverage',
        'price' => 25000,
        'stock_quantity' => 10,
        'threshold' => 1,
        'unit' => 'unit',
        'is_active' => true,
    ], $attributes));
}

function makeTransactionHistoryOrder(int $createdById, string $preparationLocation, array $assignedPrinterLocations = []): Order
{
    $inventoryItem = makeTransactionHistoryInventoryItem();

    $order = Order::create([
        'table_session_id' => null,
        'customer_user_id' => null,
        'created_by' => $createdById,
        'order_number' => 'TH-ORD-'.uniqid(),
        'status' => 'pending',
        'items_total' => 50000,
        'discount_amount' => 0,
        'total' => 50000,
        'ordered_at' => now(),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $inventoryItem->id,
        'item_name' => $inventoryItem->name,
        'item_code' => $inventoryItem->code,
        'quantity' => 2,
        'price' => 25000,
        'subtotal' => 50000,
        'discount_amount' => 0,
        'preparation_location' => $preparationLocation,
        'status' => 'pending',
    ]);

    if ($assignedPrinterLocations !== []) {
        $printerIds = Printer::query()
            ->whereIn('location', $assignedPrinterLocations)
            ->pluck('id')
            ->all();

        $inventoryItem->printers()->sync($printerIds);
    }

    return $order;
}

function makeTransactionHistoryBilling(Order $order, array $attributes = []): Billing
{
    return Billing::create(array_merge([
        'table_session_id' => $order->table_session_id,
        'order_id' => $order->id,
        'is_walk_in' => $order->table_session_id === null,
        'is_booking' => $order->table_session_id !== null,
        'minimum_charge' => 0,
        'orders_total' => (float) $order->total,
        'subtotal' => (float) $order->total,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => (float) $order->total,
        'paid_amount' => (float) $order->total,
        'billing_status' => 'paid',
        'transaction_code' => 'TH-BILL-'.uniqid(),
        'payment_method' => 'cash',
        'payment_reference_number' => null,
        'payment_mode' => 'normal',
        'split_cash_amount' => null,
        'split_debit_amount' => null,
        'split_non_cash_method' => null,
        'split_non_cash_reference_number' => null,
        'split_second_non_cash_amount' => null,
        'split_second_non_cash_method' => null,
        'split_second_non_cash_reference_number' => null,
    ], $attributes));
}

function makeTransactionHistoryPrinter(string $location, bool $isDefault = false): Printer
{
    return Printer::create([
        'name' => strtoupper($location).' Printer',
        'location' => $location,
        'connection_type' => 'log',
        'ip' => null,
        'port' => 9100,
        'path' => null,
        'timeout' => 30,
        'header' => '126 Club',
        'footer' => 'Thank you',
        'show_qr_code' => false,
        'width' => 42,
        'is_default' => $isDefault,
        'is_active' => true,
    ]);
}

function makeTransactionHistoryBookingOrder(int $createdById): Order
{
    $customer = User::factory()->create();

    $area = Area::create([
        'code' => 'TH-AREA-'.uniqid(),
        'name' => 'TH Area '.uniqid(),
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $table = Tabel::create([
        'area_id' => $area->id,
        'table_number' => 'TH-TBL-'.uniqid(),
        'qr_code' => 'TH-QR-'.uniqid(),
        'capacity' => 4,
        'status' => 'occupied',
        'is_active' => true,
    ]);

    $session = TableSession::create([
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'waiter_id' => null,
        'session_code' => 'TH-SES-'.uniqid(),
        'checked_in_at' => now(),
        'status' => 'active',
    ]);

    $order = Order::create([
        'table_session_id' => $session->id,
        'customer_user_id' => null,
        'created_by' => $createdById,
        'order_number' => 'TH-BOOK-'.uniqid(),
        'status' => 'pending',
        'items_total' => 25000,
        'discount_amount' => 0,
        'total' => 25000,
        'ordered_at' => now(),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => makeTransactionHistoryInventoryItem()->id,
        'item_name' => 'Booking Item',
        'item_code' => 'BOOK-001',
        'quantity' => 1,
        'price' => 25000,
        'subtotal' => 25000,
        'discount_amount' => 0,
        'preparation_location' => 'kitchen',
        'status' => 'pending',
    ]);

    return $order;
}

test('transaction history print requires reprint flag after first print', function () {
    $admin = adminUser();

    makeTransactionHistoryPrinter('cashier', true);
    makeTransactionHistoryPrinter('kitchen');

    $order = makeTransactionHistoryOrder($admin->id, 'kitchen', ['kitchen']);

    actingAs($admin)
        ->postJson(route('admin.transaction-history.print', $order), [
            'type' => 'kitchen',
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    expect($order->fresh()->kitchen_print_count)->toBe(1);

    actingAs($admin)
        ->postJson(route('admin.transaction-history.print', $order), [
            'type' => 'kitchen',
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    actingAs($admin)
        ->postJson(route('admin.transaction-history.print', $order), [
            'type' => 'kitchen',
            'is_reprint' => true,
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    expect($order->fresh()->kitchen_print_count)->toBe(2);
});

test('transaction history print allows bar print even when order has no bar items', function () {
    $admin = adminUser();

    makeTransactionHistoryPrinter('cashier', true);
    makeTransactionHistoryPrinter('bar');

    $order = makeTransactionHistoryOrder($admin->id, 'kitchen', ['kitchen']);

    actingAs($admin)
        ->postJson(route('admin.transaction-history.print', $order), [
            'type' => 'bar',
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    expect($order->fresh()->bar_print_count)->toBe(1);
});

test('transaction history print allows kitchen print regardless assigned printer types', function () {
    $admin = adminUser();

    makeTransactionHistoryPrinter('cashier', true);
    makeTransactionHistoryPrinter('checker');

    $order = makeTransactionHistoryOrder($admin->id, 'kitchen', ['cashier', 'checker']);

    actingAs($admin)
        ->postJson(route('admin.transaction-history.print', $order), [
            'type' => 'kitchen',
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    expect($order->fresh()->kitchen_print_count)->toBe(1);
});

test('transaction history resmi print always uses cashier type printer', function () {
    $admin = adminUser();

    $defaultKitchenPrinter = makeTransactionHistoryPrinter('kitchen', true);
    $cashierPrinter = makeTransactionHistoryPrinter('cashier', false);

    $order = makeTransactionHistoryOrder($admin->id, 'kitchen', ['kitchen']);

    mock(PrinterService::class, function (MockInterface $mock) use ($order, $cashierPrinter): void {
        $mock->shouldReceive('printReceipt')
            ->once()
            ->withArgs(function ($printedOrder, $printer) use ($order, $cashierPrinter): bool {
                return $printedOrder instanceof Order
                    && $printedOrder->id === $order->id
                    && $printer instanceof Printer
                    && $printer->id === $cashierPrinter->id;
            })
            ->andReturn(true);

        $mock->shouldReceive('printKitchenTicket')->never();
        $mock->shouldReceive('printBarTicket')->never();
    });

    actingAs($admin)
        ->postJson(route('admin.transaction-history.print', $order), [
            'type' => 'resmi',
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    expect($order->fresh()->receipt_print_count)->toBe(1)
        ->and($defaultKitchenPrinter->id)->not()->toBe($cashierPrinter->id);
});

test('transaction history resmi print fails when cashier printer is unavailable', function () {
    $admin = adminUser();

    makeTransactionHistoryPrinter('kitchen', true);
    $order = makeTransactionHistoryOrder($admin->id, 'kitchen', ['kitchen']);

    actingAs($admin)
        ->postJson(route('admin.transaction-history.print', $order), [
            'type' => 'resmi',
        ])
        ->assertStatus(400)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Tidak ada printer aktif untuk lokasi Kasir.');
});

test('transaction history print uses selected active printer when printer_id is provided', function () {
    $admin = adminUser();

    $cashierPrinter = makeTransactionHistoryPrinter('cashier', true);
    $kitchenPrinter = makeTransactionHistoryPrinter('kitchen', false);

    $order = makeTransactionHistoryOrder($admin->id, 'kitchen', ['kitchen']);

    mock(PrinterService::class, function (MockInterface $mock) use ($order, $kitchenPrinter): void {
        $mock->shouldReceive('printReceipt')
            ->once()
            ->withArgs(function ($printedOrder, $printer) use ($order, $kitchenPrinter): bool {
                return $printedOrder instanceof Order
                    && $printedOrder->id === $order->id
                    && $printer instanceof Printer
                    && $printer->id === $kitchenPrinter->id;
            })
            ->andReturn(true);

        $mock->shouldReceive('printKitchenTicket')->never();
        $mock->shouldReceive('printBarTicket')->never();
    });

    actingAs($admin)
        ->postJson(route('admin.transaction-history.print', $order), [
            'type' => 'resmi',
            'printer_id' => $kitchenPrinter->id,
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    expect($order->fresh()->receipt_print_count)->toBe(1)
        ->and($cashierPrinter->id)->not()->toBe($kitchenPrinter->id);
});

test('transaction history supports checker print type', function () {
    $admin = adminUser();

    $checkerPrinter = makeTransactionHistoryPrinter('checker', false);
    makeTransactionHistoryPrinter('cashier', true);

    $order = makeTransactionHistoryOrder($admin->id, 'kitchen', ['checker']);
    $order->update([
        'receipt_print_count' => 1,
    ]);

    mock(PrinterService::class, function (MockInterface $mock) use ($order, $checkerPrinter): void {
        $mock->shouldReceive('printReceipt')
            ->once()
            ->withArgs(function ($printedOrder, $printer) use ($order, $checkerPrinter): bool {
                return $printedOrder instanceof Order
                    && $printedOrder->id === $order->id
                    && $printer instanceof Printer
                    && $printer->id === $checkerPrinter->id;
            })
            ->andReturn(true);

        $mock->shouldReceive('printKitchenTicket')->never();
        $mock->shouldReceive('printBarTicket')->never();
        $mock->shouldReceive('printCheckerTicket')->never();
    });

    actingAs($admin)
        ->postJson(route('admin.transaction-history.print', $order), [
            'type' => 'checker',
            'printer_id' => $checkerPrinter->id,
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    expect($order->fresh()->receipt_print_count)->toBe(1)
        ->and($order->fresh()->checker_print_count)->toBe(1);
});

test('transaction history bar print uses checker layout when bar order is missing but kitchen order exists', function () {
    $admin = adminUser();

    $barPrinter = makeTransactionHistoryPrinter('bar', false);
    makeTransactionHistoryPrinter('cashier', true);

    $order = makeTransactionHistoryOrder($admin->id, 'kitchen', ['bar']);

    $kitchenOrder = KitchenOrder::create([
        'order_id' => $order->id,
        'order_number' => $order->order_number,
        'customer_user_id' => null,
        'table_id' => null,
        'total_amount' => 50000,
        'status' => 'selesai',
        'progress' => 100,
    ]);

    KitchenOrderItem::create([
        'kitchen_order_id' => $kitchenOrder->id,
        'inventory_item_id' => null,
        'quantity' => 2,
        'price' => 25000,
        'is_completed' => true,
    ]);

    mock(PrinterService::class, function (MockInterface $mock) use ($kitchenOrder, $barPrinter): void {
        $mock->shouldReceive('printBarTicket')
            ->once()
            ->withArgs(function ($printedOrder, $printer) use ($kitchenOrder, $barPrinter): bool {
                return $printedOrder instanceof KitchenOrder
                    && $printedOrder->id === $kitchenOrder->id
                    && $printer instanceof Printer
                    && $printer->id === $barPrinter->id;
            })
            ->andReturn(true);

        $mock->shouldReceive('printReceipt')->never();
        $mock->shouldReceive('printKitchenTicket')->never();
        $mock->shouldReceive('printCheckerTicket')->never();
    });

    actingAs($admin)
        ->postJson(route('admin.transaction-history.print', $order), [
            'type' => 'bar',
            'printer_id' => $barPrinter->id,
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    expect($order->fresh()->bar_print_count)->toBe(1);
});

test('checker print simulation uses pos_name so naming matches cashier receipt', function () {
    $admin = adminUser();

    $checkerPrinter = makeTransactionHistoryPrinter('checker', false);

    $inventoryItem = makeTransactionHistoryInventoryItem([
        'name' => 'Inventory Base Name',
        'pos_name' => 'POS Alias Name',
    ]);

    $order = makeTransactionHistoryOrder($admin->id, 'kitchen', ['checker']);

    $kitchenOrder = KitchenOrder::create([
        'order_id' => $order->id,
        'order_number' => $order->order_number,
        'customer_user_id' => null,
        'table_id' => null,
        'total_amount' => 50000,
        'status' => 'selesai',
        'progress' => 100,
    ]);

    KitchenOrderItem::create([
        'kitchen_order_id' => $kitchenOrder->id,
        'inventory_item_id' => $inventoryItem->id,
        'quantity' => 1,
        'price' => 25000,
        'is_completed' => true,
    ]);

    $logPath = storage_path('logs/printer.log');
    file_put_contents($logPath, '');

    $result = app(PrinterService::class)->printCheckerTicket(
        $kitchenOrder->load('items.inventoryItem', 'table'),
        $checkerPrinter,
    );

    $logContent = (string) file_get_contents($logPath);

    expect($result)->toBeTrue()
        ->and($logContent)->toContain('1x POS Alias Name')
        ->and($logContent)->not->toContain('1x Inventory Base Name');
});

test('checker print simulation shows waiter below table for booking orders', function () {
    $admin = adminUser();
    $waiter = User::factory()->create(['name' => 'Waiter Simulasi']);
    $customer = User::factory()->create();

    $area = Area::create([
        'code' => 'TH-AREA-'.uniqid(),
        'name' => 'TH Area '.uniqid(),
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $table = Tabel::create([
        'area_id' => $area->id,
        'table_number' => 'TH-TBL-'.uniqid(),
        'qr_code' => 'TH-QR-'.uniqid(),
        'capacity' => 4,
        'status' => 'occupied',
        'is_active' => true,
    ]);

    $session = TableSession::create([
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'waiter_id' => $waiter->id,
        'session_code' => 'TH-SES-'.uniqid(),
        'checked_in_at' => now(),
        'status' => 'active',
    ]);

    $checkerPrinter = makeTransactionHistoryPrinter('checker', false);
    $inventoryItem = makeTransactionHistoryInventoryItem();

    $order = Order::create([
        'table_session_id' => $session->id,
        'customer_user_id' => null,
        'created_by' => $admin->id,
        'order_number' => 'TH-BOOKING-'.uniqid(),
        'status' => 'pending',
        'items_total' => 25000,
        'discount_amount' => 0,
        'total' => 25000,
        'ordered_at' => now(),
    ]);

    $kitchenOrder = KitchenOrder::create([
        'order_id' => $order->id,
        'order_number' => $order->order_number,
        'customer_user_id' => null,
        'table_id' => $table->id,
        'total_amount' => 25000,
        'status' => 'selesai',
        'progress' => 100,
    ]);

    KitchenOrderItem::create([
        'kitchen_order_id' => $kitchenOrder->id,
        'inventory_item_id' => $inventoryItem->id,
        'quantity' => 1,
        'price' => 25000,
        'is_completed' => true,
    ]);

    $logPath = storage_path('logs/printer.log');
    file_put_contents($logPath, '');

    $result = app(PrinterService::class)->printCheckerTicket(
        $kitchenOrder->load('items.inventoryItem', 'table', 'order.tableSession.waiter.profile'),
        $checkerPrinter,
    );

    $logContent = (string) file_get_contents($logPath);

    expect($result)->toBeTrue()
        ->and($logContent)->toContain('Table : '.$table->table_number)
        ->and($logContent)->toContain('Waiter: Waiter Simulasi');
});

test('transaction history walk in mode only shows walk in orders', function () {
    $admin = adminUser();

    makeTransactionHistoryPrinter('cashier', true);

    $walkInOrder = makeTransactionHistoryOrder($admin->id, 'kitchen', ['kitchen']);
    $bookingOrder = makeTransactionHistoryBookingOrder($admin->id);

    actingAs($admin)
        ->get(route('admin.transaction-history.index', ['transaction_mode' => 'walk_in']))
        ->assertSuccessful()
        ->assertSee($walkInOrder->order_number)
        ->assertDontSee($bookingOrder->order_number)
        ->assertSee('Walk In')
        ->assertSee('Edit Payment')
        ->assertSee(':disabled="isSplitCashDisabled()"', false)
        ->assertSee(':disabled="isSplitSecondNonCashDisabled()"', false)
        ->assertSee('Print Ulang');
});

test('transaction history walk in mode filters by date range', function () {
    $admin = adminUser();

    makeTransactionHistoryPrinter('cashier', true);

    $todayOrder = makeTransactionHistoryOrder($admin->id, 'kitchen', ['kitchen']);
    $yesterdayOrder = makeTransactionHistoryOrder($admin->id, 'kitchen', ['kitchen']);

    $yesterdayOrder->update([
        'ordered_at' => now()->subDay(),
    ]);

    actingAs($admin)
        ->get(route('admin.transaction-history.index', [
            'transaction_mode' => 'walk_in',
            'date_from' => today()->toDateString(),
            'date_to' => today()->toDateString(),
        ]))
        ->assertSuccessful()
        ->assertSee($todayOrder->order_number)
        ->assertDontSee($yesterdayOrder->order_number)
        ->assertSee('Filter Tanggal');
});

test('transaction history payment edit updates billing payment mode', function () {
    $admin = adminUser();

    makeTransactionHistoryPrinter('cashier', true);

    $order = makeTransactionHistoryOrder($admin->id, 'kitchen', ['kitchen']);
    makeTransactionHistoryBilling($order);

    actingAs($admin)
        ->postJson(route('admin.transaction-history.update-payment', $order), [
            'payment_mode' => 'normal',
            'payment_method' => 'qris',
            'payment_reference_number' => 'QRIS-12345',
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    expect($order->fresh()->billing?->payment_method)->toBe('qris')
        ->and($order->fresh()->billing?->payment_reference_number)->toBe('QRIS-12345');
});

test('transaction history payment edit requires reference for non cash normal mode', function () {
    $admin = adminUser();

    makeTransactionHistoryPrinter('cashier', true);

    $order = makeTransactionHistoryOrder($admin->id, 'kitchen', ['kitchen']);
    makeTransactionHistoryBilling($order);

    actingAs($admin)
        ->postJson(route('admin.transaction-history.update-payment', $order), [
            'payment_mode' => 'normal',
            'payment_method' => 'qris',
            'payment_reference_number' => '',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['payment_reference_number']);
});

test('transaction history walk in print bypasses daily auth code requirement', function () {
    $admin = adminUser();

    makeTransactionHistoryPrinter('cashier', true);

    $order = makeTransactionHistoryOrder($admin->id, 'kitchen', ['kitchen']);
    makeTransactionHistoryBilling($order, [
        'is_walk_in' => true,
    ]);

    actingAs($admin)
        ->postJson(route('admin.transaction-history.print', $order), [
            'type' => 'resmi',
            'is_reprint' => true,
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    expect($order->fresh()->receipt_print_count)->toBe(1);
});

test('transaction history walk in table shows accurate error and resync action', function () {
    $admin = adminUser();

    makeTransactionHistoryPrinter('cashier', true);

    $order = makeTransactionHistoryOrder($admin->id, 'kitchen', ['kitchen']);
    makeTransactionHistoryBilling($order, [
        'accurate_so_number' => null,
        'accurate_inv_number' => null,
        'error_message' => 'SO Accurate gagal dibuat.',
    ]);

    actingAs($admin)
        ->get(route('admin.transaction-history.index', ['transaction_mode' => 'walk_in']))
        ->assertSuccessful()
        ->assertSee('Error Message')
        ->assertSee('Lihat Error')
        ->assertSee('Re-sync Accurate');
});

test('transaction history walk in detail payload shows split payment label', function () {
    $admin = adminUser();

    makeTransactionHistoryPrinter('cashier', true);

    $order = makeTransactionHistoryOrder($admin->id, 'kitchen', ['kitchen']);
    makeTransactionHistoryBilling($order, [
        'payment_mode' => 'split',
        'payment_method' => 'cash',
        'split_cash_amount' => 20000,
        'split_debit_amount' => 30000,
        'split_non_cash_method' => 'debit',
        'split_non_cash_reference_number' => 'REF-001',
    ]);

    $response = actingAs($admin)
        ->get(route('admin.transaction-history.index', ['transaction_mode' => 'walk_in']))
        ->assertSuccessful();

    expect($response->getContent())->toMatch('/paymentMethodDisplay.*SPLIT/s');
});

test('default transaction history table keeps original transaction columns', function () {
    $admin = adminUser();

    makeTransactionHistoryPrinter('cashier', true);
    makeTransactionHistoryOrder($admin->id, 'kitchen', ['kitchen']);
    makeTransactionHistoryBookingOrder($admin->id);

    actingAs($admin)
        ->get(route('admin.transaction-history.index'))
        ->assertSuccessful()
        ->assertSee('Total DP Hari Ini')
        ->assertSee('Tipe / Meja')
        ->assertSee('Bayar')
        ->assertDontSee('Lihat Error')
        ->assertDontSee('Re-sync Accurate');
});

test('transaction history resync accurate returns success when accurate numbers already exist', function () {
    $admin = adminUser();

    makeTransactionHistoryPrinter('cashier', true);

    $order = makeTransactionHistoryOrder($admin->id, 'kitchen', ['kitchen']);
    makeTransactionHistoryBilling($order, [
        'accurate_so_number' => 'SO-TH-001',
        'accurate_inv_number' => 'INV-TH-001',
    ]);

    actingAs($admin)
        ->post(route('admin.transaction-history.reSyncAccurate', $order))
        ->assertRedirect()
        ->assertSessionHas('success', 'SO dan Invoice Accurate sudah tersedia.');
});

test('transaction history filters orders by area_id', function () {
    $admin = adminUser();

    $areaA = \App\Models\Area::create(['code' => 'TH-A', 'name' => 'Area TH A', 'is_active' => true, 'sort_order' => 1]);
    $areaB = \App\Models\Area::create(['code' => 'TH-B', 'name' => 'Area TH B', 'is_active' => true, 'sort_order' => 2]);

    $orderA = Order::create([
        'area_id' => $areaA->id,
        'created_by' => $admin->id,
        'order_number' => 'ORD-TH-AREA-A',
        'status' => 'completed',
        'items_total' => 10000,
        'discount_amount' => 0,
        'total' => 10000,
        'ordered_at' => now(),
    ]);

    $orderB = Order::create([
        'area_id' => $areaB->id,
        'created_by' => $admin->id,
        'order_number' => 'ORD-TH-AREA-B',
        'status' => 'completed',
        'items_total' => 20000,
        'discount_amount' => 0,
        'total' => 20000,
        'ordered_at' => now(),
    ]);

    actingAs($admin)
        ->get(route('admin.transaction-history.index', ['area_id' => $areaA->id]))
        ->assertOk()
        ->assertViewHas('selectedAreaId', $areaA->id)
        ->assertViewHas('orders', fn ($orders) => $orders->contains('id', $orderA->id) && ! $orders->contains('id', $orderB->id));
});
