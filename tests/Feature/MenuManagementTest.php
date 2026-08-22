<?php

use App\Models\InventoryItem;
use App\Models\PosCategorySetting;
use App\Models\Printer;
use App\Services\AccurateService;
use Mockery\MockInterface;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

test('menus page uses categories marked as menu and shows grouped menu cards', function () {
    $admin = adminUser();

    PosCategorySetting::create([
        'category_type' => 'food-menu',
        'show_in_pos' => true,
        'is_menu' => true,
        'preparation_location' => 'kitchen',
    ]);

    PosCategorySetting::create([
        'category_type' => 'raw-material',
        'show_in_pos' => true,
        'is_menu' => false,
        'preparation_location' => 'direct',
    ]);

    InventoryItem::create([
        'code' => 'MENU-001',
        'accurate_id' => 1001,
        'name' => 'Nasi Goreng Spesial',
        'category_type' => 'food-menu',
        'price' => 45000,
        'stock_quantity' => 0,
        'threshold' => 0,
        'unit' => 'porsi',
        'is_active' => true,
    ]);

    InventoryItem::create([
        'code' => 'RAW-001',
        'accurate_id' => 1002,
        'name' => 'Beras Premium',
        'category_type' => 'raw-material',
        'price' => 12000,
        'stock_quantity' => 10,
        'threshold' => 1,
        'unit' => 'kg',
        'is_active' => true,
    ]);

    actingAs($admin)
        ->get(route('admin.menus.index'))
        ->assertOk()
        ->assertSee('food-menu')
        ->assertSee('Nasi Goreng Spesial');
});

test('menus page can search menu list by keyword', function () {
    $admin = adminUser();

    PosCategorySetting::create([
        'category_type' => 'food-menu',
        'show_in_pos' => true,
        'is_menu' => true,
        'preparation_location' => 'kitchen',
    ]);

    InventoryItem::create([
        'code' => 'MENU-SEA-001',
        'accurate_id' => 2001,
        'name' => 'Sate Ayam',
        'pos_name' => 'Sate POS',
        'category_type' => 'food-menu',
        'price' => 35000,
        'stock_quantity' => 0,
        'threshold' => 0,
        'unit' => 'porsi',
        'is_active' => true,
    ]);

    InventoryItem::create([
        'code' => 'MENU-SEA-002',
        'accurate_id' => 2002,
        'name' => 'Mie Goreng',
        'pos_name' => 'Mie POS',
        'category_type' => 'food-menu',
        'price' => 30000,
        'stock_quantity' => 0,
        'threshold' => 0,
        'unit' => 'porsi',
        'is_active' => true,
    ]);

    actingAs($admin)
        ->get(route('admin.menus.index', ['search' => 'sate']))
        ->assertOk()
        ->assertSee('Hasil pencarian untuk:')
        ->assertSee('Sate Ayam')
        ->assertViewHas('menusByCategory', function ($menusByCategory): bool {
            $foodMenus = $menusByCategory->get('food-menu', collect());

            return $foodMenus->pluck('name')->values()->all() === ['Sate Ayam'];
        });
});

test('menus page always shows fixed category main filter options even when DB empty', function () {
    $admin = adminUser();

    PosCategorySetting::create([
        'category_type' => 'food-menu',
        'show_in_pos' => true,
        'is_menu' => true,
        'preparation_location' => 'kitchen',
    ]);

    actingAs($admin)
        ->get(route('admin.menus.index'))
        ->assertOk()
        ->assertViewHas('categoryMainOptions', function ($options): bool {
            return $options->contains('food')
                && $options->contains('alcohol')
                && $options->contains('foc')
                && $options->contains('LD');
        });
});

test('menus page can filter menu list by category main', function () {
    $admin = adminUser();

    PosCategorySetting::create([
        'category_type' => 'food-menu',
        'show_in_pos' => true,
        'is_menu' => true,
        'preparation_location' => 'kitchen',
    ]);

    InventoryItem::create([
        'code' => 'MENU-CM-001',
        'accurate_id' => 3001,
        'name' => 'Ayam Bakar',
        'category_type' => 'food-menu',
        'category_main' => 'food',
        'price' => 40000,
        'stock_quantity' => 0,
        'threshold' => 0,
        'unit' => 'porsi',
        'is_active' => true,
    ]);

    InventoryItem::create([
        'code' => 'MENU-CM-002',
        'accurate_id' => 3002,
        'name' => 'Es Teh Manis',
        'category_type' => 'food-menu',
        'category_main' => 'beverage',
        'price' => 8000,
        'stock_quantity' => 0,
        'threshold' => 0,
        'unit' => 'gelas',
        'is_active' => true,
    ]);

    actingAs($admin)
        ->get(route('admin.menus.index', ['category_main' => 'food']))
        ->assertOk()
        ->assertViewHas('menusByCategory', function ($menusByCategory): bool {
            $foodMenus = $menusByCategory->get('food-menu', collect());

            return $foodMenus->pluck('name')->values()->all() === ['Ayam Bakar'];
        });
});

test('menus page can filter menu list by category type', function () {
    $admin = adminUser();

    PosCategorySetting::create([
        'category_type' => 'food-menu',
        'show_in_pos' => true,
        'is_menu' => true,
        'preparation_location' => 'kitchen',
    ]);

    PosCategorySetting::create([
        'category_type' => 'drink-menu',
        'show_in_pos' => true,
        'is_menu' => true,
        'preparation_location' => 'bar',
    ]);

    InventoryItem::create([
        'code' => 'MENU-CT-001',
        'accurate_id' => 4001,
        'name' => 'Ayam Goreng',
        'category_type' => 'food-menu',
        'price' => 30000,
        'stock_quantity' => 0,
        'threshold' => 0,
        'unit' => 'porsi',
        'is_active' => true,
    ]);

    InventoryItem::create([
        'code' => 'MENU-CT-002',
        'accurate_id' => 4002,
        'name' => 'Jus Alpukat',
        'category_type' => 'drink-menu',
        'price' => 15000,
        'stock_quantity' => 0,
        'threshold' => 0,
        'unit' => 'gelas',
        'is_active' => true,
    ]);

    actingAs($admin)
        ->get(route('admin.menus.index', ['category_type' => 'drink-menu']))
        ->assertOk()
        ->assertViewHas('menuCategoryTypes', fn ($types) => $types->values()->all() === ['drink-menu'])
        ->assertViewHas('categoryTypeOptions', fn ($options) => $options->contains('food-menu') && $options->contains('drink-menu'))
        ->assertViewHas('menusByCategory', function ($menusByCategory): bool {
            return $menusByCategory->get('drink-menu', collect())->pluck('name')->all() === ['Jus Alpukat'];
        });
});

test('menu store can save printer targets for a menu item', function () {
    $admin = adminUser();

    $printerOne = Printer::create([
        'name' => 'Kitchen A',
        'location' => 'kitchen',
        'connection_type' => 'log',
        'port' => 9100,
        'timeout' => 30,
        'header' => '126 Club',
        'footer' => 'Thank you',
        'width' => 42,
        'is_active' => true,
    ]);

    $printerTwo = Printer::create([
        'name' => 'Kitchen B',
        'location' => 'kitchen',
        'connection_type' => 'log',
        'port' => 9100,
        'timeout' => 30,
        'header' => '126 Club',
        'footer' => 'Thank you',
        'width' => 42,
        'is_active' => true,
    ]);

    mock(AccurateService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('saveItem')
            ->once()
            ->andReturn([
                'r' => [
                    'id' => 777001,
                    'no' => 'MENU-777001',
                ],
            ]);
    });

    $response = actingAs($admin)->postJson(route('admin.menus.store'), [
        'code_mode' => 'manual',
        'no' => 'MENU-002',
        'name' => 'Menu Printer',
        'item_type' => 'GROUP',
        'category_type' => 'food-menu',
        'unit' => 'porsi',
        'selling_price' => 30000,
        'printer_ids' => [$printerOne->id, $printerTwo->id],
        'detail_group' => [],
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true);

    $menu = InventoryItem::where('accurate_id', 777001)->firstOrFail();

    expect($menu->printers->pluck('id')->all())
        ->toEqualCanonicalizing([$printerOne->id, $printerTwo->id]);
});

test('menu store defaults to visible in pos', function () {
    $admin = adminUser();

    mock(AccurateService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('saveItem')
            ->once()
            ->andReturn([
                'r' => [
                    'id' => 777101,
                    'no' => 'MENU-777101',
                ],
            ]);
    });

    actingAs($admin)
        ->postJson(route('admin.menus.store'), [
            'code_mode' => 'manual',
            'no' => 'MENU-VISIBLE-DEFAULT',
            'name' => 'Menu Visible Default',
            'item_type' => 'INVENTORY',
            'category_type' => 'food-menu',
            'unit' => 'porsi',
            'selling_price' => 30000,
            'detail_group' => [],
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(InventoryItem::query()->where('accurate_id', 777101)->firstOrFail()->is_visible_in_pos)->toBeTrue();
});

test('menu detail endpoint returns visible in pos state', function () {
    $admin = adminUser();

    $menuItem = InventoryItem::create([
        'code' => 'MENU-DETAIL-VISIBLE',
        'accurate_id' => 998878,
        'name' => 'Menu Detail Visible',
        'category_type' => 'food-menu',
        'price' => 25000,
        'stock_quantity' => 0,
        'threshold' => 0,
        'unit' => 'porsi',
        'is_active' => true,
        'is_visible_in_pos' => false,
    ]);

    mock(AccurateService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('getDetailItem')
            ->once()
            ->andReturn([
                'detailGroup' => [],
            ]);
    });

    actingAs($admin)
        ->getJson(route('admin.menus.fetch-detail', $menuItem))
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('is_visible_in_pos', false);
});

test('menu store accepts compliment and foc as category main values', function (string $categoryMain, string $menuName) {
    $admin = adminUser();

    mock(AccurateService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('saveItem')
            ->once()
            ->andReturn([
                'r' => [
                    'id' => random_int(700000, 799999),
                    'no' => 'MENU-'.random_int(700000, 799999),
                ],
            ]);
    });

    actingAs($admin)
        ->postJson(route('admin.menus.store'), [
            'code_mode' => 'manual',
            'no' => 'MENU-'.strtoupper($categoryMain),
            'name' => $menuName,
            'item_type' => 'INVENTORY',
            'category_type' => 'menu-qty',
            'category_main' => $categoryMain,
            'unit' => 'pcs',
            'selling_price' => 0,
            'detail_group' => [],
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(InventoryItem::query()->where('name', $menuName)->firstOrFail()->category_main)->toBe($categoryMain);
})->with([
    ['compliment', 'Compliment Menu'],
    ['foc', 'FOC Menu'],
]);

test('menu detail endpoint returns error when accurate detail cannot be fetched', function () {
    $admin = adminUser();

    $menuItem = InventoryItem::create([
        'code' => 'MENU-DETAIL-FAIL',
        'accurate_id' => 998877,
        'name' => 'Menu Detail Gagal',
        'category_type' => 'food-menu',
        'price' => 25000,
        'stock_quantity' => 0,
        'threshold' => 0,
        'unit' => 'porsi',
        'is_active' => true,
    ]);

    mock(AccurateService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('getDetailItem')
            ->once()
            ->andReturn(null);
    });

    actingAs($admin)
        ->getJson(route('admin.menus.fetch-detail', $menuItem))
        ->assertStatus(502)
        ->assertJsonPath('success', false);
});

test('admin can update printer targets for menu item', function () {
    $admin = adminUser();

    $menu = InventoryItem::create([
        'code' => 'MENU-PRN-001',
        'accurate_id' => 991001,
        'name' => 'Menu Printer Targets',
        'category_type' => 'food-menu',
        'price' => 25000,
        'stock_quantity' => 0,
        'threshold' => 0,
        'unit' => 'porsi',
        'is_active' => true,
    ]);

    $printerOne = Printer::create([
        'name' => 'Kitchen A',
        'location' => 'kitchen',
        'connection_type' => 'log',
        'port' => 9100,
        'timeout' => 30,
        'header' => '126 Club',
        'footer' => 'Thank you',
        'width' => 42,
        'is_active' => true,
    ]);

    $printerTwo = Printer::create([
        'name' => 'Bar A',
        'location' => 'bar',
        'connection_type' => 'log',
        'port' => 9100,
        'timeout' => 30,
        'header' => '126 Club',
        'footer' => 'Thank you',
        'width' => 42,
        'is_active' => true,
    ]);

    actingAs($admin)
        ->patchJson(route('admin.menus.update-printer-targets', ['inventory' => $menu->id]), [
            'printer_ids' => [$printerOne->id, $printerTwo->id],
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($menu->fresh()->printers->pluck('id')->all())
        ->toEqualCanonicalizing([$printerOne->id, $printerTwo->id]);
});

test('menu store rejects ingredient quantity that is zero or decimal', function (int|float $quantity) {
    $admin = adminUser();

    $response = actingAs($admin)->postJson(route('admin.menus.store'), [
        'code_mode' => 'manual',
        'no' => 'MENU-INVALID-QTY',
        'name' => 'Menu Invalid Qty',
        'item_type' => 'GROUP',
        'category_type' => 'food-menu',
        'unit' => 'porsi',
        'selling_price' => 50000,
        'detail_group' => [
            [
                'item_no' => 'RAW-001',
                'detail_name' => 'Bahan Test',
                'quantity' => $quantity,
            ],
        ],
    ]);

    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors(['detail_group.0.quantity']);
})->with([
    'zero quantity' => 0,
    'decimal quantity' => 1.5,
]);

test('menu store updates existing item when accurate code already exists', function () {
    $admin = adminUser();

    $existing = InventoryItem::create([
        'code' => '100337',
        'accurate_id' => 999001,
        'name' => 'Menu Lama',
        'pos_name' => 'Menu Lama POS',
        'category_type' => 'Old Category',
        'price' => 5000,
        'stock_quantity' => 0,
        'threshold' => 0,
        'unit' => 'PCS',
        'is_active' => true,
    ]);

    mock(AccurateService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('saveItem')
            ->once()
            ->andReturn([
                'r' => [
                    'id' => 1650,
                    'no' => '100337',
                ],
            ]);
    });

    $response = actingAs($admin)->postJson(route('admin.menus.store'), [
        'code_mode' => 'auto',
        'name' => 'Test Menu 1',
        'item_type' => 'INVENTORY',
        'category_type' => 'Main Course',
        'category_main' => 'food',
        'unit' => 'PCS',
        'selling_price' => 10000,
        'include_tax' => true,
        'include_service_charge' => true,
        'detail_group' => [],
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(InventoryItem::query()->where('code', '100337')->count())->toBe(1);

    $updated = $existing->fresh();

    expect((int) $updated->accurate_id)->toBe(1650)
        ->and($updated->name)->toBe('Test Menu 1')
        ->and($updated->category_type)->toBe('Main Course')
        ->and($updated->category_main)->toBe('food');
});
