<?php

use App\Models\Area;
use App\Models\Billing;
use App\Models\DailySequence;
use App\Models\Order;
use App\Models\Tabel;
use App\Models\TableSession;
use App\Models\User;
use App\Services\OrderNumberGenerator;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

it('reserves incrementing numbers for the same scope and date', function () {
    $date = today()->toDateString();

    expect(DailySequence::next('orders:booking', $date))->toBe(1)
        ->and(DailySequence::next('orders:booking', $date))->toBe(2)
        ->and(DailySequence::next('orders:booking', $date))->toBe(3);
});

it('keeps separate sequences per scope and per date', function () {
    $today = today()->toDateString();
    $yesterday = today()->subDay()->toDateString();

    expect(DailySequence::next('orders:booking', $today))->toBe(1)
        ->and(DailySequence::next('orders:walk-in', $today))->toBe(1)
        ->and(DailySequence::next('orders:booking', $yesterday))->toBe(1)
        ->and(DailySequence::next('orders:booking', $today))->toBe(2);
});

it('seeds the first reservation from the legacy count-based numbering', function () {
    // Legacy rows created before the sequence table existed: the first
    // reservation of the day must continue after them, not restart at 0001.
    // The bridge counts legacy ROWS of the day (2), matching the old
    // count-based scheme — not the digits inside their codes.
    Order::create([
        'order_number' => 'ORD-'.today()->format('Ymd').'-0005',
        'created_by' => null,
        'status' => 'pending',
        'items_total' => 0,
        'discount_amount' => 0,
        'total' => 0,
        'ordered_at' => today(),
    ]);
    Order::create([
        'order_number' => 'WALKIN-'.today()->format('Ymd').'-0002',
        'created_by' => null,
        'status' => 'pending',
        'items_total' => 0,
        'discount_amount' => 0,
        'total' => 0,
        'ordered_at' => today(),
        'table_session_id' => null,
    ]);

    $generator = new OrderNumberGenerator;

    expect($generator->orderNumber('ORD', 'booking'))->toBe('ORD-'.today()->format('Ymd').'-0003')
        ->and($generator->orderNumber('ORD', 'booking'))->toBe('ORD-'.today()->format('Ymd').'-0004');
});

it('seeds the walk-in order scope from walk-in orders only', function () {
    $area = Area::create([
        'code' => 'SEQ-AREA-'.uniqid(),
        'name' => 'SEQ Area '.uniqid(),
        'is_active' => true,
        'sort_order' => 1,
    ]);
    $table = Tabel::create([
        'area_id' => $area->id,
        'table_number' => 'SEQ-TBL-'.uniqid(),
        'qr_code' => 'SEQ-QR-'.uniqid(),
        'capacity' => 4,
        'minimum_charge' => 0,
        'status' => 'occupied',
        'is_active' => true,
    ]);
    $session = TableSession::create([
        'table_id' => $table->id,
        'customer_id' => User::factory()->create()->id,
        'session_code' => 'SESSION-'.uniqid(),
        'checked_in_at' => now(),
        'status' => 'active',
    ]);

    // A booking order (attached to a table session)…
    Order::create([
        'order_number' => 'ORD-'.today()->format('Ymd').'-0001',
        'created_by' => null,
        'status' => 'pending',
        'items_total' => 0,
        'discount_amount' => 0,
        'total' => 0,
        'ordered_at' => today(),
        'table_session_id' => $session->id,
    ]);

    // …and a walk-in order (no table session).
    Order::create([
        'order_number' => 'WALKIN-'.today()->format('Ymd').'-0009',
        'created_by' => null,
        'status' => 'pending',
        'items_total' => 0,
        'discount_amount' => 0,
        'total' => 0,
        'ordered_at' => today(),
        'table_session_id' => null,
    ]);

    $generator = new OrderNumberGenerator;

    // The booking scope bridges from ALL legacy orders of the day (2),
    // the walk-in scope only from walk-in orders (1).
    expect($generator->orderNumber('WALKIN', 'walk-in'))->toBe('WALKIN-'.today()->format('Ymd').'-0002')
        ->and($generator->orderNumber('ORD', 'booking'))->toBe('ORD-'.today()->format('Ymd').'-0003');
});

it('formats the walk-in billing transaction code and continues from legacy billings', function () {
    Billing::create([
        'table_session_id' => null,
        'order_id' => null,
        'is_walk_in' => true,
        'is_booking' => false,
        'transaction_code' => 'WALKIN-000001',
        'orders_total' => 0,
        'subtotal' => 0,
        'tax' => 0,
        'service_charge' => 0,
        'discount_amount' => 0,
        'grand_total' => 0,
        'paid_amount' => 0,
        'remaining_balance' => 0,
        'billing_status' => 'paid',
    ]);

    $generator = new OrderNumberGenerator;

    expect($generator->walkInTransactionCode())->toBe('WALKIN-000002')
        ->and($generator->walkInTransactionCode())->toBe('WALKIN-000003');
});

it('formats the booking billing transaction code and continues from legacy billings', function () {
    Billing::create([
        'table_session_id' => null,
        'order_id' => null,
        'is_walk_in' => false,
        'is_booking' => true,
        'transaction_code' => 'BILLING-000004',
        'orders_total' => 0,
        'subtotal' => 0,
        'tax' => 0,
        'service_charge' => 0,
        'discount_amount' => 0,
        'grand_total' => 0,
        'paid_amount' => 0,
        'remaining_balance' => 0,
        'billing_status' => 'paid',
    ]);

    $generator = new OrderNumberGenerator;

    expect($generator->bookingTransactionCode())->toBe('BILLING-000002')
        ->and($generator->bookingTransactionCode())->toBe('BILLING-000003');
});

it('keeps booking and walk-in billing codes on separate sequences', function () {
    $generator = new OrderNumberGenerator;

    // Both start at 1 on the same day but must never share a counter,
    // otherwise a booking close and a walk-in sale would collide.
    expect($generator->bookingTransactionCode())->toBe('BILLING-000001')
        ->and($generator->walkInTransactionCode())->toBe('WALKIN-000001')
        ->and($generator->bookingTransactionCode())->toBe('BILLING-000002')
        ->and($generator->walkInTransactionCode())->toBe('WALKIN-000002');
});

it('reserves numbers inside an ambient transaction without breaking rollback', function () {
    $date = today()->toDateString();

    DB::beginTransaction();

    $first = DailySequence::next('orders:booking', $date);
    DB::rollBack();

    // After a rollback the reservation is undone: the next number
    // re-issues the same value instead of leaving a gap.
    $afterRollback = DailySequence::next('orders:booking', $date);

    expect($first)->toBe(1)
        ->and($afterRollback)->toBe(1);
});

it('sends an idempotency token from the pos dashboard checkout', function () {
    $admin = adminUser();

    actingAs($admin)
        ->get(route('admin.pos.index'))
        ->assertOk()
        ->assertSee('idempotency_key: this.checkoutToken ??= crypto.randomUUID()', false)
        ->assertSee('checkoutToken: null', false);
});
