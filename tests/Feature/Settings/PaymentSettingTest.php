<?php

use App\Models\BankAccount;
use App\Models\QrisSetting;
use App\Models\WhatsappSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

it('shows payment settings from the settings menu', function () {
    $admin = adminUser();

    actingAs($admin)->get(route('admin.settings.index'))
        ->assertSuccessful()
        ->assertSee('Pengaturan Pembayaran');

    actingAs($admin)->get(route('admin.settings.payment.index'))
        ->assertSuccessful()
        ->assertSee('Pengaturan Pembayaran')
        ->assertSee('Rekening Bank')
        ->assertSee('Nomor WhatsApp Konfirmasi')
        ->assertSee('QRIS');
});

it('redirects guests away from payment settings', function () {
    $this->get('/admin/settings/payment')->assertRedirect(route('login'));
});

it('creates updates and deletes a bank account', function () {
    $admin = adminUser();

    actingAs($admin)->post(route('admin.settings.payment.bank-accounts.store'), [
        'bank_name' => 'BCA',
        'account_number' => '1234567890',
        'account_holder' => 'PT 126 Club',
        'is_active' => '1',
    ])->assertRedirect(route('admin.settings.payment.index'))
        ->assertSessionHas('success');

    $account = BankAccount::query()->where('bank_name', 'BCA')->firstOrFail();
    expect($account->is_active)->toBeTrue();

    actingAs($admin)->put(route('admin.settings.payment.bank-accounts.update', $account), [
        'bank_name' => 'BCA Digital',
        'account_number' => '9999999999',
        'account_holder' => 'PT 126 Club',
        'is_active' => '0',
    ])->assertRedirect(route('admin.settings.payment.index'));

    expect($account->refresh()->bank_name)->toBe('BCA Digital')
        ->and($account->account_number)->toBe('9999999999')
        ->and($account->is_active)->toBeFalse();

    actingAs($admin)->delete(route('admin.settings.payment.bank-accounts.destroy', $account))
        ->assertRedirect(route('admin.settings.payment.index'));

    expect(BankAccount::query()->whereKey($account)->exists())->toBeFalse();
});

it('validates bank account fields', function () {
    actingAs(adminUser())->post(route('admin.settings.payment.bank-accounts.store'), [])
        ->assertSessionHasErrors(['bank_name', 'account_number', 'account_holder']);
});

it('replaces the active whatsapp setting and exposes it to the mobile api', function () {
    $oldWhatsapp = WhatsappSetting::create([
        'phone_number' => '6281111111111',
        'description' => 'Lama',
        'is_active' => true,
    ]);

    actingAs(adminUser())->post(route('admin.settings.payment.whatsapp.save'), [
        'phone_number' => '6282222222222',
        'description' => 'Nomor konfirmasi utama',
    ])->assertRedirect(route('admin.settings.payment.index'));

    expect($oldWhatsapp->refresh()->is_active)->toBeFalse()
        ->and(WhatsappSetting::query()->where('phone_number', '6282222222222')->where('is_active', true)->exists())->toBeTrue();

    $this->getJson('/api/v1/payment-info')
        ->assertSuccessful()
        ->assertJsonPath('data.whatsapp.phone_number', '6282222222222');
});

it('validates whatsapp format', function (string $phoneNumber) {
    actingAs(adminUser())->post(route('admin.settings.payment.whatsapp.save'), [
        'phone_number' => $phoneNumber,
    ])->assertSessionHasErrors('phone_number');
})->with(['+628123', '0812345', '62 8123']);

it('saves and replaces active qris while retaining referenced history files', function () {
    Storage::fake('public');
    $admin = adminUser();

    actingAs($admin)->post(route('admin.settings.payment.qris.save'), [
        'name' => '126 Club',
        'qris_image' => UploadedFile::fake()->image('qris-old.png', 600, 600),
    ])->assertRedirect(route('admin.settings.payment.index'));

    $oldQris = QrisSetting::query()->where('is_active', true)->firstOrFail();
    Storage::disk('public')->assertExists($oldQris->image_path);

    actingAs($admin)->post(route('admin.settings.payment.qris.save'), [
        'name' => 'One Two Six Club',
        'qris_image' => UploadedFile::fake()->image('qris-new.jpg', 600, 600),
    ])->assertRedirect(route('admin.settings.payment.index'));

    $newQris = QrisSetting::query()->where('is_active', true)->firstOrFail();
    expect($oldQris->refresh()->is_active)->toBeFalse()
        ->and($newQris->name)->toBe('One Two Six Club')
        ->and($newQris->image_path)->not->toBe($oldQris->image_path);
    Storage::disk('public')->assertExists($oldQris->image_path);
    Storage::disk('public')->assertExists($newQris->image_path);

    $this->getJson('/api/v1/payment-info')
        ->assertSuccessful()
        ->assertJsonPath('data.qris.name', 'One Two Six Club');
});

it('reuses the current qris image when no replacement is uploaded', function () {
    Storage::fake('public');
    $path = UploadedFile::fake()->image('qris.png')->store('qris', 'public');
    QrisSetting::create(['name' => 'Old', 'image_path' => $path, 'is_active' => true]);

    actingAs(adminUser())->post(route('admin.settings.payment.qris.save'), [
        'name' => 'Updated Merchant',
    ])->assertRedirect(route('admin.settings.payment.index'));

    $activeQris = QrisSetting::query()->where('is_active', true)->firstOrFail();
    expect($activeQris->name)->toBe('Updated Merchant')
        ->and($activeQris->image_path)->toBe($path);
    Storage::disk('public')->assertExists($path);
});

it('rejects unsupported qris uploads without changing the current setting', function () {
    Storage::fake('public');
    $current = QrisSetting::create(['name' => 'Current', 'is_active' => true]);

    actingAs(adminUser())->post(route('admin.settings.payment.qris.save'), [
        'name' => 'Invalid',
        'qris_image' => UploadedFile::fake()->create('qris.svg', 10, 'image/svg+xml'),
    ])->assertSessionHasErrors('qris_image');

    expect($current->refresh()->is_active)->toBeTrue()
        ->and(QrisSetting::query()->count())->toBe(1);
});
