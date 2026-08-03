<?php

use App\Models\Area;
use App\Models\InternalUser;
use App\Models\User;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Administrator', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Cashier', 'guard_name' => 'web']);

    $this->roomArea = Area::firstOrCreate(
        ['code' => 'ROOM'],
        ['name' => 'Room Area', 'is_active' => true, 'sort_order' => 1]
    );

    $this->loungeArea = Area::firstOrCreate(
        ['code' => 'LOUNGE'],
        ['name' => 'Lounge Area', 'is_active' => true, 'sort_order' => 2]
    );
});

it('resolves correct SO prefix for room and lounge areas', function () {
    expect($this->roomArea->so_prefix)->toBe('ROOM-');
    expect($this->loungeArea->so_prefix)->toBe('LOUNGE-');
});

it('allows super admin to switch active area in session', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $profile = \App\Models\UserProfile::create(['user_id' => $admin->id]);
    InternalUser::create([
        'user_id' => $admin->id,
        'user_profile_id' => $profile->id,
        'area_id' => null,
    ]);

    actingAs($admin)
        ->withSession(['accurate_database' => ['session' => 'mock-db-session']])
        ->post(route('admin.switch-area'), ['area_id' => $this->loungeArea->id])
        ->assertRedirect();

    expect(session('active_area_id'))->toBe($this->loungeArea->id);
    expect($admin->resolveActiveArea()->id)->toBe($this->loungeArea->id);
});

it('restricts regular cashier to their assigned area', function () {
    $cashierUser = User::factory()->create();
    $cashierUser->assignRole('Cashier');

    $profile = \App\Models\UserProfile::create(['user_id' => $cashierUser->id]);
    InternalUser::create([
        'user_id' => $cashierUser->id,
        'user_profile_id' => $profile->id,
        'area_id' => $this->loungeArea->id,
    ]);

    expect($cashierUser->hasMultiAreaAccess())->toBeFalse();
    expect($cashierUser->resolveActiveArea()->id)->toBe($this->loungeArea->id);
});
