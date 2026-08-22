<?php

use App\Models\Tier;

use function Pest\Laravel\actingAs;

test('admin can view tier settings page', function () {
    $admin = adminUser();

    actingAs($admin)
        ->withSession(['accurate_database' => 'test'])
        ->get(route('admin.settings.tier-settings.index'))
        ->assertOk();
});

test('first created tier becomes the first tier with zero minimum spent', function () {
    $admin = adminUser();

    actingAs($admin)->withSession(['accurate_database' => 'test'])->post(route('admin.settings.tier-settings.store'), [
        'name' => 'Registered',
        'discount_percentage' => 0,
        'minimum_spent' => 999999,
        'color' => 'slate',
    ])->assertRedirect(route('admin.settings.tier-settings.index'));

    $tier = Tier::sole();
    expect($tier->is_first_tier)->toBeTrue()
        ->and($tier->minimum_spent)->toBe(0)
        ->and($tier->level)->toBe(1);
});

test('subsequent tiers are not first tier and level auto-increments', function () {
    $admin = adminUser();

    Tier::create(['name' => 'Base', 'level' => 1, 'discount_percentage' => 0, 'minimum_spent' => 0, 'is_first_tier' => true, 'color' => 'slate']);

    actingAs($admin)->withSession(['accurate_database' => 'test'])->post(route('admin.settings.tier-settings.store'), [
        'name' => 'VIP',
        'discount_percentage' => 10,
        'minimum_spent' => 5000000,
        'color' => 'amber',
        'description' => 'Benefit VIP',
    ])->assertRedirect();

    $tier = Tier::where('name', 'VIP')->sole();
    expect($tier->is_first_tier)->toBeFalse()
        ->and($tier->level)->toBe(2)
        ->and($tier->description)->toBe('Benefit VIP');
});

test('admin can update tier', function () {
    $admin = adminUser();
    $tier = Tier::create(['name' => 'Old', 'level' => 1, 'discount_percentage' => 5, 'minimum_spent' => 1000, 'is_first_tier' => false, 'color' => 'blue']);

    actingAs($admin)->withSession(['accurate_database' => 'test'])->put(route('admin.settings.tier-settings.update', $tier), [
        'name' => 'New',
        'discount_percentage' => 15,
        'minimum_spent' => 2000,
        'color' => 'green',
        'description' => 'Updated',
    ])->assertRedirect();

    expect($tier->fresh())
        ->name->toBe('New')
        ->discount_percentage->toBe(15)
        ->minimum_spent->toBe(2000)
        ->color->toBe('green');
});

test('updating first tier forces minimum_spent to zero', function () {
    $admin = adminUser();
    $tier = Tier::create(['name' => 'Base', 'level' => 1, 'discount_percentage' => 0, 'minimum_spent' => 0, 'is_first_tier' => true, 'color' => 'slate']);

    actingAs($admin)->withSession(['accurate_database' => 'test'])->put(route('admin.settings.tier-settings.update', $tier), [
        'name' => 'Base',
        'discount_percentage' => 0,
        'minimum_spent' => 500000,
        'color' => 'slate',
    ])->assertRedirect();

    expect($tier->fresh()->minimum_spent)->toBe(0);
});

test('deleting first tier promotes the lowest remaining tier', function () {
    $admin = adminUser();
    $first = Tier::create(['name' => 'Base', 'level' => 1, 'discount_percentage' => 0, 'minimum_spent' => 0, 'is_first_tier' => true, 'color' => 'slate']);
    $second = Tier::create(['name' => 'Mid', 'level' => 2, 'discount_percentage' => 5, 'minimum_spent' => 5000000, 'is_first_tier' => false, 'color' => 'blue']);

    actingAs($admin)->withSession(['accurate_database' => 'test'])->delete(route('admin.settings.tier-settings.destroy', $first))->assertRedirect();

    expect(Tier::find($first->id))->toBeNull();

    $promoted = $second->fresh();
    expect($promoted->is_first_tier)->toBeTrue()
        ->and($promoted->minimum_spent)->toBe(0);
});

test('deleting non-first tier does not change first tier', function () {
    $admin = adminUser();
    $first = Tier::create(['name' => 'Base', 'level' => 1, 'discount_percentage' => 0, 'minimum_spent' => 0, 'is_first_tier' => true, 'color' => 'slate']);
    $second = Tier::create(['name' => 'Mid', 'level' => 2, 'discount_percentage' => 5, 'minimum_spent' => 5000000, 'is_first_tier' => false, 'color' => 'blue']);

    actingAs($admin)->withSession(['accurate_database' => 'test'])->delete(route('admin.settings.tier-settings.destroy', $second))->assertRedirect();

    expect($first->fresh()->is_first_tier)->toBeTrue();
});

test('color must be in palette', function () {
    $admin = adminUser();

    actingAs($admin)->withSession(['accurate_database' => 'test'])->post(route('admin.settings.tier-settings.store'), [
        'name' => 'Bad',
        'discount_percentage' => 5,
        'minimum_spent' => 0,
        'color' => 'not-a-color',
    ])->assertSessionHasErrors('color');
});
