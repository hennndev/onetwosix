<?php

use App\Models\CustomerUser;
use App\Models\Tier;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\FirebaseFcmService;

function createCustomerUser(array $overrides = []): User
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

it('registers a new customer', function () {
    $response = $this->postJson('/api/v1/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'phone' => '081234567890',
        'device_name' => 'iPhone 15',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'error',
            'message',
            'data' => [
                'user' => ['id', 'name', 'email', 'profile', 'customer'],
                'token',
            ],
        ]);

    $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
    $this->assertDatabaseHas('user_profiles', ['phone' => '081234567890']);
    $this->assertDatabaseHas('customer_users', ['user_id' => $response->json('data.user.id')]);
});

it('assigns first tier on registration', function () {
    $tier = Tier::create([
        'level' => 1,
        'name' => 'Bronze',
        'discount_percentage' => 0,
        'minimum_spent' => 0,
        'is_first_tier' => true,
        'color' => 'amber',
    ]);

    $response = $this->postJson('/api/v1/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'phone' => '081234567890',
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('customer_users', [
        'user_id' => $response->json('data.user.id'),
        'tier_id' => $tier->id,
    ]);
});

it('validates registration fields', function () {
    $response = $this->postJson('/api/v1/register', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password', 'phone']);
});

it('rejects duplicate email on registration', function () {
    User::factory()->create(['email' => 'exists@test.com']);

    $response = $this->postJson('/api/v1/register', [
        'name' => 'John',
        'email' => 'exists@test.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'phone' => '081234567890',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('logs in a customer', function () {
    createCustomerUser(['email' => 'login@test.com']);

    $response = $this->postJson('/api/v1/login', [
        'email' => 'login@test.com',
        'password' => 'password',
        'device_name' => 'iPhone 15',
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'error',
            'message',
            'data' => [
                'user' => ['id', 'name', 'email'],
                'token',
            ],
        ]);
});

it('rejects wrong password', function () {
    createCustomerUser(['email' => 'login@test.com']);

    $response = $this->postJson('/api/v1/login', [
        'email' => 'login@test.com',
        'password' => 'wrong-password',
        'device_name' => 'iPhone 15',
    ]);

    $response->assertStatus(401);
});

it('rejects non-customer login', function () {
    User::factory()->create(['email' => 'admin@test.com']);

    $response = $this->postJson('/api/v1/login', [
        'email' => 'admin@test.com',
        'password' => 'password',
        'device_name' => 'iPhone 15',
    ]);

    $response->assertStatus(403);
});

it('logs out and revokes token', function () {
    $user = createCustomerUser();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->postJson('/api/v1/logout');

    $response->assertSuccessful();
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('returns current user with /me', function () {
    $user = createCustomerUser();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/me');

    $response->assertSuccessful()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.name', $user->name);
});

it('stores firebase token on /me when token changes', function () {
    $user = createCustomerUser([
        'token_firebase' => 'old-token',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/me?token_firebase=new-token');

    $response->assertSuccessful();
    expect($user->fresh()->token_firebase)->toBe('new-token');
});

it('does not update firebase token on /me when token is unchanged', function () {
    $user = createCustomerUser([
        'token_firebase' => 'same-token',
    ]);

    $updatedAt = $user->updated_at;

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/me?token_firebase=same-token');

    $response->assertSuccessful();
    expect($user->fresh()->updated_at->equalTo($updatedAt))->toBeTrue();
});

it('rejects unauthenticated /me request', function () {
    $response = $this->getJson('/api/v1/me');

    $response->assertStatus(401);
});

it('logs in a customer using phone number', function () {
    createCustomerUser(['email' => 'phone-login@test.com']);

    $response = $this->postJson('/api/v1/login', [
        'email' => '08123456789',
        'password' => 'password',
        'device_name' => 'iPhone 15',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Login berhasil.');
});

it('rejects login with wrong password using phone number', function () {
    createCustomerUser(['email' => 'phone-wrong@test.com']);

    $response = $this->postJson('/api/v1/login', [
        'email' => '08123456789',
        'password' => 'wrong-password',
        'device_name' => 'iPhone 15',
    ]);

    $response->assertStatus(401);
});

it('rejects login with non-existent phone number', function () {
    $response = $this->postJson('/api/v1/login', [
        'email' => '089999999999',
        'password' => 'password',
        'device_name' => 'iPhone 15',
    ]);

    $response->assertStatus(401);
});

it('rejects login without email or phone', function () {
    $response = $this->postJson('/api/v1/login', [
        'password' => 'password',
        'device_name' => 'iPhone 15',
    ]);

    $response->assertStatus(422);
});

it('sends test firebase notification', function () {
    $user = createCustomerUser([
        'token_firebase' => 'firebase-token',
    ]);

    $firebase = Mockery::mock(FirebaseFcmService::class);
    $firebase->shouldReceive('sendToToken')
        ->once()
        ->with('firebase-token', 'Test Title', 'Test Body', [
            'type' => 'test_notification',
            'user_id' => (string) $user->id,
        ])
        ->andReturn(['sent' => true, 'status' => 200]);

    $this->app->instance(FirebaseFcmService::class, $firebase);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/notifications/test', [
            'title' => 'Test Title',
            'body' => 'Test Body',
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Notifikasi berhasil dikirim.');
});

it('rejects test firebase notification when user has no token', function () {
    $user = createCustomerUser();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/notifications/test');

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Token Firebase user belum tersedia.');
});
