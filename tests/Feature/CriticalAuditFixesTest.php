<?php

use App\Http\Controllers\Waiter\WaiterPosController;
use App\Models\Area;
use App\Models\Billing;
use App\Models\CustomerUser;
use App\Models\DailyAuthCode;
use App\Models\InventoryItem;
use App\Models\PosCategorySetting;
use App\Models\RecapHistory;
use App\Models\Tabel;
use App\Models\TableReservation;
use App\Models\TableSession;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\actingAs;

function critPosCategory(): void
{
    PosCategorySetting::clearCache();
    PosCategorySetting::firstOrCreate(
        ['category_type' => 'beverage'],
        ['show_in_pos' => true, 'is_menu' => false, 'is_item_group' => false, 'preparation_location' => 'bar', 'source' => 'inventory'],
    );
    PosCategorySetting::clearCache();
}

function critItem(int $stock = 50, int $price = 25000, array $overrides = []): InventoryItem
{
    critPosCategory();

    return InventoryItem::create(array_merge([
        'code' => 'CRIT-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Critical Item '.uniqid(),
        'category_type' => 'beverage',
        'price' => $price,
        'stock_quantity' => $stock,
        'unit' => 'glass',
        'is_active' => true,
        'is_visible_in_pos' => true,
    ], $overrides));
}

function critCustomer(): User
{
    $customer = User::factory()->create();
    UserProfile::create(['user_id' => $customer->id]);
    CustomerUser::create([
        'user_id' => $customer->id,
        'user_profile_id' => UserProfile::where('user_id', $customer->id)->first()->id,
        'total_visits' => 0,
        'lifetime_spending' => 0,
    ]);

    return $customer;
}

function critWaiter(): User
{
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Waiter/Server', 'guard_name' => 'web']);
    $waiter = User::factory()->create();
    $waiter->assignRole('Waiter/Server');

    return $waiter;
}

function critBookingFixture(User $customer, ?User $waiter = null): array
{
    $waiter ??= critWaiter();

    $area = Area::create(['code' => 'CRIT-'.uniqid(), 'name' => 'Crit Area', 'is_active' => true, 'sort_order' => 1]);
    $table = Tabel::create([
        'area_id' => $area->id,
        'table_number' => 'CRIT-'.uniqid(),
        'qr_code' => 'QRCRIT-'.uniqid(),
        'capacity' => 4,
        'minimum_charge' => 0,
        'status' => 'reserved',
        'is_active' => true,
    ]);
    $booking = TableReservation::create([
        'booking_code' => random_int(100000, 999999),
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
        'waiter_id' => $waiter?->id,
        'session_code' => 'SESCRIT-'.uniqid(),
        'checked_in_at' => now(),
        'status' => 'active',
    ]);

    return [$area, $table, $booking, $session];
}

function critBilling(TableSession $session, string $status = 'draft', float $grandTotal = 0): Billing
{
    $billing = Billing::create([
        'table_session_id' => $session->id,
        'area_id' => $session->table?->area_id,
        'is_walk_in' => false,
        'is_booking' => true,
        'minimum_charge' => 0,
        'orders_total' => 0,
        'subtotal' => 0,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => $grandTotal,
        'paid_amount' => 0,
        'billing_status' => $status,
    ]);

    // Relasi TableSession::billing memakai kolom billing_id (bukan lookup balik).
    $session->update(['billing_id' => $billing->id]);

    return $billing;
}

function critCart(InventoryItem $item, int $quantity = 1): array
{
    return [
        'item_'.$item->id => [
            'id' => 'item_'.$item->id,
            'name' => $item->name,
            'price' => (float) $item->price,
            'quantity' => $quantity,
            'preparation_location' => 'bar',
        ],
    ];
}

test('C1: waiter checkout succeeds when session has a billing', function () {
    $waiter = critWaiter();
    $customer = critCustomer();
    [, , , $session] = critBookingFixture($customer, $waiter);
    critBilling($session);

    $item = critItem();
    $cartKey = 'item_'.$item->id;

    $response = actingAs($waiter)
        ->withSession([
            'accurate_database' => 'test',
            WaiterPosController::CART_KEY => critCart($item, 2),
        ])
        ->postJson(route('waiter.pos.checkout'), ['session_id' => $session->id]);

    $response->assertOk()->assertJsonPath('success', true);
});

test('C1: waiter checkout on paid billing returns 422 instead of fatal class error', function () {
    $waiter = critWaiter();
    $customer = critCustomer();
    [, , , $session] = critBookingFixture($customer, $waiter);
    critBilling($session, 'paid', 50000);

    $item = critItem();
    $cartKey = 'item_'.$item->id;

    $response = actingAs($waiter)
        ->withSession([
            'accurate_database' => 'test',
            WaiterPosController::CART_KEY => critCart($item),
        ])
        ->postJson(route('waiter.pos.checkout'), ['session_id' => $session->id]);

    $response->assertStatus(422)->assertJsonPath('message', 'Billing meja sudah ditutup.');
});

test('C2: closing an already paid billing is rejected', function () {
    $admin = adminUser();
    $customer = critCustomer();
    [, , $booking, $session] = critBookingFixture($customer);
    critBilling($session);

    $item = critItem();

    actingAs($admin)
        ->withSession(['pos_cart' => critCart($item, 2)])
        ->postJson(route('admin.pos.checkout'), [
            'customer_type' => 'booking',
            'customer_user_id' => $customer->id,
            'table_id' => $session->table_id,
            'discount_percentage' => 0,
        ])->assertSuccessful();

    // Close billing menuntut semua item sudah "served" (Transaction Checker selesai).
    $session->orders()->each(fn ($order) => $order->items()->update(['status' => 'served']));

    $first = actingAs($admin)
        ->postJson(route('admin.bookings.closeBilling', $booking), [
            'payment_mode' => 'normal',
            'payment_method' => 'cash',
        ]);
    $first->assertSuccessful()->assertJsonPath('success', true);

    $second = actingAs($admin)
        ->postJson(route('admin.bookings.closeBilling', $booking), [
            'payment_mode' => 'normal',
            'payment_method' => 'cash',
        ]);

    $second->assertStatus(422);
    expect($second->json('message'))->toContain('sudah ditutup')
        ->and((float) $booking->fresh()->tableSession->billing->grand_total)->toBe(50000.0);
});

test('C3: partially paid billing can be closed again through close billing', function () {
    $admin = adminUser();
    $customer = critCustomer();
    [, , $booking, $session] = critBookingFixture($customer);
    critBilling($session);

    $item = critItem();

    actingAs($admin)
        ->withSession(['pos_cart' => critCart($item, 2)])
        ->postJson(route('admin.pos.checkout'), [
            'customer_type' => 'booking',
            'customer_user_id' => $customer->id,
            'table_id' => $session->table_id,
            'discount_percentage' => 0,
        ])->assertSuccessful();

    $session->orders()->each(fn ($order) => $order->items()->update(['status' => 'served']));

    $first = actingAs($admin)
        ->postJson(route('admin.bookings.closeBilling', $booking), [
            'payment_mode' => 'partial',
            'payment_method' => 'cash',
            'partial_paid_amount' => 20000,
        ]);
    $first->assertSuccessful()->assertJsonPath('success', true);

    expect($booking->fresh()->tableSession->billing->billing_status)->toBe('partially_paid');

    // Re-close adalah alur operasional yang disengaja: close kedua harus diizinkan.
    $session->update(['status' => 'active']);

    $session->orders()->each(fn ($order) => $order->items()->update(['status' => 'served']));

    $second = actingAs($admin)
        ->postJson(route('admin.bookings.closeBilling', $booking), [
            'payment_mode' => 'normal',
            'payment_method' => 'cash',
        ]);

    $second->assertSuccessful()->assertJsonPath('success', true);
    expect($booking->fresh()->tableSession->billing->refresh()->billing_status)->toBe('paid');
});

test('C6: cancelling a pending order restores consumed stock', function () {
    $admin = adminUser();
    $customer = critCustomer();
    [, , $booking, $session] = critBookingFixture($customer);
    critBilling($session);

    DailyAuthCode::query()->updateOrCreate(
        ['date' => now()->format('Y-m-d')],
        ['code' => '2468', 'override_code' => null, 'generated_at' => now()],
    );

    $item = critItem(stock: 10);

    actingAs($admin)
        ->withSession(['pos_cart' => critCart($item, 3)])
        ->postJson(route('admin.pos.checkout'), [
            'customer_type' => 'booking',
            'customer_user_id' => $customer->id,
            'table_id' => $session->table_id,
            'discount_percentage' => 0,
        ])->assertSuccessful();

    expect($item->fresh()->stock_quantity)->toBe(7);

    $order = $session->orders()->latest('id')->first();

    actingAs($admin)
        ->post(route('admin.bookings.cancelOrder', $booking), [
            'order_id' => $order->id,
            'cancel_auth_code' => '2468',
        ])->assertRedirect()->assertSessionHas('success');

    expect($item->fresh()->stock_quantity)->toBe(10);
});

test('C7: group menu without local recipe BOM is sold out and rejected at add to cart', function () {
    $waiter = critWaiter();
    $item = critItem(stock: 0, overrides: [
        'is_item_group' => true,
        'is_count_portion_possible' => true,
        'accurate_id' => 777001,
        'detail_group' => null,
    ]);

    foreach ([1, 2] as $attempt) {
        $response = actingAs($waiter)
            ->withSession(['accurate_database' => 'test'])
            ->postJson(route('waiter.pos.add-to-cart', 'item_'.$item->id));

        // Porsi dari BOM lokal kosong → ditolak dengan pesan resep tidak valid.
        $response->assertStatus(422);
        expect($response->json('message'))->toBe('Item ini belum memiliki resep bahan yang valid.')
            ->and($attempt)->toBe($attempt);
    }
});

test('C9: adding orders after partial close recalculates remaining balance', function () {
    $admin = adminUser();
    $customer = critCustomer();
    [, , $booking, $session] = critBookingFixture($customer);
    critBilling($session);

    $itemOne = critItem();
    $itemTwo = critItem();

    actingAs($admin)
        ->withSession(['pos_cart' => critCart($itemOne, 2)])
        ->postJson(route('admin.pos.checkout'), [
            'customer_type' => 'booking',
            'customer_user_id' => $customer->id,
            'table_id' => $session->table_id,
            'discount_percentage' => 0,
        ])->assertSuccessful();

    $session->orders()->each(fn ($order) => $order->items()->update(['status' => 'served']));

    actingAs($admin)
        ->postJson(route('admin.bookings.closeBilling', $booking), [
            'payment_mode' => 'partial',
            'payment_method' => 'cash',
            'partial_paid_amount' => 20000,
        ])->assertSuccessful();

    $billing = $booking->fresh()->tableSession->billing;
    expect($billing->billing_status)->toBe('partially_paid')
        ->and((float) $billing->grand_total)->toBe(50000.0)
        ->and((float) $billing->remaining_balance)->toBe(30000.0);

    // Simulasi tamu lanjut berkunjung: sesi dibuka kembali setelah close parsial.
    // (status tidak ada di $fillable TableSession — pakai query builder)
    \App\Models\TableSession::query()
        ->whereKey($session->id)
        ->update(['status' => 'active', 'checked_out_at' => null]);

    $secondCheckout = actingAs($admin)
        ->withSession(['pos_cart' => critCart($itemTwo, 1)])
        ->postJson(route('admin.pos.checkout'), [
            'customer_type' => 'booking',
            'customer_user_id' => $customer->id,
            'table_id' => $session->table_id,
            'discount_percentage' => 0,
        ])->assertSuccessful();

    $billing = $booking->fresh()->tableSession->billing->refresh();

    expect((float) $billing->grand_total)->toBe(75000.0)
        ->and((float) $billing->remaining_balance)->toBe(55000.0)
        ->and($billing->billing_status)->toBe('partially_paid');
});

test('C10: close day syncs pending sales before sealing the recap', function () {
    $customer = critCustomer();
    $item = critItem();
    $order = \App\Models\Order::create([
        'table_session_id' => null,
        'customer_user_id' => null,
        'created_by' => null,
        'order_number' => 'CRIT-ORD-'.uniqid(),
        'status' => 'completed',
        'items_total' => 50000,
        'discount_amount' => 0,
        'total' => 50000,
        'ordered_at' => now(),
        'area_id' => null,
    ]);

    \App\Models\OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $item->id,
        'item_name' => 'Sync Item',
        'item_code' => $item->code,
        'quantity' => 2,
        'price' => 25000,
        'subtotal' => 50000,
        'status' => 'completed',
    ]);

    Billing::create([
        'table_session_id' => null,
        'order_id' => $order->id,
        'is_walk_in' => true,
        'is_booking' => false,
        'minimum_charge' => 0,
        'orders_total' => 50000,
        'subtotal' => 50000,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => 50000,
        'paid_amount' => 50000,
        'billing_status' => 'paid',
        'payment_method' => 'cash',
        'paid_at' => now(),
        'area_id' => null,
    ]);

    Artisan::call('recap:close-day');

    $recap = RecapHistory::query()->whereNull('area_id')->latest('id')->first();

    expect($recap)->not->toBeNull()
        ->and((float) $recap->total_amount)->toBe(50000.0);
});
