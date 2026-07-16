<?php

use App\Models\DailyAuthCode;
use App\Models\GeneralSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\actingAs;

test('daily auth code can be sent to configured target whatsapp via Fonnte', function () {
    $admin = adminUser();

    GeneralSetting::instance()->update([
        'auth_code_target_email' => null,
        'auth_code_target_whatsapp' => '08123456789',
        'auth_code_delivery_channel' => 'whatsapp',
        'fonnte_token' => 'test-token',
        'daily_auth_code_access_emails' => $admin->email,
    ]);

    DailyAuthCode::forDate(now()->format('Y-m-d'))->update([
        'code' => '1234',
        'override_code' => null,
    ]);

    Http::fake([
        'api.fonnte.com/*' => Http::response(['status' => true], 200),
    ]);

    actingAs($admin)
        ->withSession(['accurate_database' => 'test'])
        ->postJson(route('admin.settings.daily-auth-code.send-email'))
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.fonnte.com/send' &&
            $request->header('Authorization')[0] === 'test-token' &&
            $request['target'] === '08123456789' &&
            str_contains($request['message'], '1234');
    });
});

test('daily auth code request fails when neither email nor whatsapp are configured', function () {
    $admin = adminUser();

    GeneralSetting::instance()->update([
        'auth_code_target_email' => null,
        'auth_code_target_whatsapp' => null,
        'auth_code_delivery_channel' => 'whatsapp',
        'daily_auth_code_access_emails' => $admin->email,
    ]);

    Http::fake();
    Mail::fake();

    actingAs($admin)
        ->withSession(['accurate_database' => 'test'])
        ->postJson(route('admin.settings.daily-auth-code.send-email'))
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
        ]);

    Http::assertNothingSent();
    Mail::assertNothingSent();
});

test('daily auth code whatsapp is not sent when channel is set to email only', function () {
    $admin = adminUser();

    GeneralSetting::instance()->update([
        'auth_code_target_email' => 'approval@company.test',
        'auth_code_target_whatsapp' => '08123456789',
        'auth_code_delivery_channel' => 'email',
        'fonnte_token' => 'test-token',
        'daily_auth_code_access_emails' => $admin->email,
    ]);

    DailyAuthCode::forDate(now()->format('Y-m-d'))->update([
        'code' => '1234',
        'override_code' => null,
    ]);

    Mail::fake();
    Http::fake();

    actingAs($admin)
        ->withSession(['accurate_database' => 'test'])
        ->postJson(route('admin.settings.daily-auth-code.send-email'))
        ->assertOk();

    Http::assertNothingSent();
});
