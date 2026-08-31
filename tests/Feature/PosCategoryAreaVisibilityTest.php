<?php

use App\Models\Area;
use App\Models\InternalUser;
use App\Models\PosCategorySetting;
use App\Models\User;
use App\Models\UserProfile;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    PosCategorySetting::clearCache();
    $this->areaA = Area::create([
        'code' => 'AR-A-'.uniqid(),
        'name' => 'Area A '.uniqid(),
        'capacity' => 10,
        'is_active' => true,
        'sort_order' => 1,
    ]);
    $this->areaB = Area::create([
        'code' => 'AR-B-'.uniqid(),
        'name' => 'Area B '.uniqid(),
        'capacity' => 10,
        'is_active' => true,
        'sort_order' => 2,
    ]);
});

function areaScopedAdmin(?int $areaId): User
{
    $user = User::factory()->create();
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Administrator', 'guard_name' => 'web']);
    $user->assignRole('Administrator');
    $profile = UserProfile::create(['user_id' => $user->id]);

    InternalUser::create([
        'user_id' => $user->id,
        'user_profile_id' => $profile->id,
        'area_id' => $areaId,
    ]);

    return $user;
}

it('shows category in every area when area_ids is empty', function () {
    PosCategorySetting::create([
        'category_type' => 'beverage',
        'show_in_pos' => true,
        'area_ids' => null,
    ]);

    expect(PosCategorySetting::visibleInArea($this->areaA->id)->has('beverage'))->toBeTrue()
        ->and(PosCategorySetting::visibleInArea($this->areaB->id)->has('beverage'))->toBeTrue()
        ->and(PosCategorySetting::visibleInArea(null)->has('beverage'))->toBeTrue();
});

it('shows category only in selected areas', function () {
    PosCategorySetting::create([
        'category_type' => 'beverage',
        'show_in_pos' => true,
        'area_ids' => [$this->areaA->id],
    ]);

    expect(PosCategorySetting::visibleInArea($this->areaA->id)->has('beverage'))->toBeTrue()
        ->and(PosCategorySetting::visibleInArea($this->areaB->id)->has('beverage'))->toBeFalse()
        ->and(PosCategorySetting::visibleInArea(null)->has('beverage'))->toBeTrue();
});

it('hides category everywhere when show_in_pos is off even with areas selected', function () {
    PosCategorySetting::create([
        'category_type' => 'beverage',
        'show_in_pos' => false,
        'area_ids' => [$this->areaA->id],
    ]);

    expect(PosCategorySetting::visibleInArea($this->areaA->id)->has('beverage'))->toBeFalse()
        ->and(PosCategorySetting::visibleInArea(null)->has('beverage'))->toBeFalse();
});

it('saves area toggles from the settings form', function () {
    $admin = areaScopedAdmin(null);

    $response = actingAs($admin)->post(route('admin.settings.pos-categories.save'), [
        'categories' => [
            'beverage' => [
                '_present' => '1',
                'show_in_pos' => '1',
                'is_menu' => '0',
                'areas' => [(string) $this->areaA->id],
            ],
            'main-course' => [
                '_present' => '1',
                'show_in_pos' => '1',
                'is_menu' => '0',
            ],
        ],
    ]);

    $response->assertRedirect(route('admin.settings.pos-categories.index'));

    $beverage = PosCategorySetting::where('category_type', 'beverage')->first();
    expect($beverage->area_ids)->toBe([$this->areaA->id])
        ->and($beverage->isVisibleInArea($this->areaA->id))->toBeTrue()
        ->and($beverage->isVisibleInArea($this->areaB->id))->toBeFalse();

    // No areas submitted = null = visible everywhere.
    $mainCourse = PosCategorySetting::where('category_type', 'main-course')->first();
    expect($mainCourse->area_ids)->toBeNull()
        ->and($mainCourse->isVisibleInArea($this->areaA->id))->toBeTrue();
});

it('rejects unknown area ids in the settings form', function () {
    $admin = areaScopedAdmin(null);

    actingAs($admin)->post(route('admin.settings.pos-categories.save'), [
        'categories' => [
            'beverage' => [
                '_present' => '1',
                'show_in_pos' => '1',
                'areas' => ['999999'],
            ],
        ],
    ])->assertInvalid(['categories.beverage.areas.0']);
});

it('filters POS categories by the logged in user area', function () {
    PosCategorySetting::create([
        'category_type' => 'beverage',
        'show_in_pos' => true,
        'area_ids' => [$this->areaB->id],
    ]);

    $userA = areaScopedAdmin($this->areaA->id);
    expect(PosCategorySetting::visibleInArea($userA->resolveActiveAreaId())->has('beverage'))->toBeFalse();

    $userB = areaScopedAdmin($this->areaB->id);
    expect(PosCategorySetting::visibleInArea($userB->resolveActiveAreaId())->has('beverage'))->toBeTrue();
});
