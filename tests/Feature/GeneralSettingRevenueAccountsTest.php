<?php

use App\Models\GeneralSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can update accurate revenue account numbers in general settings', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->put(route('admin.settings.general.update'), [
            'tax_percentage' => 10,
            'service_charge_percentage' => 5,
            'accurate_tax_account_no' => '210201',
            'accurate_service_charge_account_no' => '210202',
            'accurate_bank_account_no' => '110102',
            'accurate_cash_account_no' => '110101',
            'accurate_extra_charge_account_no' => '410105_EXTRA',
            'accurate_food_sales_account_no' => '410101_FOOD',
            'accurate_beverage_sales_account_no' => '410102_BEV',
            'accurate_cigarette_sales_account_no' => '410103_ROKOK',
            'accurate_sales_discount_account_no' => '410109_DISC',
            'accurate_breakage_account_no' => '410104_BREAK',
            'mail_provider' => 'smtp',
            'auth_code_delivery_channel' => 'both',
            'foc_discount_percentage' => 0,
            'compliment_discount_percentage' => 100,
        ])
        ->assertRedirect(route('admin.settings.general.index'))
        ->assertSessionHas('success');

    $settings = GeneralSetting::instance();
    expect($settings->accurate_extra_charge_account_no)->toBe('410105_EXTRA')
        ->and($settings->accurate_food_sales_account_no)->toBe('410101_FOOD')
        ->and($settings->accurate_beverage_sales_account_no)->toBe('410102_BEV')
        ->and($settings->accurate_cigarette_sales_account_no)->toBe('410103_ROKOK')
        ->and($settings->accurate_sales_discount_account_no)->toBe('410109_DISC')
        ->and($settings->accurate_breakage_account_no)->toBe('410104_BREAK');
});

test('revenue account numbers reject values longer than 50 characters', function (string $field) {
    $admin = adminUser();

    $this->from(route('admin.settings.general.index'))
        ->actingAs($admin)
        ->put(route('admin.settings.general.update'), [
            'tax_percentage' => 10,
            'service_charge_percentage' => 5,
            $field => str_repeat('9', 51),
            'mail_provider' => 'smtp',
            'auth_code_delivery_channel' => 'both',
            'foc_discount_percentage' => 0,
            'compliment_discount_percentage' => 100,
        ])
        ->assertSessionHasErrors([$field]);
})->with([
    'extra charge' => 'accurate_extra_charge_account_no',
    'food sales' => 'accurate_food_sales_account_no',
    'beverage sales' => 'accurate_beverage_sales_account_no',
    'cigarette sales' => 'accurate_cigarette_sales_account_no',
    'sales discount' => 'accurate_sales_discount_account_no',
    'breakage' => 'accurate_breakage_account_no',
]);

test('revenue account numbers default to null on fresh settings', function () {
    GeneralSetting::instance()->update([
        'accurate_extra_charge_account_no' => '410105',
    ]);

    expect(GeneralSetting::instance()->accurate_extra_charge_account_no)->toBe('410105')
        ->and(GeneralSetting::instance()->accurate_food_sales_account_no)->toBeNull()
        ->and(GeneralSetting::instance()->accurate_beverage_sales_account_no)->toBeNull()
        ->and(GeneralSetting::instance()->accurate_cigarette_sales_account_no)->toBeNull()
        ->and(GeneralSetting::instance()->accurate_sales_discount_account_no)->toBeNull()
        ->and(GeneralSetting::instance()->accurate_breakage_account_no)->toBeNull();
});
