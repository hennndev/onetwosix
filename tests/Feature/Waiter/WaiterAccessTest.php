<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Waiter/Server', 'guard_name' => 'web']);
});

function makeWaiter(): User
{
    $user = User::factory()->create();
    $user->assignRole('Waiter/Server');

    return $user;
}

test('unauthenticated user is redirected from waiter routes', function () {
    $this->get(route('waiter.scanner'))
        ->assertRedirect(route('login'));
});

test('non-waiter user is forbidden from waiter routes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['accurate_database' => 'test'])
        ->get(route('waiter.scanner'))
        ->assertForbidden();
});

test('waiter can access scanner page', function () {
    $this->actingAs(makeWaiter())
        ->withSession(['accurate_database' => 'test'])
        ->get(route('waiter.scanner'))
        ->assertOk()
        ->assertViewIs('waiter.scanner')
        ->assertSee('Flash On', false)
        ->assertSee('toggleTorch()', false);
});

test('waiter can access active tables page', function () {
    $this->actingAs(makeWaiter())
        ->withSession(['accurate_database' => 'test'])
        ->get(route('waiter.active-tables'))
        ->assertOk()
        ->assertViewIs('waiter.active-tables');
});

test('waiter can access pos page', function () {
    $this->actingAs(makeWaiter())
        ->withSession(['accurate_database' => 'test'])
        ->get(route('waiter.pos'))
        ->assertOk()
        ->assertViewIs('waiter.pos');
});

test('waiter can access notifications page', function () {
    $this->actingAs(makeWaiter())
        ->withSession(['accurate_database' => 'test'])
        ->get(route('waiter.notifications'))
        ->assertOk()
        ->assertViewIs('waiter.notifications');
});

test('waiter can access settings page', function () {
    $this->actingAs(makeWaiter())
        ->withSession(['accurate_database' => 'test'])
        ->get(route('waiter.settings'))
        ->assertOk()
        ->assertViewIs('waiter.settings')
        ->assertSee(route('waiter.display-messages.index'), false);
});

test('waiter can access display messages page', function () {
    $this->actingAs(makeWaiter())
        ->withSession(['accurate_database' => 'test'])
        ->get(route('waiter.display-messages.index'))
        ->assertOk()
        ->assertViewIs('waiter.display-messages')
        ->assertSee('Request Tamu', false);
});

test('waiter index redirects to scanner', function () {
    $this->actingAs(makeWaiter())
        ->withSession(['accurate_database' => 'test'])
        ->get(route('waiter.index'))
        ->assertRedirect(route('waiter.scanner'));
});
