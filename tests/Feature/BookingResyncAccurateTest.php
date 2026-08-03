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

function makeHistoryBookingFixture(array $billingOverrides = []): array
{
    $admin = adminUser();
    $customer = User::factory()->create();
    $profile = UserProfile::create([
        'user_id' => $customer->id,
        'phone' => '0812'.random_int(1000000, 9999999),
    ]);

    CustomerUser::create([
        'user_id' => $customer->id,
        'user_profile_id' => $profile->id,
        'accurate_id' => random_int(100000, 999999),
        'customer_code' => 'CUST-'.uniqid(),
        'total_visits' => 0,
        'lifetime_spending' => 0,
    ]);

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
        'status' => 'available',
        'is_active' => true,
    ]);

    $booking = TableReservation::create([
        'booking_code' => random_int(100000, 999999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->toDateString(),
        'reservation_time' => now()->format('H:i:s'),
        'status' => 'completed',
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'session_code' => 'SESSION-'.uniqid(),
        'checked_in_at' => now()->subHours(2),
        'checked_out_at' => now()->subHour(),
        'status' => 'completed',
    ]);

    $billing = Billing::create(array_merge([
        'table_session_id' => $session->id,
        'is_walk_in' => false,
        'is_booking' => true,
        'minimum_charge' => 0,
        'orders_total' => 60000,
        'subtotal' => 60000,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => 60000,
        'paid_amount' => 60000,
        'billing_status' => 'paid',
        'transaction_code' => 'BILLING-'.random_int(100000, 999999),
    ], $billingOverrides));

    $session->update(['billing_id' => $billing->id]);

    $inventoryItem = InventoryItem::create([
        'code' => 'INV-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Item '.uniqid(),
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
        'status' => 'served',
        'items_total' => 60000,
        'discount_amount' => 0,
        'total' => 60000,
        'ordered_at' => now()->subHours(2),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $inventoryItem->id,
        'item_name' => $inventoryItem->name,
        'item_code' => $inventoryItem->code,
        'quantity' => 1,
        'price' => 60000,
        'subtotal' => 60000,
        'discount_amount' => 0,
        'preparation_location' => 'bar',
        'status' => 'served',
        'served_at' => now()->subHours(2),
    ]);

    return [$admin, $booking, $billing];
}

test('history tab shows re-sync accurate action only for billing with missing accurate numbers', function () {
    [$admin, $bookingMissing] = makeHistoryBookingFixture([
        'accurate_so_number' => null,
        'accurate_inv_number' => null,
        'error_message' => 'Accurate sedang timeout.',
    ]);

    [, $bookingSynced] = makeHistoryBookingFixture([
        'accurate_so_number' => 'ROOM-BILLING-20260325-12345',
        'accurate_inv_number' => 'ROOM-BILLING-20260325-12345',
    ]);

    actingAs($admin)
        ->get(route('admin.bookings.index', ['tab' => 'history']))
        ->assertOk()
        ->assertSee('Error Message')
        ->assertSee('Lihat Error')
        ->assertSee('openHistoryErrorModal(this)', false)
        ->assertSee(route('admin.bookings.reSyncAccurate', $bookingMissing), false)
        ->assertDontSee(route('admin.bookings.reSyncAccurate', $bookingSynced), false);
});

test('history re-sync accurate creates sales order and invoice when accurate numbers are missing', function () {
    [$admin, $booking, $billing] = makeHistoryBookingFixture([
        'accurate_so_number' => null,
        'accurate_inv_number' => null,
        'tax_percentage' => 10,
        'tax' => 12000,
        'service_charge_percentage' => 5,
        'service_charge' => 6600,
    ]);

    mock(AccurateService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('saveSalesOrder')
            ->once()
            ->withArgs(function (array $payload): bool {
                if (! isset($payload['number']) || empty($payload['detailItem'])) {
                    return false;
                }

                $expenses = collect($payload['detailExpense'] ?? []);
                $taxExpense = $expenses->firstWhere('expenseName', 'PB 1');
                $scExpense = $expenses->firstWhere('expenseName', 'Service Charge');

                if (! $taxExpense || ($taxExpense['accountNo'] ?? null) !== '210201') {
                    return false;
                }

                if (! $scExpense || ($scExpense['accountNo'] ?? null) !== '210202' || (float) ($scExpense['expenseAmount'] ?? 0) !== 6600.0) {
                    return false;
                }

                return preg_match('/^ROOM-BILLING-\d{8}-\d{5}$/', $payload['number']) === 1;
            })
            ->andReturnUsing(function (array $payload) {
                return ['r' => ['number' => $payload['number']]];
            });

        $mock->shouldReceive('saveSalesInvoice')
            ->once()
            ->withArgs(function (array $payload): bool {
                if (! isset($payload['number']) || empty($payload['detailItem'])) {
                    return false;
                }

                $expenses = collect($payload['detailExpense'] ?? []);
                $taxExpense = $expenses->firstWhere('expenseName', 'PB 1');
                $scExpense = $expenses->firstWhere('expenseName', 'Service Charge');

                if (! $taxExpense || ($taxExpense['accountNo'] ?? null) !== '210201') {
                    return false;
                }

                if (! $scExpense || ($scExpense['accountNo'] ?? null) !== '210202' || (float) ($scExpense['expenseAmount'] ?? 0) !== 6600.0) {
                    return false;
                }

                return true;
            })
            ->andReturnUsing(function (array $payload) {
                return ['r' => ['number' => $payload['number']]];
            });
    });

    actingAs($admin)
        ->from(route('admin.bookings.index', ['tab' => 'history']))
        ->post(route('admin.bookings.reSyncAccurate', $booking))
        ->assertRedirect(route('admin.bookings.index', ['tab' => 'history']));

    $billing->refresh();

    expect((string) $billing->accurate_so_number)->toMatch('/^ROOM-BILLING-\d{8}-\d{5}$/')
        ->and((string) $billing->accurate_inv_number)->toMatch('/^ROOM-BILLING-\d{8}-\d{5}$/');
});

test('history re-sync accurate clears existing error message after successful sync', function () {
    [$admin, $booking, $billing] = makeHistoryBookingFixture([
        'accurate_so_number' => null,
        'accurate_inv_number' => null,
        'error_message' => 'Sync sebelumnya gagal.',
    ]);

    mock(AccurateService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('saveSalesOrder')
            ->once()
            ->andReturnUsing(function (array $payload) {
                return ['r' => ['number' => $payload['number']]];
            });

        $mock->shouldReceive('saveSalesInvoice')
            ->once()
            ->andReturnUsing(function (array $payload) {
                return ['r' => ['number' => $payload['number']]];
            });
    });

    actingAs($admin)
        ->from(route('admin.bookings.index', ['tab' => 'history']))
        ->post(route('admin.bookings.reSyncAccurate', $booking))
        ->assertRedirect(route('admin.bookings.index', ['tab' => 'history']));

    $billing->refresh();

    expect((string) $billing->accurate_so_number)->toMatch('/^ROOM-BILLING-\d{8}-\d{5}$/')
        ->and((string) $billing->accurate_inv_number)->toMatch('/^ROOM-BILLING-\d{8}-\d{5}$/')
        ->and($billing->error_message)->toBeNull();
});

test('history re-sync accurate uses warehouse name from general settings', function () {
    GeneralSetting::instance()->update(['accurate_stock_warehouse_name' => 'GD. BAR UTAMA']);

    [$admin, $booking, $billing] = makeHistoryBookingFixture([
        'accurate_so_number' => null,
        'accurate_inv_number' => null,
    ]);

    mock(AccurateService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('saveSalesOrder')
            ->once()
            ->withArgs(function (array $payload): bool {
                return ($payload['detailItem'][0]['warehouseName'] ?? null) === 'GD. BAR UTAMA';
            })
            ->andReturn(['r' => ['number' => 'SO-TEST-123']]);

        $mock->shouldReceive('saveSalesInvoice')
            ->once()
            ->withArgs(function (array $payload): bool {
                return ($payload['detailItem'][0]['warehouseName'] ?? null) === 'GD. BAR UTAMA';
            })
            ->andReturn(['r' => ['number' => 'INV-TEST-123']]);
    });

    actingAs($admin)
        ->from(route('admin.bookings.index', ['tab' => 'history']))
        ->post(route('admin.bookings.reSyncAccurate', $booking))
        ->assertRedirect(route('admin.bookings.index', ['tab' => 'history']));

    $billing->refresh();

    expect((string) $billing->accurate_so_number)->toMatch('/^ROOM-BILLING-\d{8}-\d{5}$/')
        ->and((string) $billing->accurate_inv_number)->toBe('INV-TEST-123');
});
