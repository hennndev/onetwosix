<?php

use App\Support\FeatureAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('feature yang diblokir bikin route admin 403 walau Administrator', function () {
    config()->set('features.blocked', ['rewards']);

    $admin = adminUser();

    actingAs($admin)->get(route('admin.rewards.index'))->assertForbidden();
});

test('feature yang tidak diblokir tetap bisa diakses', function () {
    config()->set('features.blocked', ['promos']);

    $admin = adminUser();

    actingAs($admin)->get(route('admin.rewards.index'))->assertSuccessful();
});

test('sidebar menyembunyikan tautan feature yang diblokir', function () {
    // User dengan permission dashboard + rewards agar tautan rewards kandidat tampil.
    $user = \App\Models\User::factory()->create();
    foreach (['admin.dashboard', 'admin.rewards.*'] as $ability) {
        Permission::firstOrCreate(['name' => $ability]);
    }
    $user->givePermissionTo(['admin.dashboard', 'admin.rewards.*']);

    // Tanpa blokir: tautan rewards muncul di sidebar.
    config()->set('features.blocked', []);
    $htmlOn = actingAs($user)->get(route('admin.dashboard'))->getContent();
    expect($htmlOn)->toContain(route('admin.rewards.index'));

    // Dengan blokir: route 403 + tautan rewards hilang dari sidebar.
    config()->set('features.blocked', ['rewards']);
    actingAs($user)->get(route('admin.rewards.index'))->assertForbidden();
    $htmlOff = actingAs($user)->get(route('admin.dashboard'))->getContent();
    expect($htmlOff)->not->toContain(route('admin.rewards.index'));
});

test('helper FeatureAccess memetakan route ke feature dengan benar', function () {
    config()->set('features.blocked', ['rewards', 'transaction-history']);

    expect(FeatureAccess::isRouteBlocked('admin.rewards.index'))->toBeTrue();
    expect(FeatureAccess::isRouteBlocked('admin.rewards.redeem'))->toBeTrue();
    expect(FeatureAccess::isRouteBlocked('admin.transaction-history.index'))->toBeTrue();
    expect(FeatureAccess::isRouteBlocked('admin.pos.index'))->toBeFalse();
    expect(FeatureAccess::isRouteBlocked('login'))->toBeFalse();
    expect(FeatureAccess::isRouteBlocked(null))->toBeFalse();
    expect(FeatureAccess::isFeatureBlocked('REWARDS'))->toBeTrue(); // case-insensitive
});

test('tanpa BLOCKED_FEATURES semua feature aktif', function () {
    config()->set('features.blocked', []);

    expect(FeatureAccess::isFeatureBlocked('rewards'))->toBeFalse();

    $admin = adminUser();
    actingAs($admin)->get(route('admin.rewards.index'))->assertSuccessful();
});
