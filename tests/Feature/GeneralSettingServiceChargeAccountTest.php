<?php

use App\Models\GeneralSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can update accurate service charge account no in general settings', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->put(route('admin.settings.general.update'), [
            'tax_percentage' => 10,
            'service_charge_percentage' => 5,
            'accurate_tax_account_no' => '210201',
            'accurate_service_charge_account_no' => '210202_SC',
            'accurate_bank_account_no' => '110101_BANK',
            'accurate_cash_account_no' => '110102_CASH',
            'accurate_stock_warehouse_name' => 'GD. OUTLET',
            'mail_provider' => 'smtp',
            'auth_code_delivery_channel' => 'both',
            'foc_discount_percentage' => 0,
            'compliment_discount_percentage' => 100,
        ])
        ->assertRedirect(route('admin.settings.general.index'))
        ->assertSessionHas('success');

    $settings = GeneralSetting::instance();
    expect($settings->accurate_service_charge_account_no)->toBe('210202_SC')
        ->and($settings->accurate_bank_account_no)->toBe('110101_BANK')
        ->and($settings->accurate_cash_account_no)->toBe('110102_CASH');
});
