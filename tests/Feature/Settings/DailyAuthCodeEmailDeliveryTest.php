<?php

use App\Mail\DailyAuthCodeDeliveryMail;
use App\Models\DailyAuthCode;
use App\Models\GeneralSetting;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\actingAs;

test('daily auth code can be sent to configured target email', function () {
    $admin = adminUser();

    GeneralSetting::instance()->update([
        'auth_code_target_email' => 'approval@company.test',
        'auth_code_delivery_channel' => 'email',
        'daily_auth_code_access_emails' => $admin->email,
    ]);

    DailyAuthCode::forDate(now()->format('Y-m-d'))->update([
        'code' => '1234',
        'override_code' => null,
    ]);

    Mail::fake();

    actingAs($admin)
        ->withSession(['accurate_database' => 'test'])
        ->postJson(route('admin.settings.daily-auth-code.send-email'))
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    Mail::assertSent(DailyAuthCodeDeliveryMail::class, function (DailyAuthCodeDeliveryMail $mail): bool {
        return $mail->hasTo('approval@company.test') && $mail->code === '1234';
    });
});

test('daily auth code email request fails when target email is not configured', function () {
    $admin = adminUser();

    GeneralSetting::instance()->update([
        'auth_code_target_email' => null,
        'auth_code_delivery_channel' => 'email',
        'daily_auth_code_access_emails' => $admin->email,
    ]);

    Mail::fake();

    actingAs($admin)
        ->withSession(['accurate_database' => 'test'])
        ->postJson(route('admin.settings.daily-auth-code.send-email'))
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
        ]);

    Mail::assertNothingSent();
});

test('selected item discount auth code request records successful delivery in session', function () {
    $admin = adminUser();

    GeneralSetting::instance()->update([
        'auth_code_target_email' => 'approval@company.test',
        'auth_code_delivery_channel' => 'email',
        'daily_auth_code_access_emails' => $admin->email,
    ]);

    Mail::fake();

    actingAs($admin)
        ->withSession(['accurate_database' => 'test'])
        ->postJson(route('admin.settings.daily-auth-code.send-email'), [
            'source' => 'pos-selected-item-discount',
        ])
        ->assertOk()
        ->assertSessionHas('pos_discount_auth_code_requested_at');
});

test('booking close discount auth code request records successful delivery in session', function () {
    $admin = adminUser();

    GeneralSetting::instance()->update([
        'auth_code_target_email' => 'approval@company.test',
        'auth_code_delivery_channel' => 'email',
        'daily_auth_code_access_emails' => $admin->email,
    ]);

    Mail::fake();

    actingAs($admin)
        ->withSession(['accurate_database' => 'test'])
        ->postJson(route('admin.settings.daily-auth-code.send-email'), [
            'source' => 'booking-close-discount',
        ])
        ->assertOk()
        ->assertSessionHas('booking_discount_auth_code_requested_at');
});

test('daily auth code email is not sent when channel is set to whatsapp only', function () {
    $admin = adminUser();

    GeneralSetting::instance()->update([
        'auth_code_target_email' => 'approval@company.test',
        'auth_code_target_whatsapp' => '08123456789',
        'auth_code_delivery_channel' => 'whatsapp',
        'fonnte_token' => 'test-token',
        'daily_auth_code_access_emails' => $admin->email,
    ]);

    DailyAuthCode::forDate(now()->format('Y-m-d'))->update([
        'code' => '1234',
        'override_code' => null,
    ]);

    Mail::fake();
    Http::fake([
        'api.fonnte.com/*' => Http::response(['status' => true], 200),
    ]);

    actingAs($admin)
        ->withSession(['accurate_database' => 'test'])
        ->postJson(route('admin.settings.daily-auth-code.send-email'))
        ->assertOk();

    Mail::assertNothingSent();
});
