<?php

use App\Models\Area;
use App\Models\Billing;
use App\Models\CustomerKeep;
use App\Models\CustomerUser;
use App\Models\Order;
use App\Models\Reward;
use App\Models\SongRequest;
use App\Models\Tabel;
use App\Models\TableSession;
use App\Models\Tier;
use App\Models\User;
use App\Models\UserProfile;

function createFeatureCustomer(array $overrides = []): User
{
    $user = User::factory()->create(array_merge([
        'name' => 'Feature Customer',
    ], $overrides));

    $profile = UserProfile::create([
        'user_id' => $user->id,
        'phone' => '08123456789',
    ]);

    CustomerUser::create([
        'user_id' => $user->id,
        'user_profile_id' => $profile->id,
        'total_visits' => 5,
        'lifetime_spending' => 5000000,
    ]);

    return $user->fresh();
}

function createFeatureActiveSession(User $user): TableSession
{
    $area = Area::create([
        'code' => 'FEATURE-'.uniqid(),
        'name' => 'Feature Area',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $table = Tabel::create([
        'area_id' => $area->id,
        'table_number' => 'FEATURE-'.uniqid(),
        'qr_code' => 'QR-'.uniqid(),
        'capacity' => 4,
        'minimum_charge' => 0,
        'status' => 'occupied',
        'is_active' => true,
    ]);

    return TableSession::create([
        'table_id' => $table->id,
        'customer_id' => $user->id,
        'session_code' => 'FEATURE-'.uniqid(),
        'status' => 'active',
        'checked_in_at' => now(),
    ]);
}

// === Song Requests ===

it('submits a song request', function () {
    $user = createFeatureCustomer();
    createFeatureActiveSession($user);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/song-requests', [
            'song_title' => 'Bohemian Rhapsody',
            'artist' => 'Queen',
            'tip' => 50000,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.song_request.song_title', 'Bohemian Rhapsody')
        ->assertJsonPath('data.song_request.status', 'pending');
});

it('lists own song requests', function () {
    $user = createFeatureCustomer();

    SongRequest::create([
        'customer_user_id' => $user->customerUser->id,
        'song_title' => 'Test Song',
        'artist' => 'Test Artist',
        'tip' => 0,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/song-requests');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data.song_requests');
});

// === Customer Keeps (Bottles) ===

it('lists own bottles', function () {
    $user = createFeatureCustomer();

    CustomerKeep::create([
        'customer_user_id' => $user->customerUser->id,
        'item_name' => 'Jack Daniels',
        'type' => 'weekday',
        'quantity' => 0.5,
        'unit' => 'bottle',
        'status' => 'active',
        'stored_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/bottles');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data.bottles')
        ->assertJsonPath('data.bottles.0.item_name', 'Jack Daniels');
});

// === Leaderboard ===

it('shows leaderboard', function () {
    createFeatureCustomer(['email' => 'leader1@test.com', 'name' => 'Top Spender']);

    $response = $this->getJson('/api/v1/leaderboard');

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => ['leaderboard']]);
});

it('shows own leaderboard rank', function () {
    $user = createFeatureCustomer();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/leaderboard/my-rank');

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => ['ranking' => ['rank', 'name', 'points']]]);
});

it('leaderboard returns period field in response', function () {
    createFeatureCustomer(['email' => 'leader2@test.com', 'name' => 'Period Tester']);

    foreach (['day', 'week', 'month', 'year'] as $period) {
        $response = $this->getJson("/api/v1/leaderboard?period={$period}");

        $response->assertSuccessful()
            ->assertJsonPath('data.period', $period);
    }
});

it('leaderboard defaults to all_time when no period given', function () {
    createFeatureCustomer(['email' => 'leader3@test.com', 'name' => 'All Time Tester']);

    $response = $this->getJson('/api/v1/leaderboard');

    $response->assertSuccessful()
        ->assertJsonPath('data.period', 'all_time');
});

it('all time leaderboard uses stored lifetime spending and visits consistently', function () {
    $topSpender = createFeatureCustomer([
        'email' => 'stored-top@test.com',
        'name' => 'Stored Top Spender',
    ]);
    $topSpender->customerUser->update([
        'lifetime_spending' => 900000,
        'total_visits' => 4,
    ]);

    $topVisitor = createFeatureCustomer([
        'email' => 'stored-visits@test.com',
        'name' => 'Stored Top Visitor',
    ]);
    $topVisitor->customerUser->update([
        'lifetime_spending' => 100000,
        'total_visits' => 20,
    ]);

    $this->getJson('/api/v1/leaderboard')
        ->assertSuccessful()
        ->assertJsonPath('data.period', 'all_time')
        ->assertJsonPath('data.leaderboard.0.customer_user_id', $topSpender->customerUser->id)
        ->assertJsonPath('data.leaderboard.0.lifetime_spending', 900000)
        ->assertJsonPath('data.leaderboard.0.period_spending', 900000)
        ->assertJsonPath('data.leaderboard.0.total_visits', 4)
        ->assertJsonPath('data.leaderboard.0.period_visits', 4);

    $this->getJson('/api/v1/leaderboard?sort_by=visits')
        ->assertSuccessful()
        ->assertJsonPath('data.leaderboard.0.customer_user_id', $topVisitor->customerUser->id)
        ->assertJsonPath('data.leaderboard.0.total_visits', 20)
        ->assertJsonPath('data.leaderboard.0.period_visits', 20);

    $this->actingAs($topVisitor, 'sanctum')
        ->getJson('/api/v1/leaderboard/my-rank')
        ->assertSuccessful()
        ->assertJsonPath('data.ranking.rank', 2)
        ->assertJsonPath('data.ranking.period_spending', 100000)
        ->assertJsonPath('data.ranking.period_visits', 20);
});

it('leaderboard ignores invalid period and defaults to all_time', function () {
    createFeatureCustomer(['email' => 'leader4@test.com', 'name' => 'Invalid Period Tester']);

    $response = $this->getJson('/api/v1/leaderboard?period=decade');

    $response->assertSuccessful()
        ->assertJsonPath('data.period', 'all_time');
});

it('leaderboard resource includes period_spending and period_visits', function () {
    createFeatureCustomer(['email' => 'leader5@test.com', 'name' => 'Resource Tester']);

    $response = $this->getJson('/api/v1/leaderboard?period=month');

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => ['leaderboard' => [['period_spending', 'period_visits']]]]);
});

it('period leaderboard includes paid walk ins and excludes force closed bills', function () {
    $customer = createFeatureCustomer([
        'email' => 'period-walk-in@test.com',
        'name' => 'Period Walk-in Customer',
    ]);
    $cashier = User::factory()->create();

    foreach ([['paid', 75000], ['force_closed', 500000]] as [$status, $total]) {
        $order = Order::create([
            'table_session_id' => null,
            'customer_user_id' => $customer->customerUser->id,
            'created_by' => $cashier->id,
            'order_number' => 'PERIOD-'.uniqid(),
            'status' => 'pending',
            'items_total' => $total,
            'discount_amount' => 0,
            'total' => $total,
            'ordered_at' => now(),
        ]);

        Billing::create([
            'table_session_id' => null,
            'order_id' => $order->id,
            'is_walk_in' => true,
            'is_booking' => false,
            'minimum_charge' => 0,
            'orders_total' => $total,
            'subtotal' => $total,
            'tax' => 0,
            'tax_percentage' => 0,
            'service_charge' => 0,
            'service_charge_percentage' => 0,
            'discount_amount' => 0,
            'grand_total' => $total,
            'paid_amount' => $status === 'paid' ? $total : 0,
            'billing_status' => $status,
            'transaction_code' => 'PERIOD-BILL-'.uniqid(),
            'payment_method' => 'cash',
            'payment_mode' => 'normal',
            'paid_at' => now(),
        ]);
    }

    $this->getJson('/api/v1/leaderboard?period=month')
        ->assertSuccessful()
        ->assertJsonPath('data.leaderboard.0.customer_user_id', $customer->customerUser->id)
        ->assertJsonPath('data.leaderboard.0.period_spending', 75000)
        ->assertJsonPath('data.leaderboard.0.period_visits', 1);
});

// === Rewards ===

it('lists available rewards', function () {
    Reward::factory()->create([
        'name' => 'Free Drink',
        'category' => 'drink',
        'points_required' => 100,
        'stock' => 10,
        'is_active' => true,
    ]);

    $user = createFeatureCustomer();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/rewards');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data.rewards');
});

it('redeems a reward', function () {
    $reward = Reward::factory()->create([
        'name' => 'Free Drink',
        'category' => 'drink',
        'points_required' => 100,
        'stock' => 10,
        'is_active' => true,
    ]);

    // Create customer with 5M spending -> 500 points
    $user = createFeatureCustomer();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/rewards/redeem', [
            'reward_id' => $reward->id,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.redemption.points_spent', 100);

    $this->assertDatabaseHas('reward_redemptions', [
        'customer_user_id' => $user->customerUser->id,
        'reward_id' => $reward->id,
    ]);

    expect($reward->fresh()->stock)->toBe(9);

    // Points should be deducted: 500 - 100 = 400
    expect($user->customerUser->fresh()->points)->toBe(400);
});

it('deducts points after multiple redemptions', function () {
    $reward = Reward::factory()->create([
        'name' => 'Small Drink',
        'category' => 'drink',
        'points_required' => 200,
        'stock' => 10,
        'is_active' => true,
    ]);

    // 5M spending -> 500 points
    $user = createFeatureCustomer();

    expect($user->customerUser->points)->toBe(500);

    // First redemption: 500 - 200 = 300
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/rewards/redeem', ['reward_id' => $reward->id])
        ->assertStatus(201);

    expect($user->customerUser->fresh()->points)->toBe(300);

    // Second redemption: 300 - 200 = 100
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/rewards/redeem', ['reward_id' => $reward->id])
        ->assertStatus(201);

    expect($user->customerUser->fresh()->points)->toBe(100);

    // Third should fail: 100 < 200
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/rewards/redeem', ['reward_id' => $reward->id])
        ->assertStatus(422);
});

it('rejects reward redeem with insufficient points', function () {
    $reward = Reward::factory()->create([
        'name' => 'VIP Access',
        'category' => 'vip',
        'points_required' => 999999,
        'stock' => 5,
        'is_active' => true,
    ]);

    $user = createFeatureCustomer();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/rewards/redeem', [
            'reward_id' => $reward->id,
        ]);

    $response->assertStatus(422);
});

// === Membership ===

it('shows tiers list', function () {
    Tier::create([
        'level' => 1,
        'name' => 'Bronze',
        'discount_percentage' => 0,
        'minimum_spent' => 0,
        'is_first_tier' => true,
        'color' => 'amber',
    ]);

    $response = $this->getJson('/api/v1/tiers');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data.tiers');
});

it('shows own membership info', function () {
    $tier = Tier::create([
        'level' => 1,
        'name' => 'Bronze',
        'discount_percentage' => 0,
        'minimum_spent' => 0,
        'is_first_tier' => true,
        'color' => 'amber',
    ]);

    $user = createFeatureCustomer();
    $user->customerUser->update(['tier_id' => $tier->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/membership');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'membership' => ['current_tier', 'points', 'lifetime_spending', 'total_visits'],
                'all_tiers',
            ],
        ]);
});

it('returns member QR data', function () {
    $user = createFeatureCustomer();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/membership/qr');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'qr_data' => ['type', 'user_id', 'customer_user_id', 'name', 'email'],
            ],
        ])
        ->assertJsonPath('data.qr_data.type', 'member');
});

// === Profile ===

it('shows own profile', function () {
    $user = createFeatureCustomer();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/profile');

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email', 'profile']]]);
});

it('updates own profile', function () {
    $user = createFeatureCustomer();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/profile', [
            'name' => 'Updated Name',
            'phone' => '08999888777',
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.user.name', 'Updated Name');

    $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated Name']);
    $this->assertDatabaseHas('user_profiles', ['user_id' => $user->id, 'phone' => '08999888777']);
});
