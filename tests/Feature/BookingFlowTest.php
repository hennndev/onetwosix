<?php

use App\Models\Area;
use App\Models\Billing;
use App\Models\CustomerUser;
use App\Models\Event;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Printer;
use App\Models\Tabel;
use App\Models\TableReservation;
use App\Models\TableSession;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\PrinterService;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery\MockInterface;

use function Pest\Laravel\mock;

// ── Helpers ──────────────────────────────────────────────────────────────────

function makeArea(): Area
{
    return Area::create([
        'code' => 'AREA-'.uniqid(),
        'name' => 'Test Area '.uniqid(),
        'is_active' => true,
        'sort_order' => 1,
    ]);
}

function makeTable(Area $area, array $attrs = []): Tabel
{
    return Tabel::create(array_merge([
        'area_id' => $area->id,
        'table_number' => 'T-'.uniqid(),
        'qr_code' => 'QR-'.uniqid(),
        'capacity' => 4,
        'minimum_charge' => 100000,
        'status' => 'available',
        'is_active' => true,
    ], $attrs));
}

function makeBookingCustomer(): User
{
    // A plain User suffices — store/updateStatus only validate customer_id exists in users
    return User::factory()->create();
}

// ── Tests ─────────────────────────────────────────────────────────────────────

test('creating a booking always starts with pending status', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area);
    $customer = makeBookingCustomer();

    $this->actingAs($admin)
        ->post(route('admin.bookings.store'), [
            'table_id' => $table->id,
            'customer_id' => $customer->id,
            'reservation_date' => now()->addDays(3)->toDateString(),
            'reservation_time' => '19:00',
            'status' => 'confirmed', // attempt to bypass — should be ignored
            'note' => null,
        ])
        ->assertRedirect(route('admin.bookings.index'));

    $booking = TableReservation::where('table_id', $table->id)
        ->where('customer_id', $customer->id)
        ->first();

    expect($booking)->not->toBeNull()
        ->and($booking->status)->toBe('pending');
});

test('creating a booking does not change table status to reserved', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area);
    $customer = makeBookingCustomer();

    $this->actingAs($admin)
        ->post(route('admin.bookings.store'), [
            'table_id' => $table->id,
            'customer_id' => $customer->id,
            'reservation_date' => now()->addDays(3)->toDateString(),
            'reservation_time' => '19:00',
        ]);

    $table->refresh();
    expect($table->status)->toBe('available');
});

test('creating a booking can persist down payment amount', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area);
    $customer = makeBookingCustomer();

    $this->actingAs($admin)
        ->post(route('admin.bookings.store'), [
            'table_id' => $table->id,
            'customer_id' => $customer->id,
            'reservation_date' => now()->addDays(2)->toDateString(),
            'reservation_time' => '20:00',
            'has_down_payment' => '1',
            'down_payment_amount' => 50000,
        ])
        ->assertRedirect(route('admin.bookings.index'));

    $booking = TableReservation::where('table_id', $table->id)
        ->where('customer_id', $customer->id)
        ->latest('id')
        ->first();

    expect($booking)->not->toBeNull()
        ->and((float) $booking->down_payment_amount)->toBe(50000.0);
});

test('creating a booking keeps down payment optional even when checkbox is checked', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area);
    $customer = makeBookingCustomer();

    $this->actingAs($admin)
        ->post(route('admin.bookings.store'), [
            'table_id' => $table->id,
            'customer_id' => $customer->id,
            'reservation_date' => now()->addDays(2)->toDateString(),
            'reservation_time' => '20:00',
            'has_down_payment' => '1',
            'down_payment_amount' => null,
        ])
        ->assertRedirect(route('admin.bookings.index'));

    $booking = TableReservation::where('table_id', $table->id)
        ->where('customer_id', $customer->id)
        ->latest('id')
        ->first();

    expect($booking)->not->toBeNull()
        ->and((float) $booking->down_payment_amount)->toBe(0.0);
});

test('creating a booking is blocked when customer has an active table session', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area);
    $otherTable = makeTable($area, ['table_number' => 'T-OTHER-'.uniqid()]);
    $customer = makeBookingCustomer();

    TableSession::create([
        'table_id' => $otherTable->id,
        'customer_id' => $customer->id,
        'session_code' => 'SES-'.uniqid(),
        'status' => 'active',
        'checked_in_at' => now(),
    ]);

    $this->actingAs($admin)
        ->from(route('admin.bookings.index'))
        ->post(route('admin.bookings.store'), [
            'table_id' => $table->id,
            'customer_id' => $customer->id,
            'reservation_date' => now()->addDays(1)->toDateString(),
            'reservation_time' => '19:00',
        ])
        ->assertRedirect(route('admin.bookings.index'))
        ->assertSessionHasErrors('customer_id');

    $bookingCount = TableReservation::where('table_id', $table->id)
        ->where('customer_id', $customer->id)
        ->count();

    expect($bookingCount)->toBe(0);
});

test('confirming a booking sets table status to reserved', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area);
    $customer = makeBookingCustomer();

    $booking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->addDays(3)->toDateString(),
        'reservation_time' => '19:00',
        'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.bookings.updateStatus', $booking), ['status' => 'confirmed'])
        ->assertRedirect(route('admin.bookings.index'));

    $booking->refresh();
    $table->refresh();

    expect($booking->status)->toBe('confirmed')
        ->and($table->status)->toBe('reserved');
});

test('confirming a second booking for the same table and date fails', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area);
    $customerA = makeBookingCustomer();
    $customerB = makeBookingCustomer();
    $date = now()->addDays(3)->toDateString();

    // First booking already confirmed
    TableReservation::create([
        'booking_code' => rand(1000, 4999),
        'table_id' => $table->id,
        'customer_id' => $customerA->id,
        'reservation_date' => $date,
        'reservation_time' => '19:00',
        'status' => 'confirmed',
    ]);
    Tabel::where('id', $table->id)->update(['status' => 'reserved']);

    // Second pending booking for the same table/date
    $bookingB = TableReservation::create([
        'booking_code' => rand(5000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customerB->id,
        'reservation_date' => $date,
        'reservation_time' => '20:00',
        'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.bookings.updateStatus', $bookingB), ['status' => 'confirmed'])
        ->assertRedirect();

    $bookingB->refresh();
    expect($bookingB->status)->toBe('pending'); // still pending, not confirmed
});

test('checking in booking applies active event adjustment to minimum charge and shows it in active tab', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area, ['minimum_charge' => 100000]);
    $customer = makeBookingCustomer();
    $reservationDate = now()->addDay()->toDateString();

    Event::create([
        'name' => 'Saturday Party',
        'slug' => 'saturday-party-'.uniqid(),
        'description' => 'Event test adjustment',
        'start_date' => $reservationDate,
        'end_date' => $reservationDate,
        'start_time' => '18:00',
        'end_time' => '23:00',
        'is_active' => true,
        'price_adjustment_type' => 'percentage',
        'price_adjustment_value' => 10,
    ]);

    $booking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => $reservationDate,
        'reservation_time' => '20:00',
        'status' => 'confirmed',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.bookings.updateStatus', $booking), ['status' => 'checked_in'])
        ->assertRedirect(route('admin.bookings.index'));

    $session = TableSession::query()
        ->where('table_reservation_id', $booking->id)
        ->where('status', 'active')
        ->first();

    expect($session)->not->toBeNull();

    $billing = $session?->billing;

    expect($billing)->not->toBeNull()
        ->and((float) ($billing?->minimum_charge ?? 0))->toBe(110000.0);

    $this->actingAs($admin)
        ->get(route('admin.bookings.index', ['tab' => 'active']))
        ->assertOk()
        ->assertSee('Saturday Party')
        ->assertSee('Rp 110.000')
        ->assertSee('Min Event: Rp 110.000');

    $this->actingAs($admin)
        ->get(route('admin.bookings.index', ['tab' => 'all']))
        ->assertOk()
        ->assertSee('Event: Saturday Party')
        ->assertSee('"event_name":"Saturday Party"', false);
});

test('close billing trigger includes adjusted minimum charge from event', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area, ['minimum_charge' => 100000]);
    $customer = makeBookingCustomer();
    $reservationDate = now()->addDay()->toDateString();

    Event::create([
        'name' => 'Night Party',
        'slug' => 'night-party-'.uniqid(),
        'description' => 'Event test adjustment',
        'start_date' => $reservationDate,
        'end_date' => $reservationDate,
        'start_time' => '18:00',
        'end_time' => '23:00',
        'is_active' => true,
        'price_adjustment_type' => 'percentage',
        'price_adjustment_value' => 10,
    ]);

    $booking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => $reservationDate,
        'reservation_time' => '20:00',
        'status' => 'confirmed',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.bookings.updateStatus', $booking), ['status' => 'checked_in'])
        ->assertRedirect(route('admin.bookings.index'));

    $session = TableSession::query()
        ->where('table_reservation_id', $booking->id)
        ->where('status', 'active')
        ->first();

    expect($session)->not->toBeNull();

    Order::create([
        'table_session_id' => $session->id,
        'created_by' => $admin->id,
        'order_number' => 'ORD-'.uniqid(),
        'status' => 'pending',
        'items_total' => 150000,
        'discount_amount' => 0,
        'total' => 150000,
        'ordered_at' => now(),
    ]);

    // Min charge 100k + 10% = 110k (event is only adjustment, not a separate line)
    $this->actingAs($admin)
        ->get(route('admin.bookings.index', ['tab' => 'active']))
        ->assertOk()
        ->assertSee('data-minimum-charge="110000"', false);

    $this->actingAs($admin)
        ->get(route('admin.bookings.index', ['tab' => 'all']))
        ->assertOk()
        ->assertSee('Min Rp 110');
});

test('all tab booking card shows who made the booking', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area, ['status' => 'reserved']);
    $customer = User::factory()->create(['name' => 'Budi Booker']);

    $this->actingAs($admin)
        ->post(route('admin.bookings.store'), [
            'table_id' => $table->id,
            'customer_id' => $customer->id,
            'booking_name' => 'Birthday Table',
            'reservation_date' => now()->toDateString(),
            'reservation_time' => '20:00',
        ])
        ->assertRedirect(route('admin.bookings.index'));

    $booking = TableReservation::latest('id')->first();

    expect((int) $booking?->created_by)->toBe((int) $admin->id);

    $this->actingAs($admin)
        ->patch(route('admin.bookings.updateStatus', $booking), ['status' => 'confirmed'])
        ->assertRedirect(route('admin.bookings.index'));

    $this->actingAs($admin)
        ->get(route('admin.bookings.index', ['tab' => 'all']))
        ->assertOk()
        ->assertSee('Birthday Table')
        ->assertSee('Dibuat oleh: '.$admin->name.' (User)');
});

test('all tab pending bookings section shows who made the booking', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area);
    $customer = makeBookingCustomer();

    $this->actingAs($admin)
        ->post(route('admin.bookings.store'), [
            'table_id' => $table->id,
            'customer_id' => $customer->id,
            'booking_name' => 'Pending All Tab Booking',
            'reservation_date' => now()->addDay()->toDateString(),
            'reservation_time' => '18:00',
        ])
        ->assertRedirect(route('admin.bookings.index'));

    $this->actingAs($admin)
        ->get(route('admin.bookings.index', ['tab' => 'all']))
        ->assertOk()
        ->assertSee('Pending All Tab Booking')
        ->assertSee('Dibuat oleh: '.$admin->name.' (User)');
});

test('booking info modal shows creator source for customer bookings', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area, ['status' => 'reserved']);
    $customer = makeBookingCustomer();

    $creator = User::factory()->create(['name' => 'Customer Creator']);
    $creatorProfile = UserProfile::create([
        'user_id' => $creator->id,
    ]);
    CustomerUser::create([
        'user_id' => $creator->id,
        'user_profile_id' => $creatorProfile->id,
        'customer_code' => 'CUST-'.uniqid(),
        'accurate_id' => random_int(1000, 9999),
        'total_visits' => 0,
        'lifetime_spending' => 0,
    ]);

    $booking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'created_by' => $creator->id,
        'booking_name' => 'Customer Made Booking',
        'reservation_date' => now()->toDateString(),
        'reservation_time' => '20:00',
        'status' => 'confirmed',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.bookings.index', ['tab' => 'all']))
        ->assertOk()
        ->assertSee('Dibuat oleh: Customer Creator (Customer)');
});

test('completing a booking sets table status back to available', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area, ['status' => 'reserved']);
    $customer = makeBookingCustomer();

    $booking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->toDateString(),
        'reservation_time' => '19:00',
        'status' => 'confirmed',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.bookings.updateStatus', $booking), ['status' => 'completed'])
        ->assertRedirect(route('admin.bookings.index'));

    $table->refresh();
    expect($table->status)->toBe('available');
});

test('cancelling a booking sets table status back to available', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area, ['status' => 'reserved']);
    $customer = makeBookingCustomer();

    $booking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->toDateString(),
        'reservation_time' => '19:00',
        'status' => 'confirmed',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.bookings.updateStatus', $booking), ['status' => 'cancelled'])
        ->assertRedirect(route('admin.bookings.index'));

    $table->refresh();
    expect($table->status)->toBe('available');
});

test('booking modal keeps table selectable when only cancelled booking exists', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area, ['status' => 'reserved']);
    $customer = makeBookingCustomer();

    TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->addDay()->toDateString(),
        'reservation_time' => '19:00',
        'status' => 'cancelled',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.bookings.index'))
        ->assertOk();

    $response->assertSee($table->table_number)
        ->assertSee('•Free')
        ->assertDontSee('•Busy');
});

test('pending tab shows pending bookings and is accessible', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area);
    $customer = makeBookingCustomer();
    $creator = User::factory()->create(['name' => 'Pending Booker']);

    $pendingBooking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'created_by' => $creator->id,
        'booking_name' => 'Pending Booking Name',
        'reservation_date' => now()->addDays(2)->toDateString(),
        'reservation_time' => '20:00',
        'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.bookings.index', ['tab' => 'pending']))
        ->assertOk()
        ->assertViewIs('bookings.index')
        ->assertViewHas('tab', 'pending')
        ->assertViewHas('conflictingPendingKeys')
        ->assertViewHas('blockedPendingKeys')
        ->assertSeeText('Nama Booking')
        ->assertSeeText('Nama Customer')
        ->assertSeeText('Reservation ID')
        ->assertSeeText('Pending Booking Name')
        ->assertSeeText($customer->name)
        ->assertSeeText('Dibuat oleh: Pending Booker (User)')
        ->assertSeeText('#'.$pendingBooking->id);
});

test('reserved table keeps active booking mapping for confirmed booking even if reservation date is past', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area, ['status' => 'reserved']);
    $customer = makeBookingCustomer();

    $booking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->subDay()->toDateString(),
        'reservation_time' => '19:00',
        'status' => 'confirmed',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.bookings.index'))
        ->assertOk();

    $activeBookingsByTable = $response->viewData('activeBookingsByTable');

    expect($activeBookingsByTable)->not->toBeNull()
        ->and($activeBookingsByTable->has($table->id))->toBeTrue()
        ->and($activeBookingsByTable->get($table->id)?->id)->toBe($booking->id);
});

test('booking info modal payload includes down payment amount', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area, ['status' => 'reserved']);
    $customer = makeBookingCustomer();

    TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->addDay()->toDateString(),
        'reservation_time' => '20:00',
        'status' => 'confirmed',
        'down_payment_amount' => 50000,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.bookings.index'))
        ->assertOk()
        ->assertSee('"down_payment_amount":50000', false);
});

test('pending tab conflict keys include competing bookings', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area);
    $date = now()->addDays(5)->toDateString();
    $customerA = makeBookingCustomer();
    $customerB = makeBookingCustomer();

    TableReservation::create([
        'booking_code' => rand(1000, 4999),
        'table_id' => $table->id,
        'customer_id' => $customerA->id,
        'reservation_date' => $date,
        'reservation_time' => '19:00',
        'status' => 'pending',
    ]);

    TableReservation::create([
        'booking_code' => rand(5000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customerB->id,
        'reservation_date' => $date,
        'reservation_time' => '20:00',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.bookings.index', ['tab' => 'pending']));

    $response->assertOk();

    $conflictKeys = $response->viewData('conflictingPendingKeys');
    expect($conflictKeys)->toContain($table->id.'_'.$date);
});

test('admin can assign a waiter to an active session', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area);
    $customer = makeBookingCustomer();

    $booking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->toDateString(),
        'reservation_time' => '20:00',
        'status' => 'checked_in',
    ]);

    $session = \App\Models\TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'session_code' => 'SES-'.uniqid(),
        'checked_in_at' => now(),
        'status' => 'active',
    ]);

    $waiterRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Waiter/Server', 'guard_name' => 'web']);
    $waiter = User::factory()->create(['name' => 'Waiter John']);
    $waiter->assignRole($waiterRole);

    $this->actingAs($admin)
        ->post(route('admin.bookings.assignWaiter', $booking->id), [
            'waiter_id' => $waiter->id,
        ])
        ->assertRedirect();

    expect($session->fresh()->waiter_id)->toBe($waiter->id);
});

test('admin can delete active table booking and reset table to available', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area, ['status' => 'occupied']);
    $customer = makeBookingCustomer();

    $booking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->toDateString(),
        'reservation_time' => '20:00',
        'status' => 'checked_in',
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'session_code' => 'SES-'.uniqid(),
        'checked_in_at' => now(),
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.bookings.destroy', $booking))
        ->assertRedirect(route('admin.bookings.index'));

    $this->assertDatabaseMissing('table_reservations', ['id' => $booking->id]);
    $this->assertDatabaseMissing('table_sessions', ['id' => $session->id]);

    expect($table->fresh()->status)->toBe('available');
});

test('admin can request move table for checked in booking with active session', function () {
    $admin = adminUser();
    $area = makeArea();
    $sourceTable = makeTable($area, ['status' => 'occupied']);
    $targetTable = makeTable($area, ['status' => 'available']);
    $customer = makeBookingCustomer();

    $booking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $sourceTable->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->toDateString(),
        'reservation_time' => '20:00',
        'status' => 'checked_in',
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $sourceTable->id,
        'customer_id' => $customer->id,
        'session_code' => 'SES-'.uniqid(),
        'checked_in_at' => now(),
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.bookings.moveTable', $booking), [
            'new_table_id' => $targetTable->id,
        ])
        ->assertRedirect();

    expect($booking->fresh()->table_id)->toBe($targetTable->id)
        ->and($session->fresh()->table_id)->toBe($targetTable->id)
        ->and($sourceTable->fresh()->status)->toBe('available')
        ->and($targetTable->fresh()->status)->toBe('occupied');
});

test('request move table for confirmed booking can move without active table session', function () {
    $admin = adminUser();
    $area = makeArea();
    $sourceTable = makeTable($area, ['status' => 'reserved']);
    $targetTable = makeTable($area, ['status' => 'available']);
    $customer = makeBookingCustomer();

    $booking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $sourceTable->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->toDateString(),
        'reservation_time' => '20:00',
        'status' => 'confirmed',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.bookings.moveTable', $booking), [
            'new_table_id' => $targetTable->id,
        ])
        ->assertRedirect();

    expect($booking->fresh()->table_id)->toBe($targetTable->id)
        ->and($sourceTable->fresh()->status)->toBe('available')
        ->and($targetTable->fresh()->status)->toBe('reserved');
});

test('request move table fails for checked in booking when active session is missing', function () {
    $admin = adminUser();
    $area = makeArea();
    $sourceTable = makeTable($area, ['status' => 'occupied']);
    $targetTable = makeTable($area, ['status' => 'available']);
    $customer = makeBookingCustomer();

    $booking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $sourceTable->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->toDateString(),
        'reservation_time' => '20:00',
        'status' => 'checked_in',
    ]);

    $this->actingAs($admin)
        ->from(route('admin.bookings.index'))
        ->post(route('admin.bookings.moveTable', $booking), [
            'new_table_id' => $targetTable->id,
        ])
        ->assertRedirect(route('admin.bookings.index'))
        ->assertSessionHasErrors('new_table_id');

    expect($booking->fresh()->table_id)->toBe($sourceTable->id)
        ->and($sourceTable->fresh()->status)->toBe('occupied')
        ->and($targetTable->fresh()->status)->toBe('available');
});

test('request move table can take over reserved table and set previous confirmed booking to pending', function () {
    $admin = adminUser();
    $area = makeArea();
    $sourceTable = makeTable($area, ['status' => 'occupied']);
    $targetTable = makeTable($area, ['status' => 'reserved']);

    $customerA = makeBookingCustomer();
    $customerB = makeBookingCustomer();

    $existingConfirmedBooking = TableReservation::create([
        'booking_code' => rand(1000, 4999),
        'table_id' => $targetTable->id,
        'customer_id' => $customerA->id,
        'reservation_date' => now()->toDateString(),
        'reservation_time' => '19:00',
        'status' => 'confirmed',
    ]);

    $bookingToMove = TableReservation::create([
        'booking_code' => rand(5000, 9999),
        'table_id' => $sourceTable->id,
        'customer_id' => $customerB->id,
        'reservation_date' => now()->toDateString(),
        'reservation_time' => '20:00',
        'status' => 'checked_in',
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $bookingToMove->id,
        'table_id' => $sourceTable->id,
        'customer_id' => $customerB->id,
        'session_code' => 'SES-'.uniqid(),
        'checked_in_at' => now(),
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.bookings.moveTable', $bookingToMove), [
            'new_table_id' => $targetTable->id,
        ])
        ->assertRedirect();

    expect($bookingToMove->fresh()->table_id)->toBe($targetTable->id)
        ->and($session->fresh()->table_id)->toBe($targetTable->id)
        ->and($existingConfirmedBooking->fresh()->status)->toBe('pending')
        ->and($sourceTable->fresh()->status)->toBe('available')
        ->and($targetTable->fresh()->status)->toBe('occupied');
});

test('move table modal lists both available and reserved target tables', function () {
    $admin = adminUser();
    $area = makeArea();

    $availableTable = makeTable($area, [
        'table_number' => 'MV-AVAILABLE-'.uniqid(),
        'status' => 'available',
    ]);

    $reservedTable = makeTable($area, [
        'table_number' => 'MV-RESERVED-'.uniqid(),
        'status' => 'reserved',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.bookings.index', ['tab' => 'active']))
        ->assertOk()
        ->assertSee($availableTable->table_number)
        ->assertSee($reservedTable->table_number)
        ->assertSee('Meja dengan status available dan reserved bisa dipilih.');
});

test('admin can print running receipt while table session is active', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area, ['status' => 'occupied', 'minimum_charge' => 100000]);
    $customer = makeBookingCustomer();

    Printer::create([
        'name' => 'Cashier Log Printer',
        'location' => 'cashier',
        'printer_type' => 'cashier',
        'connection_type' => 'log',
        'port' => 9100,
        'timeout' => 30,
        'header' => '126 Club',
        'footer' => 'Thank you',
        'width' => 42,
        'is_default' => true,
        'is_active' => true,
    ]);

    $booking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->toDateString(),
        'reservation_time' => '20:00',
        'status' => 'checked_in',
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'session_code' => 'SES-'.uniqid(),
        'checked_in_at' => now(),
        'status' => 'active',
    ]);

    $billing = Billing::create([
        'table_session_id' => $session->id,
        'is_walk_in' => false,
        'is_booking' => true,
        'minimum_charge' => 200000,
        'orders_total' => 0,
        'subtotal' => 0,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => 0,
        'paid_amount' => 0,
        'billing_status' => 'draft',
    ]);

    $session->update(['billing_id' => $billing->id]);

    $inventoryItem = InventoryItem::create([
        'code' => 'RUN-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Running Receipt Item '.uniqid(),
        'category_type' => 'beverage',
        'price' => 50000,
        'stock_quantity' => 20,
        'threshold' => 2,
        'unit' => 'glass',
        'is_active' => true,
    ]);

    $order = Order::create([
        'table_session_id' => $session->id,
        'created_by' => $admin->id,
        'order_number' => 'ORD-'.uniqid(),
        'status' => 'pending',
        'items_total' => 100000,
        'discount_amount' => 0,
        'total' => 100000,
        'ordered_at' => now(),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $inventoryItem->id,
        'item_name' => $inventoryItem->name,
        'item_code' => $inventoryItem->code,
        'quantity' => 2,
        'price' => 50000,
        'subtotal' => 100000,
        'discount_amount' => 0,
        'preparation_location' => 'bar',
        'status' => 'served',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.bookings.printRunningReceipt', $booking))
        ->assertRedirect();

    expect($billing->fresh()->billing_status)->toBe('draft')
        ->and((float) $billing->fresh()->orders_total)->toBe(100000.0)
        ->and((float) $billing->fresh()->subtotal)->toBe(100000.0)
        ->and((float) $billing->fresh()->grand_total)->toBe(100000.0)
        ->and($session->fresh()->status)->toBe('active');
});

test('print running receipt fails when active session is missing', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area, ['status' => 'reserved']);
    $customer = makeBookingCustomer();

    $booking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->toDateString(),
        'reservation_time' => '20:00',
        'status' => 'confirmed',
    ]);

    $this->actingAs($admin)
        ->from(route('admin.bookings.index'))
        ->post(route('admin.bookings.printRunningReceipt', $booking))
        ->assertRedirect(route('admin.bookings.index'))
        ->assertSessionHasErrors('error');
});

test('active bookings page includes transaction checker progress for close billing', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area, ['status' => 'occupied']);
    $customer = makeBookingCustomer();

    $booking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->toDateString(),
        'reservation_time' => now()->format('H:i:s'),
        'status' => 'checked_in',
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'session_code' => 'SESSION-'.uniqid(),
        'checked_in_at' => now(),
        'status' => 'active',
    ]);

    $billing = Billing::create([
        'table_session_id' => $session->id,
        'minimum_charge' => 0,
        'orders_total' => 120000,
        'subtotal' => 120000,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => 120000,
        'paid_amount' => 0,
        'billing_status' => 'draft',
    ]);

    $session->update(['billing_id' => $billing->id]);

    $inventoryItem = InventoryItem::create([
        'code' => 'INV-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Checker Item '.uniqid(),
        'category_type' => 'beverage',
        'price' => 60000,
        'stock_quantity' => 10,
        'threshold' => 2,
        'unit' => 'glass',
        'is_active' => true,
    ]);

    $order = Order::create([
        'table_session_id' => $session->id,
        'created_by' => $admin->id,
        'order_number' => 'ORD-'.uniqid(),
        'status' => 'ready',
        'items_total' => 120000,
        'discount_amount' => 0,
        'total' => 120000,
        'ordered_at' => now(),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $inventoryItem->id,
        'item_name' => $inventoryItem->name,
        'item_code' => $inventoryItem->code,
        'quantity' => 1,
        'price' => 60000,
        'subtotal' => 60000,
        'discount_amount' => 0,
        'preparation_location' => 'bar',
        'status' => 'served',
        'served_at' => now(),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $inventoryItem->id,
        'item_name' => $inventoryItem->name,
        'item_code' => $inventoryItem->code,
        'quantity' => 1,
        'price' => 60000,
        'subtotal' => 60000,
        'discount_amount' => 0,
        'preparation_location' => 'bar',
        'status' => 'ready',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.bookings.index', ['tab' => 'active']))
        ->assertOk()
        ->assertSee('data-checker-checked="1"', false)
        ->assertSee('data-checker-total="2"', false)
        ->assertSee('Transaction Checker belum lengkap');
});

test('active bookings page subtotal sums order item subtotal tax and service charge', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area, ['status' => 'occupied']);
    $customer = makeBookingCustomer();

    $booking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->toDateString(),
        'reservation_time' => now()->format('H:i:s'),
        'status' => 'checked_in',
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'session_code' => 'SESSION-'.uniqid(),
        'checked_in_at' => now(),
        'status' => 'active',
    ]);

    $billing = Billing::create([
        'table_session_id' => $session->id,
        'minimum_charge' => 0,
        'orders_total' => 100000,
        'subtotal' => 115000,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => 115000,
        'paid_amount' => 0,
        'billing_status' => 'draft',
    ]);

    $session->update(['billing_id' => $billing->id]);

    $inventoryItem = InventoryItem::create([
        'code' => 'INV-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Subtotal Item '.uniqid(),
        'category_type' => 'beverage',
        'price' => 100000,
        'stock_quantity' => 10,
        'threshold' => 2,
        'unit' => 'glass',
        'is_active' => true,
    ]);

    $order = Order::create([
        'table_session_id' => $session->id,
        'created_by' => $admin->id,
        'order_number' => 'ORD-'.uniqid(),
        'status' => 'pending',
        'items_total' => 100000,
        'discount_amount' => 0,
        'total' => 100000,
        'ordered_at' => now(),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $inventoryItem->id,
        'item_name' => $inventoryItem->name,
        'item_code' => $inventoryItem->code,
        'quantity' => 1,
        'price' => 100000,
        'subtotal' => 100000,
        'discount_amount' => 0,
        'tax_amount' => 10000,
        'service_charge_amount' => 5000,
        'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.bookings.index', ['tab' => 'active']))
        ->assertOk()
        ->assertSee('Subtotal')
        ->assertSee('Rp 115.000');
});

test('billing cannot be closed while transaction checker is incomplete', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area, ['status' => 'occupied']);
    $customer = makeBookingCustomer();

    $booking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->toDateString(),
        'reservation_time' => now()->format('H:i:s'),
        'status' => 'checked_in',
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'session_code' => 'SESSION-'.uniqid(),
        'checked_in_at' => now(),
        'status' => 'active',
    ]);

    $billing = Billing::create([
        'table_session_id' => $session->id,
        'minimum_charge' => 0,
        'orders_total' => 120000,
        'subtotal' => 120000,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => 120000,
        'paid_amount' => 0,
        'billing_status' => 'draft',
    ]);

    $session->update(['billing_id' => $billing->id]);

    $inventoryItem = InventoryItem::create([
        'code' => 'INV-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Checker Item '.uniqid(),
        'category_type' => 'beverage',
        'price' => 60000,
        'stock_quantity' => 10,
        'threshold' => 2,
        'unit' => 'glass',
        'is_active' => true,
    ]);

    $order = Order::create([
        'table_session_id' => $session->id,
        'created_by' => $admin->id,
        'order_number' => 'ORD-'.uniqid(),
        'status' => 'ready',
        'items_total' => 120000,
        'discount_amount' => 0,
        'total' => 120000,
        'ordered_at' => now(),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $inventoryItem->id,
        'item_name' => $inventoryItem->name,
        'item_code' => $inventoryItem->code,
        'quantity' => 1,
        'price' => 60000,
        'subtotal' => 60000,
        'discount_amount' => 0,
        'preparation_location' => 'bar',
        'status' => 'served',
        'served_at' => now(),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $inventoryItem->id,
        'item_name' => $inventoryItem->name,
        'item_code' => $inventoryItem->code,
        'quantity' => 1,
        'price' => 60000,
        'subtotal' => 60000,
        'discount_amount' => 0,
        'preparation_location' => 'bar',
        'status' => 'ready',
    ]);

    $this->actingAs($admin)
        ->postJson(route('admin.bookings.closeBilling', $booking), [
            'payment_mode' => 'normal',
            'payment_method' => 'cash',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Billing tidak bisa ditutup karena masih ada item di Transaction Checker yang belum selesai.');

    expect($billing->fresh()->billing_status)->toBe('draft')
        ->and($session->fresh()->status)->toBe('active')
        ->and($booking->fresh()->status)->toBe('checked_in')
        ->and($table->fresh()->status)->toBe('occupied');
});

test('bookings page shows close billing button when live orders total meets minimum charge despite stale billing orders total', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area, [
        'status' => 'occupied',
        'minimum_charge' => 100000,
    ]);
    $customer = makeBookingCustomer();

    $booking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->toDateString(),
        'reservation_time' => now()->format('H:i:s'),
        'status' => 'checked_in',
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'session_code' => 'SESSION-'.uniqid(),
        'checked_in_at' => now(),
        'status' => 'active',
    ]);

    $billing = Billing::create([
        'table_session_id' => $session->id,
        'minimum_charge' => 100000,
        'orders_total' => 0,
        'subtotal' => 0,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => 0,
        'paid_amount' => 0,
        'billing_status' => 'draft',
    ]);

    $session->update(['billing_id' => $billing->id]);

    $inventoryItem = InventoryItem::create([
        'code' => 'INV-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Close Billing Eligible Item '.uniqid(),
        'category_type' => 'beverage',
        'price' => 120000,
        'stock_quantity' => 20,
        'threshold' => 2,
        'unit' => 'glass',
        'is_active' => true,
    ]);

    $order = Order::create([
        'table_session_id' => $session->id,
        'created_by' => $admin->id,
        'order_number' => 'ORD-'.uniqid(),
        'status' => 'ready',
        'items_total' => 120000,
        'discount_amount' => 0,
        'total' => 120000,
        'ordered_at' => now(),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $inventoryItem->id,
        'item_name' => $inventoryItem->name,
        'item_code' => $inventoryItem->code,
        'quantity' => 1,
        'price' => 120000,
        'subtotal' => 120000,
        'discount_amount' => 0,
        'preparation_location' => 'bar',
        'status' => 'served',
        'served_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.bookings.index'))
        ->assertOk()
        ->assertSee('Tutup Billing');
});

test('bookings page reconciles stale occupied table status after session already closed', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area, ['status' => 'occupied']);
    $customer = makeBookingCustomer();

    $booking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->toDateString(),
        'reservation_time' => now()->format('H:i:s'),
        'status' => 'completed',
    ]);

    TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'session_code' => 'SESSION-'.uniqid(),
        'checked_in_at' => now()->subHours(2),
        'checked_out_at' => now()->subHour(),
        'status' => 'completed',
    ]);

    expect($table->fresh()->status)->toBe('occupied');

    $this->actingAs($admin)
        ->get(route('admin.bookings.index'))
        ->assertOk();

    expect($table->fresh()->status)->toBe('available');
});

test('bookings all tab still shows close billing button when booking is checked in but table status is reserved', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area, [
        'status' => 'reserved',
        'minimum_charge' => 100000,
    ]);
    $customer = makeBookingCustomer();

    $booking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->toDateString(),
        'reservation_time' => now()->format('H:i:s'),
        'status' => 'checked_in',
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'session_code' => 'SESSION-'.uniqid(),
        'checked_in_at' => now(),
        'status' => 'active',
    ]);

    $billing = Billing::create([
        'table_session_id' => $session->id,
        'minimum_charge' => 100000,
        'orders_total' => 120000,
        'subtotal' => 120000,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => 120000,
        'paid_amount' => 0,
        'billing_status' => 'draft',
    ]);

    $session->update(['billing_id' => $billing->id]);

    $inventoryItem = InventoryItem::create([
        'code' => 'INV-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'All Tab Eligible Item '.uniqid(),
        'category_type' => 'beverage',
        'price' => 120000,
        'stock_quantity' => 20,
        'threshold' => 2,
        'unit' => 'glass',
        'is_active' => true,
    ]);

    $order = Order::create([
        'table_session_id' => $session->id,
        'created_by' => $admin->id,
        'order_number' => 'ORD-'.uniqid(),
        'status' => 'ready',
        'items_total' => 120000,
        'discount_amount' => 0,
        'total' => 120000,
        'ordered_at' => now(),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $inventoryItem->id,
        'item_name' => $inventoryItem->name,
        'item_code' => $inventoryItem->code,
        'quantity' => 1,
        'price' => 120000,
        'subtotal' => 120000,
        'discount_amount' => 0,
        'preparation_location' => 'bar',
        'status' => 'served',
        'served_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.bookings.index'))
        ->assertOk()
        ->assertSee('Tutup Billing');
});

test('bookings all tab shows close billing for checked in booking from previous date', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area, [
        'status' => 'occupied',
        'minimum_charge' => 100000,
    ]);
    $customer = makeBookingCustomer();

    $booking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->subDay()->toDateString(),
        'reservation_time' => now()->subDay()->format('H:i:s'),
        'status' => 'checked_in',
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'session_code' => 'SESSION-'.uniqid(),
        'checked_in_at' => now()->subDay(),
        'status' => 'active',
    ]);

    $billing = Billing::create([
        'table_session_id' => $session->id,
        'minimum_charge' => 100000,
        'orders_total' => 120000,
        'subtotal' => 120000,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => 120000,
        'paid_amount' => 0,
        'billing_status' => 'draft',
    ]);

    $session->update(['billing_id' => $billing->id]);

    $inventoryItem = InventoryItem::create([
        'code' => 'INV-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Previous Date Eligible Item '.uniqid(),
        'category_type' => 'beverage',
        'price' => 120000,
        'stock_quantity' => 20,
        'threshold' => 2,
        'unit' => 'glass',
        'is_active' => true,
    ]);

    $order = Order::create([
        'table_session_id' => $session->id,
        'created_by' => $admin->id,
        'order_number' => 'ORD-'.uniqid(),
        'status' => 'ready',
        'items_total' => 120000,
        'discount_amount' => 0,
        'total' => 120000,
        'ordered_at' => now()->subDay(),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $inventoryItem->id,
        'item_name' => $inventoryItem->name,
        'item_code' => $inventoryItem->code,
        'quantity' => 1,
        'price' => 120000,
        'subtotal' => 120000,
        'discount_amount' => 0,
        'preparation_location' => 'bar',
        'status' => 'served',
        'served_at' => now()->subDay(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.bookings.index'))
        ->assertOk()
        ->assertSee('Tutup Billing');
});

test('admin can unassign a waiter from an active session', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area);
    $customer = makeBookingCustomer();

    $waiterRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Waiter/Server', 'guard_name' => 'web']);
    $waiter = User::factory()->create(['name' => 'Waiter Jane']);
    $waiter->assignRole($waiterRole);

    $booking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->toDateString(),
        'reservation_time' => '21:00',
        'status' => 'checked_in',
    ]);

    $session = \App\Models\TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'waiter_id' => $waiter->id,
        'session_code' => 'SES-'.uniqid(),
        'checked_in_at' => now(),
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.bookings.assignWaiter', $booking->id), [
            'waiter_id' => '',
        ])
        ->assertRedirect();

    expect($session->fresh()->waiter_id)->toBeNull();
});

test('history tab shows ordered items for each booking customer', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area, ['status' => 'available']);
    $customer = makeBookingCustomer();

    $booking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->subDay()->toDateString(),
        'reservation_time' => '19:00',
        'status' => 'completed',
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'session_code' => 'SES-'.uniqid(),
        'checked_in_at' => now()->subHours(2),
        'checked_out_at' => now()->subHour(),
        'status' => 'completed',
    ]);

    $item = InventoryItem::create([
        'code' => 'HIST-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Nasi Goreng Test',
        'category_type' => 'food',
        'price' => 50000,
        'stock_quantity' => 20,
        'threshold' => 5,
        'unit' => 'plate',
        'is_active' => true,
    ]);

    $order = Order::create([
        'table_session_id' => $session->id,
        'created_by' => $admin->id,
        'order_number' => 'ORD-'.uniqid(),
        'status' => 'completed',
        'items_total' => 100000,
        'discount_amount' => 0,
        'total' => 100000,
        'ordered_at' => now()->subHours(2),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $item->id,
        'item_name' => $item->name,
        'item_code' => $item->code,
        'quantity' => 2,
        'price' => 50000,
        'subtotal' => 100000,
        'discount_amount' => 0,
        'preparation_location' => 'kitchen',
        'status' => 'served',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.bookings.index', ['tab' => 'history']))
        ->assertOk()
        ->assertSee('Lihat Orders (1 item)');
});

test('history tab row includes billing detail payload for modal', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area, ['status' => 'available']);
    $customer = makeBookingCustomer();

    $booking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->subDay()->toDateString(),
        'reservation_time' => '20:00',
        'status' => 'completed',
        'down_payment_amount' => 10000,
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'session_code' => 'SES-'.uniqid(),
        'checked_in_at' => now()->subHours(3),
        'checked_out_at' => now()->subHours(1),
        'status' => 'completed',
    ]);

    Order::create([
        'table_session_id' => $session->id,
        'created_by' => $admin->id,
        'order_number' => 'ORD-'.uniqid(),
        'status' => 'completed',
        'items_total' => 120000,
        'discount_amount' => 5000,
        'total' => 115000,
        'ordered_at' => now()->subHours(2),
    ]);

    $billing = Billing::create([
        'table_session_id' => $session->id,
        'minimum_charge' => 0,
        'orders_total' => 120000,
        'subtotal' => 120000,
        'tax' => 12000,
        'tax_percentage' => 10,
        'service_charge' => 8000,
        'service_charge_percentage' => 6.67,
        'discount_amount' => 5000,
        'grand_total' => 125000,
        'paid_amount' => 125000,
        'billing_status' => 'paid',
        'payment_mode' => 'normal',
        'payment_method' => 'transfer',
        'payment_reference_number' => 'TRX-REF-001',
    ]);

    $session->update(['billing_id' => $billing->id]);

    $this->actingAs($admin)
        ->get(route('admin.bookings.index', ['tab' => 'history']))
        ->assertOk()
        ->assertSee('id="historyBillingDetailModal"', false)
        ->assertSee('data-booking-id="'.$booking->id.'"', false)
        ->assertSee('"order_count":1', false)
        ->assertSee('"total_bill":120000', false)
        ->assertSee('"tax_amount":12000', false)
        ->assertSee('"service_charge":8000', false)
        ->assertSee('"sub_total":140000', false)
        ->assertSee('"discount_amount":5000', false)
        ->assertSee('"down_payment_amount":10000', false)
        ->assertSee('"remaining_payment":125000', false)
        ->assertSee('"payment_mode":"NORMAL"', false)
        ->assertSee('"payment_method":"TRANSFER"', false)
        ->assertSee('"reference_number":"TRX-REF-001"', false);
});

test('history tab row includes split payment method and reference payload for modal', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area, ['status' => 'available']);
    $customer = makeBookingCustomer();

    $booking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->subDay()->toDateString(),
        'reservation_time' => '21:00',
        'status' => 'completed',
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'session_code' => 'SES-'.uniqid(),
        'checked_in_at' => now()->subHours(3),
        'checked_out_at' => now()->subHours(1),
        'status' => 'completed',
    ]);

    $billing = Billing::create([
        'table_session_id' => $session->id,
        'minimum_charge' => 0,
        'orders_total' => 200000,
        'subtotal' => 200000,
        'tax' => 20000,
        'tax_percentage' => 10,
        'service_charge' => 10000,
        'service_charge_percentage' => 5,
        'discount_amount' => 0,
        'grand_total' => 230000,
        'paid_amount' => 230000,
        'billing_status' => 'paid',
        'payment_mode' => 'split',
        'split_cash_amount' => 30000,
        'split_debit_amount' => 100000,
        'split_non_cash_method' => 'qris',
        'split_non_cash_reference_number' => 'QR-123',
        'split_second_non_cash_amount' => 100000,
        'split_second_non_cash_method' => 'transfer',
        'split_second_non_cash_reference_number' => 'TRF-456',
    ]);

    $session->update(['billing_id' => $billing->id]);

    $this->actingAs($admin)
        ->get(route('admin.bookings.index', ['tab' => 'history']))
        ->assertOk()
        ->assertSee('"payment_mode":"SPLIT"', false)
        ->assertSee('"payment_method":"CASH + QRIS + TRANSFER"', false)
        ->assertSee('"reference_number":"Ref 1: QR-123 | Ref 2: TRF-456"', false);
});

test('history tab shows edit payment action in billing detail modal', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area, ['status' => 'available']);
    $customer = makeBookingCustomer();

    $booking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->subDay()->toDateString(),
        'reservation_time' => '22:00',
        'status' => 'completed',
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'session_code' => 'SES-'.uniqid(),
        'checked_in_at' => now()->subHours(3),
        'checked_out_at' => now()->subHours(1),
        'status' => 'completed',
    ]);

    $billing = Billing::create([
        'table_session_id' => $session->id,
        'minimum_charge' => 0,
        'orders_total' => 100000,
        'subtotal' => 100000,
        'tax' => 10000,
        'tax_percentage' => 10,
        'service_charge' => 5000,
        'service_charge_percentage' => 5,
        'discount_amount' => 0,
        'grand_total' => 115000,
        'paid_amount' => 115000,
        'billing_status' => 'paid',
        'payment_mode' => 'normal',
        'payment_method' => 'transfer',
        'payment_reference_number' => 'HIST-PAY-001',
    ]);

    $session->update(['billing_id' => $billing->id]);

    $this->actingAs($admin)
        ->get(route('admin.bookings.index', ['tab' => 'history']))
        ->assertOk()
        ->assertSee('id="historyBillingEditPaymentButton"', false)
        ->assertSee('id="historyPaymentEditModal"', false)
        ->assertSee('"update_payment_url":', false);
});

test('history payment update endpoint updates billing payment fields', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area, ['status' => 'available']);
    $customer = makeBookingCustomer();

    $booking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->subDay()->toDateString(),
        'reservation_time' => '22:30',
        'status' => 'completed',
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'session_code' => 'SES-'.uniqid(),
        'checked_in_at' => now()->subHours(3),
        'checked_out_at' => now()->subHours(1),
        'status' => 'completed',
    ]);

    $billing = Billing::create([
        'table_session_id' => $session->id,
        'minimum_charge' => 0,
        'orders_total' => 120000,
        'subtotal' => 120000,
        'tax' => 12000,
        'tax_percentage' => 10,
        'service_charge' => 6000,
        'service_charge_percentage' => 5,
        'discount_amount' => 0,
        'grand_total' => 138000,
        'paid_amount' => 138000,
        'billing_status' => 'paid',
        'payment_mode' => 'normal',
        'payment_method' => 'cash',
    ]);

    $session->update(['billing_id' => $billing->id]);

    $this->actingAs($admin)
        ->patchJson(route('admin.bookings.updateHistoryPayment', $booking), [
            'payment_mode' => 'normal',
            'payment_method' => 'transfer',
            'payment_reference_number' => 'TRF-HISTORY-987',
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $billing->refresh();

    expect((string) $billing->payment_mode)->toBe('normal')
        ->and((string) $billing->payment_method)->toBe('transfer')
        ->and((string) $billing->payment_reference_number)->toBe('TRF-HISTORY-987')
        ->and($billing->split_cash_amount)->toBeNull()
        ->and($billing->split_debit_amount)->toBeNull();
});

test('history payment split method non-cash requires reference number', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area, ['status' => 'available']);
    $customer = makeBookingCustomer();

    $booking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->subDay()->toDateString(),
        'reservation_time' => '22:00',
        'status' => 'completed',
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'session_code' => 'SES-'.uniqid(),
        'checked_in_at' => now()->subHours(3),
        'checked_out_at' => now()->subHour(),
        'status' => 'completed',
    ]);

    $billing = Billing::create([
        'table_session_id' => $session->id,
        'minimum_charge' => 0,
        'orders_total' => 120000,
        'subtotal' => 120000,
        'tax' => 12000,
        'tax_percentage' => 10,
        'service_charge' => 6000,
        'service_charge_percentage' => 5,
        'discount_amount' => 0,
        'grand_total' => 138000,
        'paid_amount' => 138000,
        'billing_status' => 'paid',
        'payment_mode' => 'normal',
        'payment_method' => 'cash',
    ]);

    $session->update(['billing_id' => $billing->id]);

    $this->actingAs($admin)
        ->patchJson(route('admin.bookings.updateHistoryPayment', $booking), [
            'payment_mode' => 'split',
            'split_cash_amount' => 38000,
            'split_non_cash_amount' => 100000,
            'split_non_cash_method' => 'qris',
            'split_non_cash_reference_number' => '',
            'split_second_non_cash_amount' => 0,
            'split_second_non_cash_method' => null,
            'split_second_non_cash_reference_number' => null,
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.split_non_cash_reference_number.0', 'Nomor referensi non-cash pertama untuk split bill wajib diisi.');
});

test('history tab shows reprint receipt action when booking has billing', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area, ['status' => 'available']);
    $customer = makeBookingCustomer();

    $booking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->subDay()->toDateString(),
        'reservation_time' => '19:00',
        'status' => 'completed',
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'session_code' => 'SES-'.uniqid(),
        'checked_in_at' => now()->subHours(2),
        'checked_out_at' => now()->subHour(),
        'status' => 'completed',
    ]);

    $billing = Billing::create([
        'table_session_id' => $session->id,
        'minimum_charge' => 0,
        'orders_total' => 100000,
        'subtotal' => 100000,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => 100000,
        'paid_amount' => 100000,
        'billing_status' => 'paid',
    ]);

    $session->update(['billing_id' => $billing->id]);

    $this->actingAs($admin)
        ->get(route('admin.bookings.index', ['tab' => 'history']))
        ->assertOk()
        ->assertSee(route('admin.bookings.reprintReceipt', $booking, false), false)
        ->assertSee('Print Ulang');
});

test('history reprint receipt dispatches directly to configured printer', function () {
    $admin = adminUser();
    $area = makeArea();
    $table = makeTable($area, ['status' => 'available']);
    $customer = makeBookingCustomer();

    $booking = TableReservation::create([
        'booking_code' => rand(1000, 9999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => now()->subDay()->toDateString(),
        'reservation_time' => '19:00',
        'status' => 'completed',
    ]);

    $session = TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'session_code' => 'SES-'.uniqid(),
        'checked_in_at' => now()->subHours(2),
        'checked_out_at' => now()->subHour(),
        'status' => 'completed',
    ]);

    $billing = Billing::create([
        'table_session_id' => $session->id,
        'minimum_charge' => 0,
        'orders_total' => 100000,
        'subtotal' => 100000,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => 100000,
        'paid_amount' => 100000,
        'billing_status' => 'paid',
    ]);
    $session->update(['billing_id' => $billing->id]);

    $printer = Printer::create([
        'name' => 'History Reprint Printer',
        'location' => 'cashier',
        'printer_type' => 'cashier',
        'connection_type' => 'network',
        'ip' => '192.168.1.20',
        'port' => 9100,
        'timeout' => 30,
        'width' => 42,
        'is_default' => false,
        'is_active' => true,
    ]);

    \App\Models\GeneralSetting::instance()->update([
        'closed_billing_receipt_printer_id' => $printer->id,
    ]);

    mock(PrinterService::class, function (MockInterface $mock) use ($billing, $session, $printer): void {
        $mock->shouldReceive('printClosedBillingReceipt')
            ->once()
            ->withArgs(fn ($billingArg, $sessionArg, $printerArg): bool => (int) $billingArg->id === (int) $billing->id
                && (int) $sessionArg->id === (int) $session->id
                && (int) $printerArg->id === (int) $printer->id)
            ->andReturnTrue();
    });

    $this->actingAs($admin)
        ->post(route('admin.bookings.reprintReceipt', $booking))
        ->assertRedirect()
        ->assertSessionHas('success');
});

test('history tab uses pagination for bookings', function () {
    $admin = adminUser();
    $area = makeArea();

    for ($index = 1; $index <= 12; $index++) {
        $table = makeTable($area, [
            'table_number' => 'HIST-PAG-'.$index,
            'qr_code' => 'HIST-PAG-QR-'.$index,
            'status' => 'available',
        ]);
        $customer = makeBookingCustomer();

        TableReservation::create([
            'booking_code' => 100000 + $index,
            'table_id' => $table->id,
            'customer_id' => $customer->id,
            'reservation_date' => now()->subDays($index)->toDateString(),
            'reservation_time' => '19:00',
            'status' => 'completed',
        ]);
    }

    $response = $this->actingAs($admin)
        ->get(route('admin.bookings.index', ['tab' => 'history']))
        ->assertOk();

    $response->assertViewHas('bookings', function ($bookings): bool {
        return $bookings instanceof LengthAwarePaginator
            && $bookings->perPage() === 10
            && $bookings->total() >= 12;
    });

    $response->assertSee('page=2', false);
});

test('history tab pagination shows first and last page with ellipsis on middle pages', function () {
    $admin = adminUser();
    $area = makeArea();

    for ($index = 1; $index <= 55; $index++) {
        $table = makeTable($area, [
            'table_number' => 'HIST-FLEX-'.$index,
            'qr_code' => 'HIST-FLEX-QR-'.$index,
            'status' => 'available',
        ]);
        $customer = makeBookingCustomer();

        TableReservation::create([
            'booking_code' => 200000 + $index,
            'table_id' => $table->id,
            'customer_id' => $customer->id,
            'reservation_date' => now()->subDays($index)->toDateString(),
            'reservation_time' => '20:00',
            'status' => 'completed',
        ]);
    }

    $this->actingAs($admin)
        ->get(route('admin.bookings.index', ['tab' => 'history', 'page' => 3]))
        ->assertOk()
        ->assertSee('page=1', false)
        ->assertSee('page=6', false)
        ->assertSee('pagination-ellipsis', false);
});
