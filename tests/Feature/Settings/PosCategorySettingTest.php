<?php

use App\Models\InventoryItem;
use App\Models\PosCategorySetting;

use function Pest\Laravel\actingAs;

test('admin can save menu flag for pos category settings', function () {
    $admin = adminUser();

    InventoryItem::create([
        'code' => 'MENU-001',
        'accurate_id' => 100001,
        'name' => 'Creamy Pasta',
        'category_type' => 'main-course',
        'price' => 55000,
        'stock_quantity' => 7,
        'threshold' => 2,
        'unit' => 'portion',
        'is_active' => true,
    ]);

    $response = actingAs($admin)->post(route('admin.settings.pos-categories.save'), [
        'categories' => [
            'main-course' => [
                '_present' => '1',
                'show_in_pos' => '1',
                'is_menu' => '1',
            ],
        ],
    ]);

    $response->assertRedirect(route('admin.settings.pos-categories.index'));

    expect(PosCategorySetting::query()->where('category_type', 'main-course')->first())
        ->not->toBeNull()
        ->show_in_pos->toBeTrue()
        ->is_menu->toBeTrue();
});

test('pos category settings page renders bulk toggles', function () {
    $admin = adminUser();

    InventoryItem::create([
        'code' => 'MENU-002',
        'accurate_id' => 100002,
        'name' => 'Iced Latte',
        'category_type' => 'beverage',
        'price' => 30000,
        'stock_quantity' => 10,
        'threshold' => 2,
        'unit' => 'cup',
        'is_active' => true,
    ]);

    actingAs($admin)->get(route('admin.settings.pos-categories.index'))
        ->assertOk()
        ->assertSee('data-bulk="show_in_pos"', false)
        ->assertSee('data-bulk="is_menu"', false);
});
