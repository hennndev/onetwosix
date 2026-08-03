<?php

use App\Models\Area;
use App\Models\Billing;
use App\Models\InternalUser;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Tabel;
use App\Models\TableReservation;
use App\Models\TableSession;
use App\Models\User;
use App\Models\UserProfile;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

afterEach(function (): void {
    Carbon::setTestNow();
});

function waiterPerformanceWaiter(): User
{
    $waiter = User::factory()->create(['name' => 'Waiter History']);
    $profile = UserProfile::create([
        'user_id' => $waiter->id,
    ]);

    Role::firstOrCreate(['name' => 'Waiter/Server', 'guard_name' => 'web']);
    $waiter->assignRole('Waiter/Server');

    $area = Area::create([
        'code' => 'WPH-AREA-'.uniqid(),
        'name' => 'Waiter Perf Area '.uniqid(),
        'is_active' => true,
    ]);

    InternalUser::create([
        'user_id' => $waiter->id,
        'user_profile_id' => $profile->id,
        'is_active' => true,
        'area_id' => $area->id,
        'accurate_id' => random_int(1000, 9999),
    ]);

    return $waiter;
}

function createWaiterHistoryEntry(
    User $waiter,
    User $admin,
    string $customerName,
    Carbon $checkedInAt,
    string $itemName,
    int $amount
): void {
    $customer = User::factory()->create(['name' => $customerName]);
    $tableArea = Area::create([
        'code' => 'WPH-PAGE-AREA-'.uniqid(),
        'name' => 'Waiter Perf Page Area '.uniqid(),
        'is_active' => true,
    ]);

    $table = Tabel::create([
        'area_id' => $tableArea->id,
        'table_number' => 'WPH-PAGE-TBL-'.uniqid(),
        'qr_code' => 'WPH-PAGE-QR-'.uniqid(),
        'capacity' => 4,
        'status' => 'available',
        'is_active' => true,
    ]);

    $reservation = TableReservation::create([
        'booking_code' => random_int(100000, 999999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => $checkedInAt->toDateString(),
        'reservation_time' => $checkedInAt->format('H:i:s'),
        'status' => 'completed',
        'down_payment_amount' => 0,
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $reservation->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'waiter_id' => $waiter->id,
        'session_code' => 'WPH-PAGE-SES-'.uniqid(),
        'checked_in_at' => $checkedInAt,
        'status' => 'completed',
    ]);

    $order = Order::create([
        'table_session_id' => $session->id,
        'created_by' => $admin->id,
        'order_number' => 'WPH-PAGE-ORD-'.uniqid(),
        'status' => 'completed',
        'items_total' => $amount,
        'discount_amount' => 0,
        'total' => $amount,
        'ordered_at' => $checkedInAt->copy()->addMinutes(10),
    ]);

    $order->forceFill([
        'created_at' => $checkedInAt->copy()->addMinutes(10),
        'updated_at' => $checkedInAt->copy()->addMinutes(10),
    ])->save();

    $inventoryItem = InventoryItem::create([
        'code' => 'WPH-PAGE-INV-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => $itemName,
        'category_type' => 'food',
        'price' => $amount,
        'stock_quantity' => 50,
        'threshold' => 5,
        'unit' => 'porsi',
        'is_active' => true,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $inventoryItem->id,
        'item_name' => $itemName,
        'item_code' => 'WPH-PAGE-ITEM-'.uniqid(),
        'quantity' => 1,
        'price' => $amount,
        'subtotal' => $amount,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'service_charge_amount' => 0,
        'preparation_location' => 'kitchen',
        'status' => 'served',
    ]);

    $billing = Billing::create([
        'table_session_id' => $session->id,
        'order_id' => $order->id,
        'is_booking' => true,
        'is_walk_in' => false,
        'orders_total' => $amount,
        'subtotal' => $amount,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => $amount,
        'paid_amount' => $amount,
        'billing_status' => 'paid',
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
    ]);

    $session->update(['billing_id' => $billing->id]);
}

test('waiter performance shows daily history using 09:00 to 09:00 operational window', function () {
    Carbon::setTestNow(Carbon::create(2026, 4, 10, 8, 0, 0, 'Asia/Jakarta'));

    $admin = adminUser();
    $waiter = waiterPerformanceWaiter();
    $customer = User::factory()->create(['name' => 'Customer History']);
    $tableArea = Area::create([
        'code' => 'WPH-TBL-AREA-'.uniqid(),
        'name' => 'Waiter Perf Table Area '.uniqid(),
        'is_active' => true,
    ]);

    $table = Tabel::create([
        'area_id' => $tableArea->id,
        'table_number' => 'WPH-TBL-'.uniqid(),
        'qr_code' => 'WPH-QR-'.uniqid(),
        'capacity' => 4,
        'status' => 'available',
        'is_active' => true,
    ]);

    $reservation = TableReservation::create([
        'booking_code' => random_int(100000, 999999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => Carbon::create(2026, 4, 10, 7, 0, 0, 'Asia/Jakarta')->toDateString(),
        'reservation_time' => '07:00:00',
        'status' => 'completed',
        'down_payment_amount' => 30000,
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $reservation->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'waiter_id' => $waiter->id,
        'session_code' => 'WPH-SES-'.uniqid(),
        'checked_in_at' => Carbon::create(2026, 4, 10, 7, 0, 0, 'Asia/Jakarta'),
        'status' => 'completed',
    ]);

    $order = Order::create([
        'table_session_id' => $session->id,
        'created_by' => $admin->id,
        'order_number' => 'WPH-ORD-'.uniqid(),
        'status' => 'completed',
        'items_total' => 100000,
        'discount_amount' => 0,
        'total' => 100000,
        'ordered_at' => Carbon::create(2026, 4, 10, 7, 10, 0, 'Asia/Jakarta'),
    ]);

    $order->forceFill([
        'created_at' => Carbon::create(2026, 4, 10, 7, 10, 0, 'Asia/Jakarta'),
        'updated_at' => Carbon::create(2026, 4, 10, 7, 10, 0, 'Asia/Jakarta'),
    ])->save();

    $inventoryItem = InventoryItem::create([
        'code' => 'WPH-INV-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Nasi Goreng',
        'category_type' => 'food',
        'price' => 100000,
        'stock_quantity' => 50,
        'threshold' => 5,
        'unit' => 'porsi',
        'is_active' => true,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $inventoryItem->id,
        'item_name' => 'Nasi Goreng',
        'item_code' => 'WPH-ITEM-'.uniqid(),
        'quantity' => 1,
        'price' => 100000,
        'subtotal' => 100000,
        'discount_amount' => 5000,
        'tax_amount' => 11000,
        'service_charge_amount' => 10000,
        'preparation_location' => 'kitchen',
        'status' => 'served',
    ]);

    $billing = Billing::create([
        'table_session_id' => $session->id,
        'order_id' => $order->id,
        'is_booking' => true,
        'is_walk_in' => false,
        'transaction_code' => 'BILLING-WPH-001',
        'orders_total' => 100000,
        'subtotal' => 100000,
        'tax' => 11000,
        'tax_percentage' => 11,
        'service_charge' => 10000,
        'service_charge_percentage' => 10,
        'discount_amount' => 5000,
        'grand_total' => 116000,
        'paid_amount' => 116000,
        'billing_status' => 'paid',
        'payment_method' => 'cash',
        'payment_mode' => 'split',
        'split_cash_amount' => 60000,
        'split_debit_amount' => 56000,
        'split_non_cash_method' => 'qris',
        'split_non_cash_reference_number' => 'QR-REF-001',
    ]);

    $session->update(['billing_id' => $billing->id]);

    actingAs($admin)
        ->get(route('admin.waiter-performance.index', [
            'mode' => 'individual',
            'period' => 'today',
            'waiter_id' => $waiter->id,
        ]))
        ->assertSuccessful()
        ->assertSeeText('Riwayat Harian (End Day)')
        ->assertSeeText('2026-04-09')
        ->assertSeeText('100.000')
        ->assertSeeText('Customer History')
        ->assertSeeText('Nasi Goreng')
        ->assertSeeText('Reference')
        ->assertSeeText('Billing')
        ->assertSee('tab=history&amp;search=BILLING-WPH-001&amp;session_id='.$session->id, false)
        ->assertSeeText('Hasil Billing')
        ->assertSeeText('Pecahan Transaksi')
        ->assertSeeText('DP')
        ->assertSeeText('Diskon')
        ->assertSeeText('PPN')
        ->assertSeeText('Service Charge')
        ->assertSeeText('30.000')
        ->assertSeeText('5.000')
        ->assertSeeText('11.000')
        ->assertSeeText('10.000')
        ->assertSeeText('60.000')
        ->assertSeeText('56.000')
        ->assertSeeText('QRIS')
        ->assertSeeText('QR-REF-001');
});

test('waiter performance daily history supports pagination and per page rows', function () {
    Carbon::setTestNow(Carbon::create(2026, 4, 12, 12, 0, 0, 'Asia/Jakarta'));

    $admin = adminUser();
    $waiter = waiterPerformanceWaiter();

    createWaiterHistoryEntry(
        $waiter,
        $admin,
        'Customer Page One',
        Carbon::create(2026, 4, 12, 10, 0, 0, 'Asia/Jakarta'),
        'Page One Item',
        120000
    );

    createWaiterHistoryEntry(
        $waiter,
        $admin,
        'Customer Page Two',
        Carbon::create(2026, 4, 11, 10, 0, 0, 'Asia/Jakarta'),
        'Page Two Item',
        80000
    );

    actingAs($admin)
        ->get(route('admin.waiter-performance.index', [
            'mode' => 'individual',
            'period' => 'today',
            'waiter_id' => $waiter->id,
            'history_per_page' => 1,
        ]))
        ->assertSuccessful()
        ->assertSeeText('2026-04-12')
        ->assertSeeText('Page One Item')
        ->assertSee('name="history_per_page"', false)
        ->assertSee('page=2', false);

    actingAs($admin)
        ->get(route('admin.waiter-performance.index', [
            'mode' => 'individual',
            'period' => 'today',
            'waiter_id' => $waiter->id,
            'history_per_page' => 1,
            'page' => 2,
        ]))
        ->assertSuccessful()
        ->assertSeeText('2026-04-11')
        ->assertSeeText('Page Two Item')
        ->assertSee('page=1', false);
});

test('waiter performance all mode supports pagination with minimum 5 rows per page', function () {
    Carbon::setTestNow(Carbon::create(2026, 4, 12, 12, 0, 0, 'Asia/Jakarta'));

    $admin = adminUser();

    for ($index = 0; $index < 6; $index++) {
        waiterPerformanceWaiter();
    }

    actingAs($admin)
        ->get(route('admin.waiter-performance.index', [
            'mode' => 'all',
            'period' => 'today',
            'all_waiters_per_page' => 5,
        ]))
        ->assertSuccessful()
        ->assertSee('name="all_waiters_per_page"', false)
        ->assertSee('page=2', false);

    actingAs($admin)
        ->get(route('admin.waiter-performance.index', [
            'mode' => 'all',
            'period' => 'today',
            'all_waiters_per_page' => 1,
        ]))
        ->assertSuccessful()
        ->assertSee('name="all_waiters_per_page"', false)
        ->assertSee('page=2', false);
});

test('waiter performance can be filtered by selected date', function () {
    Carbon::setTestNow(Carbon::create(2026, 4, 13, 12, 0, 0, 'Asia/Jakarta'));

    $admin = adminUser();
    $waiter = waiterPerformanceWaiter();

    createWaiterHistoryEntry(
        $waiter,
        $admin,
        'Customer Date Match',
        Carbon::create(2026, 4, 11, 10, 0, 0, 'Asia/Jakarta'),
        'Date Match Item',
        120000
    );

    createWaiterHistoryEntry(
        $waiter,
        $admin,
        'Customer Date Other',
        Carbon::create(2026, 4, 12, 10, 0, 0, 'Asia/Jakarta'),
        'Date Other Item',
        90000
    );

    actingAs($admin)
        ->get(route('admin.waiter-performance.index', [
            'mode' => 'individual',
            'period' => 'month',
            'date' => '2026-04-11',
            'waiter_id' => $waiter->id,
        ]))
        ->assertSuccessful()
        ->assertSeeText('11 April 2026')
        ->assertSeeText('Date Match Item')
        ->assertDontSeeText('Date Other Item');
});

test('waiter performance monthly history route lists selected month records with default 31 days', function () {
    Carbon::setTestNow(Carbon::create(2026, 4, 20, 12, 0, 0, 'Asia/Jakarta'));

    $admin = adminUser();
    $waiter = waiterPerformanceWaiter();

    createWaiterHistoryEntry(
        $waiter,
        $admin,
        'Customer April',
        Carbon::create(2026, 4, 11, 10, 0, 0, 'Asia/Jakarta'),
        'April Item',
        100000
    );

    createWaiterHistoryEntry(
        $waiter,
        $admin,
        'Customer March',
        Carbon::create(2026, 3, 25, 10, 0, 0, 'Asia/Jakarta'),
        'March Item',
        90000
    );

    actingAs($admin)
        ->get(route('admin.waiter-performance.monthly-history', [
            'waiter' => $waiter->id,
            'month' => '2026-04',
        ]))
        ->assertSuccessful()
        ->assertSeeText('Riwayat Bulanan Waiter')
        ->assertSeeText('April 2026')
        ->assertSeeText('2026-04-11')
        ->assertDontSeeText('2026-03-25')
        ->assertSeeText('100.000')
        ->assertSeeText('Detail Order (Klik per Order)')
        ->assertSeeText('Customer April')
        ->assertSeeText('April Item')
        ->assertSee('x-data="{ openHistory: null }"', false)
        ->assertSee('x-show="openHistory === 0"', false);
});

test('waiter performance monthly pull button submits to monthly history route', function () {
    Carbon::setTestNow(Carbon::create(2026, 4, 21, 12, 0, 0, 'Asia/Jakarta'));

    $admin = adminUser();
    $waiter = waiterPerformanceWaiter();

    actingAs($admin)
        ->get(route('admin.waiter-performance.index', [
            'mode' => 'individual',
            'period' => 'month',
            'waiter_id' => $waiter->id,
        ]))
        ->assertSuccessful()
        ->assertSee(
            'formaction="'.route('admin.waiter-performance.monthly-history', ['waiter' => $waiter->id]).'"',
            false
        );
});
