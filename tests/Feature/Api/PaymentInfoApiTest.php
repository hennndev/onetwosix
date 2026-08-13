<?php

use App\Models\BankAccount;
use App\Models\WhatsappSetting;

it('returns active bank accounts and whatsapp in single endpoint', function () {
    BankAccount::create([
        'bank_name' => 'BCA',
        'account_number' => '1234567890',
        'account_holder' => 'PT 126 Club',
        'is_active' => true,
    ]);

    BankAccount::create([
        'bank_name' => 'Mandiri',
        'account_number' => '0987654321',
        'account_holder' => 'PT 126 Club',
        'is_active' => false,
    ]);

    WhatsappSetting::create([
        'phone_number' => '6281234567890',
        'description' => 'Konfirmasi booking',
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/v1/payment-info');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data.bank_accounts')
        ->assertJsonPath('data.bank_accounts.0.bank_name', 'BCA')
        ->assertJsonPath('data.whatsapp.phone_number', '6281234567890');
});

it('returns null whatsapp when none is active', function () {
    BankAccount::create([
        'bank_name' => 'BNI',
        'account_number' => '1122334455',
        'account_holder' => 'PT 126 Club',
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/v1/payment-info');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data.bank_accounts')
        ->assertJsonPath('data.whatsapp', null);
});

it('returns empty bank accounts when none are active', function () {
    WhatsappSetting::create([
        'phone_number' => '6281234567890',
        'description' => 'Konfirmasi',
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/v1/payment-info');

    $response->assertSuccessful()
        ->assertJsonCount(0, 'data.bank_accounts')
        ->assertJsonPath('data.whatsapp.phone_number', '6281234567890');
});

it('returns empty data when nothing is configured', function () {
    $response = $this->getJson('/api/v1/payment-info');

    $response->assertSuccessful()
        ->assertJsonCount(0, 'data.bank_accounts')
        ->assertJsonPath('data.whatsapp', null);
});
