<?php

use App\Models\Area;
use App\Models\Billing;
use App\Models\CustomerUser;
use App\Models\DailyAuthCode;
use App\Models\InventoryItem;
use App\Models\Tabel;
use App\Models\TableReservation;
use App\Models\TableSession;
use App\Models\User;
use App\Models\UserProfile;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

test('walk-in checkout with FOC requires valid daily auth code, keeps normal total, and decrements stock', function () {
    $today = now()->format('Y-m-d');
    $authCode = DailyAuthCode::forDate($today)->active_code;

    $user = adminUser();
    actingAs($user);

    $customer = User::factory()->create(['name' => 'WalkIn Customer FOC']);
    $profile = UserProfile::create(['user_id' => $customer->id, 'full_name' => 'WalkIn Customer FOC']);
    CustomerUser::create([
        'user_id' => $customer->id,
        'user_profile_id' => $profile->id,
        'customer_code' => 'CUST-'.uniqid(),
    ]);

    $item = InventoryItem::create([
        'code' => 'ITEM-FOC-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Vodka Bottle',
        'category_type' => 'alcohol',
        'price' => 500000,
        'stock_quantity' => 20,
        'threshold' => 2,
        'unit' => 'bottle',
        'is_active' => true,
        'is_visible_in_pos' => true,
    ]);

    session()->put('pos_cart', [
        'item_'.$item->id => [
            'quantity' => 2,
            'price' => 500000,
        ],
    ]);

    // 1. Without auth code -> fails
    $resFail = postJson(route('admin.pos.checkout'), [
        'customer_type' => 'walk-in',
        'walk_in_customer_id' => $customer->id,
        'foc_comp_payment_method' => 'FOC',
        'payment_method' => 'cash',
        'foc_comp_auth_code' => '',
    ]);

    $resFail->assertStatus(422)
        ->assertJsonValidationErrors(['foc_comp_auth_code']);

    // 2. With invalid auth code -> fails
    $resFailInvalid = postJson(route('admin.pos.checkout'), [
        'customer_type' => 'walk-in',
        'walk_in_customer_id' => $customer->id,
        'foc_comp_payment_method' => 'FOC',
        'payment_method' => 'cash',
        'foc_comp_auth_code' => '9999',
    ]);

    $resFailInvalid->assertStatus(422)
        ->assertJsonValidationErrors(['foc_comp_auth_code']);

    // 3. With valid auth code -> succeeds, normal total, stock decremented
    $resSuccess = postJson(route('admin.pos.checkout'), [
        'customer_type' => 'walk-in',
        'walk_in_customer_id' => $customer->id,
        'foc_comp_payment_method' => 'FOC',
        'payment_method' => 'cash',
        'foc_comp_auth_code' => $authCode,
    ]);

    $resSuccess->assertStatus(200)
        ->assertJson(['success' => true]);

    $item->refresh();
    expect($item->stock_quantity)->toBe(18);

    $billing = Billing::latest('id')->first();
    expect($billing->foc_comp_payment_method)->toBe('FOC')
        ->and((float) $billing->grand_total)->toBeGreaterThan(0);
});

test('walk-in checkout with Compliment requires valid daily auth code, sets total to 0, and decrements stock', function () {
    $today = now()->format('Y-m-d');
    $authCode = DailyAuthCode::forDate($today)->active_code;

    $user = adminUser();
    actingAs($user);

    $customer = User::factory()->create(['name' => 'WalkIn Customer Compliment']);
    $profile = UserProfile::create(['user_id' => $customer->id, 'full_name' => 'WalkIn Customer Compliment']);
    CustomerUser::create([
        'user_id' => $customer->id,
        'user_profile_id' => $profile->id,
        'customer_code' => 'CUST-'.uniqid(),
    ]);

    $item = InventoryItem::create([
        'code' => 'ITEM-COMP-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Cocktail Glass',
        'category_type' => 'beverage',
        'price' => 150000,
        'stock_quantity' => 15,
        'threshold' => 2,
        'unit' => 'glass',
        'is_active' => true,
        'is_visible_in_pos' => true,
    ]);

    session()->put('pos_cart', [
        'item_'.$item->id => [
            'quantity' => 3,
            'price' => 150000,
        ],
    ]);

    // Checkout with Compliment & valid auth code
    $res = postJson(route('admin.pos.checkout'), [
        'customer_type' => 'walk-in',
        'walk_in_customer_id' => $customer->id,
        'foc_comp_payment_method' => 'Compliment',
        'payment_method' => 'cash',
        'foc_comp_auth_code' => $authCode,
    ]);

    $res->assertStatus(200)
        ->assertJson(['success' => true]);

    $item->refresh();
    expect($item->stock_quantity)->toBe(12);

    $billing = Billing::latest('id')->first();
    expect($billing->foc_comp_payment_method)->toBe('Compliment')
        ->and((float) $billing->grand_total)->toBe(0.0);
});

test('close billing for booking with FOC or Compliment requires valid auth code', function () {
    $today = now()->format('Y-m-d');
    $authCode = DailyAuthCode::forDate($today)->active_code;

    $user = adminUser();
    actingAs($user);

    $area = Area::create([
        'code' => 'AREA-FOC-'.uniqid(),
        'name' => 'FOC Area',
        'is_active' => true,
    ]);

    $table = Tabel::create([
        'area_id' => $area->id,
        'table_number' => 'TBL-FOC-'.uniqid(),
        'qr_code' => 'QR-FOC-'.uniqid(),
        'capacity' => 4,
        'status' => 'available',
        'is_active' => true,
    ]);

    $customer = User::factory()->create(['name' => 'Booking Customer']);
    $reservation = TableReservation::create([
        'booking_code' => random_int(100000, 999999),
        'customer_id' => $customer->id,
        'table_id' => $table->id,
        'reservation_date' => today()->toDateString(),
        'reservation_time' => '19:00:00',
        'status' => 'checked_in',
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $reservation->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'waiter_id' => $user->id,
        'session_code' => 'SES-'.uniqid(),
        'checked_in_at' => now(),
        'status' => 'active',
    ]);

    $billing = Billing::create([
        'table_session_id' => $session->id,
        'transaction_code' => 'BILL-'.uniqid(),
        'is_booking' => true,
        'orders_total' => 300000,
        'subtotal' => 300000,
        'grand_total' => 300000,
        'billing_status' => 'unpaid',
    ]);

    $session->update(['billing_id' => $billing->id]);

    // 1. FOC without auth code -> fails
    $resFocFail = postJson(route('admin.bookings.closeBilling', $reservation->id), [
        'payment_mode' => 'normal',
        'payment_method' => 'cash',
        'foc_comp_payment_method' => 'FOC',
        'foc_comp_auth_code' => '',
    ]);

    $resFocFail->assertStatus(422)
        ->assertJsonValidationErrors(['foc_comp_auth_code']);

    // 2. Compliment with valid auth code -> succeeds with 0 grand total
    $resCompSuccess = $this
        ->withSession(['booking_discount_auth_code_requested_at' => now()->timestamp])
        ->postJson(route('admin.bookings.closeBilling', $reservation->id), [
            'payment_mode' => 'normal',
            'payment_method' => 'cash',
            'foc_comp_payment_method' => 'Compliment',
            'foc_comp_auth_code' => $authCode,
        ]);

    $resCompSuccess->assertStatus(200)
        ->assertJson(['success' => true]);

    $billing->refresh();
    expect($billing->foc_comp_payment_method)->toBe('Compliment')
        ->and((float) $billing->grand_total)->toBe(0.0);
});
