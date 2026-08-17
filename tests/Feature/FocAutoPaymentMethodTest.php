<?php

use App\Models\Area;
use App\Models\Billing;
use App\Models\DailyAuthCode;
use App\Models\GeneralSetting;
use App\Models\InventoryItem;
use App\Models\PosCategorySetting;
use App\Models\User;

use function Pest\Laravel\actingAs;

function focSeedAuthCode(): void
{
    DailyAuthCode::query()->updateOrCreate(
        ['date' => now()->format('Y-m-d')],
        [
            'code' => '9753',
            'override_code' => null,
            'generated_at' => now(),
        ],
    );
}

function focMakePosInventoryItem(array $attributes = []): InventoryItem
{
    return InventoryItem::create(array_merge([
        'code' => 'FOC-ITEM-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'FOC Item '.uniqid(),
        'category_type' => 'main-course',
        'price' => 25000,
        'stock_quantity' => 20,
        'threshold' => 5,
        'unit' => 'porsi',
        'is_active' => true,
    ], $attributes));
}

function focMakeArea(): Area
{
    return Area::create([
        'code' => 'FOC-AREA-'.uniqid(),
        'name' => 'FOC Area '.uniqid(),
        'is_active' => true,
        'sort_order' => 1,
    ]);
}

function focMakeTable(Area $area): \App\Models\Tabel
{
    return \App\Models\Tabel::create([
        'area_id' => $area->id,
        'table_number' => 'FOC-TBL-'.uniqid(),
        'qr_code' => 'FOC-QR-'.uniqid(),
        'capacity' => 4,
        'minimum_charge' => 0,
        'status' => 'occupied',
        'is_active' => true,
    ]);
}

test('walk-in FOC checkout auto-sets payment method without payment_method field', function () {
    $admin = adminUser();
    $customer = User::factory()->create();
    $area = focMakeArea();
    $table = focMakeTable($area);

    GeneralSetting::instance()->update([
        'service_charge_percentage' => 0,
        'tax_percentage' => 0,
    ]);

    $inventoryItem = focMakePosInventoryItem();
    $inventoryItem->printers()->sync([]);

    PosCategorySetting::updateOrCreate(
        ['category_type' => 'main-course'],
        [
            'show_in_pos' => true,
            'is_menu' => true,
            'is_item_group' => false,
            'preparation_location' => 'kitchen',
            'source' => 'inventory',
        ]
    );

    focSeedAuthCode();

    $cartKey = 'item_'.$inventoryItem->id;

    $response = actingAs($admin)
        ->withSession([
            'pos_cart' => [
                $cartKey => [
                    'id' => $cartKey,
                    'name' => $inventoryItem->name,
                    'price' => (float) $inventoryItem->price,
                    'quantity' => 1,
                    'preparation_location' => 'kitchen',
                ],
            ],
        ])
        ->postJson(route('admin.pos.checkout'), [
            'customer_type' => 'walk-in',
            'walk_in_customer_id' => $customer->id,
            'payment_mode' => 'normal',
            'foc_comp_payment_method' => 'FOC',
            'discount_type' => 'none',
            'foc_comp_auth_code' => '9753',
            // TANPA payment_method → harus auto-set ke FOC.
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $billing = Billing::query()->latest('id')->first();

    expect($billing)->not->toBeNull()
        ->and($billing->foc_comp_payment_method)->toBe('FOC')
        ->and($billing->payment_method)->toBe('FOC');
});

test('walk-in Compliment checkout auto-sets payment method', function () {
    $admin = adminUser();
    $customer = User::factory()->create();
    $area = focMakeArea();
    $table = focMakeTable($area);

    GeneralSetting::instance()->update([
        'service_charge_percentage' => 0,
        'tax_percentage' => 0,
    ]);

    $inventoryItem = focMakePosInventoryItem();

    PosCategorySetting::updateOrCreate(
        ['category_type' => 'main-course'],
        [
            'show_in_pos' => true,
            'is_menu' => true,
            'is_item_group' => false,
            'preparation_location' => 'kitchen',
            'source' => 'inventory',
        ]
    );

    focSeedAuthCode();

    $cartKey = 'item_'.$inventoryItem->id;

    actingAs($admin)
        ->withSession([
            'pos_cart' => [
                $cartKey => [
                    'id' => $cartKey,
                    'name' => $inventoryItem->name,
                    'price' => (float) $inventoryItem->price,
                    'quantity' => 1,
                    'preparation_location' => 'kitchen',
                ],
            ],
        ])
        ->postJson(route('admin.pos.checkout'), [
            'customer_type' => 'walk-in',
            'walk_in_customer_id' => $customer->id,
            'payment_mode' => 'normal',
            'foc_comp_payment_method' => 'Compliment',
            'discount_type' => 'percentage',
            'discount_percentage' => 100,
            'foc_comp_auth_code' => '9753',
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $billing = Billing::query()->latest('id')->first();

    expect($billing)->not->toBeNull()
        ->and($billing->foc_comp_payment_method)->toBe('Compliment')
        ->and($billing->payment_method)->toBe('Compliment')
        ->and((float) $billing->grand_total)->toBe(0.0);
});

test('walk-in FOC without payment_method and without reference succeeds', function () {
    $admin = adminUser();
    $customer = User::factory()->create();
    $area = focMakeArea();
    $table = focMakeTable($area);

    GeneralSetting::instance()->update([
        'service_charge_percentage' => 0,
        'tax_percentage' => 0,
    ]);

    $inventoryItem = focMakePosInventoryItem();

    PosCategorySetting::updateOrCreate(
        ['category_type' => 'main-course'],
        [
            'show_in_pos' => true,
            'is_menu' => true,
            'is_item_group' => false,
            'preparation_location' => 'kitchen',
            'source' => 'inventory',
        ]
    );

    focSeedAuthCode();

    $cartKey = 'item_'.$inventoryItem->id;

    $response = actingAs($admin)
        ->withSession([
            'pos_cart' => [
                $cartKey => [
                    'id' => $cartKey,
                    'name' => $inventoryItem->name,
                    'price' => (float) $inventoryItem->price,
                    'quantity' => 1,
                    'preparation_location' => 'kitchen',
                ],
            ],
        ])
        ->postJson(route('admin.pos.checkout'), [
            'customer_type' => 'walk-in',
            'walk_in_customer_id' => $customer->id,
            'payment_mode' => 'normal',
            'foc_comp_payment_method' => 'FOC',
            'discount_type' => 'none',
            'foc_comp_auth_code' => '9753',
        ]);

    $response->assertSuccessful()->assertJsonPath('success', true);
});
