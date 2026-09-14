<?php

use App\Models\Area;
use App\Models\CustomerUser;
use App\Models\DailyAuthCode;
use App\Models\InventoryItem;
use App\Models\PosCategorySetting;
use App\Models\Tabel;
use App\Models\TableReservation;
use App\Models\TableSession;
use App\Models\User;
use App\Models\UserProfile;

use function Pest\Laravel\actingAs;

function discountAuthItem(): InventoryItem
{
    PosCategorySetting::clearCache();
    PosCategorySetting::firstOrCreate(
        ['category_type' => 'beverage'],
        ['show_in_pos' => true, 'is_menu' => false, 'is_item_group' => false, 'preparation_location' => 'bar', 'source' => 'inventory'],
    );
    PosCategorySetting::clearCache();

    return InventoryItem::create([
        'code' => 'AUTH-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Auth Test Item '.uniqid(),
        'category_type' => 'beverage',
        'price' => 25000,
        'stock_quantity' => 50,
        'unit' => 'glass',
        'is_active' => true,
        'is_visible_in_pos' => true,
    ]);
}

function discountAuthCart(InventoryItem $item): array
{
    return [
        'item_'.$item->id => [
            'id' => 'item_'.$item->id,
            'name' => $item->name,
            'price' => (float) $item->price,
            'quantity' => 1,
            'preparation_location' => 'bar',
        ],
    ];
}

function discountAuthWalkInCustomer(): User
{
    $customer = User::factory()->create();
    UserProfile::create(['user_id' => $customer->id]);
    CustomerUser::create([
        'user_id' => $customer->id,
        'user_profile_id' => UserProfile::where('user_id', $customer->id)->first()->id,
        'total_visits' => 0,
        'lifetime_spending' => 0,
    ]);

    return $customer;
}

function discountAuthBookingSession(): array
{
    $customer = discountAuthWalkInCustomer();

    $area = Area::create(['code' => 'AUTH-'.uniqid(), 'name' => 'Auth Area', 'is_active' => true, 'sort_order' => 1]);
    $table = Tabel::create([
        'area_id' => $area->id,
        'table_number' => 'AUTH-'.uniqid(),
        'qr_code' => 'QRAUTH-'.uniqid(),
        'capacity' => 4,
        'minimum_charge' => 0,
        'status' => 'reserved',
        'is_active' => true,
    ]);
    $booking = TableReservation::create([
        'booking_code' => random_int(100000, 999999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->toDateString(),
        'reservation_time' => now()->format('H:i:s'),
        'status' => 'checked_in',
    ]);
    $session = TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'waiter_id' => 1,
        'session_code' => 'SESAUTH-'.uniqid(),
        'checked_in_at' => now(),
        'status' => 'active',
    ]);

    return [$customer, $table];
}

test('walk in without discount ignores stale invalid auth code', function () {
    $admin = adminUser();
    $customer = discountAuthWalkInCustomer();
    $item = discountAuthItem();

    $response = actingAs($admin)
        ->withSession(['pos_cart' => discountAuthCart($item)])
        ->postJson(route('admin.pos.checkout'), [
            'customer_type' => 'walk-in',
            'walk_in_customer_id' => $customer->id,
            'payment_mode' => 'normal',
            'payment_method' => 'cash',
            'discount_type' => 'none',
            'discount_auth_code' => '12',
        ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('discount_amount', 0);
});

test('walk in booking without discount ignores stale alphabetic auth code', function () {
    $admin = adminUser();
    [$customer, $table] = discountAuthBookingSession();
    $item = discountAuthItem();

    $response = actingAs($admin)
        ->withSession(['pos_cart' => discountAuthCart($item)])
        ->postJson(route('admin.pos.checkout'), [
            'customer_type' => 'booking',
            'customer_user_id' => $customer->id,
            'table_id' => $table->id,
            'discount_type' => 'none',
            'discount_percentage' => 0,
            'discount_auth_code' => 'abcd',
        ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('discount_amount', 0);
});

test('walk in manual discount with malformed auth code returns indonesian format message', function () {
    $admin = adminUser();
    $customer = discountAuthWalkInCustomer();
    $item = discountAuthItem();

    DailyAuthCode::query()->updateOrCreate(
        ['date' => now()->format('Y-m-d')],
        ['code' => '9753', 'override_code' => null, 'generated_at' => now()],
    );

    $response = actingAs($admin)
        ->withSession(['pos_cart' => discountAuthCart($item)])
        ->postJson(route('admin.pos.checkout'), [
            'customer_type' => 'walk-in',
            'walk_in_customer_id' => $customer->id,
            'payment_mode' => 'normal',
            'payment_method' => 'cash',
            'discount_type' => 'percentage',
            'discount_percentage' => 10,
            'discount_auth_code' => '12',
        ]);

    $response
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Auth code diskon harus 4 digit.');
});

test('booking tier percentage without auth code keeps requiring manager auth', function () {
    $admin = adminUser();
    [$customer, $table] = discountAuthBookingSession();
    $item = discountAuthItem();

    $response = actingAs($admin)
        ->withSession(['pos_cart' => discountAuthCart($item)])
        ->postJson(route('admin.pos.checkout'), [
            'customer_type' => 'booking',
            'customer_user_id' => $customer->id,
            'table_id' => $table->id,
            'discount_type' => 'none',
            'discount_percentage' => 10,
        ]);

    $response
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Auth code wajib diisi untuk diskon.');
});

test('booking nominal discount without auth code is rejected', function () {
    $admin = adminUser();
    [$customer, $table] = discountAuthBookingSession();
    $item = discountAuthItem();

    DailyAuthCode::query()->updateOrCreate(
        ['date' => now()->format('Y-m-d')],
        ['code' => '9753', 'override_code' => null, 'generated_at' => now()],
    );

    $response = actingAs($admin)
        ->withSession(['pos_cart' => discountAuthCart($item)])
        ->postJson(route('admin.pos.checkout'), [
            'customer_type' => 'booking',
            'customer_user_id' => $customer->id,
            'table_id' => $table->id,
            'discount_type' => 'nominal',
            'discount_nominal' => 10000,
        ]);

    $response
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Auth code wajib diisi untuk diskon.');

    $allowed = actingAs($admin)
        ->withSession(['pos_cart' => discountAuthCart($item)])
        ->postJson(route('admin.pos.checkout'), [
            'customer_type' => 'booking',
            'customer_user_id' => $customer->id,
            'table_id' => $table->id,
            'discount_type' => 'nominal',
            'discount_nominal' => 10000,
            'discount_auth_code' => '9753',
        ]);

    $allowed
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('discount_amount', 10000);
});

test('walk in manual discount with valid daily auth code succeeds', function () {
    $admin = adminUser();
    $customer = discountAuthWalkInCustomer();
    $item = discountAuthItem();

    DailyAuthCode::query()->updateOrCreate(
        ['date' => now()->format('Y-m-d')],
        ['code' => '9753', 'override_code' => null, 'generated_at' => now()],
    );

    $response = actingAs($admin)
        ->withSession(['pos_cart' => discountAuthCart($item)])
        ->postJson(route('admin.pos.checkout'), [
            'customer_type' => 'walk-in',
            'walk_in_customer_id' => $customer->id,
            'payment_mode' => 'normal',
            'payment_method' => 'cash',
            'discount_type' => 'percentage',
            'discount_percentage' => 10,
            'discount_auth_code' => '9753',
        ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('discount_amount', 2500);
});
