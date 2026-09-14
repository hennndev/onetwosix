<?php

use App\Models\Area;
use App\Models\Billing;
use App\Models\CustomerUser;
use App\Models\GeneralSetting;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Tabel;
use App\Models\TableReservation;
use App\Models\TableSession;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\AccurateService;
use Mockery\MockInterface;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

function focCompCustomer(): CustomerUser
{
    $customer = User::factory()->create();
    $profile = UserProfile::create(['user_id' => $customer->id]);

    return CustomerUser::create([
        'user_id' => $customer->id,
        'user_profile_id' => $profile->id,
        'accurate_id' => null,
        'customer_code' => null,
        'total_visits' => 0,
        'lifetime_spending' => 0,
    ]);
}

function focCompInventoryItem(int $price = 25000): InventoryItem
{
    return InventoryItem::create([
        'code' => 'FOC-COMP-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Foc Comp Item '.uniqid(),
        'category_type' => 'beverage',
        'price' => $price,
        'stock_quantity' => 50,
        'threshold' => 5,
        'unit' => 'glass',
        'is_active' => true,
    ]);
}

function mockFocCompAccurateService(array &$payloads, bool $withReceipt = false): void
{
    mock(AccurateService::class, function (MockInterface $mock) use (&$payloads, $withReceipt): void {
        $mock->shouldReceive('saveCustomer')->andReturn([
            'r' => ['id' => 98765, 'customerNo' => 'CUST-FOC-COMP'],
        ]);

        $mock->shouldReceive('saveSalesOrder')->once()->withArgs(function (array $payload) use (&$payloads): bool {
            $payloads['sales_order'] = $payload;

            return true;
        })->andReturnUsing(fn (array $payload): array => ['r' => ['number' => $payload['number']]]);

        $mock->shouldReceive('saveSalesInvoice')->once()->withArgs(function (array $payload) use (&$payloads): bool {
            $payloads['sales_invoice'] = $payload;

            return true;
        })->andReturn(['r' => ['number' => 'INV-FOC-COMP']]);

        if ($withReceipt) {
            $mock->shouldReceive('saveSalesReceipt')->once()->andReturn(['r' => ['number' => 'RCPT-FOC-COMP']]);
        } else {
            $mock->shouldReceive('saveSalesReceipt')->zeroOrMoreTimes();
        }
    });
}

test('walk in compliment invoice pushes original amounts with compliment expense line', function () {
    $admin = adminUser();
    $customerUser = focCompCustomer();

    GeneralSetting::instance()->update([
        'accurate_compliment_account_no' => '410204_COMP',
        'compliment_requires_auth_code' => false,
        'tax_percentage' => 0,
        'service_charge_percentage' => 0,
    ]);

    $item = focCompInventoryItem();
    $cartKey = 'item_'.$item->id;
    $payloads = [];

    mockFocCompAccurateService($payloads);

    actingAs($admin)
        ->withSession([
            'pos_cart' => [
                $cartKey => [
                    'id' => $cartKey,
                    'name' => $item->name,
                    'price' => (float) $item->price,
                    'quantity' => 2,
                    'preparation_location' => 'bar',
                ],
            ],
        ])
        ->postJson(route('admin.pos.checkout'), [
            'customer_type' => 'walk-in',
            'walk_in_customer_id' => $customerUser->user_id,
            'payment_method' => 'cash',
            'payment_mode' => 'normal',
            'foc_comp_payment_method' => 'Compliment',
            'auto_print_receipt' => false,
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $billing = Billing::query()->latest('id')->first();

    expect($billing->foc_comp_payment_method)->toBe('Compliment')
        ->and((float) $billing->discount_amount)->toBe(50000.0)
        ->and((float) $billing->grand_total)->toBe(0.0);

    foreach (['sales_order', 'sales_invoice'] as $document) {
        $line = $payloads[$document]['detailItem'][0];

        expect($line['discountPercent'])->toBe(0.0)
            ->and($line['unitPrice'])->toBe(25000.0)
            ->and($line['quantity'])->toBe(2);

        $complimentExpense = collect($payloads[$document]['detailExpense'] ?? [])
            ->firstWhere('expenseName', 'Compliment');

        expect($complimentExpense)->not->toBeNull()
            ->and($complimentExpense['accountNo'])->toBe('410204_COMP')
            ->and((float) $complimentExpense['expenseAmount'])->toBe(-50000.0);
    }
});

test('walk in foc invoice pushes original amounts with foc expense line', function () {
    $admin = adminUser();
    $customerUser = focCompCustomer();

    GeneralSetting::instance()->update([
        'accurate_foc_account_no' => '410203_FOC',
        'foc_requires_auth_code' => false,
        'foc_discount_percentage' => 50,
        'tax_percentage' => 0,
        'service_charge_percentage' => 0,
    ]);

    $item = focCompInventoryItem();
    $cartKey = 'item_'.$item->id;
    $payloads = [];

    mockFocCompAccurateService($payloads);

    actingAs($admin)
        ->withSession([
            'pos_cart' => [
                $cartKey => [
                    'id' => $cartKey,
                    'name' => $item->name,
                    'price' => (float) $item->price,
                    'quantity' => 2,
                    'preparation_location' => 'bar',
                ],
            ],
        ])
        ->postJson(route('admin.pos.checkout'), [
            'customer_type' => 'walk-in',
            'walk_in_customer_id' => $customerUser->user_id,
            'payment_method' => 'cash',
            'payment_mode' => 'normal',
            'foc_comp_payment_method' => 'FOC',
            'auto_print_receipt' => false,
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $billing = Billing::query()->latest('id')->first();

    expect($billing->foc_comp_payment_method)->toBe('FOC')
        ->and((float) $billing->discount_amount)->toBe(25000.0)
        ->and((float) $billing->grand_total)->toBe(25000.0);

    foreach (['sales_order', 'sales_invoice'] as $document) {
        $line = $payloads[$document]['detailItem'][0];

        expect($line['discountPercent'])->toBe(0.0)
            ->and($line['unitPrice'])->toBe(25000.0);

        $focExpense = collect($payloads[$document]['detailExpense'] ?? [])
            ->firstWhere('expenseName', 'FOC');

        expect($focExpense)->not->toBeNull()
            ->and($focExpense['accountNo'])->toBe('410203_FOC')
            ->and((float) $focExpense['expenseAmount'])->toBe(-25000.0);
    }
});

test('walk in compliment falls back to discount percent when compliment account is empty', function () {
    $admin = adminUser();
    $customerUser = focCompCustomer();

    GeneralSetting::instance()->update([
        'compliment_requires_auth_code' => false,
        'tax_percentage' => 0,
        'service_charge_percentage' => 0,
    ]);

    $item = focCompInventoryItem();
    $cartKey = 'item_'.$item->id;
    $payloads = [];

    mockFocCompAccurateService($payloads);

    actingAs($admin)
        ->withSession([
            'pos_cart' => [
                $cartKey => [
                    'id' => $cartKey,
                    'name' => $item->name,
                    'price' => (float) $item->price,
                    'quantity' => 2,
                    'preparation_location' => 'bar',
                ],
            ],
        ])
        ->postJson(route('admin.pos.checkout'), [
            'customer_type' => 'walk-in',
            'walk_in_customer_id' => $customerUser->user_id,
            'payment_method' => 'cash',
            'payment_mode' => 'normal',
            'foc_comp_payment_method' => 'Compliment',
            'auto_print_receipt' => false,
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    foreach (['sales_order', 'sales_invoice'] as $document) {
        expect($payloads[$document]['detailItem'][0]['discountPercent'])->toBe(100.0);

        $complimentExpense = collect($payloads[$document]['detailExpense'] ?? [])
            ->firstWhere('expenseName', 'Compliment');

        expect($complimentExpense)->toBeNull();
    }
});

test('booking close billing compliment pushes original amounts with compliment expense line', function () {
    $admin = adminUser();
    $customerUser = focCompCustomer();

    GeneralSetting::instance()->update([
        'accurate_compliment_account_no' => '410204_COMP',
        'compliment_requires_auth_code' => false,
        'tax_percentage' => 0,
        'service_charge_percentage' => 0,
    ]);

    $customerUser->update(['accurate_id' => 120204, 'customer_code' => 'CUST-CLOSE-COMP']);

    $area = Area::create([
        'code' => 'AREA-'.uniqid(),
        'name' => 'Area '.uniqid(),
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $table = Tabel::create([
        'area_id' => $area->id,
        'table_number' => 'TBL-'.uniqid(),
        'qr_code' => 'QR-'.uniqid(),
        'capacity' => 4,
        'minimum_charge' => 0,
        'status' => 'occupied',
        'is_active' => true,
    ]);

    $booking = TableReservation::create([
        'booking_code' => random_int(100000, 999999),
        'table_id' => $table->id,
        'customer_id' => $customerUser->user_id,
        'reservation_date' => now()->toDateString(),
        'reservation_time' => now()->format('H:i:s'),
        'status' => 'checked_in',
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $table->id,
        'customer_id' => $customerUser->user_id,
        'session_code' => 'SESSION-'.uniqid(),
        'checked_in_at' => now(),
        'status' => 'active',
    ]);

    $billing = Billing::create([
        'table_session_id' => $session->id,
        'is_walk_in' => false,
        'is_booking' => true,
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
        'billing_status' => 'draft',
    ]);

    $session->update(['billing_id' => $billing->id]);

    $item = focCompInventoryItem(60000);

    $order = Order::create([
        'table_session_id' => $session->id,
        'customer_user_id' => $customerUser->id,
        'created_by' => $admin->id,
        'order_number' => 'ORD-'.uniqid(),
        'status' => 'pending',
        'items_total' => 120000,
        'discount_amount' => 0,
        'total' => 120000,
        'ordered_at' => now(),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $item->id,
        'item_name' => $item->name,
        'item_code' => $item->code,
        'quantity' => 2,
        'price' => 60000,
        'subtotal' => 120000,
        'discount_amount' => 0,
        'preparation_location' => 'bar',
        'status' => 'served',
    ]);

    $payloads = [];

    mockFocCompAccurateService($payloads);

    actingAs($admin)
        ->postJson(route('admin.bookings.closeBilling', $booking), [
            'payment_mode' => 'normal',
            'payment_method' => 'cash',
            'foc_comp_payment_method' => 'Compliment',
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    foreach (['sales_order', 'sales_invoice'] as $document) {
        $line = $payloads[$document]['detailItem'][0];

        expect($line['discountPercent'])->toBe(0.0)
            ->and($line['unitPrice'])->toBe(60000.0)
            ->and($line['quantity'])->toBe(2);

        $complimentExpense = collect($payloads[$document]['detailExpense'] ?? [])
            ->firstWhere('expenseName', 'Compliment');

        expect($complimentExpense)->not->toBeNull()
            ->and($complimentExpense['accountNo'])->toBe('410204_COMP')
            ->and((float) $complimentExpense['expenseAmount'])->toBe(-120000.0);
    }
});

test('transaction history resync of compliment billing pushes original amounts with expense line', function () {
    $admin = adminUser();
    $customerUser = focCompCustomer();

    GeneralSetting::instance()->update([
        'accurate_compliment_account_no' => '410204_COMP',
        'tax_percentage' => 0,
        'service_charge_percentage' => 0,
    ]);

    $customerUser->update(['accurate_id' => 12345, 'customer_code' => 'CUST-RESYNC-COMP']);

    $item = focCompInventoryItem();

    $order = Order::create([
        'table_session_id' => null,
        'customer_user_id' => $customerUser->id,
        'created_by' => $admin->id,
        'order_number' => 'TH-ORD-'.uniqid(),
        'status' => 'completed',
        'items_total' => 50000,
        'discount_amount' => 5000,
        'total' => 45000,
        'ordered_at' => now(),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $item->id,
        'item_name' => $item->name,
        'item_code' => $item->code,
        'quantity' => 2,
        'price' => 25000,
        'subtotal' => 50000,
        'discount_amount' => 5000,
        'preparation_location' => 'bar',
        'status' => 'completed',
    ]);

    Billing::create([
        'table_session_id' => null,
        'order_id' => $order->id,
        'is_walk_in' => true,
        'is_booking' => false,
        'minimum_charge' => 0,
        'orders_total' => 50000,
        'subtotal' => 50000,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 5000,
        'grand_total' => 45000,
        'paid_amount' => 45000,
        'billing_status' => 'paid',
        'payment_method' => 'Compliment',
        'foc_comp_payment_method' => 'Compliment',
        'accurate_so_number' => null,
        'accurate_inv_number' => null,
    ]);

    $payloads = [];

    mockFocCompAccurateService($payloads, withReceipt: true);

    actingAs($admin)
        ->post(route('admin.transaction-history.reSyncAccurate', $order))
        ->assertRedirect()
        ->assertSessionHas('success', 'Re-sync Accurate berhasil.');

    foreach (['sales_order', 'sales_invoice'] as $document) {
        expect($payloads[$document]['detailItem'][0]['discountPercent'])->toBe(0.0);

        $complimentExpense = collect($payloads[$document]['detailExpense'] ?? [])
            ->firstWhere('expenseName', 'Compliment');

        expect($complimentExpense)->not->toBeNull()
            ->and($complimentExpense['accountNo'])->toBe('410204_COMP')
            ->and((float) $complimentExpense['expenseAmount'])->toBe(-5000.0);
    }
});
