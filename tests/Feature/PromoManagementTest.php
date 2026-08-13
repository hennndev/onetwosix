<?php

use App\Models\Promo;
use Database\Seeders\RolePermissionsSeeder;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

test('manager sees promo menu directly below events and can open promo management', function () {
    $this->seed(RolePermissionsSeeder::class);

    $manager = \App\Models\User::factory()->create();
    $manager->assignRole(Role::findByName('Manager'));

    Promo::create([
        'name' => 'Happy Hour Test',
        'slug' => 'happy-hour-test',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'is_active' => true,
        'discount_type' => 'percentage',
        'discount_value' => 20,
    ]);

    actingAs($manager)
        ->get(route('admin.promos.index'))
        ->assertSuccessful()
        ->assertSeeInOrder(['Acara', 'Promo'])
        ->assertSee('Happy Hour Test');
});
