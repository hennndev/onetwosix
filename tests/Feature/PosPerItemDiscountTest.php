<?php

use App\Models\Area;
use App\Models\Billing;
use App\Models\CustomerUser;
use App\Models\DailyAuthCode;
use App\Models\GeneralSetting;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Tabel;
use App\Models\TableReservation;
use App\Models\TableSession;
use App\Models\User;
use App\Models\UserProfile;

use function Pest\Laravel\actingAs;

function makePerItemBookingFixture(): array
{
    $cashier = adminUser();
    $customer = User::factory()->create();
    $profile = UserProfile::create(['user_id' => $customer->id, 'phone' => '081234567802']);
    CustomerUser::create(['user_id' => $customer->id, 'user_profile_id' => $profile->id]);
    $area = Area::create([
        'code' => 'DISC-'.uniqid(),
        'name' => 'Discount Area',
        'is_active' => true,
        'sort_order' => 1,
    ]);
    $table = Tabel::create([
        'area_id' => $area->id,
        'table_number' => 'DISC-'.uniqid(),
        'qr_code' => 'QR-DISC-'.uniqid(),
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
    $session = TableSession::create([
        'table_reservation_id' => $reservation->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'waiter_id' => $cashier->id,
        'session_code' => 'DISC-'.uniqid(),
        'checked_in_at' => now(),
        'status' => 'active',
    ]);
    $billing = Billing::create([
        'table_session_id' => $session->id,
        'minimum_charge' => 0,
        'billing_status' => 'draft',
    ]);
    $session->update(['billing_id' => $billing->id]);

    $items = collect(['Selected', 'Regular'])->map(fn (string $name): InventoryItem => InventoryItem::create([
        'code' => 'DISC-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => $name,
        'category_type' => 'food',
        'price' => 100000,
        'stock_quantity' => 10,
        'include_tax' => true,
        'include_service_charge' => true,
        'is_active' => true,
        'is_visible_in_pos' => true,
    ]));
    $cart = $items->mapWithKeys(fn (InventoryItem $item): array => [
        'item_'.$item->id => [
            'id' => 'item_'.$item->id,
            'name' => $item->name,
            'price' => 100000,
            'quantity' => 1,
            'preparation_location' => 'kitchen',
        ],
    ])->all();

    GeneralSetting::instance()->update(['foc_discount_percentage' => 50, 'compliment_discount_percentage' => 100]);

    return compact('cashier', 'customer', 'table', 'session', 'billing', 'items', 'cart');
}

function makePerItemCloseBillingFixture(User $admin): array
{
    $customer = User::factory()->create();

    $area = Area::create([
        'code' => 'AREA-'.uniqid(),
        'name' => 'Area '.uniqid(),
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $table = Tabel::create([
        'area_id' => $area->id,
        'table_number' => 'TBL-'.uniqid(),
        'qr_code' => 'QR-'.uniqid(),
        'capacity' => 4,
        'minimum_charge' => 0,
        'status' => 'occupied',
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
        'session_code' => 'SESSION-'.uniqid(),
        'checked_in_at' => now(),
        'status' => 'active',
    ]);

    $billing = Billing::create([
        'table_session_id' => $session->id,
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
        'grand_total' => 0,
        'paid_amount' => 0,
        'billing_status' => 'draft',
    ]);

    $session->update(['billing_id' => $billing->id]);

    $inventoryItem = InventoryItem::create([
        'code' => 'INV-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Billing Item '.uniqid(),
        'category_type' => 'beverage',
        'price' => 60000,
        'stock_quantity' => 50,
        'threshold' => 5,
        'unit' => 'glass',
        'is_active' => true,
    ]);

    $order = Order::create([
        'table_session_id' => $session->id,
        'created_by' => $admin->id,
        'order_number' => 'ORD-'.uniqid(),
        'status' => 'pending',
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
        'quantity' => 2,
        'price' => 60000,
        'subtotal' => 120000,
        'discount_amount' => 0,
        'preparation_location' => 'bar',
        'status' => 'served',
    ]);

    return [$booking, $session, $billing];
}

test('walk-in FOC bulk marks all order items is_discount with discount_pct and rupiah', function () {
    $admin = adminUser();
    GeneralSetting::instance()->update(['foc_discount_percentage' => 50]);
    $customer = User::factory()->create();
    $profile = UserProfile::create(['user_id' => $customer->id]);
    CustomerUser::create(['user_id' => $customer->id, 'user_profile_id' => $profile->id]);

    $items = collect(['A', 'B', 'C'])->map(fn (string $name) => InventoryItem::create([
        'code' => 'FOC-BULK-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => $name,
        'category_type' => 'food',
        'price' => 10000,
        'stock_quantity' => 10,
        'include_tax' => true,
        'include_service_charge' => true,
        'is_active' => true,
        'is_visible_in_pos' => true,
    ]));
    $cart = $items->mapWithKeys(fn (InventoryItem $item): array => [
        'item_'.$item->id => [
            'id' => 'item_'.$item->id,
            'name' => $item->name,
            'price' => 10000,
            'quantity' => 1,
            'preparation_location' => 'kitchen',
        ],
    ])->all();

    $code = DailyAuthCode::forDate(now()->format('Y-m-d'))->active_code;

    $response = actingAs($admin)
        ->withSession(['pos_cart' => $cart])
        ->postJson(route('admin.pos.checkout'), [
            'customer_type' => 'walk-in',
            'walk_in_customer_id' => $customer->id,
            'foc_comp_payment_method' => 'FOC',
            'foc_comp_auth_code' => $code,
            'payment_mode' => 'normal',
            'payment_method' => 'FOC',
        ]);

    $response->assertSuccessful()->assertJsonPath('success', true);

    $order = Order::where('created_by', $admin->id)->latest('id')->firstOrFail();

    expect($order->items()->count())->toBe(3)
        ->and($order->items()->where('is_discount', true)->count())->toBe(3)
        ->and($order->items()->where('discount_pct', 50)->count())->toBe(3)
        ->and((float) $order->items()->sum('discount_amount'))->toBe(15000.0)
        ->and((float) $order->discount_amount)->toBe(15000.0);
});

test('walk-in regular discount via per-item mode marks only selected item is_discount', function () {
    $fixture = makePerItemBookingFixture();

    $code = DailyAuthCode::forDate(now()->format('Y-m-d'))->active_code;

    actingAs($fixture['cashier'])
        ->withSession(['pos_cart' => $fixture['cart']])
        ->postJson(route('admin.pos.checkout'), [
            'customer_type' => 'walk-in',
            'walk_in_customer_id' => $fixture['customer']->id,
            'discount_type' => 'item',
            'discount_item_ids' => [$fixture['items'][0]->id],
            'discount_item_type' => 'percentage',
            'discount_item_value' => 10,
            'discount_auth_code' => $code,
            'payment_mode' => 'normal',
            'payment_method' => 'cash',
        ])
        ->assertSuccessful();

    $order = Order::where('created_by', $fixture['cashier']->id)->latest('id')->firstOrFail();
    $selected = $order->items()->where('inventory_item_id', $fixture['items'][0]->id)->firstOrFail();
    $regular = $order->items()->where('inventory_item_id', $fixture['items'][1]->id)->firstOrFail();

    expect($selected->is_discount)->toBeTrue()
        ->and((float) $selected->discount_pct)->toBe(10.0)
        ->and((float) $selected->discount_amount)->toBe(10000.0)
        ->and($regular->is_discount)->toBeFalse()
        ->and((float) $regular->discount_amount)->toBe(0.0)
        ->and((float) $order->discount_amount)->toBe(10000.0);
});

test('close billing percentage marks selected item is_discount with its own amount', function () {
    $admin = adminUser();
    [$booking] = makePerItemCloseBillingFixture($admin);

    DailyAuthCode::query()->updateOrCreate(
        ['date' => now()->format('Y-m-d')],
        ['code' => '4321', 'override_code' => null, 'generated_at' => now()],
    );

    $item = $booking->tableSession->orders->first()->items->first(); // subtotal 120000
    actingAs($admin)
        ->withSession(['booking_discount_auth_code_requested_at' => now()->timestamp])
        ->postJson(route('admin.bookings.closeBilling', $booking), [
            'payment_mode' => 'normal',
            'payment_method' => 'cash',
            'discount_type' => 'percentage',
            'discount_percentage' => 10,
            'discount_order_item_ids' => [$item->id],
            'discount_auth_code' => '4321',
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $updatedBilling = $booking->fresh()->tableSession->billing;
    $itemFresh = $item->fresh();

    expect($itemFresh->is_discount)->toBeTrue()
        ->and((float) $itemFresh->discount_pct)->toBe(10.0)
        ->and((float) $itemFresh->discount_amount)->toBe(12000.0)
        ->and((float) $updatedBilling->discount_amount)->toBe(12000.0);
});

test('close billing FOC marks all items is_discount', function () {
    $admin = adminUser();
    [$booking] = makePerItemCloseBillingFixture($admin);

    GeneralSetting::instance()->update(['foc_discount_percentage' => 50]);

    DailyAuthCode::query()->updateOrCreate(
        ['date' => now()->format('Y-m-d')],
        ['code' => '8765', 'override_code' => null, 'generated_at' => now()],
    );

    actingAs($admin)
        ->withSession(['booking_discount_auth_code_requested_at' => now()->timestamp])
        ->postJson(route('admin.bookings.closeBilling', $booking), [
            'payment_mode' => 'normal',
            'payment_method' => 'FOC',
            'foc_comp_payment_method' => 'FOC',
            'foc_comp_auth_code' => '8765',
            'discount_type' => 'percentage',
            'discount_percentage' => 50,
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $session = $booking->fresh()->tableSession;
    $items = $session->orders->flatMap(fn ($o) => $o->items);

    expect($items->count())->toBeGreaterThanOrEqual(1)
        ->and($items->every(fn ($i) => $i->is_discount))->toBeTrue()
        ->and($items->every(fn ($i) => (float) $i->discount_pct === 50.0))->toBeTrue();
});

test('order updateTotals preserves legacy global discount when no item has discount', function () {
    $admin = adminUser();
    [$booking] = makePerItemCloseBillingFixture($admin);

    $order = $booking->tableSession->orders->first();
    $order->update(['discount_amount' => 5000]); // legacy global discount tanpa item flag
    $order->updateTotals();

    // ponytail: max() menjaga legacy order yang diskonnya global (item flag 0) tak terhapus.
    expect((float) $order->fresh()->discount_amount)->toBe(5000.0);
});
