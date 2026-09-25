<?php

use App\Models\Area;
use App\Models\Billing;
use App\Models\CustomerUser;
use App\Models\Order;
use App\Models\Tabel;
use App\Models\TableSession;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Pagination\LengthAwarePaginator;

function createCustomerForLeaderboard(array $attributes): CustomerUser
{
    $user = User::factory()->create([
        'name' => $attributes['name'],
        'email' => $attributes['email'],
    ]);

    $profile = UserProfile::create([
        'user_id' => $user->id,
        'phone' => $attributes['phone'] ?? null,
    ]);

    return CustomerUser::create([
        'accurate_id' => $attributes['accurate_id'],
        'customer_code' => $attributes['customer_code'],
        'user_id' => $user->id,
        'user_profile_id' => $profile->id,
        'total_visits' => $attributes['total_visits'],
        'lifetime_spending' => $attributes['lifetime_spending'],
    ]);
}

function createWalkInBillingHistory(CustomerUser $customer, float $grandTotal, string $status = 'paid', $createdAt = null): void
{
    $cashier = User::factory()->create();

    $order = Order::create([
        'table_session_id' => null,
        'customer_user_id' => $customer->id,
        'created_by' => $cashier->id,
        'order_number' => 'ORD-WALKIN-'.uniqid(),
        'status' => 'pending',
        'items_total' => $grandTotal,
        'discount_amount' => 0,
        'total' => $grandTotal,
        'ordered_at' => $createdAt ?? now(),
        'created_at' => $createdAt,
    ]);

    Billing::create([
        'table_session_id' => null,
        'order_id' => $order->id,
        'is_walk_in' => true,
        'is_booking' => false,
        'minimum_charge' => 0,
        'orders_total' => $grandTotal,
        'subtotal' => $grandTotal,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => $grandTotal,
        'paid_amount' => $grandTotal,
        'billing_status' => $status,
        'transaction_code' => 'WALKIN-'.uniqid(),
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
        'created_at' => $createdAt,
    ]);
}

function createWalkInTransactionOnly(CustomerUser $customer, float $grandTotal, string $status = 'pending', $createdAt = null): void
{
    $cashier = User::factory()->create();

    Order::create([
        'table_session_id' => null,
        'customer_user_id' => $customer->id,
        'created_by' => $cashier->id,
        'order_number' => 'ORD-WALKIN-TX-'.uniqid(),
        'status' => $status,
        'items_total' => $grandTotal,
        'discount_amount' => 0,
        'total' => $grandTotal,
        'ordered_at' => $createdAt ?? now(),
        'created_at' => $createdAt,
    ]);
}

function createBookingBillingHistory(
    CustomerUser $customer,
    float $grandTotal,
    string $status = 'paid',
    $createdAt = null,
    bool $attachCustomerUserIdToOrder = true
): void {
    $area = Area::create([
        'code' => 'AREA-'.uniqid(),
        'name' => 'Area '.uniqid(),
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $table = Tabel::create([
        'area_id' => $area->id,
        'table_number' => 'TB-'.uniqid(),
        'qr_code' => 'QR-'.uniqid(),
        'capacity' => 4,
        'minimum_charge' => 0,
        'status' => 'available',
        'is_active' => true,
    ]);

    $session = TableSession::create([
        'table_id' => $table->id,
        'customer_id' => $customer->user_id,
        'session_code' => 'SESSION-'.uniqid(),
        'checked_in_at' => $createdAt ?? now(),
        'status' => 'completed',
    ]);

    // Create an associated order for the booking
    $cashier = User::factory()->create();
    $order = Order::create([
        'table_session_id' => $session->id,
        'customer_user_id' => $attachCustomerUserIdToOrder ? $customer->id : null,
        'created_by' => $cashier->id,
        'order_number' => 'ORD-BOOKING-'.uniqid(),
        'status' => 'pending',
        'items_total' => $grandTotal,
        'discount_amount' => 0,
        'total' => $grandTotal,
        'ordered_at' => $createdAt ?? now(),
        'created_at' => $createdAt,
    ]);

    Billing::create([
        'table_session_id' => $session->id,
        'order_id' => $order->id,
        'is_walk_in' => false,
        'is_booking' => true,
        'minimum_charge' => 0,
        'orders_total' => $grandTotal,
        'subtotal' => $grandTotal,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => $grandTotal,
        'paid_amount' => $grandTotal,
        'billing_status' => $status,
        'transaction_code' => 'BILLING-'.uniqid(),
        'payment_method' => 'cash',
        'payment_mode' => 'normal',
        'created_at' => $createdAt,
    ]);
}

test('admin customers lifetime leaderboard uses stored spending and visits', function () {
    $admin = adminUser();

    $alice = createCustomerForLeaderboard([
        'name' => 'Alice Score',
        'email' => 'alice.score@example.com',
        'accurate_id' => 991001,
        'customer_code' => 'CUST-991001',
        'total_visits' => 99,
        'lifetime_spending' => 999999,
    ]);

    $bravo = createCustomerForLeaderboard([
        'name' => 'Bravo Score',
        'email' => 'bravo.score@example.com',
        'accurate_id' => 991002,
        'customer_code' => 'CUST-991002',
        'total_visits' => 2,
        'lifetime_spending' => 300000,
    ]);

    $charlie = createCustomerForLeaderboard([
        'name' => 'Charlie Score',
        'email' => 'charlie.score@example.com',
        'accurate_id' => 991003,
        'customer_code' => 'CUST-991003',
        'total_visits' => 1,
        'lifetime_spending' => 120000,
    ]);

    createWalkInTransactionOnly($charlie, 120000);
    createWalkInTransactionOnly($bravo, 30000);
    createBookingBillingHistory($bravo, 20000);
    createBookingBillingHistory($alice, 450000, 'draft');

    $response = $this->actingAs($admin)
        ->get(route('admin.customers.index'));

    $response->assertOk();

    $response->assertViewHas('leaderboard', function ($leaderboard) use ($alice, $bravo, $charlie) {
        $orderedIds = $leaderboard->pluck('id')->values()->all();

        if ($orderedIds !== [$alice->id, $bravo->id, $charlie->id]) {
            return false;
        }

        $topScore = (int) ($leaderboard->first()->leaderboard_score ?? 0);

        return $topScore === 198;
    });

    $response->assertSeeInOrder([
        'Alice Score',
        'Bravo Score',
        'Charlie Score',
    ]);

    $response->assertSee('Rp 999.999');
    $response->assertSee('Rp 300.000');
    $response->assertSee('Rp 120.000');
    $response->assertDontSee('Points');
    $response->assertDontSee(' points');
});

test('admin customers leaderboard limit can be configured via query parameter', function () {
    $admin = adminUser();

    for ($index = 1; $index <= 25; $index++) {
        $customer = createCustomerForLeaderboard([
            'name' => "Leaderboard Customer {$index}",
            'email' => "leaderboard-customer-{$index}@example.com",
            'accurate_id' => 992000 + $index,
            'customer_code' => 'CUST-'.(992000 + $index),
            'total_visits' => 0,
            'lifetime_spending' => 0,
        ]);

        createWalkInTransactionOnly($customer, $index * 1000);
    }

    $response = $this->actingAs($admin)
        ->get(route('admin.customers.index', [
            'tab' => 'leaderboard',
            'leaderboard_limit' => 20,
        ]));

    $response->assertOk();
    $response->assertViewHas('leaderboardLimit', 20);
    $response->assertViewHas('leaderboard', fn ($leaderboard) => $leaderboard->count() === 20);
    $response->assertSee('Top 20 Spenders');
});

test('admin customers leaderboard uses default limit when query value is invalid', function () {
    $admin = adminUser();

    for ($index = 1; $index <= 25; $index++) {
        $customer = createCustomerForLeaderboard([
            'name' => "Fallback Customer {$index}",
            'email' => "fallback-customer-{$index}@example.com",
            'accurate_id' => 993000 + $index,
            'customer_code' => 'CUST-'.(993000 + $index),
            'total_visits' => 0,
            'lifetime_spending' => 0,
        ]);

        createWalkInTransactionOnly($customer, $index * 1000);
    }

    $response = $this->actingAs($admin)
        ->get(route('admin.customers.index', [
            'tab' => 'leaderboard',
            'leaderboard_limit' => 15,
        ]));

    $response->assertOk();
    $response->assertViewHas('leaderboardLimit', 10);
    $response->assertViewHas('leaderboard', fn ($leaderboard) => $leaderboard->count() === 10);
    $response->assertSee('Top 10 Spenders');
});

test('admin customers list uses default pagination and can change rows per page', function () {
    $admin = adminUser();

    for ($index = 1; $index <= 30; $index++) {
        createCustomerForLeaderboard([
            'name' => "Pagination Customer {$index}",
            'email' => "pagination-customer-{$index}@example.com",
            'accurate_id' => 994000 + $index,
            'customer_code' => 'CUST-'.(994000 + $index),
            'total_visits' => 0,
            'lifetime_spending' => 0,
        ]);
    }

    $defaultResponse = $this->actingAs($admin)
        ->get(route('admin.customers.index'));

    $defaultResponse->assertOk();
    $defaultResponse->assertViewHas('customers', function ($customers): bool {
        if (! $customers instanceof LengthAwarePaginator) {
            return false;
        }

        return $customers->perPage() === 10
            && $customers->count() === 10
            && $customers->total() === 30;
    });
    $defaultResponse->assertViewHas('perPage', 10);

    $customResponse = $this->actingAs($admin)
        ->get(route('admin.customers.index', [
            'per_page' => 25,
        ]));

    $customResponse->assertOk();
    $customResponse->assertViewHas('customers', function ($customers): bool {
        if (! $customers instanceof LengthAwarePaginator) {
            return false;
        }

        return $customers->perPage() === 25
            && $customers->count() === 25
            && $customers->total() === 30;
    });
    $customResponse->assertViewHas('perPage', 25);
});

test('admin customers leaderboard today shows only transactions from 9am to 9am next day', function () {
    $admin = adminUser();

    $customer = createCustomerForLeaderboard([
        'name' => 'Today Spender',
        'email' => 'today.spender@example.com',
        'accurate_id' => 995001,
        'customer_code' => 'CUST-995001',
        'total_visits' => 0,
        'lifetime_spending' => 0,
    ]);

    // Create a transaction at 8:30 AM (before today's window opens)
    $cashier = User::factory()->create();
    Order::create([
        'table_session_id' => null,
        'customer_user_id' => $customer->id,
        'created_by' => $cashier->id,
        'order_number' => 'ORD-BEFORE-'.uniqid(),
        'status' => 'pending',
        'items_total' => 50000,
        'discount_amount' => 0,
        'total' => 50000,
        'ordered_at' => now()->startOfDay()->setHour(8)->setMinute(30),
        'created_at' => now()->startOfDay()->setHour(8)->setMinute(30),
    ]);

    // Create a transaction at 3 PM (within today's window) - use booking instead
    createBookingBillingHistory($customer, 100000, 'paid', now()->setHour(15)->setMinute(0));

    $response = $this->actingAs($admin)
        ->get(route('admin.customers.index', [
            'tab' => 'leaderboard',
            'leaderboard_type' => 'today',
        ]));

    $response->assertOk();
    $response->assertViewHas('leaderboardToday');
});

test('admin customers today leaderboard is sorted by daily leaderboard score', function () {
    $admin = adminUser();

    $alice = createCustomerForLeaderboard([
        'name' => 'Alice Today',
        'email' => 'alice.today@example.com',
        'accurate_id' => 996001,
        'customer_code' => 'CUST-996001',
        'total_visits' => 0,
        'lifetime_spending' => 0,
    ]);

    $bravo = createCustomerForLeaderboard([
        'name' => 'Bravo Today',
        'email' => 'bravo.today@example.com',
        'accurate_id' => 996002,
        'customer_code' => 'CUST-996002',
        'total_visits' => 0,
        'lifetime_spending' => 0,
    ]);

    $charlie = createCustomerForLeaderboard([
        'name' => 'Charlie Today',
        'email' => 'charlie.today@example.com',
        'accurate_id' => 996003,
        'customer_code' => 'CUST-996003',
        'total_visits' => 0,
        'lifetime_spending' => 0,
    ]);

    // Create today's transactions within 9AM-9AM window
    createBookingBillingHistory($charlie, 120000, 'paid', now()->setHour(10)->setMinute(0));
    createBookingBillingHistory($bravo, 100000, 'paid', now()->setHour(10)->setMinute(30));
    createBookingBillingHistory($bravo, 50000, 'paid', now()->setHour(11)->setMinute(0));
    createBookingBillingHistory($alice, 450000, 'paid', now()->setHour(12)->setMinute(0));

    $response = $this->actingAs($admin)
        ->get(route('admin.customers.index', [
            'tab' => 'leaderboard',
            'leaderboard_type' => 'today',
        ]));

    $response->assertOk();

    $response->assertViewHas('leaderboardToday');
    $response->assertSee('Today Top Spenders');
});

test('admin customers today leaderboard correctly aggregates walk-in and booking orders for same customer', function () {
    $admin = adminUser();

    $customer = createCustomerForLeaderboard([
        'name' => 'Mixed Orders Customer',
        'email' => 'mixed.orders@example.com',
        'accurate_id' => 997001,
        'customer_code' => 'CUST-997001',
        'total_visits' => 0,
        'lifetime_spending' => 0,
    ]);

    // Create a walk-in billing (table_session_id null path)
    createWalkInBillingHistory($customer, 50000, 'paid', now()->setHour(10)->setMinute(0));

    // Create a booking order for the same customer without customer_user_id reference
    createBookingBillingHistory($customer, 100000, 'paid', now()->setHour(11)->setMinute(0), false);

    $response = $this->actingAs($admin)
        ->get(route('admin.customers.index', [
            'tab' => 'leaderboard',
            'leaderboard_type' => 'today',
        ]));

    $response->assertOk();

    $response->assertViewHas('leaderboardToday', function ($leaderboardToday) use ($customer) {
        if ($leaderboardToday->count() === 0) {
            return false;
        }

        $found = $leaderboardToday->first(fn ($c) => $c->id === $customer->id);

        if ($found === null) {
            return false;
        }

        $spending = (float) ($found->transaction_daily_spending ?? 0);
        $visits = (int) ($found->transaction_daily_visits ?? 0);
        $score = (int) ($found->daily_leaderboard_score ?? 0);

        // Should have total spending of 150000 (50000 + 100000)
        // Should have total visits of 2
        // Score should be FLOOR(150000 / 10000) + 2 = 15 + 2 = 17
        return $spending === 150000.0
            && $visits === 2
            && $score === 17;
    });
});
