<?php

use App\Models\Area;
use App\Models\Billing;
use App\Models\CustomerUser;
use App\Models\SongRequest;
use App\Models\Tabel;
use App\Models\TableSession;
use App\Models\User;
use App\Models\UserProfile;

function createSongRequestCustomer(array $overrides = []): User
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

function createActiveSession(User $user, ?Billing $billing = null): TableSession
{
    $area = Area::create([
        'code' => 'AREA-'.uniqid(),
        'name' => 'Test Area',
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

    if ($billing) {
        $billing->update(['table_session_id' => $session->id]);
        $session->update(['billing_id' => $billing->id]);
    }

    return $session->fresh();
}

function createBillingForSession(TableSession $session): Billing
{
    $billing = Billing::create([
        'table_session_id' => $session->id,
        'is_walk_in' => false,
        'is_booking' => true,
        'minimum_charge' => 100000,
        'orders_total' => 0,
        'subtotal' => 0,
        'song_tip' => 0,
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

it('lists all song requests queue for authenticated user', function () {
    $user1 = createSongRequestCustomer(['name' => 'User One', 'email' => 'user1@test.com']);
    $user2 = createSongRequestCustomer(['name' => 'User Two', 'email' => 'user2@test.com']);

    SongRequest::create([
        'customer_user_id' => $user1->customerUser->id,
        'song_title' => 'Bohemian Rhapsody',
        'artist' => 'Queen',
        'tip' => 50000,
        'status' => 'pending',
    ]);

    SongRequest::create([
        'customer_user_id' => $user2->customerUser->id,
        'song_title' => 'Hotel California',
        'artist' => 'Eagles',
        'tip' => 30000,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user1, 'sanctum')
        ->getJson('/api/v1/song-requests');

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data.song_requests');
});

it('lists only today song requests', function () {
    $user = createSongRequestCustomer(['name' => 'User One', 'email' => 'song-today@test.com']);

    $todayRequest = SongRequest::create([
        'customer_user_id' => $user->customerUser->id,
        'song_title' => 'Today Song',
        'artist' => 'Today Artist',
        'tip' => 10000,
        'status' => 'pending',
    ]);

    $yesterdayRequest = SongRequest::create([
        'customer_user_id' => $user->customerUser->id,
        'song_title' => 'Yesterday Song',
        'artist' => 'Yesterday Artist',
        'tip' => 10000,
        'status' => 'pending',
    ]);

    SongRequest::query()
        ->whereKey($yesterdayRequest->id)
        ->update(['created_at' => now()->subDay()]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/song-requests');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data.song_requests')
        ->assertJsonPath('data.song_requests.0.id', $todayRequest->id);
});

it('includes user info in song request response', function () {
    $user = createSongRequestCustomer(['name' => 'John Doe', 'email' => 'john@test.com']);

    SongRequest::create([
        'customer_user_id' => $user->customerUser->id,
        'song_title' => 'Imagine',
        'artist' => 'John Lennon',
        'tip' => 0,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/song-requests');

    $response->assertSuccessful()
        ->assertJsonPath('data.song_requests.0.user.id', $user->id)
        ->assertJsonPath('data.song_requests.0.user.name', 'John Doe');
});

it('includes is_mine flag correctly', function () {
    $user1 = createSongRequestCustomer(['name' => 'User One', 'email' => 'user1@test.com']);
    $user2 = createSongRequestCustomer(['name' => 'User Two', 'email' => 'user2@test.com']);

    SongRequest::create([
        'customer_user_id' => $user1->customerUser->id,
        'song_title' => 'My Song',
        'artist' => 'My Artist',
        'tip' => 0,
        'status' => 'pending',
    ]);

    SongRequest::create([
        'customer_user_id' => $user2->customerUser->id,
        'song_title' => 'Other Song',
        'artist' => 'Other Artist',
        'tip' => 0,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user1, 'sanctum')
        ->getJson('/api/v1/song-requests');

    $response->assertSuccessful();

    $songs = $response->json('data.song_requests');
    $mySong = collect($songs)->firstWhere('song_title', 'My Song');
    $otherSong = collect($songs)->firstWhere('song_title', 'Other Song');

    expect($mySong['is_mine'])->toBeTrue()
        ->and($otherSong['is_mine'])->toBeFalse();
});

it('creates a song request successfully when user has active session', function () {
    $user = createSongRequestCustomer(['email' => 'store@test.com']);
    createActiveSession($user);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/song-requests', [
            'song_title' => 'Stairway to Heaven',
            'artist' => 'Led Zeppelin',
            'tip' => 100000,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.song_request.song_title', 'Stairway to Heaven')
        ->assertJsonPath('data.song_request.status', 'pending')
        ->assertJsonPath('data.song_request.is_mine', true)
        ->assertJsonPath('data.song_request.user.name', $user->name);
});

it('returns 403 when user has no active session', function () {
    $user = createSongRequestCustomer(['email' => 'nosession@test.com']);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/song-requests', [
            'song_title' => 'Bohemian Rhapsody',
            'artist' => 'Queen',
            'tip' => 50000,
        ]);

    $response->assertForbidden()
        ->assertJsonPath('message', 'Kamu tidak memiliki booking aktif. Request lagu hanya bisa dilakukan saat check-in.');
});

it('does not update billing song_tip when song request is stored (only on played)', function () {
    $user = createSongRequestCustomer(['email' => 'tiptest@test.com']);
    $session = createActiveSession($user);
    $billing = createBillingForSession($session);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/song-requests', [
            'song_title' => 'Hotel California',
            'artist' => 'Eagles',
            'tip' => 75000,
        ])
        ->assertStatus(201);

    $billing->refresh();
    expect((float) $billing->song_tip)->toBe(0.0)
        ->and((float) $billing->grand_total)->toBe(100000.0);
});

it('does not accumulate song_tip in billing across multiple stored song requests', function () {
    $user = createSongRequestCustomer(['email' => 'multitip@test.com']);
    $session = createActiveSession($user);
    $billing = createBillingForSession($session);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/song-requests', [
            'song_title' => 'Song 1',
            'artist' => 'Artist 1',
            'tip' => 50000,
        ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/song-requests', [
            'song_title' => 'Song 2',
            'artist' => 'Artist 2',
            'tip' => 30000,
        ]);

    $billing->refresh();
    expect((float) $billing->song_tip)->toBe(0.0);
});

it('shows song request detail with user info', function () {
    $user = createSongRequestCustomer(['email' => 'show@test.com']);

    $songRequest = SongRequest::create([
        'customer_user_id' => $user->customerUser->id,
        'song_title' => 'Yesterday',
        'artist' => 'The Beatles',
        'tip' => 25000,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/song-requests/{$songRequest->id}");

    $response->assertSuccessful()
        ->assertJsonPath('data.song_request.song_title', 'Yesterday')
        ->assertJsonPath('data.song_request.is_mine', true)
        ->assertJsonPath('data.song_request.user.id', $user->id);
});

it('requires authentication for song requests', function () {
    $response = $this->getJson('/api/v1/song-requests');

    $response->assertUnauthorized();
});
