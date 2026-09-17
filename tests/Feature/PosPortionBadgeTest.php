<?php

use App\Models\InventoryItem;
use App\Models\PosCategorySetting;

use function Pest\Laravel\actingAs;

function portionBadgeSetting(): void
{
    PosCategorySetting::clearCache();
    PosCategorySetting::updateOrCreate(
        ['category_type' => 'main-course'],
        [
            'show_in_pos' => true,
            'is_menu' => true,
            'is_item_group' => true,
            'preparation_location' => 'kitchen',
            'source' => 'inventory',
        ],
    );
    PosCategorySetting::clearCache();
}

function portionBadgeItem(array $overrides = []): InventoryItem
{
    return InventoryItem::create(array_merge([
        'code' => 'PRT-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Portion Menu '.uniqid(),
        'category_type' => 'main-course',
        'price' => 50000,
        'stock_quantity' => 0,
        'is_item_group' => true,
        'is_count_portion_possible' => true,
        'is_active' => true,
        'is_visible_in_pos' => true,
    ], $overrides));
}

test('pos index shows portion badge for group menu with count portion on', function () {
    $admin = adminUser();
    portionBadgeSetting();

    $menu = portionBadgeItem([
        'detail_group' => [['accurate_id' => 910001, 'name' => 'Bahan A', 'quantity' => 2]],
    ]);

    InventoryItem::create([
        'code' => 'PRT-ING-'.uniqid(),
        'accurate_id' => 910001,
        'name' => 'Bahan A',
        'category_type' => 'ingredient',
        'price' => 1000,
        'stock_quantity' => 6,
        'is_active' => true,
    ]);

    $response = actingAs($admin)->get(route('admin.pos.index'));

    $response->assertOk()
        ->assertSee('Sisa 3 porsi', false);
});

test('pos index hides portion badge for group menu without local recipe', function () {
    $admin = adminUser();
    portionBadgeSetting();

    // Group count ON tapi BOM lokal kosong → possible_portions null → tanpa badge.
    portionBadgeItem([
        'detail_group' => null,
    ]);

    $response = actingAs($admin)->get(route('admin.pos.index'));

    $response->assertOk()
        ->assertSee('Item Group', false)
        // Badge porsi tidak dirender untuk group tanpa BOM ("Sisa Bayar" tetap ada di halaman).
        ->assertDontSee('Sisa 0 porsi', false);
});

test('pos index still shows stock badge for plain inventory items', function () {
    $admin = adminUser();

    // Kategori non-group: item biasa memakai stok sendiri.
    PosCategorySetting::clearCache();
    PosCategorySetting::updateOrCreate(
        ['category_type' => 'beverage'],
        ['show_in_pos' => true, 'is_menu' => false, 'is_item_group' => false, 'preparation_location' => 'bar', 'source' => 'inventory'],
    );
    PosCategorySetting::clearCache();

    $item = InventoryItem::create([
        'code' => 'PRT-PLAIN-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Plain Stock Item '.uniqid(),
        'category_type' => 'beverage',
        'price' => 15000,
        'stock_quantity' => 25,
        'is_item_group' => false,
        'is_active' => true,
        'is_visible_in_pos' => true,
    ]);

    $response = actingAs($admin)->get(route('admin.pos.index'));

    $response->assertOk()
        ->assertSee('Stock: '.$item->stock_quantity, false);
});
