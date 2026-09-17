<?php

use App\Models\InventoryItem;
use App\Models\PosCategorySetting;
use Mockery\MockInterface;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

function portionWarehouseSetting(): void
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

function portionWarehouseGroup(array $overrides = []): InventoryItem
{
    portionWarehouseSetting();

    return InventoryItem::create(array_merge([
        'code' => 'WH-PRT-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Warehouse Portion Menu '.uniqid(),
        'category_type' => 'main-course',
        'price' => 50000,
        'stock_quantity' => 0,
        'threshold' => 0,
        'unit' => 'porsi',
        'is_item_group' => true,
        'is_count_portion_possible' => true,
        'is_active' => true,
        'is_visible_in_pos' => true,
    ], $overrides));
}

function portionWarehouseIngredient(int $accurateId, int $stock): InventoryItem
{
    return InventoryItem::create([
        'code' => 'WH-ING-'.uniqid(),
        'accurate_id' => $accurateId,
        'name' => 'Warehouse Bahan '.uniqid(),
        'category_type' => 'ingredient',
        'price' => 1000,
        'stock_quantity' => $stock,
        'threshold' => 0,
        'unit' => 'gram',
        'is_active' => true,
    ]);
}

test('possible portions computes min floor across bom ingredients', function () {
    portionWarehouseSetting();

    $menu = portionWarehouseGroup([
        'is_count_portion_possible' => true,
        'detail_group' => [
            ['accurate_id' => 800001, 'name' => 'Bahan A', 'quantity' => 2],
            ['accurate_id' => 800002, 'name' => 'Bahan B', 'quantity' => 5],
        ],
    ]);

    portionWarehouseIngredient(800001, stock: 7);
    portionWarehouseIngredient(800002, stock: 13);

    // Bahan A: 7/2 = 3; Bahan B: 13/5 = 2 → min = 2.
    expect($menu->possiblePortions())->toBe(2);
});

test('possible portions returns null for non count portion items', function () {
    portionWarehouseSetting();

    $plain = portionWarehouseGroup(['is_count_portion_possible' => false]);
    $offGroup = portionWarehouseGroup([
        'is_count_portion_possible' => true,
        'is_count_portion_possible' => false,
    ]);

    expect($plain->possiblePortions())->toBeNull()
        ->and($offGroup->fresh()->possiblePortions())->toBeNull();
});

test('warehouse index shows portion badge for count portion group items', function () {
    $admin = adminUser();
    portionWarehouseSetting();

    $menu = portionWarehouseGroup();
    portionWarehouseIngredient(800101, stock: 8);
    $menu->update(['detail_group' => [['accurate_id' => 800101, 'name' => 'Bahan X', 'quantity' => 2]]]);

    actingAs($admin)
        ->get(route('admin.inventory.index'))
        ->assertOk()
        ->assertSee('± 4 porsi', false);
});

test('warehouse index does not show portion badge for plain items', function () {
    $admin = adminUser();
    portionWarehouseSetting();

    $plain = InventoryItem::create([
        'code' => 'WH-PLAIN-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Plain Warehouse Item '.uniqid(),
        'category_type' => 'ingredient',
        'price' => 1000,
        'stock_quantity' => 5,
        'threshold' => 0,
        'unit' => 'gram',
        'is_active' => true,
    ]);

    actingAs($admin)
        ->get(route('admin.inventory.index'))
        ->assertOk()
        ->assertDontSee('porsi', false);
});

test('inventory detail endpoint returns current stock per bom ingredient', function () {
    $admin = adminUser();
    portionWarehouseSetting();

    $menu = portionWarehouseGroup(['accurate_id' => 1674]);
    portionWarehouseIngredient(800201, stock: 6);

    $menu->update(['detail_group' => [
        ['accurate_id' => 800201, 'name' => 'Bahan A', 'quantity' => 2],
    ]]);

    // Mock BOM dari Accurate (endpoint detail) — komponen menunjuk bahan lokal.
    mock(App\Services\AccurateService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('getDetailItem')->once()->andReturn([
            'name' => 'NASI GORENG CABAI HIJAU',
            'detailGroup' => [
                ['itemId' => 800201, 'detailName' => 'Bahan A', 'quantity' => 2, 'itemUnit' => ['name' => 'gram'], 'seq' => 1],
            ],
        ]);
    });

    $response = actingAs($admin)
        ->getJson(route('admin.inventory.fetchDetail', $menu->fresh()));

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('detail_group.0.item_id', 800201)
        ->assertJsonPath('detail_group.0.stock', 6);
});
