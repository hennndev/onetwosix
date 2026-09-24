<?php

use App\Models\Area;
use App\Models\Billing;
use App\Models\CustomerUser;
use App\Models\DisplayMessageRequest;
use App\Models\Tabel;
use App\Models\TableSession;
use App\Models\User;
use App\Models\UserProfile;

function createDisplayMessageCustomer(array $overrides = []): User
{
    $user = User::factory()->create(array_merge([
        'name' => 'Test Customer',
        'email' => 'customer@test.com',
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

function createDmActiveSession(User $user): TableSession
{
    $area = Area::create([
        'code' => 'AREA-'.uniqid(),
        'name' => 'DM Test Area',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $table = Tabel::create([
        'area_id' => $area->id,
        'table_number' => 'T-'.uniqid(),
        'qr_code' => 'QR-'.uniqid(),
        'capacity' => 4,
        'minimum_charge' => 100000,
        'status' => 'occupied',
        'is_active' => true,
    ]);

    $session = TableSession::create([
        'table_id' => $table->id,
        'customer_id' => $user->id,
        'session_code' => 'SES-'.uniqid(),
        'status' => 'active',
        'checked_in_at' => now(),
    ]);

    return $session->fresh();
}

function createDmBillingForSession(TableSession $session): Billing
{
    $billing = Billing::create([
        'table_session_id' => $session->id,
        'is_walk_in' => false,
        'is_booking' => true,
        'minimum_charge' => 100000,
        'orders_total' => 0,
        'subtotal' => 0,
        'song_tip' => 0,
        'display_tip' => 0,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => 100000,
        'paid_amount' => 0,
        'billing_status' => 'draft',
    ]);

    $session->update(['billing_id' => $billing->id]);

    return $billing->fresh();
}

it('lists all display messages queue for authenticated user', function () {
    $user1 = createDisplayMessageCustomer(['name' => 'User One', 'email' => 'dm-user1@test.com']);
    $user2 = createDisplayMessageCustomer(['name' => 'User Two', 'email' => 'dm-user2@test.com']);

    DisplayMessageRequest::create([
        'customer_id' => $user1->id,
        'message' => 'Hello from user 1!',
        'tip' => 50000,
        'status' => 'pending',
    ]);

    DisplayMessageRequest::create([
        'customer_id' => $user2->id,
        'message' => 'Hello from user 2!',
        'tip' => 30000,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user1, 'sanctum')
        ->getJson('/api/v1/display-messages');

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data.display_messages');
});

it('lists only today display messages', function () {
    $user = createDisplayMessageCustomer(['name' => 'User One', 'email' => 'dm-today@test.com']);

    $todayMessage = DisplayMessageRequest::create([
        'customer_id' => $user->id,
        'message' => 'Today message',
        'tip' => 10000,
        'status' => 'pending',
    ]);

    $yesterdayMessage = DisplayMessageRequest::create([
        'customer_id' => $user->id,
        'message' => 'Yesterday message',
        'tip' => 10000,
        'status' => 'pending',
    ]);

    DisplayMessageRequest::query()
        ->whereKey($yesterdayMessage->id)
        ->update(['created_at' => now()->subDay()]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/display-messages');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data.display_messages')
        ->assertJsonPath('data.display_messages.0.id', $todayMessage->id);
});

it('includes user info in display message response', function () {
    $user = createDisplayMessageCustomer(['name' => 'John Doe', 'email' => 'dm-john@test.com']);

    DisplayMessageRequest::create([
        'customer_id' => $user->id,
        'message' => 'Happy birthday!',
        'tip' => 0,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/display-messages');

    $response->assertSuccessful()
        ->assertJsonPath('data.display_messages.0.user.id', $user->id)
        ->assertJsonPath('data.display_messages.0.user.name', 'John Doe');
});

it('includes is_mine flag correctly for display messages', function () {
    $user1 = createDisplayMessageCustomer(['name' => 'User One', 'email' => 'dm-mine1@test.com']);
    $user2 = createDisplayMessageCustomer(['name' => 'User Two', 'email' => 'dm-mine2@test.com']);

    DisplayMessageRequest::create([
        'customer_id' => $user1->id,
        'message' => 'My message',
        'tip' => 0,
        'status' => 'pending',
    ]);

    DisplayMessageRequest::create([
        'customer_id' => $user2->id,
        'message' => 'Other message',
        'tip' => 0,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user1, 'sanctum')
        ->getJson('/api/v1/display-messages');

    $response->assertSuccessful();

    $messages = $response->json('data.display_messages');
    $myMessage = collect($messages)->firstWhere('message', 'My message');
    $otherMessage = collect($messages)->firstWhere('message', 'Other message');

    expect($myMessage['is_mine'])->toBeTrue()
        ->and($otherMessage['is_mine'])->toBeFalse();
});

it('creates a display message successfully', function () {
    $user = createDisplayMessageCustomer(['email' => 'dm-store@test.com']);
    $session = createDmActiveSession($user);
    createDmBillingForSession($session);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/display-messages', [
            'message' => 'Happy New Year everyone!',
            'tip' => 100000,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.display_message.message', 'Happy New Year everyone!')
        ->assertJsonPath('data.display_message.status', 'pending')
        ->assertJsonPath('data.display_message.is_mine', true)
        ->assertJsonPath('data.display_message.user.name', $user->name);
});

it('returns 403 when user has no active session for display message', function () {
    $user = createDisplayMessageCustomer(['email' => 'dm-nosession@test.com']);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/display-messages', ['message' => 'Hello!', 'tip' => 50000])
        ->assertForbidden()
        ->assertJsonPath('message', 'Kamu tidak memiliki booking aktif. Display message hanya bisa dikirim saat check-in.');
});

it('does not update billing display_tip when display message is stored (only on displayed)', function () {
    $user = createDisplayMessageCustomer(['email' => 'dm-tiptest@test.com']);
    $session = createDmActiveSession($user);
    $billing = createDmBillingForSession($session);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/display-messages', ['message' => 'Happy birthday!', 'tip' => 75000])
        ->assertStatus(201);

    $billing->refresh();

    expect((float) $billing->display_tip)->toBe(0.0)
        ->and((float) $billing->grand_total)->toBe(100000.0);
});

it('does not accumulate display_tip in billing across multiple stored display messages', function () {
    $user = createDisplayMessageCustomer(['email' => 'dm-accum@test.com']);
    $session = createDmActiveSession($user);
    $billing = createDmBillingForSession($session);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/display-messages', ['message' => 'First message', 'tip' => 40000])
        ->assertStatus(201);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/display-messages', ['message' => 'Second message', 'tip' => 60000])
        ->assertStatus(201);

    $billing->refresh();

    expect((float) $billing->display_tip)->toBe(0.0)
        ->and((float) $billing->grand_total)->toBe(100000.0);
});

it('shows display message detail with user info', function () {
    $user = createDisplayMessageCustomer(['email' => 'dm-show@test.com']);

    $displayMessage = DisplayMessageRequest::create([
        'customer_id' => $user->id,
        'message' => 'Congratulations!',
        'tip' => 25000,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/display-messages/{$displayMessage->id}");

    $response->assertSuccessful()
        ->assertJsonPath('data.display_message.message', 'Congratulations!')
        ->assertJsonPath('data.display_message.is_mine', true)
        ->assertJsonPath('data.display_message.user.id', $user->id);
});

it('validates message is required for display messages', function () {
    $user = createDisplayMessageCustomer(['email' => 'dm-validate@test.com']);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/display-messages', [
            'tip' => 50000,
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['message']);
});

it('requires authentication for display messages', function () {
    $response = $this->getJson('/api/v1/display-messages');

    $response->assertUnauthorized();
});
