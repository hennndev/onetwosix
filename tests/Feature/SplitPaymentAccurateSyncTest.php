<?php

use App\Models\Billing;
use App\Models\GeneralSetting;
use App\Models\TableReservation;
use App\Models\TableSession;
use App\Models\User;
use App\Services\AccurateService;
use Mockery\MockInterface;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

test('split bill sync sends separate sales receipts with cash account for cash and bank account for non-cash', function () {
    GeneralSetting::instance()->update([
        'accurate_cash_account_no' => '110102_CASH',
        'accurate_bank_account_no' => '110101_BANK',
    ]);

    $admin = adminUser();

    $customerUser = User::factory()->create();
    $profile = \App\Models\UserProfile::create([
        'user_id' => $customerUser->id,
        'phone' => '08123456789',
    ]);
    \App\Models\CustomerUser::create([
        'user_id' => $customerUser->id,
        'user_profile_id' => $profile->id,
        'customer_code' => 'CUST-001',
    ]);

    $area = \App\Models\Area::create([
        'code' => 'AREA-01',
        'name' => 'Lounge',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $table = \App\Models\Tabel::create([
        'area_id' => $area->id,
        'table_number' => 'TBL-01',
        'qr_code' => 'QR-01',
        'capacity' => 4,
        'minimum_charge' => 0,
        'status' => 'available',
        'is_active' => true,
    ]);

    $booking = TableReservation::create([
        'booking_code' => random_int(100000, 999999),
        'table_id' => $table->id,
        'customer_id' => $customerUser->id,
        'reservation_date' => now()->toDateString(),
        'reservation_time' => now()->format('H:i:s'),
        'status' => 'completed',
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $table->id,
        'customer_id' => $customerUser->id,
        'session_code' => 'SESS-001',
        'status' => 'completed',
    ]);

    $item = \App\Models\InventoryItem::create([
        'accurate_id' => 999,
        'name' => 'Menu Test',
        'code' => 'BRG-999',
        'unit' => 'Pcs',
        'category_type' => 'Food',
        'price' => 100000,
        'stock_quantity' => 10,
        'is_active' => true,
    ]);

    $order = \App\Models\Order::create([
        'table_session_id' => $session->id,
        'order_number' => 'ORD-001',
        'status' => 'completed',
        'total' => 100000,
    ]);

    \App\Models\OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $item->id,
        'item_code' => 'BRG-999',
        'item_name' => 'Menu Test',
        'quantity' => 1,
        'price' => 100000,
        'subtotal' => 100000,
        'status' => 'served',
    ]);

    $billing = Billing::create([
        'table_session_id' => $session->id,
        'area_id' => $booking->table?->area_id,
        'grand_total' => 100000,
        'paid_amount' => 100000,
        'payment_mode' => 'split',
        'split_cash_amount' => 40000,
        'split_debit_amount' => 60000,
        'split_non_cash_method' => 'qris',
        'split_non_cash_reference_number' => 'REF-QRIS-123',
        'transaction_code' => 'BILLING-000001',
    ]);

    $session->update(['billing_id' => $billing->id]);

    $receiptPayloads = [];

    mock(AccurateService::class, function (MockInterface $mock) use (&$receiptPayloads): void {
        $mock->shouldReceive('saveSalesOrder')->once()->andReturn(['r' => ['number' => 'ROOM-BILLING-20260801-00001']]);
        $mock->shouldReceive('saveSalesInvoice')->once()->andReturn(['r' => ['number' => 'ROOM-BILLING-20260801-00001']]);
        $mock->shouldReceive('saveSalesReceipt')
            ->twice()
            ->withArgs(function (array $payload) use (&$receiptPayloads): bool {
                $receiptPayloads[] = $payload;

                return true;
            })
            ->andReturn(['r' => ['number' => 'REC-001']]);
    });

    actingAs($admin)
        ->from(route('admin.bookings.index', ['tab' => 'history']))
        ->post(route('admin.bookings.reSyncAccurate', $booking))
        ->assertRedirect(route('admin.bookings.index', ['tab' => 'history']));

    expect($receiptPayloads)->toHaveCount(2);

    $cashReceipt = collect($receiptPayloads)->firstWhere('bankNo', '110102_CASH');
    $bankReceipt = collect($receiptPayloads)->firstWhere('bankNo', '110101_BANK');

    expect($cashReceipt)->not->toBeNull();
    expect((float) $cashReceipt['chequeAmount'])->toBe(40000.0);
    expect($cashReceipt['description'])->toContain('Tunai');

    expect($bankReceipt)->not->toBeNull();
    expect((float) $bankReceipt['chequeAmount'])->toBe(60000.0);
    expect($bankReceipt['description'])->toContain('QRIS');
});

test('split bill sync with both non-cash uses bank account for both receipts', function () {
    GeneralSetting::instance()->update([
        'accurate_cash_account_no' => '110102_CASH',
        'accurate_bank_account_no' => '110101_BANK',
    ]);

    $admin = adminUser();

    $customerUser = User::factory()->create();
    $profile = \App\Models\UserProfile::create([
        'user_id' => $customerUser->id,
        'phone' => '08123456780',
    ]);
    \App\Models\CustomerUser::create([
        'user_id' => $customerUser->id,
        'user_profile_id' => $profile->id,
        'customer_code' => 'CUST-002',
    ]);

    $area = \App\Models\Area::create([
        'code' => 'AREA-02',
        'name' => 'Bar',
        'is_active' => true,
        'sort_order' => 2,
    ]);

    $table = \App\Models\Tabel::create([
        'area_id' => $area->id,
        'table_number' => 'TBL-02',
        'qr_code' => 'QR-02',
        'capacity' => 4,
        'minimum_charge' => 0,
        'status' => 'available',
        'is_active' => true,
    ]);

    $booking = TableReservation::create([
        'booking_code' => random_int(100000, 999999),
        'table_id' => $table->id,
        'customer_id' => $customerUser->id,
        'reservation_date' => now()->toDateString(),
        'reservation_time' => now()->format('H:i:s'),
        'status' => 'completed',
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $table->id,
        'customer_id' => $customerUser->id,
        'session_code' => 'SESS-002',
        'status' => 'completed',
    ]);

    $item = \App\Models\InventoryItem::create([
        'accurate_id' => 998,
        'name' => 'Drink Test',
        'code' => 'BRG-998',
        'unit' => 'Pcs',
        'category_type' => 'Beverage',
        'price' => 200000,
        'stock_quantity' => 10,
        'is_active' => true,
    ]);

    $order = \App\Models\Order::create([
        'table_session_id' => $session->id,
        'order_number' => 'ORD-002',
        'status' => 'completed',
        'total' => 200000,
    ]);

    \App\Models\OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $item->id,
        'item_code' => 'BRG-998',
        'item_name' => 'Drink Test',
        'quantity' => 1,
        'price' => 200000,
        'subtotal' => 200000,
        'status' => 'served',
    ]);

    $billing = Billing::create([
        'table_session_id' => $session->id,
        'area_id' => $booking->table?->area_id,
        'grand_total' => 200000,
        'paid_amount' => 200000,
        'payment_mode' => 'split',
        'split_cash_amount' => 0,
        'split_debit_amount' => 100000,
        'split_non_cash_method' => 'qris',
        'split_non_cash_reference_number' => 'REF-QRIS-999',
        'split_second_non_cash_amount' => 100000,
        'split_second_non_cash_method' => 'card',
        'split_second_non_cash_reference_number' => 'REF-CARD-999',
        'transaction_code' => 'BILLING-000002',
    ]);

    $session->update(['billing_id' => $billing->id]);

    $receiptPayloads = [];

    mock(AccurateService::class, function (MockInterface $mock) use (&$receiptPayloads): void {
        $mock->shouldReceive('saveSalesOrder')->once()->andReturn(['r' => ['number' => 'ROOM-BILLING-20260801-00002']]);
        $mock->shouldReceive('saveSalesInvoice')->once()->andReturn(['r' => ['number' => 'ROOM-BILLING-20260801-00002']]);
        $mock->shouldReceive('saveSalesReceipt')
            ->twice()
            ->withArgs(function (array $payload) use (&$receiptPayloads): bool {
                $receiptPayloads[] = $payload;

                return true;
            })
            ->andReturn(['r' => ['number' => 'REC-002']]);
    });

    actingAs($admin)
        ->from(route('admin.bookings.index', ['tab' => 'history']))
        ->post(route('admin.bookings.reSyncAccurate', $booking))
        ->assertRedirect(route('admin.bookings.index', ['tab' => 'history']));

    expect($receiptPayloads)->toHaveCount(2);

    $cashReceipt = collect($receiptPayloads)->firstWhere('bankNo', '110102_CASH');
    expect($cashReceipt)->toBeNull();

    $bankReceipts = collect($receiptPayloads)->where('bankNo', '110101_BANK');
    expect($bankReceipts)->toHaveCount(2);
});
