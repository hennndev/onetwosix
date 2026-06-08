<?php

use App\Models\Area;
use App\Models\DisplayMessageRequest;
use App\Models\Tabel;
use App\Models\TableReservation;
use App\Models\TableSession;
use App\Models\User;
use Spatie\Permission\Models\Role;

function makeDisplayMessageWaiter(): User
{
    Role::firstOrCreate(['name' => 'Waiter/Server', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('Waiter/Server');

    return $user;
}

function makeActiveGuestSession(User $customer, User $waiter, string $tableNumber = 'VIP-1'): TableSession
{
    $area = Area::create([
        'code' => 'WTR-'.uniqid(),
        'name' => 'Waiter Area '.uniqid(),
        'capacity' => 20,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $table = Tabel::create([
        'area_id' => $area->id,
        'table_number' => $tableNumber,
        'qr_code' => 'QR-'.$tableNumber.'-'.uniqid(),
        'capacity' => 4,
        'minimum_charge' => 0,
        'status' => 'occupied',
        'is_active' => true,
    ]);

    $reservation = TableReservation::create([
        'booking_code' => random_int(100000, 999999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => today(),
        'reservation_time' => now()->format('H:i:s'),
        'status' => 'checked_in',
    ]);

    return TableSession::create([
        'table_reservation_id' => $reservation->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'waiter_id' => $waiter->id,
        'session_code' => 'SES-'.uniqid(),
        'checked_in_at' => now(),
        'status' => 'active',
    ]);
}

test('waiter can submit display message from the request page', function () {
    $waiter = makeDisplayMessageWaiter();
    $customer = User::factory()->create();
    $session = makeActiveGuestSession($customer, $waiter);

    $this->actingAs($waiter)
        ->withSession(['accurate_database' => 'test'])
        ->post(route('waiter.display-messages.store'), [
            'session_id' => $session->id,
            'message' => 'Happy birthday untuk meja VIP 1',
            'tip' => 50000,
        ])
        ->assertRedirect(route('waiter.display-messages.index', ['session_id' => $session->id]))
        ->assertSessionHas('success', 'Display message berhasil dikirim.');

    $displayMessage = DisplayMessageRequest::query()->first();

    expect(DisplayMessageRequest::query()->count())->toBe(1)
        ->and($displayMessage?->customer_id)->toBe($customer->id);

    expect($displayMessage?->message)->toBe('Happy birthday untuk meja VIP 1')
        ->and((int) $displayMessage?->tip)->toBe(50000)
        ->and($displayMessage?->status)->toBe('pending');
});

test('non waiter cannot submit display message from the request page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['accurate_database' => 'test'])
        ->post(route('waiter.display-messages.store'), [
            'message' => 'Test message',
        ])
        ->assertForbidden();
});

test('waiter can see display message request list on the request page', function () {
    $waiter = makeDisplayMessageWaiter();
    $customer = User::factory()->create();
    $session = makeActiveGuestSession($customer, $waiter, 'VIP-2');

    DisplayMessageRequest::create([
        'customer_id' => $customer->id,
        'message' => 'Request lagu ulang tahun',
        'tip' => 25000,
        'status' => 'pending',
    ]);

    $this->actingAs($waiter)
        ->withSession(['accurate_database' => 'test'])
        ->get(route('waiter.display-messages.index', ['session_id' => $session->id]))
        ->assertOk()
        ->assertViewIs('waiter.display-messages')
        ->assertSee('Request lagu ulang tahun', false)
        ->assertSee('Pending', false)
        ->assertSee('Meja VIP-2', false);
});
