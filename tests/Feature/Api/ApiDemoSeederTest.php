<?php

use App\Models\Area;
use App\Models\BankAccount;
use App\Models\CustomerUser;
use App\Models\Event;
use App\Models\Promo;
use App\Models\QrisSetting;
use App\Models\Reward;
use App\Models\TableReservation;
use App\Models\TableSession;
use App\Models\User;
use App\Models\WhatsappSetting;
use Database\Seeders\ApiDemoSeeder;

it('creates complete API demo data idempotently', function () {
    $this->seed(ApiDemoSeeder::class);
    $this->seed(ApiDemoSeeder::class);

    $user = User::query()->where('email', ApiDemoSeeder::CUSTOMER_EMAIL)->firstOrFail();

    expect(User::query()->where('email', ApiDemoSeeder::CUSTOMER_EMAIL)->count())->toBe(1)
        ->and($user->profile)->not->toBeNull()
        ->and($user->customerUser)->toBeInstanceOf(CustomerUser::class)
        ->and($user->customerUser->tier)->not->toBeNull()
        ->and(Area::query()->whereIn('code', ['LNG', 'ROOM'])->count())->toBe(2)
        ->and(Event::query()->where('slug', 'api-demo-tonight')->count())->toBe(1)
        ->and(Promo::query()->where('slug', 'api-demo-happy-hour')->count())->toBe(1)
        ->and(Reward::query()->where('name', 'API Demo House Cocktail')->count())->toBe(1)
        ->and(BankAccount::query()->where('is_active', true)->count())->toBeGreaterThanOrEqual(2)
        ->and(QrisSetting::query()->where('is_active', true)->exists())->toBeTrue()
        ->and(WhatsappSetting::query()->where('is_active', true)->exists())->toBeTrue()
        ->and(TableReservation::query()->whereBetween('booking_code', [9_900_001, 9_900_003])->count())->toBe(3)
        ->and(TableSession::query()->where('session_code', 'API-DEMO-ACTIVE-SESSION')->where('status', 'active')->exists())->toBeTrue();
});

it('provides working credentials and populated API responses', function () {
    $this->seed(ApiDemoSeeder::class);

    $login = $this->postJson('/api/v1/login', [
        'email' => ApiDemoSeeder::CUSTOMER_EMAIL,
        'password' => ApiDemoSeeder::CUSTOMER_PASSWORD,
        'device_name' => 'Pest API Demo',
    ]);

    $login->assertSuccessful()
        ->assertJsonPath('data.user.email', ApiDemoSeeder::CUSTOMER_EMAIL)
        ->assertJsonStructure(['data' => ['token']]);

    $token = $login->json('data.token');
    $headers = ['Authorization' => 'Bearer '.$token];

    $this->withHeaders($headers)->getJson('/api/v1/me')
        ->assertSuccessful()
        ->assertJsonPath('data.user.email', ApiDemoSeeder::CUSTOMER_EMAIL);

    $this->getJson('/api/v1/events?filter=today')
        ->assertSuccessful()
        ->assertJsonPath('data.events.0.slug', 'api-demo-tonight');

    $this->getJson('/api/v1/promos?filter=today')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.promos');

    $this->getJson('/api/v1/payment-info')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data.bank_accounts');

    $this->withHeaders($headers)->getJson('/api/v1/bookings')
        ->assertSuccessful()
        ->assertJsonPath('data.meta.total', 3);

    $this->withHeaders($headers)->getJson('/api/v1/bottles')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data.bottles');

    $this->withHeaders($headers)->getJson('/api/v1/rewards/my-redemptions')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.redemptions');

    $this->withHeaders($headers)->getJson('/api/v1/song-requests')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.song_requests');

    $this->withHeaders($headers)->getJson('/api/v1/display-messages')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.display_messages');
});
