<?php

use App\Models\Area;
use App\Models\Billing;
use App\Models\DailyAuthCode;
use App\Models\GeneralSetting;
use App\Models\InventoryItem;
use App\Models\PosCategorySetting;
use App\Models\Tabel;
use App\Models\TableSession;
use App\Models\User;

use function Pest\Laravel\actingAs;

function focSettingSeedAuthCode(): void
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

function focSettingMakeItem(): InventoryItem
{
    $item = InventoryItem::create([
        'code' => 'FOCSET-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'FOC Setting Item '.uniqid(),
        'category_type' => 'main-course',
        'price' => 25000,
        'stock_quantity' => 20,
        'threshold' => 5,
        'unit' => 'porsi',
        'is_active' => true,
    ]);
    $item->printers()->sync([]);

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

    return $item;
}

function focSettingWalkInCheckout(array $overrides = []): \Illuminate\Testing\TestResponse
{
    $admin = adminUser();
    $customer = User::factory()->create();
    $item = focSettingMakeItem();

    GeneralSetting::instance()->update([
        'service_charge_percentage' => 0,
        'tax_percentage' => 0,
    ]);

    focSettingSeedAuthCode();

    $cartKey = 'item_'.$item->id;

    return actingAs($admin)
        ->withSession([
            'pos_cart' => [
                $cartKey => [
                    'id' => $cartKey,
                    'name' => $item->name,
                    'price' => (float) $item->price,
                    'quantity' => 1,
                    'preparation_location' => 'kitchen',
                ],
            ],
        ])
        ->postJson(route('admin.pos.checkout'), array_merge([
            'customer_type' => 'walk-in',
            'walk_in_customer_id' => $customer->id,
            'payment_mode' => 'normal',
            'foc_comp_payment_method' => 'FOC',
            'discount_type' => 'none',
            'foc_comp_auth_code' => '9753',
        ], $overrides));
}

test('default settings preserve current behavior (Compliment 100%, FOC 0%, auth required)', function () {
    $settings = GeneralSetting::instance();

    expect($settings->foc_enabled)->toBeTrue()
        ->and($settings->compliment_enabled)->toBeTrue()
        ->and($settings->foc_requires_auth_code)->toBeTrue()
        ->and($settings->compliment_requires_auth_code)->toBeTrue()
        ->and($settings->foc_discount_percentage)->toBe(0)
        ->and($settings->compliment_discount_percentage)->toBe(100);
});

test('FOC with auth code disabled succeeds without auth code', function () {
    GeneralSetting::instance()->update([
        'foc_requires_auth_code' => false,
    ]);

    focSettingWalkInCheckout(['foc_comp_auth_code' => ''])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $billing = Billing::query()->latest('id')->first();
    expect($billing->foc_comp_payment_method)->toBe('FOC')
        ->and($billing->payment_method)->toBe('FOC');
});

test('Compliment discount percentage is configurable', function () {
    GeneralSetting::instance()->update([
        'compliment_discount_percentage' => 50,
        'compliment_requires_auth_code' => true,
    ]);

    $response = focSettingWalkInCheckout([
        'foc_comp_payment_method' => 'Compliment',
        'discount_type' => 'percentage',
        'foc_comp_auth_code' => '9753',
    ]);

    $response->assertSuccessful()->assertJsonPath('success', true);

    $billing = Billing::query()->latest('id')->first();
    expect((float) $billing->grand_total)->toBe(12500.0)   // 25000 - 50%
        ->and($billing->payment_method)->toBe('Compliment');
});

test('FOC discount percentage configurable to charge partial', function () {
    GeneralSetting::instance()->update([
        'foc_discount_percentage' => 20,
        'foc_requires_auth_code' => true,
    ]);

    $response = focSettingWalkInCheckout([
        'discount_type' => 'percentage',
        'foc_comp_auth_code' => '9753',
    ]);

    $response->assertSuccessful()->assertJsonPath('success', true);

    $billing = Billing::query()->latest('id')->first();
    expect((float) $billing->grand_total)->toBe(20000.0);   // 25000 - 20%
});

test('FOC with 0% discount charges full price', function () {
    GeneralSetting::instance()->update([
        'foc_discount_percentage' => 0,
        'foc_requires_auth_code' => true,
    ]);

    $response = focSettingWalkInCheckout(['foc_comp_auth_code' => '9753']);

    $response->assertSuccessful()->assertJsonPath('success', true);

    $billing = Billing::query()->latest('id')->first();
    expect((float) $billing->grand_total)->toBe(25000.0);
});

test('FOC with discount and auth code disabled succeeds without auth code', function () {
    GeneralSetting::instance()->update([
        'foc_discount_percentage' => 20,
        'foc_requires_auth_code' => false,
    ]);

    $response = focSettingWalkInCheckout(['foc_comp_auth_code' => '']);

    $response->assertSuccessful()->assertJsonPath('success', true);

    $billing = Billing::query()->latest('id')->first();
    expect((float) $billing->grand_total)->toBe(20000.0)   // 25000 - 20%
        ->and($billing->payment_method)->toBe('FOC');
});

test('Compliment with discount and auth code disabled succeeds without auth code', function () {
    GeneralSetting::instance()->update([
        'compliment_discount_percentage' => 50,
        'compliment_requires_auth_code' => false,
    ]);

    $response = focSettingWalkInCheckout([
        'foc_comp_payment_method' => 'Compliment',
        'foc_comp_auth_code' => '',
    ]);

    $response->assertSuccessful()->assertJsonPath('success', true);

    $billing = Billing::query()->latest('id')->first();
    expect((float) $billing->grand_total)->toBe(12500.0)   // 25000 - 50%
        ->and($billing->payment_method)->toBe('Compliment');
});

test('booking checkout FOC with auth code disabled succeeds without auth code', function () {
    $admin = adminUser();
    $customer = User::factory()->create();
    $area = Area::create([
        'code' => 'AREA-'.uniqid(),
        'name' => 'Area '.uniqid(),
        'is_active' => true,
        'sort_order' => 1,
    ]);
    $table = Tabel::create([
        'area_id' => $area->id,
        'table_number' => 'TBL-'.uniqid(),
        'qr_code' => 'QR-'.uniqid(),
        'capacity' => 4,
        'minimum_charge' => 0,
        'status' => 'occupied',
        'is_active' => true,
    ]);

    TableSession::create([
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'session_code' => 'SESSION-'.uniqid(),
        'checked_in_at' => now(),
        'status' => 'active',
    ]);

    $item = focSettingMakeItem();

    GeneralSetting::instance()->update([
        'service_charge_percentage' => 0,
        'tax_percentage' => 0,
        'foc_discount_percentage' => 20,
        'foc_requires_auth_code' => false,
    ]);

    $cartKey = 'item_'.$item->id;

    $response = actingAs($admin)
        ->withSession([
            'pos_cart' => [
                $cartKey => [
                    'id' => $cartKey,
                    'name' => $item->name,
                    'price' => (float) $item->price,
                    'quantity' => 1,
                    'preparation_location' => 'kitchen',
                ],
            ],
        ])
        ->postJson(route('admin.pos.checkout'), [
            'customer_type' => 'booking',
            'customer_user_id' => $customer->id,
            'table_id' => $table->id,
            'foc_comp_payment_method' => 'FOC',
            'foc_comp_auth_code' => '',
        ]);

    $response->assertSuccessful()->assertJsonPath('success', true);

    // FOC auth-off → checkout sukses tanpa auth code (dulu error "Auth code diskon tidak valid").
    \App\Models\Order::query()->latest('id')->firstOrFail();
});

test('settings update persists FOC configuration', function () {
    $admin = adminUser();

    actingAs($admin)
        ->from(route('admin.settings.general.index'))
        ->put(route('admin.settings.general.update'), [
            'tax_percentage' => 0,
            'service_charge_percentage' => 0,
            'operational_start_time' => '10:00',
            'can_choose_checker' => 0,
            'mail_provider' => 'smtp',
            'auth_code_delivery_channel' => 'both',
            'foc_enabled' => 0,
            'compliment_enabled' => 1,
            'foc_requires_auth_code' => 0,
            'compliment_requires_auth_code' => 1,
            'foc_discount_percentage' => 15,
            'compliment_discount_percentage' => 90,
        ])
        ->assertRedirect(route('admin.settings.general.index'));

    $settings = GeneralSetting::instance()->fresh();

    expect($settings->foc_enabled)->toBeFalse()
        ->and($settings->compliment_enabled)->toBeTrue()
        ->and($settings->foc_requires_auth_code)->toBeFalse()
        ->and($settings->compliment_requires_auth_code)->toBeTrue()
        ->and($settings->foc_discount_percentage)->toBe(15)
        ->and($settings->compliment_discount_percentage)->toBe(90);
});
