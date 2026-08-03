<?php

use App\Models\Area;
use App\Models\Billing;
use App\Models\CustomerUser;
use App\Models\Tabel;
use App\Models\TableReservation;
use App\Models\TableSession;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('billing gets area_id from table when reservation session billing is created', function () {
    $area = Area::create(['name' => 'LOUNGE', 'code' => 'LNG']);
    $table = Tabel::create(['area_id' => $area->id, 'table_number' => 'T-LNG-01', 'capacity' => 4, 'minimum_charge' => 100000, 'qr_code' => 'QR-LNG-01']);

    $customerUser = User::factory()->create();
    UserProfile::create(['user_id' => $customerUser->id, 'phone_number' => '08123456789']);

    $booking = TableReservation::create([
        'customer_id' => $customerUser->id,
        'table_id' => $table->id,
        'reservation_date' => now()->toDateString(),
        'reservation_time' => '19:00',
        'start_time' => '19:00',
        'pax' => 2,
        'status' => 'confirmed',
        'booking_code' => 'RES-TEST-001',
    ]);

    $admin = adminUser();

    $this->actingAs($admin)
        ->patch(route('admin.bookings.updateStatus', $booking), [
            'status' => 'checked_in',
        ]);

    $booking->refresh();
    $session = $booking->tableSession;

    expect($session)->not->toBeNull();
    expect($session->billing)->not->toBeNull();
    expect((int) $session->billing->area_id)->toBe($area->id);
});

test('close billing preserves and updates area_id on billing', function () {
    $area = Area::create(['name' => 'ROOM', 'code' => 'RM']);
    $table = Tabel::create(['area_id' => $area->id, 'table_number' => 'T-RM-01', 'capacity' => 6, 'minimum_charge' => 200000, 'qr_code' => 'QR-RM-01']);

    $customerUser = User::factory()->create();
    $profile = UserProfile::create(['user_id' => $customerUser->id, 'phone_number' => '08198765432']);
    CustomerUser::create(['user_id' => $customerUser->id, 'user_profile_id' => $profile->id, 'customer_code' => 'CUST-001']);

    $booking = TableReservation::create([
        'customer_id' => $customerUser->id,
        'table_id' => $table->id,
        'reservation_date' => now()->toDateString(),
        'reservation_time' => '20:00',
        'start_time' => '20:00',
        'pax' => 4,
        'status' => 'checked_in',
        'booking_code' => 'RES-TEST-002',
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $table->id,
        'customer_id' => $customerUser->id,
        'session_code' => 'SES-TEST-002',
        'checked_in_at' => now(),
        'status' => 'active',
    ]);

    $billing = Billing::create([
        'area_id' => $area->id,
        'table_session_id' => $session->id,
        'is_walk_in' => false,
        'is_booking' => true,
        'minimum_charge' => 100000,
        'orders_total' => 0,
        'subtotal' => 0,
        'tax' => 0,
        'grand_total' => 0,
        'paid_amount' => 0,
        'billing_status' => 'draft',
    ]);

    $session->update(['billing_id' => $billing->id]);

    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.bookings.closeBilling', $booking), [
            'payment_mode' => 'normal',
            'payment_method' => 'cash',
        ]);

    $billing->refresh();

    expect((int) $billing->area_id)->toBe($area->id);
    expect($billing->billing_status)->toBe('paid');
});
