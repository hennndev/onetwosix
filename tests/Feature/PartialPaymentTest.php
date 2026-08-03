<?php

use App\Models\Billing;
use App\Models\BillingPayment;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('billing can record initial partial payment and update debt status', function () {
    $user = adminUser();
    $this->actingAs($user);

    $billing = Billing::create([
        'orders_total' => 1000000,
        'subtotal' => 1000000,
        'tax' => 100000,
        'tax_percentage' => 10,
        'service_charge' => 50000,
        'service_charge_percentage' => 5,
        'discount_amount' => 0,
        'grand_total' => 1150000,
        'paid_amount' => 500000,
        'remaining_balance' => 650000,
        'is_debt' => true,
        'is_parsial_payment' => true,
        'billing_status' => 'partially_paid',
        'paid_at' => now(),
        'transaction_code' => 'BILLING-TEST-001',
        'payment_method' => 'cash',
        'payment_mode' => 'partial',
    ]);

    BillingPayment::create([
        'billing_id' => $billing->id,
        'amount_paid' => 500000,
        'payment_method' => 'cash',
        'payment_type' => 'initial_partial',
        'created_by' => $user->id,
        'paid_at' => now(),
    ]);

    expect($billing->billing_status)->toBe('partially_paid');
    expect($billing->is_debt)->toBeTrue();
    expect($billing->is_parsial_payment)->toBeTrue();
    expect((float) $billing->remaining_balance)->toBe(650000.0);
    expect($billing->payments()->count())->toBe(1);
});

test('settleDebt endpoint records debt payment and updates billing to paid when balance is cleared', function () {
    $user = adminUser();
    $this->actingAs($user);

    $order = Order::create([
        'order_number' => 'WALKIN-TEST-001',
        'customer_user_id' => null,
        'created_by' => $user->id,
        'status' => 'completed',
        'items_total' => 1000000,
        'discount_amount' => 0,
        'total' => 1000000,
        'ordered_at' => now(),
    ]);

    $billing = Billing::create([
        'order_id' => $order->id,
        'is_walk_in' => true,
        'orders_total' => 1000000,
        'subtotal' => 1000000,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => 1000000,
        'paid_amount' => 600000,
        'remaining_balance' => 400000,
        'is_debt' => true,
        'is_parsial_payment' => true,
        'billing_status' => 'partially_paid',
        'paid_at' => now(),
        'transaction_code' => 'BILLING-TEST-002',
        'payment_method' => 'cash',
        'payment_mode' => 'partial',
    ]);

    BillingPayment::create([
        'billing_id' => $billing->id,
        'amount_paid' => 600000,
        'payment_method' => 'cash',
        'payment_type' => 'initial_partial',
        'created_by' => $user->id,
        'paid_at' => now(),
    ]);

    $response = $this->postJson(route('admin.transaction-history.settle-debt', $order), [
        'amount_paid' => 400000,
        'payment_method' => 'qris',
        'payment_reference_number' => 'QRIS-REF-12345',
        'notes' => 'Pelunasan sisa 400rb',
    ]);

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'billing' => [
            'billing_status' => 'paid',
            'paid_amount' => 1000000,
            'remaining_balance' => 0,
            'is_debt' => false,
        ],
    ]);

    $billing->refresh();
    expect($billing->billing_status)->toBe('paid');
    expect($billing->is_debt)->toBeFalse();
    expect($billing->is_parsial_payment)->toBeFalse();
    expect((float) $billing->remaining_balance)->toBe(0.0);
    expect($billing->payments()->count())->toBe(2);
});

test('booking settlePayment endpoint settles debt balance and updates status', function () {
    $user = adminUser();
    $this->actingAs($user);

    $customer = \App\Models\User::factory()->create();
    $profile = \App\Models\UserProfile::create(['user_id' => $customer->id, 'phone' => '08123456789']);
    \App\Models\CustomerUser::create(['user_id' => $customer->id, 'user_profile_id' => $profile->id]);

    $area = \App\Models\Area::create(['name' => 'VIP', 'code' => 'VIP', 'sort_order' => 1]);
    $table = \App\Models\Tabel::create(['table_number' => 'V1', 'capacity' => 4, 'area_id' => $area->id, 'status' => 'occupied', 'qr_code' => 'QR-V1']);

    $booking = \App\Models\TableReservation::create([
        'customer_id' => $customer->id,
        'table_id' => $table->id,
        'reservation_date' => now()->toDateString(),
        'reservation_time' => '19:00',
        'booking_code' => 'BK-PART-001',
        'status' => 'completed',
    ]);

    $session = \App\Models\TableSession::create([
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'table_reservation_id' => $booking->id,
        'session_code' => 'SESS-001',
        'checked_in_at' => now(),
        'checked_out_at' => now(),
        'status' => 'completed',
    ]);

    $billing = Billing::create([
        'table_session_id' => $session->id,
        'area_id' => $area->id,
        'orders_total' => 2000000,
        'subtotal' => 2000000,
        'tax' => 0,
        'service_charge' => 0,
        'grand_total' => 2000000,
        'paid_amount' => 1000000,
        'remaining_balance' => 1000000,
        'is_debt' => true,
        'is_parsial_payment' => true,
        'billing_status' => 'partially_paid',
        'paid_at' => now(),
        'transaction_code' => 'BILLING-PART-001',
        'payment_method' => 'cash',
        'payment_mode' => 'partial',
    ]);

    $session->update(['billing_id' => $billing->id]);

    BillingPayment::create([
        'billing_id' => $billing->id,
        'amount_paid' => 1000000,
        'payment_method' => 'cash',
        'payment_type' => 'initial_partial',
        'created_by' => $user->id,
        'paid_at' => now(),
    ]);

    $response = $this->postJson(route('admin.bookings.settlePayment', $booking), [
        'amount_paid' => 1000000,
        'payment_method' => 'transfer',
        'payment_reference_number' => 'TRF-98765',
        'notes' => 'Pelunasan sisa 1 juta',
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);

    $billing->refresh();
    expect($billing->billing_status)->toBe('paid');
    expect($billing->is_debt)->toBeFalse();
    expect($billing->is_parsial_payment)->toBeFalse();
    expect((float) $billing->remaining_balance)->toBe(0.0);
    expect($billing->payments()->count())->toBe(2);
});
