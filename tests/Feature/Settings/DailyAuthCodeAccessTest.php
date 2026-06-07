<?php

use App\Models\GeneralSetting;
use App\Models\User;

use function Pest\Laravel\actingAs;

test('administrator with whitelisted email can open daily auth code settings page', function () {
    $admin = adminUser();

    GeneralSetting::instance()->update([
        'daily_auth_code_access_emails' => $admin->email,
    ]);

    actingAs($admin)
        ->withSession(['accurate_database' => 'test'])
        ->get(route('admin.settings.daily-auth-code.index'))
        ->assertOk()
        ->assertViewIs('settings.daily-auth-code');
});

test('administrator without whitelisted email is forbidden from daily auth code settings page', function () {
    $admin = adminUser();

    GeneralSetting::instance()->update([
        'daily_auth_code_access_emails' => 'approval@company.test',
    ]);

    actingAs($admin)
        ->withSession(['accurate_database' => 'test'])
        ->get(route('admin.settings.daily-auth-code.index'))
        ->assertForbidden();
});

test('non-administrator with whitelisted email is forbidden from daily auth code settings page', function () {
    $user = User::factory()->create([
        'email' => 'approval@company.test',
    ]);

    GeneralSetting::instance()->update([
        'daily_auth_code_access_emails' => 'approval@company.test',
    ]);

    actingAs($user)
        ->withSession(['accurate_database' => 'test'])
        ->get(route('admin.settings.daily-auth-code.index'))
        ->assertForbidden();
});
