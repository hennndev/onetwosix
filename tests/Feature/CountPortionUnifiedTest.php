<?php

use App\Models\InventoryItem;
use App\Models\PosCategorySetting;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

function unifiedSetting(bool $isItemGroup): void
{
    PosCategorySetting::clearCache();
    PosCategorySetting::updateOrCreate(
        ['category_type' => 'main-course'],
        [
            'show_in_pos' => true,
            'is_menu' => true,
            'is_item_group' => $isItemGroup,
            'preparation_location' => 'kitchen',
            'source' => 'inventory',
        ],
    );
    PosCategorySetting::clearCache();
}

function unifiedItem(array $overrides = []): InventoryItem
{
    return InventoryItem::create(array_merge([
        'code' => 'UNI-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Unified Item '.uniqid(),
        'category_type' => 'main-course',
        'price' => 50000,
        'stock_quantity' => 0,
        'unit' => 'porsi',
        'is_active' => true,
        'is_visible_in_pos' => true,
    ], $overrides));
}

function unifiedWaiter(): \App\Models\User
{
    Role::firstOrCreate(['name' => 'Waiter/Server', 'guard_name' => 'web']);
    $waiter = \App\Models\User::factory()->create();
    $waiter->assignRole('Waiter/Server');

    return $waiter;
}

test('setting flagged group with count portion uses ingredient based availability on pos index', function () {
    $admin = adminUser();
    unifiedSetting(true);

    // Item group via KATEGORI saja (flag item false), stok sendiri 0.
    $menu = unifiedItem([
        'accurate_id' => 551001,
        'is_count_portion_possible' => true,
        'stock_quantity' => 0,
        'detail_group' => [['accurate_id' => 552001, 'name' => 'Bahan', 'quantity' => 2]],
    ]);

    unifiedItem(['accurate_id' => 552001, 'stock_quantity' => 6]);

    $response = actingAs($admin)->get(route('admin.pos.index'));

    $response->assertOk();

    // Verifikasi via payload live endpoint (basis data sama dengan index).
    $live = collect(actingAs($admin)->getJson(route('admin.pos.live'))->json('products'))
        ->firstWhere('id', 'item_'.$menu->id);

    expect($live['is_item_group'])->toBeTrue()
        ->and($live['stock'])->toBeNull()
        ->and($live['possible_portions'])->toBe(3)
        ->and($live['is_available'])->toBeTrue();
});

test('setting flagged group can be added to cart even when own stock is zero', function () {
    $admin = adminUser();
    unifiedSetting(true);

    $menu = unifiedItem([
        'accurate_id' => 551002,
        'is_count_portion_possible' => true,
        'stock_quantity' => 0,
        'detail_group' => [['accurate_id' => 552002, 'name' => 'Bahan', 'quantity' => 2]],
    ]);

    unifiedItem(['accurate_id' => 552002, 'stock_quantity' => 10]);

    actingAs($admin)
        ->postJson(route('admin.pos.add-to-cart', ['productId' => 'item_'.$menu->id]))
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('cart.0.id', 'item_'.$menu->id);
});

test('setting flagged group without count portion has no own stock guard', function () {
    $admin = adminUser();
    unifiedSetting(true);

    // COUNT OFF: group tanpa hitung porsi — stok sendiri diabaikan total.
    $menu = unifiedItem([
        'accurate_id' => 551003,
        'is_count_portion_possible' => false,
        'stock_quantity' => 0,
    ]);

    actingAs($admin)
        ->postJson(route('admin.pos.add-to-cart', ['productId' => 'item_'.$menu->id]))
        ->assertSuccessful()
        ->assertJsonPath('success', true);
});

test('waiter pos rejects simple item with empty stock regardless of count flag', function () {
    $waiter = unifiedWaiter();
    unifiedSetting(false);

    $item = unifiedItem([
        'stock_quantity' => 0,
        'is_count_portion_possible' => false,
    ]);

    actingAs($waiter)
        ->withSession(['accurate_database' => 'test'])
        ->postJson(route('waiter.pos.add-to-cart', 'item_'.$item->id))
        ->assertStatus(422)
        ->assertJsonPath('message', 'Stok tidak mencukupi.');
});

test('waiter live feed marks simple item unavailable when stock is empty regardless of count flag', function () {
    $waiter = unifiedWaiter();
    unifiedSetting(false);

    $item = unifiedItem([
        'stock_quantity' => 0,
        'is_count_portion_possible' => null,
    ]);

    $products = collect(actingAs($waiter)->getJson(route('waiter.pos.live'))->json('products'))
        ->firstWhere('id', 'item_'.$item->id);

    expect($products['is_available'])->toBeFalse();
});

test('waiter live feed shows setting flagged group portions with null stock', function () {
    $waiter = unifiedWaiter();
    unifiedSetting(true);

    $menu = unifiedItem([
        'accurate_id' => 551004,
        'is_count_portion_possible' => true,
        'stock_quantity' => 0,
        'detail_group' => [['accurate_id' => 552004, 'name' => 'Bahan', 'quantity' => 4]],
    ]);

    unifiedItem(['accurate_id' => 552004, 'stock_quantity' => 8]);

    $products = collect(actingAs($waiter)->getJson(route('waiter.pos.live'))->json('products'))
        ->firstWhere('id', 'item_'.$menu->id);

    expect($products['stock'])->toBeNull()
        ->and($products['possible_portions'])->toBe(2)
        ->and($products['is_available'])->toBeTrue();
});
