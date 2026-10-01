<?php

use App\Models\Area;
use App\Models\CustomerKeep;
use App\Models\CustomerUser;
use App\Models\Tabel;
use App\Models\TableReservation;
use App\Models\User;
use App\Models\UserProfile;

function createQrScannerCustomer(string $name): CustomerUser
{
    $user = User::factory()->create(['name' => $name]);
    $profile = UserProfile::create([
        'user_id' => $user->id,
        'phone' => '081234567890',
    ]);

    return CustomerUser::create([
        'accurate_id' => random_int(100000, 999999),
        'customer_code' => 'QR-'.uniqid(),
        'user_id' => $user->id,
        'user_profile_id' => $profile->id,
        'total_visits' => 0,
        'lifetime_spending' => 0,
    ]);
}

test('customer keep scanner filters the list using bottle id', function () {
    $admin = adminUser();
    $customer = createQrScannerCustomer('QR Keep Customer');
    $scannedKeep = CustomerKeep::create([
        'customer_user_id' => $customer->id,
        'item_name' => 'Whisky',
        'type' => 'weekday',
        'quantity' => 1,
        'unit' => 'Botol',
        'status' => 'active',
    ]);

    CustomerKeep::create([
        'customer_user_id' => $customer->id,
        'item_name' => 'Hidden Bottle',
        'type' => 'weekday',
        'quantity' => 1,
        'unit' => 'Botol',
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.customer-keep.index', ['scan_keep' => $scannedKeep->id]))
        ->assertSuccessful()
        ->assertSee('Data botol hasil scan berhasil ditemukan.')
        ->assertSee('Whisky')
        ->assertDontSee('Hidden Bottle')
        ->assertViewHas('keeps', fn ($keeps) => $keeps->count() === 1 && $keeps->first()->is($scannedKeep))
        ->assertSee('data-expected="bottle"', false)
        ->assertSee('Scan QR Customer');

    $this->actingAs($admin)
        ->get(route('admin.customer-keep.index', [
            'scan_keep' => $scannedKeep->id,
            'scan_item' => 'Wrong Bottle',
            'scan_type' => 'weekday',
            'scan_quantity' => 1,
            'scan_unit' => 'Botol',
        ]))
        ->assertSuccessful()
        ->assertSee('Data botol dari QR tidak ditemukan.')
        ->assertViewHas('keeps', fn ($keeps) => $keeps->isEmpty());
});

test('booking form provides a customer qr scanner and customer id mapping', function () {
    $admin = adminUser();
    $customer = createQrScannerCustomer('QR Booking Customer');

    $this->actingAs($admin)
        ->get(route('admin.bookings.index'))
        ->assertSuccessful()
        ->assertSee('Scan QR Customer')
        ->assertSee('data-name="booking-customer"', false)
        ->assertSee('"customer_id":'.$customer->id, false);
});

test('booking list can be filtered by the customer id from a qr scan', function () {
    $admin = adminUser();
    $scannedCustomer = createQrScannerCustomer('Scanned Booking Customer');
    $otherCustomer = createQrScannerCustomer('Other Booking Customer');
    $area = Area::create([
        'code' => 'QR-AREA',
        'name' => 'QR Area',
        'is_active' => true,
        'sort_order' => 1,
    ]);
    $table = Tabel::create([
        'area_id' => $area->id,
        'table_number' => 'QR-01',
        'qr_code' => 'QR-TABLE-01',
        'capacity' => 4,
        'minimum_charge' => 0,
        'status' => 'available',
        'is_active' => true,
    ]);
    $matchingBooking = TableReservation::create([
        'booking_code' => 910001,
        'table_id' => $table->id,
        'customer_id' => $scannedCustomer->user_id,
        'reservation_date' => now()->addDay()->toDateString(),
        'reservation_time' => '19:00',
        'status' => 'pending',
    ]);
    TableReservation::create([
        'booking_code' => 910002,
        'table_id' => $table->id,
        'customer_id' => $otherCustomer->user_id,
        'reservation_date' => now()->addDays(2)->toDateString(),
        'reservation_time' => '20:00',
        'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.bookings.index', ['customer_id' => $scannedCustomer->user_id]))
        ->assertSuccessful()
        ->assertSee('Cari via QR')
        ->assertSee('Menampilkan booking milik')
        ->assertSee('Scanned Booking Customer')
        ->assertDontSee('Other Booking Customer')
        ->assertSee('data-name="booking-search-customer"', false)
        ->assertViewHas('bookings', fn ($bookings) => $bookings->count() === 1 && $bookings->first()->is($matchingBooking))
        ->assertViewHas('customers', fn ($customers) => $customers->count() === 1 && $customers->first()->is($scannedCustomer->user))
        ->assertViewHas('todayPendingBookings', fn ($bookings) => $bookings->count() === 1 && $bookings->first()->is($matchingBooking))
        ->assertViewHas('totalBookings', 1)
        ->assertViewHas('pendingBookings', 1)
        ->assertViewHas('confirmedBookings', 0)
        ->assertViewHas('checkedInBookings', 0);

    $this->actingAs($admin)
        ->get(route('admin.bookings.index', ['customer_user_id' => $scannedCustomer->id]))
        ->assertSuccessful()
        ->assertViewHas('bookings', fn ($bookings) => $bookings->count() === 1 && $bookings->first()->is($matchingBooking))
        ->assertViewHas('customers', fn ($customers) => $customers->count() === 1 && $customers->first()->is($scannedCustomer->user));
});

test('point of sale provides a customer qr scanner for booking and walk in selection', function () {
    $this->actingAs(adminUser())
        ->get(route('admin.pos.index'))
        ->assertSuccessful()
        ->assertSee('Scan QR Customer')
        ->assertSee('data-name="pos-customer"', false)
        ->assertSee('pos-walk-in-customer-qr', false);
});
