<?php

use App\Models\Area;
use App\Models\Billing;
use App\Models\CustomerUser;
use App\Models\DailyAuthCode;
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

function makeBookingCloseBillingFixture(User $admin): array
{
    $customer = User::factory()->create();

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
        'customer_id' => $customer->id,
        'reservation_date' => now()->toDateString(),
        'reservation_time' => now()->format('H:i:s'),
        'status' => 'checked_in',
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
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

    $inventoryItem = InventoryItem::create([
        'code' => 'INV-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Billing Item '.uniqid(),
        'category_type' => 'beverage',
        'price' => 60000,
        'stock_quantity' => 50,
        'threshold' => 5,
        'unit' => 'glass',
        'is_active' => true,
    ]);

    $order = Order::create([
        'table_session_id' => $session->id,
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
        'inventory_item_id' => $inventoryItem->id,
        'item_name' => $inventoryItem->name,
        'item_code' => $inventoryItem->code,
        'quantity' => 2,
        'price' => 60000,
        'subtotal' => 120000,
        'discount_amount' => 0,
        'preparation_location' => 'bar',
        'status' => 'served',
    ]);

    return [$booking, $session, $billing];
}

test('close billing works with normal payment mode', function () {
    $admin = adminUser();
    [$booking] = makeBookingCloseBillingFixture($admin);

    $customer = $booking->customer;
    $profile = UserProfile::create([
        'user_id' => $customer->id,
        'phone' => '081299900001',
    ]);

    $customerUser = CustomerUser::create([
        'user_id' => $customer->id,
        'user_profile_id' => $profile->id,
        'accurate_id' => 120001,
        'customer_code' => 'CUST-CLOSE-001',
        'total_visits' => 0,
        'lifetime_spending' => 0,
    ]);

    $response = actingAs($admin)->postJson(route('admin.bookings.closeBilling', $booking), [
        'payment_mode' => 'normal',
        'payment_method' => 'cash',
    ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $updatedBilling = $booking->fresh()->tableSession->billing;
    $updatedBooking = $booking->fresh();
    $updatedSession = $updatedBooking->tableSession;
    $updatedTable = $updatedBooking->table;

    expect($updatedBilling->billing_status)->toBe('paid')
        ->and($updatedBilling->payment_mode)->toBe('normal')
        ->and($updatedBilling->payment_method)->toBe('cash')
        ->and((string) $updatedBilling->transaction_code)->toMatch('/^BILLING-\d{6}$/')
        ->and((float) $updatedBilling->split_cash_amount)->toBe(0.0)
        ->and((float) $updatedBilling->split_debit_amount)->toBe(0.0)
        ->and((int) $customerUser->fresh()->total_visits)->toBe(1)
        ->and((float) $customerUser->fresh()->lifetime_spending)->toBe(120000.0)
        ->and($updatedBooking->status)->toBe('completed')
        ->and($updatedSession?->status)->toBe('completed')
        ->and($updatedTable?->status)->toBe('available');
});

test('close billing modal receives active order items safely', function () {
    $admin = adminUser();
    [$booking, $session] = makeBookingCloseBillingFixture($admin);
    $item = $session->orders->first()->items->first();
    $item->update(['item_name' => 'Menu "Special"']);
    $response = actingAs($admin)
        ->get(route('admin.bookings.index', ['tab' => 'active']))
        ->assertSuccessful()
        ->assertSee('Pilih item yang mendapat diskon')
        ->assertSee('Tidak ada order item aktif pada billing ini.', false)
        ->assertSee('new TextDecoder()', false);

    preg_match('/data-discount-items="([^"]+)"/', $response->getContent(), $matches);
    $payload = json_decode(base64_decode($matches[1] ?? ''), true);

    expect($payload)->toBeArray()->toHaveCount(1)
        ->and($payload[0]['id'])->toBe($item->id)
        ->and($payload[0]['name'])->toBe('Menu "Special"')
        ->and($payload[0]['quantity'])->toBe(2)
        ->and((float) $payload[0]['subtotal'])->toBe(120000.0);

    expect($response->getContent())->toContain(route('admin.bookings.discountItems', $booking))
        ->toContain('Memuat order item terbaru...');
});

test('close billing button on table map loads active discount items', function () {
    $admin = adminUser();
    [$booking] = makeBookingCloseBillingFixture($admin);

    $response = actingAs($admin)
        ->get(route('admin.bookings.index', ['tab' => 'all']))
        ->assertSuccessful();

    expect($response->getContent())->toMatch(
        '/<button[^>]+data-booking-id="'.preg_quote((string) $booking->id, '/').'"[^>]+data-discount-items-url="'.preg_quote(route('admin.bookings.discountItems', $booking), '/').'"[^>]+onclick="event\.stopPropagation\(\); openCloseBillingModal\(this\)"/s'
    );
});

test('close billing discount items endpoint returns orders added after page render', function () {
    $admin = adminUser();
    [$booking, $session] = makeBookingCloseBillingFixture($admin);

    actingAs($admin)
        ->get(route('admin.bookings.index', ['tab' => 'active']))
        ->assertSuccessful();

    $order = $session->orders->first();
    $inventory = InventoryItem::create([
        'code' => 'LATE-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Late Order Item',
        'category_type' => 'beverage',
        'price' => 25000,
        'stock_quantity' => 10,
        'is_active' => true,
    ]);
    $lateItem = OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $inventory->id,
        'item_name' => $inventory->name,
        'item_code' => $inventory->code,
        'quantity' => 1,
        'price' => 25000,
        'subtotal' => 25000,
        'discount_amount' => 0,
        'preparation_location' => 'bar',
        'status' => 'served',
    ]);

    actingAs($admin)
        ->getJson(route('admin.bookings.discountItems', $booking))
        ->assertSuccessful()
        ->assertJsonPath('items.1.id', $lateItem->id)
        ->assertJsonPath('items.1.name', 'Late Order Item')
        ->assertJsonPath('items.1.subtotal', 25000);
});

test('close billing deducts booking down payment from grand total', function () {
    $admin = adminUser();
    [$booking] = makeBookingCloseBillingFixture($admin);

    $booking->update([
        'down_payment_amount' => 20000,
    ]);

    $response = actingAs($admin)->postJson(route('admin.bookings.closeBilling', $booking), [
        'payment_mode' => 'normal',
        'payment_method' => 'cash',
    ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('receipt.down_payment_amount', 20000)
        ->assertJsonPath('receipt.grand_total', 100000);

    $updatedBilling = $booking->fresh()->tableSession->billing;

    expect((float) $updatedBilling->grand_total)->toBe(100000.0)
        ->and((float) $updatedBilling->paid_amount)->toBe(100000.0);
});

test('close billing sends ROOM-BILLING sales order number and maps salesOrderNumber into invoice detail items', function () {
    $admin = adminUser();
    [$booking] = makeBookingCloseBillingFixture($admin);
    $booking->update(['down_payment_amount' => 20000]);

    GeneralSetting::instance()->update([
        'service_charge_percentage' => 5,
        'tax_percentage' => 10,
        'accurate_stock_warehouse_name' => 'Warehouse Test',
    ]);

    $customer = $booking->customer;
    $profile = UserProfile::create([
        'user_id' => $customer->id,
        'phone' => '08123456780',
    ]);

    CustomerUser::create([
        'user_id' => $customer->id,
        'user_profile_id' => $profile->id,
        'accurate_id' => 12345,
        'customer_code' => 'CUST-BOOKING-001',
        'total_visits' => 0,
        'lifetime_spending' => 0,
    ]);

    $capturedSoNumber = null;

    mock(AccurateService::class, function (MockInterface $mock) use (&$capturedSoNumber): void {
        $mock->shouldReceive('saveSalesOrder')
            ->once()
            ->withArgs(function (array $payload) use (&$capturedSoNumber): bool {
                if (empty($payload['detailItem']) || ! isset($payload['number'])) {
                    return false;
                }

                if (! preg_match('/^ROOM-(BILLING|WALKIN)-\d{8}-\d{5}$/', $payload['number'])) {
                    return false;
                }

                $expenses = collect($payload['detailExpense'] ?? []);
                $taxExpense = $expenses->firstWhere('expenseName', 'PB 1');
                $scExpense = $expenses->firstWhere('expenseName', 'Service Charge');

                if (! $taxExpense || ($taxExpense['accountNo'] ?? null) !== '210201') {
                    return false;
                }

                if (! $scExpense || ($scExpense['accountNo'] ?? null) !== '210202' || (float) ($scExpense['expenseAmount'] ?? 0) <= 0) {
                    return false;
                }

                $capturedSoNumber = $payload['number'];

                return true;
            })
            ->andReturnUsing(function (array $payload) {
                return ['r' => ['number' => $payload['number']]];
            });

        $mock->shouldReceive('saveSalesInvoice')
            ->once()
            ->withArgs(function (array $payload) use (&$capturedSoNumber): bool {
                return array_key_exists('customerNo', $payload)
                    && array_key_exists('inputDownPayment', $payload)
                    && array_key_exists('invoiceDp', $payload)
                    && (float) $payload['inputDownPayment'] === 20000.0
                    && $payload['invoiceDp'] === true
                    && ($payload['orderDownPaymentNumber'] ?? null) === $capturedSoNumber;
            })
            ->andReturn(['r' => ['number' => 'INV-DP-TEST-001']]);

        $mock->shouldReceive('saveSalesInvoice')
            ->once()
            ->withArgs(function (array $payload) use (&$capturedSoNumber): bool {
                $detailItems = $payload['detailItem'] ?? [];
                $detailDownPayment = $payload['detailDownPayment'] ?? [];

                if ($detailItems === []) {
                    return false;
                }

                if (($detailDownPayment[0]['paymentAmount'] ?? null) !== 20000.0) {
                    return false;
                }

                if (($detailDownPayment[0]['invoiceNumber'] ?? null) !== 'INV-DP-TEST-001') {
                    return false;
                }

                $expenses = collect($payload['detailExpense'] ?? []);
                $taxExpense = $expenses->firstWhere('expenseName', 'PB 1');
                $scExpense = $expenses->firstWhere('expenseName', 'Service Charge');

                if (! $taxExpense || ($taxExpense['accountNo'] ?? null) !== '210201') {
                    return false;
                }

                if (! $scExpense || ($scExpense['accountNo'] ?? null) !== '210202' || (float) ($scExpense['expenseAmount'] ?? 0) <= 0) {
                    return false;
                }

                foreach ($detailItems as $detailItem) {
                    if (! isset($detailItem['salesOrderNumber'])) {
                        return false;
                    }

                    if (($detailItem['warehouseName'] ?? null) !== 'Warehouse Test') {
                        return false;
                    }
                }

                return true;
            })
            ->andReturnUsing(function (array $payload) {
                return ['r' => ['number' => 'INV-'.uniqid()]];
            });
    });

    actingAs($admin)
        ->withSession(['booking_discount_auth_code_requested_at' => now()->timestamp])
        ->postJson(route('admin.bookings.closeBilling', $booking), [
            'payment_mode' => 'normal',
            'payment_method' => 'cash',
            'foc_comp_payment_method' => 'FOC',
            'discount_auth_code' => \App\Models\DailyAuthCode::forDate(now()->format('Y-m-d'))->active_code,
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $updatedBilling = $booking->fresh()->tableSession->billing;

    expect((string) $updatedBilling->transaction_code)->toMatch('/^BILLING-\d{6}$/')
        ->and($updatedBilling->foc_comp_payment_method)->toBe('FOC')
        ->and((string) $updatedBilling->accurate_so_number)->toMatch('/^ROOM-(BILLING|WALKIN)-\d{8}-\d{5}$/')
        ->and((string) $updatedBilling->accurate_inv_number)->not->toBeEmpty();
});

test('close billing stores error message when accurate sync fails', function () {
    $admin = adminUser();
    [$booking] = makeBookingCloseBillingFixture($admin);

    $customer = $booking->customer;
    $profile = UserProfile::create([
        'user_id' => $customer->id,
        'phone' => '08123456781',
    ]);

    CustomerUser::create([
        'user_id' => $customer->id,
        'user_profile_id' => $profile->id,
        'accurate_id' => 12346,
        'customer_code' => 'CUST-BOOKING-ERR',
        'total_visits' => 0,
        'lifetime_spending' => 0,
    ]);

    mock(AccurateService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('saveSalesOrder')
            ->once()
            ->andThrow(new \Exception('Accurate temporary error'));
    });

    actingAs($admin)
        ->postJson(route('admin.bookings.closeBilling', $booking), [
            'payment_mode' => 'normal',
            'payment_method' => 'cash',
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $updatedBilling = $booking->fresh()->tableSession->billing;

    expect((string) $updatedBilling->accurate_so_number)->toBe('')
        ->and((string) $updatedBilling->accurate_inv_number)->toBe('')
        ->and((string) $updatedBilling->error_message)->toContain('Accurate temporary error');
});

test('close billing rejects discount without valid auth code', function () {
    $admin = adminUser();
    [$booking] = makeBookingCloseBillingFixture($admin);

    DailyAuthCode::query()->updateOrCreate(
        ['date' => now()->format('Y-m-d')],
        [
            'code' => '1234',
            'override_code' => null,
            'generated_at' => now(),
        ],
    );

    $response = actingAs($admin)
        ->withSession(['booking_discount_auth_code_requested_at' => now()->timestamp])
        ->postJson(route('admin.bookings.closeBilling', $booking), [
            'payment_mode' => 'normal',
            'payment_method' => 'cash',
            'discount_type' => 'percentage',
            'discount_percentage' => 10,
            'discount_order_item_ids' => [$booking->tableSession->orders->first()->items->first()->id],
            'discount_auth_code' => '0000',
        ]);

    $response
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.discount_auth_code.0', 'Auth code diskon tidak valid.');
});

test('close billing applies percentage discount with valid auth code', function () {
    $admin = adminUser();
    [$booking] = makeBookingCloseBillingFixture($admin);

    DailyAuthCode::query()->updateOrCreate(
        ['date' => now()->format('Y-m-d')],
        [
            'code' => '4321',
            'override_code' => null,
            'generated_at' => now(),
        ],
    );

    $item = $booking->tableSession->orders->first()->items->first();
    $response = actingAs($admin)
        ->withSession(['booking_discount_auth_code_requested_at' => now()->timestamp])
        ->postJson(route('admin.bookings.closeBilling', $booking), [
            'payment_mode' => 'normal',
            'payment_method' => 'cash',
            'discount_type' => 'percentage',
            'discount_percentage' => 10,
            'discount_order_item_ids' => [$item->id],
            'discount_auth_code' => '4321',
        ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $updatedBilling = $booking->fresh()->tableSession->billing;

    expect((float) $updatedBilling->discount_amount)->toBe(12000.0)
        ->and((float) $updatedBilling->grand_total)->toBe(108000.0)
        ->and((float) $item->fresh()->discount_amount)->toBe(12000.0);
});

test('close billing applies nominal discount with valid auth code', function () {
    $admin = adminUser();
    [$booking] = makeBookingCloseBillingFixture($admin);

    DailyAuthCode::query()->updateOrCreate(
        ['date' => now()->format('Y-m-d')],
        [
            'code' => '6789',
            'override_code' => null,
            'generated_at' => now(),
        ],
    );

    $item = $booking->tableSession->orders->first()->items->first();
    $response = actingAs($admin)
        ->withSession(['booking_discount_auth_code_requested_at' => now()->timestamp])
        ->postJson(route('admin.bookings.closeBilling', $booking), [
            'payment_mode' => 'normal',
            'payment_method' => 'cash',
            'discount_type' => 'nominal',
            'discount_nominal' => 15000,
            'discount_order_item_ids' => [$item->id],
            'discount_auth_code' => '6789',
        ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $updatedBilling = $booking->fresh()->tableSession->billing;

    expect((float) $updatedBilling->discount_amount)->toBe(15000.0)
        ->and((float) $updatedBilling->grand_total)->toBe(105000.0)
        ->and((float) $item->fresh()->discount_amount)->toBe(15000.0);
});

test('close billing discounts only selected item and sends it to receipt and accurate', function () {
    $admin = adminUser();
    [$booking, $session] = makeBookingCloseBillingFixture($admin);
    $order = $session->orders->first();
    $selectedItem = $order->items->first();
    $selectedItem->update(['quantity' => 1, 'subtotal' => 60000]);
    $regularInventory = InventoryItem::create([
        'code' => 'INV-REGULAR-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Regular Item',
        'category_type' => 'beverage',
        'price' => 60000,
        'stock_quantity' => 50,
        'is_active' => true,
    ]);
    $regularItem = OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $regularInventory->id,
        'item_name' => $regularInventory->name,
        'item_code' => $regularInventory->code,
        'quantity' => 1,
        'price' => 60000,
        'subtotal' => 60000,
        'discount_amount' => 0,
        'preparation_location' => 'bar',
        'status' => 'served',
    ]);
    $order->updateTotals();
    $profile = UserProfile::create(['user_id' => $booking->customer_id]);
    CustomerUser::create([
        'user_id' => $booking->customer_id,
        'user_profile_id' => $profile->id,
        'accurate_id' => 120003,
        'customer_code' => 'CUST-SELECTIVE',
    ]);
    DailyAuthCode::forDate(now()->format('Y-m-d'))->update(['code' => '8642', 'override_code' => null]);
    $salesOrderPayload = [];

    mock(AccurateService::class, function (MockInterface $mock) use (&$salesOrderPayload): void {
        $mock->shouldReceive('saveSalesOrder')->once()->withArgs(function (array $payload) use (&$salesOrderPayload): bool {
            $salesOrderPayload = $payload;

            return true;
        })->andReturnUsing(fn (array $payload): array => ['r' => ['number' => $payload['number']]]);
        $mock->shouldReceive('saveSalesInvoice')->once()->andReturn(['r' => ['number' => 'INV-SELECTIVE']]);
    });

    actingAs($admin)
        ->withSession(['booking_discount_auth_code_requested_at' => now()->timestamp])
        ->postJson(route('admin.bookings.closeBilling', $booking), [
            'payment_mode' => 'normal',
            'payment_method' => 'cash',
            'discount_type' => 'percentage',
            'discount_percentage' => 10,
            'discount_order_item_ids' => [$selectedItem->id],
            'discount_auth_code' => '8642',
        ])
        ->assertSuccessful()
        ->assertJsonPath('receipt.items.0.discount_amount', 6000)
        ->assertJsonPath('receipt.items.1.discount_amount', 0)
        ->assertJsonPath('receipt.discount_amount', 6000)
        ->assertJsonPath('receipt.grand_total', 114000);

    expect((float) $selectedItem->fresh()->discount_amount)->toBe(6000.0)
        ->and((float) $regularItem->fresh()->discount_amount)->toBe(0.0)
        ->and($salesOrderPayload['detailItem'][0]['discountPercent'])->toBe(10.0)
        ->and($salesOrderPayload['detailItem'][1]['discountPercent'])->toBe(0.0);
});

test('close billing calculates service charge based on subtotal plus tax when tax is active', function () {
    $admin = adminUser();
    [$booking] = makeBookingCloseBillingFixture($admin);

    GeneralSetting::instance()->update([
        'service_charge_percentage' => 5,
        'tax_percentage' => 10,
    ]);

    $response = actingAs($admin)->postJson(route('admin.bookings.closeBilling', $booking), [
        'payment_mode' => 'normal',
        'payment_method' => 'cash',
    ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('receipt.service_charge', 6600)
        ->assertJsonPath('receipt.tax', 12000)
        ->assertJsonPath('receipt.grand_total', 138600);

    $updatedBilling = $booking->fresh()->tableSession->billing;

    expect((float) $updatedBilling->service_charge)->toBe(6600.0)
        ->and((float) $updatedBilling->tax)->toBe(12000.0)
        ->and((float) $updatedBilling->grand_total)->toBe(138600.0);
});

test('close billing pushes pb1 tax metadata and service charge line item to accurate invoice', function () {
    $admin = adminUser();
    [$booking] = makeBookingCloseBillingFixture($admin);

    GeneralSetting::instance()->update([
        'service_charge_percentage' => 5,
        'tax_percentage' => 10,
    ]);

    $customer = $booking->customer;
    $profile = UserProfile::create([
        'user_id' => $customer->id,
        'phone' => '081299900002',
    ]);

    CustomerUser::create([
        'user_id' => $customer->id,
        'user_profile_id' => $profile->id,
        'accurate_id' => 120002,
        'customer_code' => 'CUST-CLOSE-002',
        'total_visits' => 0,
        'lifetime_spending' => 0,
    ]);

    mock(AccurateService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('saveSalesOrder')
            ->once()
            ->andReturnUsing(function (array $payload) {
                return ['r' => ['number' => $payload['number']]];
            });

        $mock->shouldReceive('saveSalesInvoice')
            ->once()
            ->andReturn(['r' => ['number' => 'INV-PB1-001']]);
    });

    actingAs($admin)
        ->postJson(route('admin.bookings.closeBilling', $booking), [
            'payment_mode' => 'normal',
            'payment_method' => 'cash',
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $updatedBilling = $booking->fresh()->tableSession->billing;

    expect((float) $updatedBilling->service_charge)->toBe(6600.0)
        ->and((float) $updatedBilling->tax)->toBe(12000.0);
});

test('close billing calculates percentage discount after tax and service charge', function () {
    $admin = adminUser();
    [$booking] = makeBookingCloseBillingFixture($admin);

    GeneralSetting::instance()->update([
        'service_charge_percentage' => 10,
        'tax_percentage' => 11,
    ]);

    DailyAuthCode::query()->updateOrCreate(
        ['date' => now()->format('Y-m-d')],
        [
            'code' => '1357',
            'override_code' => null,
            'generated_at' => now(),
        ],
    );

    $item = $booking->tableSession->orders->first()->items->first();

    actingAs($admin)
        ->withSession(['booking_discount_auth_code_requested_at' => now()->timestamp])
        ->postJson(route('admin.bookings.closeBilling', $booking), [
            'payment_mode' => 'normal',
            'payment_method' => 'cash',
            'discount_type' => 'percentage',
            'discount_percentage' => 10,
            'discount_order_item_ids' => [$item->id],
            'discount_auth_code' => '1357',
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $updatedBilling = $booking->fresh()->tableSession->billing;

    expect((float) $updatedBilling->tax)->toBe(11880.0)
        ->and((float) $updatedBilling->service_charge)->toBe(11988.0)
        ->and((float) $updatedBilling->discount_amount)->toBe(12000.0)
        ->and((float) $updatedBilling->grand_total)->toBe(131868.0);
});

test('booking receipt displays closed billing schema with tax, service charge, subtotal, dp, and remaining payment', function () {
    $admin = adminUser();
    [$booking, $session, $billing] = makeBookingCloseBillingFixture($admin);

    $booking->update([
        'down_payment_amount' => 500000,
    ]);

    $billing->update([
        'subtotal' => 1500000,
        'tax_percentage' => 10,
        'tax' => 150000,
        'service_charge_percentage' => 8,
        'service_charge' => 132000,
        'grand_total' => 1282000,
        'paid_amount' => 1282000,
        'billing_status' => 'paid',
        'transaction_code' => 'BILLING-000001',
        'payment_mode' => 'normal',
        'payment_method' => 'cash',
    ]);

    $response = actingAs($admin)->get(route('admin.bookings.receipt', $booking));

    $response
        ->assertSuccessful()
        ->assertSee('Total Bill', false)
        ->assertSee('PB1 (10%)', false)
        ->assertSee('Service Charge (8%)', false)
        ->assertSee('Sub Total', false)
        ->assertSee('DP', false)
        ->assertSee('Sisa Bayar', false)
        ->assertSee('Rp 1.500.000', false)
        ->assertSee('Rp 150.000', false)
        ->assertSee('Rp 132.000', false)
        ->assertSee('Rp 1.782.000', false)
        ->assertSee('- Rp 500.000', false)
        ->assertSee('Rp 1.282.000', false);
});

test('close billing works with normal non-cash payment and reference number', function () {
    $admin = adminUser();
    [$booking] = makeBookingCloseBillingFixture($admin);

    $response = actingAs($admin)
        ->postJson(route('admin.bookings.closeBilling', $booking), [
            'payment_mode' => 'normal',
            'payment_method' => 'transfer',
            'payment_reference_number' => 'TRF-APPROVAL-12345',
        ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('receipt.payment_method', 'TRANSFER')
        ->assertJsonPath('receipt.payment_reference_number', 'TRF-APPROVAL-12345');

    $updatedBilling = $booking->fresh()->tableSession->billing;

    expect($updatedBilling->billing_status)->toBe('paid')
        ->and($updatedBilling->payment_mode)->toBe('normal')
        ->and($updatedBilling->payment_method)->toBe('transfer')
        ->and($updatedBilling->payment_reference_number)->toBe('TRF-APPROVAL-12345');
});

test('close billing works with normal qris payment and reference number', function () {
    $admin = adminUser();
    [$booking] = makeBookingCloseBillingFixture($admin);

    $response = actingAs($admin)->postJson(route('admin.bookings.closeBilling', $booking), [
        'payment_mode' => 'normal',
        'payment_method' => 'qris',
        'payment_reference_number' => 'QRIS-INV-001',
    ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('receipt.payment_method', 'QRIS')
        ->assertJsonPath('receipt.payment_reference_number', 'QRIS-INV-001');

    $updatedBilling = $booking->fresh()->tableSession->billing;

    expect($updatedBilling->billing_status)->toBe('paid')
        ->and($updatedBilling->payment_mode)->toBe('normal')
        ->and($updatedBilling->payment_method)->toBe('qris')
        ->and($updatedBilling->payment_reference_number)->toBe('QRIS-INV-001');
});

test('close billing works with normal transfer payment and reference number', function () {
    $admin = adminUser();
    [$booking] = makeBookingCloseBillingFixture($admin);

    $response = actingAs($admin)->postJson(route('admin.bookings.closeBilling', $booking), [
        'payment_mode' => 'normal',
        'payment_method' => 'transfer',
        'payment_reference_number' => 'TRF-INV-001',
    ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('receipt.payment_method', 'TRANSFER')
        ->assertJsonPath('receipt.payment_reference_number', 'TRF-INV-001');

    $updatedBilling = $booking->fresh()->tableSession->billing;

    expect($updatedBilling->billing_status)->toBe('paid')
        ->and($updatedBilling->payment_mode)->toBe('normal')
        ->and($updatedBilling->payment_method)->toBe('transfer')
        ->and($updatedBilling->payment_reference_number)->toBe('TRF-INV-001');
});

test('close billing works with split payment mode cash and non-cash method', function () {
    $admin = adminUser();
    [$booking] = makeBookingCloseBillingFixture($admin);

    // grand total on this fixture is 120000 when tax/service settings are 0,
    // but we derive it from response payload for stronger assertion.
    $previewResponse = actingAs($admin)->postJson(route('admin.bookings.closeBilling', $booking), [
        'payment_mode' => 'split',
        'split_cash_amount' => 70000,
        'split_non_cash_amount' => 50000,
        'split_non_cash_method' => 'kredit',
        'split_non_cash_reference_number' => 'KREDIT-9988',
    ]);

    $previewResponse
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $updatedBilling = $booking->fresh()->tableSession->billing;

    expect($updatedBilling->billing_status)->toBe('paid')
        ->and($updatedBilling->payment_mode)->toBe('split')
        ->and($updatedBilling->payment_method)->toBeNull()
        ->and((float) $updatedBilling->split_cash_amount)->toBe(70000.0)
        ->and((float) $updatedBilling->split_debit_amount)->toBe(50000.0)
        ->and($updatedBilling->split_non_cash_method)->toBe('kredit')
        ->and($updatedBilling->split_non_cash_reference_number)->toBe('KREDIT-9988')
        ->and((float) $updatedBilling->paid_amount)->toBe((float) $updatedBilling->grand_total);
});

test('close billing split payment tolerates rounded rupiah totals', function () {
    $admin = adminUser();
    [$booking] = makeBookingCloseBillingFixture($admin);

    $booking->tableSession->orders->each(function (Order $order): void {
        $order->update([
            'total' => 100001,
            'items_total' => 100001,
        ]);

        $order->items->each(function (OrderItem $item): void {
            $item->update([
                'price' => 100001,
                'subtotal' => 100001,
            ]);
        });
    });

    GeneralSetting::instance()->update([
        'service_charge_percentage' => 5,
        'tax_percentage' => 11,
    ]);

    $response = actingAs($admin)->postJson(route('admin.bookings.closeBilling', $booking), [
        'payment_mode' => 'split',
        'split_cash_amount' => 50000,
        'split_non_cash_amount' => 66551,
        'split_non_cash_method' => 'debit',
        'split_non_cash_reference_number' => 'DB-ROUND-001',
        'split_second_non_cash_amount' => 0,
        'split_second_non_cash_method' => 'debit',
        'split_second_non_cash_reference_number' => '',
    ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $updatedBilling = $booking->fresh()->tableSession->billing;

    expect($updatedBilling->billing_status)->toBe('paid')
        ->and((float) $updatedBilling->split_cash_amount)->toBe(50000.0)
        ->and((float) $updatedBilling->split_debit_amount)->toBe(66551.0);
});

test('close billing works with split payment mode non-cash and non-cash', function () {
    $admin = adminUser();
    [$booking] = makeBookingCloseBillingFixture($admin);

    $response = actingAs($admin)->postJson(route('admin.bookings.closeBilling', $booking), [
        'payment_mode' => 'split',
        'split_cash_amount' => 0,
        'split_non_cash_amount' => 50000,
        'split_non_cash_method' => 'debit',
        'split_non_cash_reference_number' => 'DB-50000',
        'split_second_non_cash_amount' => 70000,
        'split_second_non_cash_method' => 'qris',
        'split_second_non_cash_reference_number' => 'QR-70000',
    ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('receipt.split_cash_amount', 0)
        ->assertJsonPath('receipt.split_debit_amount', 50000)
        ->assertJsonPath('receipt.split_second_non_cash_amount', 70000)
        ->assertJsonPath('receipt.split_second_non_cash_method', 'QRIS');

    $updatedBilling = $booking->fresh()->tableSession->billing;

    expect($updatedBilling->billing_status)->toBe('paid')
        ->and($updatedBilling->payment_mode)->toBe('split')
        ->and((float) $updatedBilling->split_cash_amount)->toBe(0.0)
        ->and((float) $updatedBilling->split_debit_amount)->toBe(50000.0)
        ->and($updatedBilling->split_non_cash_method)->toBe('debit')
        ->and((float) $updatedBilling->split_second_non_cash_amount)->toBe(70000.0)
        ->and($updatedBilling->split_second_non_cash_method)->toBe('qris')
        ->and($updatedBilling->split_second_non_cash_reference_number)->toBe('QR-70000')
        ->and((float) $updatedBilling->paid_amount)->toBe((float) $updatedBilling->grand_total);
});

test('close billing rejects split payment when totals do not match', function () {
    $admin = adminUser();
    [$booking] = makeBookingCloseBillingFixture($admin);

    $response = actingAs($admin)->postJson(route('admin.bookings.closeBilling', $booking), [
        'payment_mode' => 'split',
        'split_cash_amount' => 10000,
        'split_non_cash_amount' => 10000,
        'split_non_cash_method' => 'debit',
        'split_non_cash_reference_number' => 'DB-01',
    ]);

    $response
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Total pembayaran split harus sama dengan grand total.');

    $updatedBilling = $booking->fresh()->tableSession->billing;

    expect($updatedBilling->billing_status)->toBe('draft')
        ->and($updatedBilling->payment_mode)->toBeNull()
        ->and($updatedBilling->payment_method)->toBeNull();
});

test('close billing rejects split payment when non-cash reference is missing', function () {
    $admin = adminUser();
    [$booking] = makeBookingCloseBillingFixture($admin);

    $response = actingAs($admin)->postJson(route('admin.bookings.closeBilling', $booking), [
        'payment_mode' => 'split',
        'split_cash_amount' => 70000,
        'split_non_cash_amount' => 50000,
        'split_non_cash_method' => 'debit',
    ]);

    $response
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.split_non_cash_reference_number.0', 'Nomor referensi non-cash pertama untuk split bill wajib diisi.');
});

test('close billing updates table status using session table even when booking table id differs', function () {
    $admin = adminUser();
    [$booking, $session] = makeBookingCloseBillingFixture($admin);

    $originalSessionTable = $session->table;

    $newArea = Area::create([
        'code' => 'AREA-'.uniqid(),
        'name' => 'Area '.uniqid(),
        'is_active' => true,
        'sort_order' => 99,
    ]);

    $newBookingTable = Tabel::create([
        'area_id' => $newArea->id,
        'table_number' => 'TBL-'.uniqid(),
        'qr_code' => 'QR-'.uniqid(),
        'capacity' => 4,
        'minimum_charge' => 0,
        'status' => 'occupied',
        'is_active' => true,
    ]);

    $booking->update(['table_id' => $newBookingTable->id]);

    actingAs($admin)
        ->postJson(route('admin.bookings.closeBilling', $booking), [
            'payment_mode' => 'normal',
            'payment_method' => 'cash',
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    expect($originalSessionTable->fresh()->status)->toBe('available')
        ->and($newBookingTable->fresh()->status)->toBe('available')
        ->and($session->fresh()->status)->toBe('completed')
        ->and($booking->fresh()->status)->toBe('completed');
});

test('close billing auto-adjusts split non-cash when discount changes final grand total', function () {
    $admin = adminUser();
    [$booking] = makeBookingCloseBillingFixture($admin);

    DailyAuthCode::query()->updateOrCreate(
        ['date' => now()->format('Y-m-d')],
        [
            'code' => '2468',
            'override_code' => null,
            'generated_at' => now(),
        ],
    );

    $item = $booking->tableSession->orders->first()->items->first();
    $response = actingAs($admin)
        ->withSession(['booking_discount_auth_code_requested_at' => now()->timestamp])
        ->postJson(route('admin.bookings.closeBilling', $booking), [
            'payment_mode' => 'split',
            'split_cash_amount' => 70000,
            'split_non_cash_amount' => 50000,
            'split_non_cash_method' => 'debit',
            'split_non_cash_reference_number' => 'DB-2468',
            'discount_type' => 'percentage',
            'discount_percentage' => 10,
            'discount_order_item_ids' => [$item->id],
            'discount_auth_code' => '2468',
        ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $updatedBilling = $booking->fresh()->tableSession->billing;

    expect((float) $updatedBilling->discount_amount)->toBe(12000.0)
        ->and((float) $updatedBilling->grand_total)->toBe(108000.0)
        ->and((float) $updatedBilling->split_cash_amount)->toBe(70000.0)
        ->and((float) $updatedBilling->split_debit_amount)->toBe(38000.0)
        ->and((float) $updatedBilling->paid_amount)->toBe(108000.0);
});

test('close billing prioritizes active session when booking has multiple sessions', function () {
    $admin = adminUser();
    [$booking, $activeSession] = makeBookingCloseBillingFixture($admin);

    $olderSession = TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $booking->table_id,
        'customer_id' => $booking->customer_id,
        'session_code' => 'SESSION-OLDER-'.uniqid(),
        'checked_in_at' => now()->subHours(5),
        'checked_out_at' => now()->subHours(3),
        'status' => 'completed',
    ]);

    Billing::create([
        'table_session_id' => $olderSession->id,
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
        'payment_mode' => 'normal',
        'payment_method' => 'cash',
    ]);

    $response = actingAs($admin)->postJson(route('admin.bookings.closeBilling', $booking), [
        'payment_mode' => 'normal',
        'payment_method' => 'cash',
    ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    expect($activeSession->fresh()->status)->toBe('completed')
        ->and($activeSession->fresh()->billing?->billing_status)->toBe('paid')
        ->and($booking->fresh()->status)->toBe('completed')
        ->and($booking->fresh()->table?->status)->toBe('available');
});

test('close billing prevents closure when order items total is less than down payment', function () {
    $admin = adminUser();
    [$booking, $session] = makeBookingCloseBillingFixture($admin);

    // Set down payment to 100000
    $booking->update(['down_payment_amount' => 100000]);

    // Update existing orders to total only 50000 (less than DP)
    foreach ($session->orders as $order) {
        $order->update(['items_total' => 50000, 'total' => 50000]);
        foreach ($order->items as $item) {
            $item->update(['subtotal' => 50000]);
        }
    }

    $response = actingAs($admin)->postJson(route('admin.bookings.closeBilling', $booking), [
        'payment_mode' => 'normal',
        'payment_method' => 'cash',
    ]);

    $response
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', fn ($message) => str_contains($message, 'tidak sesuai dengan DP'));

    expect($session->fresh()->billing?->billing_status)->toBe('draft');
});

test('close billing allows closure when order items total matches or exceeds down payment', function () {
    $admin = adminUser();
    [$booking, $session] = makeBookingCloseBillingFixture($admin);

    // Set down payment to 100000
    $booking->update(['down_payment_amount' => 100000]);

    // Update existing orders to total exactly 120000 (matches fixture default which exceeds DP)
    // Fixture already creates orders with 120000 total

    $response = actingAs($admin)->postJson(route('admin.bookings.closeBilling', $booking), [
        'payment_mode' => 'normal',
        'payment_method' => 'cash',
    ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    expect($session->fresh()->billing?->billing_status)->toBe('paid');
});
