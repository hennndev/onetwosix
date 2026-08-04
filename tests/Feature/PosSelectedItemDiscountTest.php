<?php

use App\Models\Area;
use App\Models\Billing;
use App\Models\CustomerUser;
use App\Models\DailyAuthCode;
use App\Models\GeneralSetting;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\PosDiscountApproval;
use App\Models\Tabel;
use App\Models\TableReservation;
use App\Models\TableSession;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\AccurateService;
use Mockery\MockInterface;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

function selectedDiscountFixture(): array
{
    $cashier = adminUser();
    $customer = User::factory()->create();
    $profile = UserProfile::create(['user_id' => $customer->id, 'phone' => '081234567801']);
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

    return compact('cashier', 'customer', 'table', 'session', 'billing', 'items', 'cart');
}

test('manager approval discounts only selected items before tax and service', function () {
    GeneralSetting::instance()->update(['tax_percentage' => 10, 'service_charge_percentage' => 10]);
    $fixture = selectedDiscountFixture();
    $code = DailyAuthCode::forDate(now()->format('Y-m-d'))->active_code;

    $approvalResponse = actingAs($fixture['cashier'])
        ->withSession([
            'pos_cart' => $fixture['cart'],
            'pos_discount_auth_code_requested_at' => now()->timestamp,
        ])
        ->postJson(route('admin.pos.discount-approvals.store'), [
            'customer_type' => 'booking',
            'customer_user_id' => $fixture['customer']->id,
            'table_id' => $fixture['table']->id,
            'selected_item_ids' => [$fixture['items'][0]->id],
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'reason' => 'Service recovery',
            'manager_auth_code' => $code,
        ])
        ->assertCreated()
        ->assertJsonPath('discount_amount', 10000);

    $token = $approvalResponse->json('approval_token');
    $approval = PosDiscountApproval::query()->firstOrFail();

    expect($approval->token_hash)->toBe(hash('sha256', $token))
        ->and($approval->token_hash)->not->toBe($token);

    actingAs($fixture['cashier'])
        ->withSession(['pos_cart' => $fixture['cart']])
        ->postJson(route('admin.pos.checkout'), [
            'customer_type' => 'booking',
            'customer_user_id' => $fixture['customer']->id,
            'table_id' => $fixture['table']->id,
            'discount_percentage' => 0,
            'discount_approval_token' => $token,
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $order = Order::query()->where('table_session_id', $fixture['session']->id)->firstOrFail();
    $selected = $order->items()->where('inventory_item_id', $fixture['items'][0]->id)->firstOrFail();
    $regular = $order->items()->where('inventory_item_id', $fixture['items'][1]->id)->firstOrFail();
    $billing = $fixture['billing']->fresh();

    expect((float) $selected->discount_amount)->toBe(10000.0)
        ->and($selected->discount_reason)->toBe('Service recovery')
        ->and((float) $regular->discount_amount)->toBe(0.0)
        ->and((float) $order->items_total)->toBe(200000.0)
        ->and((float) $order->discount_amount)->toBe(10000.0)
        ->and((float) $order->total)->toBe(190000.0)
        ->and((float) $billing->tax)->toBe(19000.0)
        ->and((float) $billing->service_charge)->toBe(20900.0)
        ->and((float) $billing->grand_total)->toBe(229900.0)
        ->and($approval->fresh()->consumed_order_id)->toBe($order->id);
});

test('discount approval is rejected after cart quantity changes', function () {
    $fixture = selectedDiscountFixture();
    $code = DailyAuthCode::forDate(now()->format('Y-m-d'))->active_code;

    $approval = actingAs($fixture['cashier'])
        ->withSession([
            'pos_cart' => $fixture['cart'],
            'pos_discount_auth_code_requested_at' => now()->timestamp,
        ])
        ->postJson(route('admin.pos.discount-approvals.store'), [
            'customer_type' => 'booking',
            'customer_user_id' => $fixture['customer']->id,
            'table_id' => $fixture['table']->id,
            'selected_item_ids' => [$fixture['items'][0]->id],
            'discount_type' => 'nominal',
            'discount_value' => 10000,
            'reason' => 'Manager approval',
            'manager_auth_code' => $code,
        ])->assertCreated();

    $changedCart = $fixture['cart'];
    $changedCart['item_'.$fixture['items'][0]->id]['quantity'] = 2;

    actingAs($fixture['cashier'])
        ->withSession(['pos_cart' => $changedCart])
        ->postJson(route('admin.pos.checkout'), [
            'customer_type' => 'booking',
            'customer_user_id' => $fixture['customer']->id,
            'table_id' => $fixture['table']->id,
            'discount_percentage' => 0,
            'discount_approval_token' => $approval->json('approval_token'),
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Cart berubah setelah approval. Ajukan approval ulang.');

    expect(Order::query()->where('table_session_id', $fixture['session']->id)->count())->toBe(0)
        ->and(PosDiscountApproval::query()->firstOrFail()->consumed_at)->toBeNull();
});

test('walk in selected item discount is sent to accurate sales order and invoice', function () {
    GeneralSetting::instance()->update(['tax_percentage' => 0, 'service_charge_percentage' => 0]);
    $fixture = selectedDiscountFixture();
    $customerUser = CustomerUser::query()->where('user_id', $fixture['customer']->id)->firstOrFail();
    $customerUser->update(['accurate_id' => 12345, 'customer_code' => 'CUST-DISCOUNT']);
    $code = DailyAuthCode::forDate(now()->format('Y-m-d'))->active_code;
    $payloads = [];

    mock(AccurateService::class, function (MockInterface $mock) use (&$payloads): void {
        $mock->shouldReceive('getItemGroupComponents')->andReturn([]);
        $mock->shouldReceive('saveSalesOrder')->once()->withArgs(function (array $payload) use (&$payloads): bool {
            $payloads['sales_order'] = $payload;

            return true;
        })->andReturn(['r' => ['number' => 'SO-DISCOUNT']]);
        $mock->shouldReceive('saveSalesInvoice')->once()->withArgs(function (array $payload) use (&$payloads): bool {
            $payloads['sales_invoice'] = $payload;

            return true;
        })->andReturn(['r' => ['number' => 'INV-DISCOUNT']]);
    });

    $approval = actingAs($fixture['cashier'])
        ->withSession([
            'pos_cart' => $fixture['cart'],
            'pos_discount_auth_code_requested_at' => now()->timestamp,
        ])
        ->postJson(route('admin.pos.discount-approvals.store'), [
            'customer_type' => 'walk-in',
            'walk_in_customer_id' => $fixture['customer']->id,
            'selected_item_ids' => [$fixture['items'][0]->id],
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'reason' => 'Service recovery',
            'manager_auth_code' => $code,
        ])->assertCreated();

    actingAs($fixture['cashier'])
        ->withSession(['pos_cart' => $fixture['cart']])
        ->postJson(route('admin.pos.checkout'), [
            'customer_type' => 'walk-in',
            'walk_in_customer_id' => $fixture['customer']->id,
            'payment_mode' => 'normal',
            'payment_method' => 'cash',
            'discount_type' => 'none',
            'discount_approval_token' => $approval->json('approval_token'),
            'auto_print_receipt' => false,
        ])->assertSuccessful();

    expect($payloads['sales_order']['detailItem'])->toHaveCount(2)
        ->and($payloads['sales_order']['detailItem'][0]['discountPercent'])->toBe(10.0)
        ->and($payloads['sales_order']['detailItem'][1]['discountPercent'])->toBe(0.0)
        ->and($payloads['sales_invoice']['detailItem'][0]['discountPercent'])->toBe(10.0)
        ->and($payloads['sales_invoice']['detailItem'][1]['discountPercent'])->toBe(0.0);
});

test('selected item discount rejects direct auth code without requesting it first', function () {
    $fixture = selectedDiscountFixture();
    $code = DailyAuthCode::forDate(now()->format('Y-m-d'))->active_code;

    actingAs($fixture['cashier'])
        ->withSession(['pos_cart' => $fixture['cart']])
        ->postJson(route('admin.pos.discount-approvals.store'), [
            'customer_type' => 'booking',
            'customer_user_id' => $fixture['customer']->id,
            'table_id' => $fixture['table']->id,
            'selected_item_ids' => [$fixture['items'][0]->id],
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'reason' => 'Service recovery',
            'manager_auth_code' => $code,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.manager_auth_code.0', 'Request auth code terlebih dahulu sebelum mengajukan diskon.');

    expect(PosDiscountApproval::query()->count())->toBe(0);
});
