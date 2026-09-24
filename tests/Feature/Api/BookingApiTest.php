<?php

use App\Models\Area;
use App\Models\Billing;
use App\Models\CustomerUser;
use App\Models\DisplayMessageRequest;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SongRequest;
use App\Models\Tabel;
use App\Models\TableReservation;
use App\Models\TableSession;
use App\Models\User;
use App\Models\UserProfile;

function createBookingCustomer(array $overrides = []): User
{
    $user = User::factory()->create(array_merge([
        'name' => 'Booking Customer',
        'email' => 'booking@test.com',
    ], $overrides));

    $profile = UserProfile::create([
        'user_id' => $user->id,
        'phone' => '08123456789',
    ]);

    CustomerUser::create([
        'user_id' => $user->id,
        'user_profile_id' => $profile->id,
        'total_visits' => 0,
        'lifetime_spending' => 0,
    ]);

    return $user->fresh();
}

function createTestTable(): Tabel
{
    $area = Area::create([
        'code' => 'VIP',
        'name' => 'VIP Area',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    return Tabel::create([
        'area_id' => $area->id,
        'table_number' => 'V1',
        'qr_code' => 'QR-TEST-V1',
        'capacity' => 6,
        'minimum_charge' => 500000,
        'status' => 'available',
        'is_active' => true,
    ]);
}

it('creates a booking', function () {
    $user = createBookingCustomer();
    $table = createTestTable();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/bookings', [
            'table_id' => $table->id,
            'reservation_date' => now()->addDays(1)->format('Y-m-d'),
            'reservation_time' => '20:00',
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'error',
            'message',
            'data' => [
                'booking' => ['id', 'booking_code', 'status', 'table'],
            ],
        ])
        ->assertJsonPath('data.booking.status', 'pending');
});

it('rejects booking for already booked table on same date', function () {
    $user = createBookingCustomer();
    $otherUser = User::factory()->create();
    $table = createTestTable();
    $date = now()->addDays(2)->format('Y-m-d');

    TableReservation::create([
        'booking_code' => 'EXISTING1',
        'booking_name' => 'Other Guest',
        'table_id' => $table->id,
        'customer_id' => $otherUser->id,
        'reservation_date' => $date,
        'reservation_time' => '19:00',
        'status' => 'confirmed',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/bookings', [
            'table_id' => $table->id,
            'reservation_date' => $date,
            'reservation_time' => '21:00',
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Meja sudah dipesan untuk tanggal tersebut.');
});

it('lists own bookings', function () {
    $user = createBookingCustomer();
    $table = createTestTable();

    TableReservation::create([
        'booking_code' => 'BK001',
        'booking_name' => $user->name,
        'table_id' => $table->id,
        'customer_id' => $user->id,
        'reservation_date' => now()->addDays(1),
        'reservation_time' => '20:00',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/bookings');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data.bookings');
});

it('cancels a pending booking', function () {
    $user = createBookingCustomer();
    $table = createTestTable();

    $booking = TableReservation::create([
        'booking_code' => 'BK002',
        'booking_name' => $user->name,
        'table_id' => $table->id,
        'customer_id' => $user->id,
        'reservation_date' => now()->addDays(1),
        'reservation_time' => '20:00',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/bookings/{$booking->id}/cancel");

    $response->assertSuccessful()
        ->assertJsonPath('data.booking.status', 'cancelled');
});

it('cannot cancel a completed booking', function () {
    $user = createBookingCustomer();
    $table = createTestTable();

    $booking = TableReservation::create([
        'booking_code' => 'BK003',
        'booking_name' => $user->name,
        'table_id' => $table->id,
        'customer_id' => $user->id,
        'reservation_date' => now()->subDays(1),
        'reservation_time' => '20:00',
        'status' => 'completed',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/bookings/{$booking->id}/cancel");

    $response->assertStatus(422);
});

it('shows available tables for a date', function () {
    $user = createBookingCustomer();
    $table = createTestTable();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/bookings/available-tables?date='.now()->addDays(1)->format('Y-m-d'));

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => ['available_tables']]);
});

it('cannot view other customer booking', function () {
    $user = createBookingCustomer();
    $otherUser = User::factory()->create();
    $table = createTestTable();

    $booking = TableReservation::create([
        'booking_code' => 'BK999',
        'booking_name' => 'Other',
        'table_id' => $table->id,
        'customer_id' => $otherUser->id,
        'reservation_date' => now()->addDays(1),
        'reservation_time' => '20:00',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/bookings/{$booking->id}");

    $response->assertForbidden();
});

it('shows booking transaction history with billing and orders', function () {
    $user = createBookingCustomer();
    $admin = User::factory()->create();
    $table = createTestTable();

    $booking = TableReservation::create([
        'booking_code' => 'BK004',
        'booking_name' => $user->name,
        'table_id' => $table->id,
        'customer_id' => $user->id,
        'reservation_date' => now()->subDay(),
        'reservation_time' => '20:00',
        'status' => 'completed',
        'down_payment_amount' => 50000,
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $table->id,
        'customer_id' => $user->id,
        'session_code' => 'SES-TEST-001',
        'checked_in_at' => now()->subHours(3),
        'checked_out_at' => now()->subHour(),
        'status' => 'completed',
    ]);

    $billing = Billing::create([
        'table_session_id' => $session->id,
        'is_booking' => true,
        'minimum_charge' => 0,
        'orders_total' => 100000,
        'subtotal' => 100000,
        'tax' => 10000,
        'tax_percentage' => 10,
        'service_charge' => 5000,
        'service_charge_percentage' => 5,
        'discount_amount' => 0,
        'song_tip' => 25000,
        'display_tip' => 15000,
        'grand_total' => 95000,
        'paid_amount' => 95000,
        'billing_status' => 'paid',
        'payment_mode' => 'normal',
        'payment_method' => 'cash',
    ]);

    $session->update(['billing_id' => $billing->id]);

    SongRequest::create([
        'customer_user_id' => $user->customerUser->id,
        'table_session_id' => $session->id,
        'song_title' => 'Test Song',
        'artist' => 'Test Artist',
        'tip' => 25000,
        'status' => 'played',
    ]);

    DisplayMessageRequest::create([
        'customer_id' => $user->id,
        'table_session_id' => $session->id,
        'message' => 'Happy birthday!',
        'tip' => 15000,
        'status' => 'displayed',
    ]);

    $inventoryItem = InventoryItem::create([
        'code' => 'API-HIST-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'API History Item',
        'category_type' => 'food',
        'price' => 50000,
        'stock_quantity' => 10,
        'threshold' => 2,
        'unit' => 'plate',
        'is_active' => true,
    ]);

    $order = Order::create([
        'table_session_id' => $session->id,
        'created_by' => $admin->id,
        'order_number' => 'ORD-API-HIST',
        'status' => 'completed',
        'items_total' => 100000,
        'discount_amount' => 10000,
        'total' => 90000,
        'ordered_at' => now()->subHours(2),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $inventoryItem->id,
        'item_name' => $inventoryItem->name,
        'item_code' => $inventoryItem->code,
        'quantity' => 2,
        'price' => 50000,
        'subtotal' => 100000,
        'discount_amount' => 0,
        'preparation_location' => 'kitchen',
        'status' => 'served',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/bookings/{$booking->id}");

    $response->assertSuccessful()
        ->assertJsonPath('data.booking.transaction_history.table_session.session_code', 'SES-TEST-001')
        ->assertJsonPath('data.booking.transaction_history.billing.status', 'paid')
        ->assertJsonPath('data.booking.transaction_history.billing.song_tip', 25000)
        ->assertJsonPath('data.booking.transaction_history.billing.display_tip', 15000)
        ->assertJsonPath('data.booking.transaction_history.billing.discount', 10000)
        ->assertJsonPath('data.booking.transaction_history.billing.discount_amount', 0)
        ->assertJsonPath('data.booking.transaction_history.billing.order_discount_amount', 10000)
        ->assertJsonPath('data.booking.transaction_history.billing.grand_total', 105000)
        ->assertJsonPath('data.booking.transaction_history.orders.0.order_number', 'ORD-API-HIST')
        ->assertJsonPath('data.booking.transaction_history.orders.0.items.0.item_name', 'API History Item')
        ->assertJsonPath('data.booking.transaction_history.song_requests.0.song_title', 'Test Song')
        ->assertJsonPath('data.booking.transaction_history.song_requests.0.tip', 25000)
        ->assertJsonPath('data.booking.transaction_history.display_messages.0.message', 'Happy birthday!')
        ->assertJsonPath('data.booking.transaction_history.display_messages.0.tip', 15000);
});
