<?php

use App\Models\DailyAuthCode;
use App\Models\GeneralSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Regresi kasus produksi: tombol "Request Auth Code" di POS selalu
 * "Akses ditolak" — semua route admin.settings.daily-auth-code.* tadinya
 * dijaga whitelist email, padahal endpoint verify/send-email dipakai kasir
 * di alur checkout dan tidak mengekspos kode apa pun.
 */
function kasirUser(): \App\Models\User
{
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Cashier']);
    $user = \App\Models\User::factory()->create(['type' => 'internal']);
    $user->assignRole('Cashier');

    return $user;
}

function seedDailyAuthCode(): DailyAuthCode
{
    GeneralSetting::instance()->update([
        'auth_code_target_email' => 'manager@126club.com',
        'auth_code_target_whatsapp' => '08123456789',
        'auth_code_delivery_channel' => 'both',
    ]);

    return DailyAuthCode::create([
        'date' => now()->toDateString(),
        'code' => '2468',
        'generated_at' => now(),
    ]);
}

test('kasir bisa verify kode dari POS (tidak lagi 403)', function () {
    seedDailyAuthCode();
    $cashier = kasirUser();

    actingAs($cashier)
        ->postJson(route('admin.settings.daily-auth-code.verify'), ['code' => '2468'])
        ->assertSuccessful()
        ->assertJsonPath('valid', true);

    actingAs($cashier)
        ->postJson(route('admin.settings.daily-auth-code.verify'), ['code' => '9999'])
        ->assertSuccessful()
        ->assertJsonPath('valid', false);
});

test('kasir bisa request auth code via email/wa dari POS (tidak lagi 403)', function () {
    $record = seedDailyAuthCode();
    Mail::fake();
    Http::fake(['*' => Http::response(['status' => true], 200)]);

    $cashier = kasirUser();

    actingAs($cashier)
        ->postJson(route('admin.settings.daily-auth-code.send-email'))
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    Mail::assertSent(\App\Mail\DailyAuthCodeDeliveryMail::class, fn ($mail): bool => $mail->code === $record->active_code);
});

test('kasir tetap tidak bisa membuka halaman/manajemen auth code', function () {
    seedDailyAuthCode();
    $cashier = kasirUser();

    actingAs($cashier)->get(route('admin.settings.daily-auth-code.index'))->assertForbidden();
    actingAs($cashier)->postJson(route('admin.settings.daily-auth-code.regenerate'))->assertForbidden();
    actingAs($cashier)->postJson(route('admin.settings.daily-auth-code.override'), ['code' => '1111'])->assertForbidden();
});

test('administrator di luar whitelist tetap tidak bisa manajemen kode, tapi bisa workflow POS', function () {
    seedDailyAuthCode();
    Mail::fake();
    Http::fake(['*' => Http::response(['status' => true], 200)]);

    $admin = \App\Models\User::factory()->create(['type' => 'internal', 'email' => 'otheradmin@126club.com']);
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Administrator']);
    $admin->assignRole('Administrator');

    actingAs($admin)->get(route('admin.settings.daily-auth-code.index'))->assertForbidden();

    actingAs($admin)
        ->postJson(route('admin.settings.daily-auth-code.send-email'))
        ->assertSuccessful()
        ->assertJsonPath('success', true);
});
