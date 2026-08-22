<?php

use App\Models\BarOrder;
use App\Models\Billing;
use App\Models\InventoryItem;
use App\Models\KitchenOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Printer;
use App\Models\Tabel;
use App\Services\PrinterService;

beforeEach(function () {
    $logPath = storage_path('logs/printer.log');
    if (file_exists($logPath)) {
        unlink($logPath);
    }
});

it('prints a single ticket per job in log mode', function () {
    $table = new Tabel;
    $table->table_number = 'T-42';

    $kitchenOrder = new KitchenOrder;
    $kitchenOrder->order_number = 'ORD-TEST-SINGLE-001';
    $kitchenOrder->setRelation('table', $table);
    $kitchenOrder->setRelation('items', collect());

    $printer = Printer::make(['name' => 'Single Test', 'connection_type' => 'log', 'width' => 42]);

    $result = (new PrinterService)->printKitchenTicket($kitchenOrder, $printer);

    $log = file_get_contents(storage_path('logs/printer.log'));

    expect($result)->toBeTrue()
        ->and(substr_count($log, '[PRINT SIMULATION] KITCHEN'))->toBe(1);
});

it('prints a single test print per job', function () {
    $printer = Printer::make(['name' => 'Single Test', 'connection_type' => 'log', 'width' => 42]);

    $result = (new PrinterService)->testPrint($printer);

    $log = file_get_contents(storage_path('logs/printer.log'));

    expect($result)->toBeTrue()
        ->and(substr_count($log, '[PRINT SIMULATION] TEST PRINT'))->toBe(1);
});

it('prints a receiver ticket per item when a receiver printer is configured', function () {
    $table = new Tabel;
    $table->table_number = 'T-42';

    $item1 = new OrderItem;
    $item1->quantity = 2;
    $item1->notes = 'Extra pedas';
    $item1->setRelation('inventoryItem', InventoryItem::make(['name' => 'Nasi Goreng', 'pos_name' => 'Nasgor']));

    $item2 = new OrderItem;
    $item2->quantity = 1;
    $item2->notes = '';
    $item2->setRelation('inventoryItem', InventoryItem::make(['name' => 'Es Teh']));

    $kitchenOrder = new KitchenOrder;
    $kitchenOrder->order_number = 'ORD-TEST-RCV-001';
    $kitchenOrder->setRelation('table', $table);
    $kitchenOrder->setRelation('items', collect([$item1, $item2]));

    $printer = Printer::make(['name' => 'Kitchen', 'connection_type' => 'log', 'width' => 42, 'enable_receiver' => true, 'is_active' => true]);

    $result = (new PrinterService)->printKitchenTicket($kitchenOrder, $printer);

    $log = file_get_contents(storage_path('logs/printer.log'));

    expect($result)->toBeTrue()
        ->and(substr_count($log, '[PRINT SIMULATION] KITCHEN'))->toBe(1)
        ->and(substr_count($log, '[PRINT SIMULATION] RECEIVER'))->toBe(2)
        ->and($log)->toContain('TERIMA PESANAN')
        ->and($log)->toContain('SILAKAN AMBIL & ANTAR KE MEJA')
        ->and($log)->toContain('Nasgor')
        ->and($log)->toContain('Es Teh');
});

it('falls back to production ticket when receiver printer is inactive', function () {
    $table = new Tabel;
    $table->table_number = 'T-42';

    $kitchenOrder = new KitchenOrder;
    $kitchenOrder->order_number = 'ORD-TEST-RCV-002';
    $kitchenOrder->setRelation('table', $table);
    $kitchenOrder->setRelation('items', collect());

    // enable_receiver true tapi printer non-aktif → receiver tidak jalan.
    $printer = Printer::make(['name' => 'Kitchen', 'connection_type' => 'log', 'width' => 42, 'enable_receiver' => true, 'is_active' => false]);

    $result = (new PrinterService)->printKitchenTicket($kitchenOrder, $printer);

    $log = file_get_contents(storage_path('logs/printer.log'));

    expect($result)->toBeTrue()
        ->and(substr_count($log, '[PRINT SIMULATION] KITCHEN'))->toBe(1)
        ->and(substr_count($log, '[PRINT SIMULATION] RECEIVER'))->toBe(0);
});

it('does not print receiver tickets for checker or cashier sections', function () {
    $table = new Tabel;
    $table->table_number = 'T-42';

    $kitchenOrder = new KitchenOrder;
    $kitchenOrder->order_number = 'ORD-TEST-RCV-003';
    $kitchenOrder->setRelation('table', $table);
    $kitchenOrder->setRelation('items', collect());

    $printer = Printer::make(['name' => 'Kitchen', 'connection_type' => 'log', 'width' => 42, 'enable_receiver' => true, 'is_active' => true]);

    (new PrinterService)->printCheckerTicket($kitchenOrder, $printer);

    $log = file_get_contents(storage_path('logs/printer.log'));

    expect(substr_count($log, '[PRINT SIMULATION] CHECKER'))->toBe(1)
        ->and(substr_count($log, '[PRINT SIMULATION] RECEIVER'))->toBe(0);
});

it('prints kitchen ticket with the correct table_number', function () {
    $table = new Tabel;
    $table->table_number = 'T-42';

    $kitchenOrder = new KitchenOrder;
    $kitchenOrder->order_number = 'ORD-TEST-K001';
    $kitchenOrder->setRelation('table', $table);
    $kitchenOrder->setRelation('items', collect());

    $printer = Printer::make(['name' => 'Kitchen Test', 'connection_type' => 'log', 'width' => 42]);

    $result = (new PrinterService)->printKitchenTicket($kitchenOrder, $printer);

    $log = file_get_contents(storage_path('logs/printer.log'));

    expect($result)->toBeTrue()
        ->and($log)->toContain('T-42')
        ->and($log)->toContain('KITCHEN')
        ->and($log)->toContain('ORD-TEST-K001');
});

it('prints bar ticket with the correct table_number', function () {
    $table = new Tabel;
    $table->table_number = 'B-07';

    $barOrder = new BarOrder;
    $barOrder->order_number = 'ORD-TEST-B001';
    $barOrder->setRelation('table', $table);
    $barOrder->setRelation('items', collect());

    $printer = Printer::make(['name' => 'Bar Test', 'connection_type' => 'log', 'width' => 42]);

    $result = (new PrinterService)->printBarTicket($barOrder, $printer);

    $log = file_get_contents(storage_path('logs/printer.log'));

    expect($result)->toBeTrue()
        ->and($log)->toContain('B-07')
        ->and($log)->toContain('BAR')
        ->and($log)->toContain('ORD-TEST-B001');
});

it('shows N/A in bar ticket when no table is assigned', function () {
    $barOrder = new BarOrder;
    $barOrder->order_number = 'ORD-TEST-B002';
    $barOrder->setRelation('table', null);
    $barOrder->setRelation('items', collect());

    $printer = Printer::make(['name' => 'Bar Test', 'connection_type' => 'log', 'width' => 42]);

    (new PrinterService)->printBarTicket($barOrder, $printer);

    $log = file_get_contents(storage_path('logs/printer.log'));

    expect($log)->toContain('N/A');
});

it('prints kitchen ticket with correct item names from inventoryItem relationship', function () {
    $table = new Tabel;
    $table->table_number = 'T-10';

    $inventoryItem = new \App\Models\InventoryItem;
    $inventoryItem->name = 'Test Food';

    $item = new \App\Models\KitchenOrderItem;
    $item->quantity = 2;
    $item->setRelation('inventoryItem', $inventoryItem);

    $kitchenOrder = new KitchenOrder;
    $kitchenOrder->order_number = 'ORD-TEST-K002';
    $kitchenOrder->setRelation('table', $table);
    $kitchenOrder->setRelation('items', collect([$item]));

    $printer = Printer::make(['name' => 'Kitchen Test', 'connection_type' => 'log', 'width' => 42]);

    (new PrinterService)->printKitchenTicket($kitchenOrder, $printer);

    $log = file_get_contents(storage_path('logs/printer.log'));

    expect($log)->toContain('2x Test Food');
});

it('prints walk-in billing simulation with discount row after subtotal', function () {
    $orderItem = new OrderItem;
    $orderItem->item_name = 'Test Food Lounge';
    $orderItem->quantity = 1;
    $orderItem->price = 5000000;
    $orderItem->subtotal = 5000000;

    $order = new Order;
    $order->order_number = 'WALKIN-TEST-01';
    $order->ordered_at = now();
    $order->setRelation('items', collect([$orderItem]));
    $order->setRelation('customer', null);
    $order->setRelation('createdBy', null);

    $billing = new Billing;
    $billing->transaction_code = 'WALKIN-000001';
    $billing->updated_at = now();
    $billing->minimum_charge = 0;
    $billing->subtotal = 5000000;
    $billing->tax = 550000;
    $billing->tax_percentage = 11;
    $billing->service_charge = 277500;
    $billing->service_charge_percentage = 5;
    $billing->discount_amount = 582750;
    $billing->grand_total = 5244750;
    $billing->payment_mode = 'normal';
    $billing->payment_method = 'cash';

    $printer = Printer::make([
        'name' => 'Walkin Test',
        'connection_type' => 'log',
        'width' => 42,
    ]);

    $result = (new PrinterService)->printWalkInBillingReceipt($order, $billing, $printer);

    $log = (string) file_get_contents(storage_path('logs/printer.log'));

    $subTotalPos = strpos($log, 'Sub Total');
    $discountPos = strpos($log, 'Diskon');
    $remainingPos = strpos($log, 'Sisa Bayar');

    expect($result)->toBeTrue()
        ->and($log)->toContain('WALK-IN RECEIPT')
        ->and($log)->toContain('- Rp 582.750')
        ->and($subTotalPos)->not->toBeFalse()
        ->and($discountPos)->not->toBeFalse()
        ->and($remainingPos)->not->toBeFalse()
        ->and($discountPos > $subTotalPos)->toBeTrue()
        ->and($remainingPos > $discountPos)->toBeTrue();
});

it('prints compliment and foc prices using their original values in cashier receipt output', function () {
    $complimentItem = new InventoryItem;
    $complimentItem->category_main = 'compliment';

    $focItem = new InventoryItem;
    $focItem->category_main = 'foc';

    $complimentOrderItem = new OrderItem;
    $complimentOrderItem->item_name = 'Compliment Soup';
    $complimentOrderItem->quantity = 1;
    $complimentOrderItem->price = 45000;
    $complimentOrderItem->subtotal = 45000;
    $complimentOrderItem->setRelation('inventoryItem', $complimentItem);

    $focOrderItem = new OrderItem;
    $focOrderItem->item_name = 'FOC Drink';
    $focOrderItem->quantity = 2;
    $focOrderItem->price = 30000;
    $focOrderItem->subtotal = 60000;
    $focOrderItem->setRelation('inventoryItem', $focItem);

    $order = new Order;
    $order->order_number = 'WALKIN-ZERO-01';
    $order->ordered_at = now();
    $order->setRelation('items', collect([$complimentOrderItem, $focOrderItem]));
    $order->setRelation('customer', null);
    $order->setRelation('createdBy', null);

    $billing = new Billing;
    $billing->transaction_code = 'WALKIN-ZERO-001';
    $billing->updated_at = now();
    $billing->minimum_charge = 0;
    $billing->subtotal = 105000;
    $billing->tax = 0;
    $billing->tax_percentage = 0;
    $billing->service_charge = 0;
    $billing->service_charge_percentage = 0;
    $billing->discount_amount = 0;
    $billing->grand_total = 105000;
    $billing->payment_mode = 'normal';
    $billing->payment_method = 'cash';

    $printer = Printer::make([
        'name' => 'Walkin Zero Test',
        'connection_type' => 'log',
        'width' => 42,
    ]);

    $result = (new PrinterService)->printWalkInBillingReceipt($order, $billing, $printer);

    $log = (string) file_get_contents(storage_path('logs/printer.log'));

    expect($result)->toBeTrue()
        ->and($log)->toContain('Compliment Soup 1x')
        ->and($log)->toContain('FOC Drink 2x')
        ->and($log)->toContain('Rp 45.000')
        ->and($log)->toContain('Rp 60.000')
        ->and(substr_count($log, 'Harga: Rp 0'))->toBe(0)
        ->and(substr_count($log, 'Total: Rp 0'))->toBe(0);
});

it('prints compliment and foc prices using their original values in closed billing receipt output', function () {
    $table = new Tabel;
    $table->table_number = 'C-11';

    $complimentItem = new InventoryItem;
    $complimentItem->category_main = 'compliment';

    $focItem = new InventoryItem;
    $focItem->category_main = 'foc';

    $complimentOrderItem = new OrderItem;
    $complimentOrderItem->item_name = 'Compliment Soup';
    $complimentOrderItem->quantity = 1;
    $complimentOrderItem->price = 45000;
    $complimentOrderItem->subtotal = 45000;
    $complimentOrderItem->setRelation('inventoryItem', $complimentItem);

    $focOrderItem = new OrderItem;
    $focOrderItem->item_name = 'FOC Drink';
    $focOrderItem->quantity = 2;
    $focOrderItem->price = 30000;
    $focOrderItem->subtotal = 60000;
    $focOrderItem->setRelation('inventoryItem', $focItem);

    $order = new Order;
    $order->order_number = 'CLOSED-ZERO-01';
    $order->ordered_at = now();
    $order->setRelation('items', collect([$complimentOrderItem, $focOrderItem]));

    $session = new \App\Models\TableSession;
    $session->setRelation('table', $table);
    $session->setRelation('customer', null);
    $session->setRelation('reservation', null);
    $session->setRelation('orders', collect([$order]));

    $billing = new Billing;
    $billing->transaction_code = 'CLOSED-ZERO-001';
    $billing->updated_at = now();
    $billing->minimum_charge = 0;
    $billing->subtotal = 105000;
    $billing->tax = 0;
    $billing->tax_percentage = 0;
    $billing->service_charge = 0;
    $billing->service_charge_percentage = 0;
    $billing->discount_amount = 0;
    $billing->grand_total = 105000;
    $billing->payment_mode = 'normal';
    $billing->payment_method = 'cash';

    $printer = Printer::make([
        'name' => 'Closed Zero Test',
        'connection_type' => 'log',
        'width' => 42,
    ]);

    $result = (new PrinterService)->printClosedBillingReceipt($billing, $session, $printer);

    $log = (string) file_get_contents(storage_path('logs/printer.log'));

    expect($result)->toBeTrue()
        ->and($log)->toContain('Compliment Soup')
        ->and($log)->toContain('FOC Drink')
        ->and($log)->toContain('Rp 45.000')
        ->and($log)->toContain('Rp 60.000')
        ->and(substr_count($log, 'Harga: Rp 0'))->toBe(0)
        ->and(substr_count($log, 'Total: Rp 0'))->toBe(0);
});

it('prints Jenis Transaksi FOC on walk-in billing receipt', function () {
    $order = new \App\Models\Order;
    $order->order_number = 'WALKIN-FOC-01';
    $order->ordered_at = now();
    $order->setRelation('items', collect());
    $order->setRelation('customer', null);
    $order->setRelation('createdBy', null);

    $billing = new \App\Models\Billing;
    $billing->transaction_code = 'WALKIN-FOC-001';
    $billing->updated_at = now();
    $billing->minimum_charge = 0;
    $billing->subtotal = 50000;
    $billing->tax = 0;
    $billing->tax_percentage = 0;
    $billing->service_charge = 0;
    $billing->service_charge_percentage = 0;
    $billing->discount_amount = 0;
    $billing->grand_total = 50000;
    $billing->payment_mode = 'normal';
    $billing->payment_method = 'cash';
    $billing->foc_comp_payment_method = 'FOC';

    $printer = Printer::make([
        'name' => 'Walkin FOC Test',
        'connection_type' => 'log',
        'width' => 42,
    ]);

    $result = (new PrinterService)->printWalkInBillingReceipt($order, $billing, $printer);

    $log = (string) file_get_contents(storage_path('logs/printer.log'));

    expect($result)->toBeTrue()
        ->and($log)->toContain('FOC');
});

it('prints Jenis Transaksi Compliment on closed billing receipt', function () {
    $table = new \App\Models\Tabel;
    $table->table_number = 'C-12';

    $order = new \App\Models\Order;
    $order->order_number = 'CLOSED-COMP-01';
    $order->ordered_at = now();
    $order->setRelation('items', collect());

    $session = new \App\Models\TableSession;
    $session->setRelation('table', $table);
    $session->setRelation('customer', null);
    $session->setRelation('reservation', null);
    $session->setRelation('orders', collect([$order]));

    $billing = new \App\Models\Billing;
    $billing->transaction_code = 'CLOSED-COMP-001';
    $billing->updated_at = now();
    $billing->minimum_charge = 0;
    $billing->subtotal = 0;
    $billing->tax = 0;
    $billing->tax_percentage = 0;
    $billing->service_charge = 0;
    $billing->service_charge_percentage = 0;
    $billing->discount_amount = 0;
    $billing->grand_total = 0;
    $billing->payment_mode = 'normal';
    $billing->payment_method = 'cash';
    $billing->foc_comp_payment_method = 'Compliment';

    $printer = Printer::make([
        'name' => 'Closed Comp Test',
        'connection_type' => 'log',
        'width' => 42,
    ]);

    $result = (new PrinterService)->printClosedBillingReceipt($billing, $session, $printer);

    $log = (string) file_get_contents(storage_path('logs/printer.log'));

    expect($result)->toBeTrue()
        ->and($log)->toContain('Compliment');
});

it('prints end day recap with LD quantity row', function () {
    $printer = Printer::make([
        'name' => 'End Day Test',
        'location' => 'cashier',
        'connection_type' => 'log',
        'width' => 42,
    ]);

    $recapData = [
        'selectedStartDatetime' => '16/04/2026 09:00',
        'selectedEndDatetime' => '17/04/2026 09:00',
        'cashierCount' => 2,
        'cashierRevenue' => 150000,
        'totalTax' => 15000,
        'totalServiceCharge' => 10000,
        'totalDiscount' => 0,
        'totalDownPayment' => 0,
        'paymentMethodTotals' => [
            'cash' => 150000,
            'transfer' => 0,
            'debit' => 0,
            'kredit' => 0,
            'qris' => 0,
        ],
        'dashboardPreview' => [
            'total_kitchen_items' => 3,
            'total_bar_items' => 4,
            'total_staff_meal' => 25000,
            'total_ld_quantity' => 7,
        ],
        'kitchenQtyTotal' => 3,
        'barQtyTotal' => 4,
        'rokokItems' => [],
        'cashierTransactions' => [],
    ];

    $result = (new PrinterService)->printEndDayRecap($recapData, $printer);

    $log = file_get_contents(storage_path('logs/printer.log'));

    expect($result)->toBeTrue()
        ->and($log)->toContain('Total Staff Meal')
        ->and($log)->toContain('25.000')
        ->and($log)->toContain('Total LD Qty')
        ->and($log)->toContain('7');
});
