<?php

use App\Models\InventoryItem;

use function Pest\Laravel\actingAs;

function makeInventoryItems(int $count, array $attributes = []): void
{
    for ($i = 0; $i < $count; $i++) {
        InventoryItem::create(array_merge([
            'code' => 'INV-PG-'.uniqid().'-'.$i,
            'accurate_id' => random_int(100000, 999999),
            'name' => 'Pagination Item '.$i.' '.uniqid(),
            'category_type' => 'beverage',
            'price' => 10000,
            'stock_quantity' => 10,
            'threshold' => 2,
            'unit' => 'pcs',
            'is_active' => true,
        ], $attributes));
    }
}

test('inventory index paginates items with default per page', function () {
    $admin = adminUser();
    makeInventoryItems(25);

    actingAs($admin)
        ->get(route('admin.inventory.index'))
        ->assertSuccessful()
        ->assertViewHas('items', fn ($items) => $items->perPage() === 20 && $items->total() >= 25);
});

test('inventory index respects per_page and keeps query string on links', function () {
    $admin = adminUser();
    makeInventoryItems(15);

    actingAs($admin)
        ->get(route('admin.inventory.index', ['per_page' => 10]))
        ->assertSuccessful()
        ->assertViewHas('items', fn ($items) => $items->perPage() === 10 && $items->count() === 10)
        ->assertSee('per_page=10', escape: false);
});

test('inventory index shows second page items', function () {
    $admin = adminUser();
    makeInventoryItems(12);

    $page1 = actingAs($admin)->get(route('admin.inventory.index', ['per_page' => 10]));
    $page2 = actingAs($admin)->get(route('admin.inventory.index', ['per_page' => 10, 'page' => 2]));

    $page1->assertSuccessful();
    $page2->assertSuccessful();

    $firstPageIds = $page1->viewData('items')->pluck('id');
    $secondPageIds = $page2->viewData('items')->pluck('id');

    expect($firstPageIds->intersect($secondPageIds))->toBeEmpty();
});

test('inventory index filters by search keyword via query string', function () {
    $admin = adminUser();
    InventoryItem::create([
        'code' => 'INV-SRCH-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Kopi Arabika Unik',
        'category_type' => 'beverage',
        'price' => 25000,
        'stock_quantity' => 5,
        'threshold' => 1,
        'unit' => 'pcs',
        'is_active' => true,
    ]);

    actingAs($admin)
        ->get(route('admin.inventory.index', ['search' => 'Arabika']))
        ->assertSuccessful()
        ->assertSee('Kopi Arabika Unik')
        ->assertViewHas('items', fn ($items) => $items->total() >= 1);
});

test('inventory index filters low stock items via query string', function () {
    $admin = adminUser();
    InventoryItem::create([
        'code' => 'INV-LOW-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Stok Tipis Sekali',
        'category_type' => 'beverage',
        'price' => 5000,
        'stock_quantity' => 1,
        'threshold' => 10,
        'unit' => 'pcs',
        'is_active' => true,
    ]);
    InventoryItem::create([
        'code' => 'INV-OK-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Stok Aman Banyak',
        'category_type' => 'beverage',
        'price' => 5000,
        'stock_quantity' => 100,
        'threshold' => 10,
        'unit' => 'pcs',
        'is_active' => true,
    ]);

    $names = actingAs($admin)
        ->get(route('admin.inventory.index', ['stock_filter' => 'low']))
        ->assertSuccessful()
        ->viewData('items')
        ->pluck('name');

    expect($names)->toContain('Stok Tipis Sekali')
        ->not->toContain('Stok Aman Banyak');
});

test('inventory index still provides all items for threshold bulk edit modal', function () {
    $admin = adminUser();
    makeInventoryItems(3);

    actingAs($admin)
        ->get(route('admin.inventory.index', ['per_page' => 2]))
        ->assertSuccessful()
        ->assertViewHas('allItems', fn ($allItems) => $allItems->count() >= 3);
});
