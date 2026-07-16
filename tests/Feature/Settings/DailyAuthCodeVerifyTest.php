<?php

use App\Models\DailyAuthCode;

test('verify returns valid true when code matches active code', function () {
    $user = adminUser();
    $today = now()->format('Y-m-d');

    \App\Models\GeneralSetting::instance()->update([
        'daily_auth_code_access_emails' => $user->email,
    ]);

    DailyAuthCode::create([
        'date' => $today,
        'code' => '1234',
        'generated_at' => now(),
    ]);

    $this->actingAs($user)
        ->withSession(['accurate_database' => 'test'])
        ->postJson(route('admin.settings.daily-auth-code.verify'), ['code' => '1234'])
        ->assertOk()
        ->assertJson(['valid' => true]);
});

test('verify returns valid false when code does not match', function () {
    $user = adminUser();
    $today = now()->format('Y-m-d');

    \App\Models\GeneralSetting::instance()->update([
        'daily_auth_code_access_emails' => $user->email,
    ]);

    DailyAuthCode::create([
        'date' => $today,
        'code' => '1234',
        'generated_at' => now(),
    ]);

    $this->actingAs($user)
        ->withSession(['accurate_database' => 'test'])
        ->postJson(route('admin.settings.daily-auth-code.verify'), ['code' => '9999'])
        ->assertOk()
        ->assertJson(['valid' => false]);
});

test('verify uses override code when set', function () {
    $user = adminUser();
    $today = now()->format('Y-m-d');

    \App\Models\GeneralSetting::instance()->update([
        'daily_auth_code_access_emails' => $user->email,
    ]);

    DailyAuthCode::create([
        'date' => $today,
        'code' => '1234',
        'override_code' => '5678',
        'generated_at' => now(),
    ]);

    $this->actingAs($user)
        ->withSession(['accurate_database' => 'test'])
        ->postJson(route('admin.settings.daily-auth-code.verify'), ['code' => '5678'])
        ->assertOk()
        ->assertJson(['valid' => true]);
});

test('verify requires exactly 4 digits', function () {
    $user = adminUser();

    \App\Models\GeneralSetting::instance()->update([
        'daily_auth_code_access_emails' => $user->email,
    ]);

    $this->actingAs($user)
        ->withSession(['accurate_database' => 'test'])
        ->postJson(route('admin.settings.daily-auth-code.verify'), ['code' => '12'])
        ->assertUnprocessable();
});

test('verify requires authentication', function () {
    $this->postJson(route('admin.settings.daily-auth-code.verify'), ['code' => '1234'])
        ->assertUnauthorized();
});
