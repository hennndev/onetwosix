<?php

use App\Http\Controllers\Waiter\WaiterPosController;
use App\Models\Area;
use App\Models\InventoryItem;
use App\Models\PosCategorySetting;
use App\Models\Tabel;
use App\Models\TableSession;
use App\Models\User;
use App\Models\UserProfile;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

function makeLiveInventoryItem(array $attributes = []): InventoryItem
{
    PosCategorySetting::clearCache();

    PosCategorySetting::firstOrCreate(
        ['category_type' => 'beverage'],
        [
            'show_in_pos' => true,
            'is_menu' => false,
            'is_item_group' => false,
            'preparation_location' => 'bar',
            'source' => 'inventory',
        ]
    );

    PosCategorySetting::clearCache();

    return InventoryItem::create(array_merge([
        'code' => 'LIVE-ITEM-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Live Item '.uniqid(),
        'category_type' => 'beverage',
        'price' => 25000,
        'stock_quantity' => 20,
        'threshold' => 5,
        'unit' => 'glass',
        'is_active' => true,
        'is_visible_in_pos' => true,
    ], $attributes));
}

function makeLiveArea(): Area
{
    return Area::create([
        'code' => 'LIVE-AREA-'.uniqid(),
        'name' => 'Live Area '.uniqid(),
        'is_active' => true,
        'sort_order' => 1,
    ]);
}

function makeLiveTable(Area $area): Tabel
{
    return Tabel::create([
        'area_id' => $area->id,
        'table_number' => 'LIVE-TBL-'.uniqid(),
        'qr_code' => 'LIVE-QR-'.uniqid(),
        'capacity' => 4,
        'minimum_charge' => 0,
        'status' => 'available',
        'is_active' => true,
    ]);
}

test('admin pos live endpoint returns product availability payload', function () {
    $admin = adminUser();

    PosCategorySetting::create([
        'category_type' => 'beverage',
        'show_in_pos' => true,
        'is_menu' => false,
        'is_item_group' => false,
        'preparation_location' => 'bar',
    ]);

    $inStock = makeLiveInventoryItem(['stock_quantity' => 15]);
    $soldOut = makeLiveInventoryItem(['stock_quantity' => 0]);

    $response = actingAs($admin)->getJson(route('admin.pos.live'));

    $response->assertSuccessful();

    $products = collect($response->json('products'))->keyBy('id');

    expect($products->get('item_'.$inStock->id))->not->toBeNull()
        ->and($products->get('item_'.$inStock->id)['is_available'])->toBeTrue()
        ->and($products->get('item_'.$inStock->id)['stock'])->toBe(15)
        ->and($products->get('item_'.$soldOut->id)['is_available'])->toBeFalse()
        ->and($products->get('item_'.$soldOut->id)['stock'])->toBe(0);
});

test('admin pos live endpoint does not read or mutate the cashier cart session', function () {
    $admin = adminUser();

    $cart = [
        'item_1' => [
            'id' => 'item_1',
            'name' => 'Cart Item',
            'price' => 10000,
            'quantity' => 2,
            'preparation_location' => 'bar',
        ],
    ];

    actingAs($admin)
        ->withSession(['pos_cart' => $cart])
        ->getJson(route('admin.pos.live'))
        ->assertSuccessful();

    expect(session('pos_cart'))->toBe($cart);
});

test('admin pos live marks item group unavailable when flagged sold out', function () {
    $admin = adminUser();

    PosCategorySetting::create([
        'category_type' => 'main-course',
        'show_in_pos' => true,
        'is_menu' => true,
        'is_item_group' => true,
        'preparation_location' => 'kitchen',
    ]);

    $groupItem = makeLiveInventoryItem([
        'category_type' => 'main-course',
        'stock_quantity' => 0,
        'is_item_group' => true,
        'is_group_sold_out' => true,
    ]);

    $response = actingAs($admin)->getJson(route('admin.pos.live'));

    $response->assertSuccessful();

    $product = collect($response->json('products'))
        ->firstWhere('id', 'item_'.$groupItem->id);

    expect($product['is_available'])->toBeFalse()
        ->and($product['stock'])->toBeNull();
});

test('admin pos live resolves possible portions for item group with count portion enabled', function () {
    $admin = adminUser();

    PosCategorySetting::create([
        'category_type' => 'main-course',
        'show_in_pos' => true,
        'is_menu' => true,
        'is_item_group' => true,
        'preparation_location' => 'kitchen',
    ]);

    $menuItem = makeLiveInventoryItem([
        'accurate_id' => 8801,
        'category_type' => 'main-course',
        'stock_quantity' => 999,
        'is_item_group' => true,
        'is_count_portion_possible' => true,
        'detail_group' => [['accurate_id' => 9901, 'name' => 'Bahan', 'quantity' => 2]],
    ]);

    makeLiveInventoryItem([
        'accurate_id' => 9901,
        'stock_quantity' => 7,
    ]);

    $response = actingAs($admin)->getJson(route('admin.pos.live'));

    $response->assertSuccessful();

    $product = collect($response->json('products'))
        ->firstWhere('id', 'item_'.$menuItem->id);

    expect($product['is_available'])->toBeTrue()
        ->and($product['possible_portions'])->toBe(3);
});

test('admin pos live returns active session ids scoped to area', function () {
    $admin = adminUser();
    $customer = User::factory()->create();
    UserProfile::create(['user_id' => $customer->id]);

    $area = makeLiveArea();
    $table = makeLiveTable($area);

    $activeSession = TableSession::create([
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'session_code' => 'SESSION-'.uniqid(),
        'checked_in_at' => now(),
        'status' => 'active',
    ]);

    TableSession::create([
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'session_code' => 'SESSION-'.uniqid(),
        'checked_in_at' => now()->subDay(),
        'checked_out_at' => now()->subDay(),
        'status' => 'closed',
    ]);

    $response = actingAs($admin)->getJson(route('admin.pos.live'));

    $response->assertSuccessful();

    $sessionIds = collect($response->json('active_session_ids'));

    expect($sessionIds)->toContain($activeSession->id)
        ->and($sessionIds)->toHaveCount(1);
});

function makeLiveWaiter(): User
{
    Role::firstOrCreate(['name' => 'Waiter/Server', 'guard_name' => 'web']);
    $waiter = User::factory()->create();
    $waiter->assignRole('Waiter/Server');

    return $waiter;
}

test('waiter checkout decrement is visible to cashier pos live poll', function () {
    $admin = adminUser();
    $waiter = makeLiveWaiter();
    $customer = User::factory()->create();

    $area = makeLiveArea();
    $table = makeLiveTable($area);

    $reservation = \App\Models\TableReservation::create([
        'booking_code' => random_int(100000, 999999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => today(),
        'reservation_time' => now()->format('H:i:s'),
        'status' => 'checked_in',
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $reservation->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'waiter_id' => $waiter->id,
        'session_code' => 'SESSION-'.uniqid(),
        'checked_in_at' => now(),
        'status' => 'active',
    ]);

    $product = makeLiveInventoryItem(['stock_quantity' => 5]);
    $cartKey = 'item_'.$product->id;

    // Waiter checkout consumes 5 units of stock.
    actingAs($waiter)
        ->withSession([
            'accurate_database' => 'test',
            WaiterPosController::CART_KEY => [
                $cartKey => [
                    'id' => $cartKey,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'quantity' => 5,
                    'preparation_location' => 'bar',
                ],
            ],
        ])
        ->post(route('waiter.pos.checkout'), ['session_id' => $session->id])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($product->fresh()->stock_quantity)->toBe(0);

    // Cashier live poll now reports the item as sold out.
    $live = collect(actingAs($admin)->getJson(route('admin.pos.live'))
        ->assertSuccessful()
        ->json('products'))
        ->firstWhere('id', $cartKey);

    expect($live['stock'])->toBe(0)
        ->and($live['is_available'])->toBeFalse();
});

test('cashier cart containing the same item is blocked after waiter checkout drains stock', function () {
    $admin = adminUser();
    $waiter = makeLiveWaiter();
    $customer = User::factory()->create();

    $area = makeLiveArea();
    $table = makeLiveTable($area);

    $reservation = \App\Models\TableReservation::create([
        'booking_code' => random_int(100000, 999999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => today(),
        'reservation_time' => now()->format('H:i:s'),
        'status' => 'checked_in',
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $reservation->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'waiter_id' => $waiter->id,
        'session_code' => 'SESSION-'.uniqid(),
        'checked_in_at' => now(),
        'status' => 'active',
    ]);

    $product = makeLiveInventoryItem(['stock_quantity' => 4]);
    $cartKey = 'item_'.$product->id;

    // Cashier already has 3 of the item in their cart.
    $cashierCart = [
        $cartKey => [
            'id' => $cartKey,
            'name' => $product->name,
            'price' => (float) $product->price,
            'quantity' => 3,
            'preparation_location' => 'bar',
        ],
    ];

    // Waiter checks out 4 units and drains the stock first.
    actingAs($waiter)
        ->withSession([
            'accurate_database' => 'test',
            WaiterPosController::CART_KEY => [
                $cartKey => [
                    'id' => $cartKey,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'quantity' => 4,
                    'preparation_location' => 'bar',
                ],
            ],
        ])
        ->post(route('waiter.pos.checkout'), ['session_id' => $session->id])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($product->fresh()->stock_quantity)->toBe(0);

    // Cashier's periodic availability refresh now blocks checkout.
    actingAs($admin)
        ->withSession(['pos_cart' => $cashierCart])
        ->getJson(route('admin.pos.preview-checkout-availability'))
        ->assertSuccessful()
        ->assertJsonPath('can_checkout', false)
        ->assertJsonPath('stock_issues.0.type', 'stock')
        ->assertJsonPath('stock_issues.0.product_id', $cartKey)
        ->assertJsonPath('stock_issues.0.available_stock', 0)
        ->assertJsonPath('stock_issues.0.requested_quantity', 3);
});
