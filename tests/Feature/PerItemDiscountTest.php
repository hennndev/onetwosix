<?php

use App\Models\Area;
use App\Models\Billing;
use App\Models\CustomerUser;
use App\Models\DailyAuthCode;
use App\Models\GeneralSetting;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosCategorySetting;
use App\Models\Tabel;
use App\Models\TableReservation;
use App\Models\TableSession;
use App\Models\User;
use App\Models\UserProfile;

use function Pest\Laravel\actingAs;

function perItemSeedAuthCode(): void
{
    DailyAuthCode::query()->updateOrCreate(
        ['date' => now()->format('Y-m-d')],
        ['code' => '9753', 'override_code' => null, 'generated_at' => now()],
    );
}

function perItemMakeInventoryItem(int $price): InventoryItem
{
    $item = InventoryItem::create([
        'code' => 'PI-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'PerItem '.uniqid(),
        'category_type' => 'main-course',
        'price' => $price,
        'stock_quantity' => 50,
        'threshold' => 5,
        'unit' => 'porsi',
        'is_active' => true,
        'include_tax' => true,
        'include_service_charge' => true,
    ]);
    $item->printers()->sync([]);
    PosCategorySetting::updateOrCreate(
        ['category_type' => 'main-course'],
        ['show_in_pos' => true, 'is_menu' => true, 'is_item_group' => false, 'preparation_location' => 'kitchen', 'source' => 'inventory']
    );

    return $item;
}

function perItemCloseBillingFixture(User $admin): array
{
    $customer = User::factory()->create();
    $area = Area::create(['code' => 'PI-AREA-'.uniqid(), 'name' => 'Area '.uniqid(), 'is_active' => true, 'sort_order' => 1]);
    $table = Tabel::create([
        'area_id' => $area->id,
        'table_number' => 'PI-TBL-'.uniqid(),
        'qr_code' => 'PI-QR-'.uniqid(),
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
        'session_code' => 'PI-SES-'.uniqid(),
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

    $itemA = perItemMakeInventoryItem(60000);
    $order = Order::create([
        'table_session_id' => $session->id,
        'created_by' => $admin->id,
        'order_number' => 'PI-ORD-'.uniqid(),
        'status' => 'pending',
        'items_total' => 120000,
        'discount_amount' => 0,
        'total' => 120000,
        'ordered_at' => now(),
    ]);
    $item = OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $itemA->id,
        'item_name' => $itemA->name,
        'item_code' => $itemA->code,
        'quantity' => 2,
        'price' => 60000,
        'subtotal' => 120000,
        'discount_amount' => 0,
        'preparation_location' => 'kitchen',
        'status' => 'served',
    ]);

    return [$booking, $session, $billing, $item];
}

test('walk-in per-item percentage discount marks only selected item is_discount', function () {
    $admin = adminUser();
    $customer = User::factory()->create();

    GeneralSetting::instance()->update(['service_charge_percentage' => 0, 'tax_percentage' => 0]);
    perItemSeedAuthCode();

    $itemA = perItemMakeInventoryItem(10000);
    $itemB = perItemMakeInventoryItem(10000);
    $cart = collect([$itemA, $itemB])->mapWithKeys(fn (InventoryItem $item): array => [
        'item_'.$item->id => [
            'id' => 'item_'.$item->id,
            'name' => $item->name,
            'price' => 10000,
            'quantity' => 1,
            'preparation_location' => 'kitchen',
        ],
    ])->all();

    actingAs($admin)
        ->withSession(['pos_cart' => $cart])
        ->postJson(route('admin.pos.checkout'), [
            'customer_type' => 'walk-in',
            'walk_in_customer_id' => $customer->id,
            'payment_mode' => 'normal',
            'payment_method' => 'cash',
            'discount_type' => 'item',
            'discount_item_ids' => [$itemA->id],
            'discount_item_type' => 'percentage',
            'discount_item_value' => 10,
            'discount_auth_code' => '9753',
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $order = Order::where('created_by', $admin->id)->latest('id')->firstOrFail();
    $orderItemA = $order->items()->where('inventory_item_id', $itemA->id)->firstOrFail();
    $orderItemB = $order->items()->where('inventory_item_id', $itemB->id)->firstOrFail();
    $billing = Billing::query()->latest('id')->first();

    expect($orderItemA->is_discount)->toBeTrue()
        ->and((float) $orderItemA->discount_pct)->toBe(10.0)
        ->and((float) $orderItemA->discount_amount)->toBe(1000.0)
        ->and($orderItemB->is_discount)->toBeFalse()
        ->and((float) $orderItemB->discount_amount)->toBe(0.0)
        ->and((float) $order->discount_amount)->toBe(1000.0)
        ->and((float) $billing->discount_amount)->toBe(1000.0);
});

test('walk-in per-item nominal discount converts to discount_pct', function () {
    $admin = adminUser();
    $customer = User::factory()->create();

    GeneralSetting::instance()->update(['service_charge_percentage' => 0, 'tax_percentage' => 0]);
    perItemSeedAuthCode();

    $item = perItemMakeInventoryItem(120000);
    $cart = [
        'item_'.$item->id => [
            'id' => 'item_'.$item->id,
            'name' => $item->name,
            'price' => 120000,
            'quantity' => 1,
            'preparation_location' => 'kitchen',
        ],
    ];

    actingAs($admin)
        ->withSession(['pos_cart' => $cart])
        ->postJson(route('admin.pos.checkout'), [
            'customer_type' => 'walk-in',
            'walk_in_customer_id' => $customer->id,
            'payment_mode' => 'normal',
            'payment_method' => 'cash',
            'discount_type' => 'item',
            'discount_item_ids' => [$item->id],
            'discount_item_type' => 'nominal',
            'discount_item_value' => 5000,
            'discount_auth_code' => '9753',
        ])
        ->assertSuccessful();

    $order = Order::where('created_by', $admin->id)->latest('id')->firstOrFail();
    $orderItem = $order->items()->where('inventory_item_id', $item->id)->firstOrFail();

    expect($orderItem->is_discount)->toBeTrue()
        ->and((float) $orderItem->discount_pct)->toBe(4.17)
        ->and((float) $orderItem->discount_amount)->toBe(5000.0);
});

test('walk-in FOC bulk marks all items is_discount with setting percentage', function () {
    $admin = adminUser();
    $customer = User::factory()->create();

    GeneralSetting::instance()->update(['service_charge_percentage' => 0, 'tax_percentage' => 0, 'foc_discount_percentage' => 50]);
    perItemSeedAuthCode();

    $itemA = perItemMakeInventoryItem(10000);
    $itemB = perItemMakeInventoryItem(10000);
    $cart = collect([$itemA, $itemB])->mapWithKeys(fn (InventoryItem $item): array => [
        'item_'.$item->id => ['id' => 'item_'.$item->id, 'name' => $item->name, 'price' => 10000, 'quantity' => 1, 'preparation_location' => 'kitchen'],
    ])->all();

    actingAs($admin)
        ->withSession(['pos_cart' => $cart])
        ->postJson(route('admin.pos.checkout'), [
            'customer_type' => 'walk-in',
            'walk_in_customer_id' => $customer->id,
            'payment_mode' => 'normal',
            'foc_comp_payment_method' => 'FOC',
            'foc_comp_auth_code' => '9753',
        ])
        ->assertSuccessful();

    $order = Order::where('created_by', $admin->id)->latest('id')->firstOrFail();
    $billing = Billing::query()->latest('id')->first();

    expect($order->items()->where('is_discount', true)->count())->toBe(2)
        ->and($order->items()->where('discount_pct', 50)->count())->toBe(2)
        ->and((float) $order->items()->sum('discount_amount'))->toBe(10000.0)
        // Item gratis, pajak tetap: total = 20000 - 10000 = 10000 (tax/SC 0).
        ->and((float) $billing->grand_total)->toBe(10000.0);
});

test('walk-in global percentage discount marks all items is_discount for struk per baris', function () {
    $admin = adminUser();
    $customer = User::factory()->create();

    GeneralSetting::instance()->update(['service_charge_percentage' => 0, 'tax_percentage' => 0]);
    perItemSeedAuthCode();

    $itemA = perItemMakeInventoryItem(799000);
    $cart = ['item_'.$itemA->id => ['id' => 'item_'.$itemA->id, 'name' => $itemA->name, 'price' => 799000, 'quantity' => 1, 'preparation_location' => 'kitchen']];

    actingAs($admin)
        ->withSession(['pos_cart' => $cart])
        ->postJson(route('admin.pos.checkout'), [
            'customer_type' => 'walk-in',
            'walk_in_customer_id' => $customer->id,
            'payment_mode' => 'normal',
            'payment_method' => 'cash',
            'discount_type' => 'percentage',
            'discount_percentage' => 10,
            'discount_auth_code' => '9753',
        ])
        ->assertSuccessful();

    $order = Order::where('created_by', $admin->id)->latest('id')->firstOrFail();
    $orderItem = $order->items()->firstOrFail();
    $billing = Billing::query()->latest('id')->first();

    // Diskon global % kini menandai item → struk menampilkan "Diskon Item" per baris.
    expect($orderItem->is_discount)->toBeTrue()
        ->and((float) $orderItem->discount_pct)->toBe(10.0)
        ->and((float) $orderItem->discount_amount)->toBe(79900.0)
        ->and((float) $billing->discount_amount)->toBe(79900.0)
        ->and((float) $billing->grand_total)->toBe(719100.0);
});

test('walk-in per-item discount rejects combined with transaction discount', function () {
    $admin = adminUser();
    $customer = User::factory()->create();

    GeneralSetting::instance()->update(['service_charge_percentage' => 0, 'tax_percentage' => 0]);
    perItemSeedAuthCode();

    $item = perItemMakeInventoryItem(10000);
    $cart = ['item_'.$item->id => ['id' => 'item_'.$item->id, 'name' => $item->name, 'price' => 10000, 'quantity' => 1, 'preparation_location' => 'kitchen']];

    actingAs($admin)
        ->withSession(['pos_cart' => $cart])
        ->postJson(route('admin.pos.checkout'), [
            'customer_type' => 'walk-in',
            'walk_in_customer_id' => $customer->id,
            'payment_mode' => 'normal',
            'payment_method' => 'cash',
            'discount_type' => 'percentage',
            'discount_percentage' => 5,
            'discount_item_ids' => [$item->id],
            'discount_item_type' => 'percentage',
            'discount_item_value' => 10,
            'discount_auth_code' => '9753',
        ])
        ->assertStatus(422);
});

test('close billing per-item percentage marks selected item and billing discount', function () {
    $admin = adminUser();
    [$booking, $session, $billing, $item] = perItemCloseBillingFixture($admin);

    GeneralSetting::instance()->update(['service_charge_percentage' => 0, 'tax_percentage' => 0]);
    perItemSeedAuthCode();

    actingAs($admin)
        ->postJson(route('admin.bookings.closeBilling', $booking), [
            'payment_mode' => 'normal',
            'payment_method' => 'cash',
            'discount_type' => 'item',
            'discount_order_item_ids' => [$item->id],
            'discount_item_type' => 'percentage',
            'discount_item_value' => 10,
            'discount_auth_code' => '9753',
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $itemFresh = $item->fresh();
    $billingFresh = $billing->fresh();

    expect($itemFresh->is_discount)->toBeTrue()
        ->and((float) $itemFresh->discount_pct)->toBe(10.0)
        ->and((float) $itemFresh->discount_amount)->toBe(12000.0)
        ->and((float) $billingFresh->discount_amount)->toBe(12000.0);
});

test('close billing FOC marks all items is_discount', function () {
    $admin = adminUser();
    [$booking, $session, $billing, $item] = perItemCloseBillingFixture($admin);

    GeneralSetting::instance()->update(['service_charge_percentage' => 0, 'tax_percentage' => 0, 'foc_discount_percentage' => 50]);
    perItemSeedAuthCode();

    actingAs($admin)
        ->postJson(route('admin.bookings.closeBilling', $booking), [
            'payment_mode' => 'normal',
            'payment_method' => 'FOC',
            'foc_comp_payment_method' => 'FOC',
            'foc_comp_auth_code' => '9753',
        ])
        ->assertSuccessful();

    $itemFresh = $item->fresh();

    expect($itemFresh->is_discount)->toBeTrue()
        ->and((float) $itemFresh->discount_pct)->toBe(50.0)
        ->and((float) $itemFresh->discount_amount)->toBe(60000.0);
});

test('close billing re-applying same percentage does not double count', function () {
    $admin = adminUser();
    [$booking, $session, $billing, $item] = perItemCloseBillingFixture($admin);

    GeneralSetting::instance()->update(['service_charge_percentage' => 0, 'tax_percentage' => 0]);
    perItemSeedAuthCode();

    // Item sudah berdiskon 10% (12000) dari checkout sebelumnya.
    $item->update(['is_discount' => true, 'discount_pct' => 10, 'discount_amount' => 12000]);
    $billing->update(['discount_amount' => 12000]);

    actingAs($admin)
        ->postJson(route('admin.bookings.closeBilling', $booking), [
            'payment_mode' => 'normal',
            'payment_method' => 'cash',
            'discount_type' => 'item',
            'discount_order_item_ids' => [$item->id],
            'discount_item_type' => 'percentage',
            'discount_item_value' => 10,
            'discount_auth_code' => '9753',
        ])
        ->assertSuccessful();

    // Delta 0 → billing discount tidak bertambah.
    expect((float) $billing->fresh()->discount_amount)->toBe(0.0);
});

test('order updateTotals recomputes total from items and discount', function () {
    $admin = adminUser();
    [$booking, $session, $billing, $item] = perItemCloseBillingFixture($admin);

    $order = $item->order;
    $order->update(['discount_amount' => 50000]);
    $order->updateTotals();

    // items_total = 120000, discount_amount = 50000 → total = 70000.
    expect((float) $order->fresh()->total)->toBe(70000.0);
});

test('order updateTotals clamps total to zero when discount exceeds items total', function () {
    $admin = adminUser();
    [$booking, $session, $billing, $item] = perItemCloseBillingFixture($admin);

    $order = $item->order;
    $order->update(['discount_amount' => 200000]);
    $order->updateTotals();

    expect((float) $order->fresh()->total)->toBe(0.0);
});
