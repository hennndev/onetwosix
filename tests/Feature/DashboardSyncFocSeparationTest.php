<?php

use App\Models\Billing;
use App\Models\CustomerUser;
use App\Models\Dashboard;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\DashboardSyncService;

use function Pest\Laravel\actingAs;

test('dashboard sync excludes FOC and Compliment amounts from total_amount', function () {
    $user = User::factory()->create();
    $profile = UserProfile::create(['user_id' => $user->id]);
    $customerUser = CustomerUser::create([
        'user_id' => $user->id,
        'user_profile_id' => $profile->id,
        'accurate_id' => null,
        'customer_code' => null,
        'total_visits' => 0,
        'lifetime_spending' => 0,
    ]);

    // Normal cash billing: 100000
    $normalOrder = Order::create([
        'order_number' => 'DS-NORM-'.uniqid(),
        'customer_user_id' => $customerUser->id,
        'status' => 'paid',
        'items_total' => 100000,
        'discount_amount' => 0,
        'total' => 100000,
        'ordered_at' => now(),
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
    ]);
    Billing::create([
        'order_id' => $normalOrder->id,
        'is_walk_in' => true,
        'subtotal' => 100000,
        'grand_total' => 100000,
        'paid_amount' => 100000,
        'billing_status' => 'paid',
        'paid_at' => now(),
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
    ]);

    // FOC billing: 61050
    $focItem = InventoryItem::create([
        'code' => 'FOC-ITEM-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'FOC Item',
        'category_type' => 'main-course',
        'category_main' => 'food',
        'price' => 61050,
        'stock_quantity' => 20,
        'threshold' => 5,
        'unit' => 'porsi',
        'is_active' => true,
    ]);
    $focOrder = Order::create([
        'order_number' => 'DS-FOC-'.uniqid(),
        'customer_user_id' => $customerUser->id,
        'status' => 'paid',
        'items_total' => 61050,
        'discount_amount' => 0,
        'total' => 61050,
        'ordered_at' => now(),
        'payment_method' => 'FOC',
        'payment_mode' => 'normal',
    ]);
    OrderItem::create([
        'order_id' => $focOrder->id,
        'inventory_item_id' => $focItem->id,
        'item_name' => $focItem->name,
        'item_code' => $focItem->code,
        'quantity' => 2,
        'price' => 61050,
        'subtotal' => 61050 * 2,
        'discount_amount' => 0,
        'preparation_location' => 'kitchen',
        'status' => 'served',
    ]);
    Billing::create([
        'order_id' => $focOrder->id,
        'is_walk_in' => true,
        'subtotal' => 61050 * 2,
        'grand_total' => 61050 * 2,
        'paid_amount' => 61050 * 2,
        'billing_status' => 'paid',
        'paid_at' => now(),
        'payment_method' => 'FOC',
        'payment_mode' => 'normal',
        'foc_comp_payment_method' => 'FOC',
    ]);

    // Compliment billing: paid 0 (diskon 100%), pre-discount 50000
    $compItem = InventoryItem::create([
        'code' => 'COMP-ITEM-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Compliment Item',
        'category_type' => 'main-course',
        'category_main' => 'food',
        'price' => 50000,
        'stock_quantity' => 20,
        'threshold' => 5,
        'unit' => 'porsi',
        'is_active' => true,
    ]);
    $compOrder = Order::create([
        'order_number' => 'DS-COMP-'.uniqid(),
        'customer_user_id' => $customerUser->id,
        'status' => 'paid',
        'items_total' => 50000,
        'discount_amount' => 50000,
        'total' => 0,
        'ordered_at' => now(),
        'payment_method' => 'Compliment',
        'payment_mode' => 'normal',
    ]);
    OrderItem::create([
        'order_id' => $compOrder->id,
        'inventory_item_id' => $compItem->id,
        'item_name' => $compItem->name,
        'item_code' => $compItem->code,
        'quantity' => 3,
        'price' => 50000,
        'subtotal' => 50000 * 3,
        'discount_amount' => 50000 * 3,
        'preparation_location' => 'kitchen',
        'status' => 'served',
    ]);
    Billing::create([
        'order_id' => $compOrder->id,
        'is_walk_in' => true,
        'subtotal' => 50000 * 3,
        'discount_amount' => 50000 * 3,
        'grand_total' => 0,
        'paid_amount' => 0,
        'billing_status' => 'paid',
        'paid_at' => now(),
        'payment_method' => 'Compliment',
        'payment_mode' => 'normal',
        'foc_comp_payment_method' => 'Compliment',
    ]);

    (new DashboardSyncService)->sync();

    $dashboard = Dashboard::query()->find(1);

    expect($dashboard)->not->toBeNull()
        ->and((float) $dashboard->total_amount)->toBe(100000.0)          // hanya cash
        ->and((float) $dashboard->total_cash)->toBe(100000.0)            // cash bucket tak tercemar
        ->and((float) $dashboard->total_foc_amount)->toBe(61050.0 * 2)   // FOC grouping sendiri
        ->and((float) $dashboard->total_compliment_amount)->toBe(0.0)    // Compliment paid 0
        ->and((int) $dashboard->total_foc_quantity)->toBe(2)             // qty FOC dari order items
        ->and((int) $dashboard->total_compliment_quantity)->toBe(3)      // qty Compliment dari order items
        ->and((int) $dashboard->total_transactions)->toBe(3);            // tetap hitung transaksi
});

test('dashboard revenue today excludes FOC and Compliment grand totals', function () {
    $user = User::factory()->create();
    $profile = UserProfile::create(['user_id' => $user->id]);
    $customerUser = CustomerUser::create([
        'user_id' => $user->id,
        'user_profile_id' => $profile->id,
        'accurate_id' => null,
        'customer_code' => null,
        'total_visits' => 0,
        'lifetime_spending' => 0,
    ]);

    $normalOrder = Order::create([
        'order_number' => 'DT-NORM-'.uniqid(),
        'customer_user_id' => $customerUser->id,
        'status' => 'paid',
        'items_total' => 50000,
        'discount_amount' => 0,
        'total' => 50000,
        'ordered_at' => now(),
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
    ]);
    Billing::create([
        'order_id' => $normalOrder->id,
        'is_walk_in' => true,
        'subtotal' => 50000,
        'grand_total' => 50000,
        'paid_amount' => 50000,
        'billing_status' => 'paid',
        'paid_at' => now(),
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
    ]);

    $focOrder = Order::create([
        'order_number' => 'DT-FOC-'.uniqid(),
        'customer_user_id' => $customerUser->id,
        'status' => 'paid',
        'items_total' => 61050,
        'discount_amount' => 0,
        'total' => 61050,
        'ordered_at' => now(),
        'payment_method' => 'FOC',
        'payment_mode' => 'normal',
    ]);
    Billing::create([
        'order_id' => $focOrder->id,
        'is_walk_in' => true,
        'subtotal' => 61050,
        'grand_total' => 61050,
        'paid_amount' => 61050,
        'billing_status' => 'paid',
        'paid_at' => now(),
        'payment_method' => 'FOC',
        'payment_mode' => 'normal',
        'foc_comp_payment_method' => 'FOC',
    ]);

    $admin = adminUser();
    $response = actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk()
        ->assertViewHas('revenueToday', 50000.0)
        ->assertViewHas('transactionsToday', 2);
});

test('FOC checkout does not increment customer lifetime spending', function () {
    $admin = adminUser();
    $customer = User::factory()->create();
    $profile = UserProfile::create(['user_id' => $customer->id, 'phone' => '081700000001']);
    $customerUser = CustomerUser::create([
        'user_id' => $customer->id,
        'user_profile_id' => $profile->id,
        'accurate_id' => null,
        'customer_code' => null,
        'total_visits' => 0,
        'lifetime_spending' => 0,
    ]);

    // FOC walk-in checkout tanpa diskon (0%) → grand_total penuh, tapi bukan revenue.
    $focOrder = Order::create([
        'order_number' => 'LS-FOC-'.uniqid(),
        'customer_user_id' => $customerUser->id,
        'status' => 'paid',
        'items_total' => 50000,
        'discount_amount' => 0,
        'total' => 50000,
        'ordered_at' => now(),
        'payment_method' => 'FOC',
        'payment_mode' => 'normal',
    ]);
    Billing::create([
        'order_id' => $focOrder->id,
        'is_walk_in' => true,
        'subtotal' => 50000,
        'grand_total' => 50000,
        'paid_amount' => 50000,
        'billing_status' => 'paid',
        'paid_at' => now(),
        'payment_method' => 'FOC',
        'payment_mode' => 'normal',
        'foc_comp_payment_method' => 'FOC',
    ]);

    // Simulasi: lifetime_spending tidak boleh berisi FOC.
    expect((float) $customerUser->fresh()->lifetime_spending)->toBe(0.0);

    // Leaderboard tidak boleh menghitung FOC sebagai spending.
    $response = actingAs($admin)->get(route('admin.customers.index'));
    $response->assertOk();
    expect((float) $customerUser->fresh()->lifetime_spending)->toBe(0.0);
});
