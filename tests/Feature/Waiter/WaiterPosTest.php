<?php

use App\Http\Controllers\Waiter\WaiterPosController;
use App\Models\Area;
use App\Models\BarOrder;
use App\Models\CustomerUser;
use App\Models\GeneralSetting;
use App\Models\InventoryItem;
use App\Models\KitchenOrder;
use App\Models\KitchenOrderItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosCategorySetting;
use App\Models\Printer;
use App\Models\Tabel;
use App\Models\TableReservation;
use App\Models\TableSession;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\AccurateService;
use App\Services\PrinterService;
use Mockery\MockInterface;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

function posWaiter(string $name = 'Waiter'): User
{
    Role::firstOrCreate(['name' => 'Waiter/Server', 'guard_name' => 'web']);
    $user = User::factory()->create(['name' => $name]);
    $user->assignRole('Waiter/Server');

    return $user;
}

function posArea(): Area
{
    return Area::create([
        'code' => 'POS-'.uniqid(),
        'name' => 'POS Area '.uniqid(),
        'capacity' => 20,
        'is_active' => true,
        'sort_order' => 1,
    ]);
}

function posTable(Area $area, string $number): Tabel
{
    return Tabel::create([
        'area_id' => $area->id,
        'table_number' => $number,
        'qr_code' => 'QR-POS-'.$number.'-'.uniqid(),
        'capacity' => 4,
        'minimum_charge' => 0,
        'status' => 'occupied',
        'is_active' => true,
    ]);
}

function posSession(Tabel $table, User $customer, User $waiter, bool $asBooking = true): TableSession
{
    $reservationId = null;

    if ($asBooking) {
        $reservation = TableReservation::create([
            'booking_code' => random_int(100000, 999999),
            'table_id' => $table->id,
            'customer_id' => $customer->id,
            'reservation_date' => today(),
            'reservation_time' => now()->format('H:i:s'),
            'status' => 'checked_in',
        ]);

        $reservationId = $reservation->id;
    }

    return TableSession::create([
        'table_reservation_id' => $reservationId,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'waiter_id' => $waiter->id,
        'session_code' => 'SES-POS-'.uniqid(),
        'checked_in_at' => now(),
        'status' => 'active',
    ]);
}

function posProduct(string $categoryType = 'food'): InventoryItem
{
    PosCategorySetting::clearCache();

    PosCategorySetting::firstOrCreate(
        ['category_type' => $categoryType],
        [
            'show_in_pos' => true,
            'is_menu' => false,
            'preparation_location' => $categoryType === 'food' ? 'kitchen' : 'bar',
            'source' => 'inventory',
        ]
    );

    PosCategorySetting::clearCache();

    return InventoryItem::create([
        'name' => 'Test '.ucfirst($categoryType).' Item '.uniqid(),
        'code' => 'TST-'.uniqid(),
        'accurate_id' => 'ACC-'.uniqid(),
        'category_type' => $categoryType,
        'price' => 50000,
        'stock_quantity' => 99,
        'is_active' => true,
    ]);
}

beforeEach(function () {
    PosCategorySetting::clearCache();
});

test('waiter can add a product to cart via waiter route', function () {
    $waiter = posWaiter();
    $product = posProduct('food');
    $productId = 'item_'.$product->id;

    actingAs($waiter)
        ->withSession(['accurate_database' => 'test'])
        ->post(route('waiter.pos.add-to-cart', $productId))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('cart.'.$productId.'.qty', 1);
});

test('waiter add to cart uses inventory pos_name for cart display when available', function () {
    $waiter = posWaiter();
    $product = posProduct('food');
    $product->update(['pos_name' => 'POS Waiter Name']);
    $productId = 'item_'.$product->id;

    actingAs($waiter)
        ->withSession(['accurate_database' => 'test'])
        ->post(route('waiter.pos.add-to-cart', $productId))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('cart.'.$productId.'.name', 'POS Waiter Name');
});

test('waiter checkout creates order for their own session and clears cart', function () {
    $waiter = posWaiter();
    $customer = User::factory()->create();
    $area = posArea();
    $table = posTable($area, 'P-01');
    $session = posSession($table, $customer, $waiter);
    $product = posProduct('food');
    $productId = 'item_'.$product->id;

    $cartData = [
        $productId => [
            'id' => $productId,
            'name' => $product->name,
            'price' => 50000.00,
            'quantity' => 2,
            'preparation_location' => 'kitchen',
        ],
    ];

    $response = actingAs($waiter)
        ->withSession([
            'accurate_database' => 'test',
            WaiterPosController::CART_KEY => $cartData,
        ])
        ->post(route('waiter.pos.checkout'), ['session_id' => $session->id])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect((string) $response->json('order_number'))->toMatch('/^ORD-\d{8}-\d{4}$/');

    expect(Order::where('table_session_id', $session->id)->exists())->toBeTrue();
    expect($product->fresh()->stock_quantity)->toBe(97);
});

test('waiter checkout persists cart item note to order and kitchen order items', function () {
    $waiter = posWaiter();
    $customer = User::factory()->create();
    $area = posArea();
    $table = posTable($area, 'P-NOTE');
    $session = posSession($table, $customer, $waiter);
    $product = posProduct('food');
    $kitchenPrinter = Printer::create([
        'name' => 'Waiter Note Kitchen',
        'location' => 'kitchen',
        'connection_type' => 'log',
        'port' => 9100,
        'timeout' => 30,
        'header' => '126 Club',
        'footer' => 'Thank you',
        'width' => 42,
        'is_active' => true,
    ]);
    $product->printers()->sync([$kitchenPrinter->id]);
    $productId = 'item_'.$product->id;
    $itemNote = 'No ice, extra spicy';

    actingAs($waiter)
        ->withSession([
            'accurate_database' => 'test',
            WaiterPosController::CART_KEY => [
                $productId => [
                    'id' => $productId,
                    'name' => $product->name,
                    'price' => 50000.00,
                    'quantity' => 1,
                    'preparation_location' => 'kitchen',
                    'notes' => $itemNote,
                ],
            ],
        ])
        ->post(route('waiter.pos.checkout'), ['session_id' => $session->id])
        ->assertOk()
        ->assertJsonPath('success', true);

    $order = Order::query()->where('table_session_id', $session->id)->latest('id')->first();
    $orderItem = OrderItem::query()->where('order_id', $order?->id)->latest('id')->first();
    $kitchenOrder = KitchenOrder::query()->where('order_id', $order?->id)->latest('id')->first();
    $kitchenOrderItem = KitchenOrderItem::query()->where('kitchen_order_id', $kitchenOrder?->id)->latest('id')->first();

    expect($order)->not->toBeNull()
        ->and($orderItem)->not->toBeNull()
        ->and($kitchenOrder)->not->toBeNull()
        ->and($kitchenOrderItem)->not->toBeNull()
        ->and($orderItem?->notes)->toBe($itemNote)
        ->and($kitchenOrderItem?->notes)->toBe($itemNote);
});

test('waiter checkout links kitchen order to customer user for booking session', function () {
    $waiter = posWaiter();
    $customer = User::factory()->create();
    $profile = UserProfile::create([
        'user_id' => $customer->id,
        'phone' => '081234567890',
    ]);
    $customerUser = CustomerUser::create([
        'user_id' => $customer->id,
        'user_profile_id' => $profile->id,
        'total_visits' => 0,
        'lifetime_spending' => 0,
    ]);

    $area = posArea();
    $table = posTable($area, 'P-11');
    $session = posSession($table, $customer, $waiter);
    $product = posProduct('food');
    $kitchenPrinter = Printer::create([
        'name' => 'Waiter Kitchen Link Test',
        'location' => 'kitchen',
        'connection_type' => 'log',
        'port' => 9100,
        'timeout' => 30,
        'header' => '126 Club',
        'footer' => 'Thank you',
        'width' => 42,
        'is_active' => true,
    ]);
    $product->printers()->sync([$kitchenPrinter->id]);
    $productId = 'item_'.$product->id;

    actingAs($waiter)
        ->withSession([
            'accurate_database' => 'test',
            WaiterPosController::CART_KEY => [
                $productId => [
                    'id' => $productId,
                    'name' => $product->name,
                    'price' => 50000,
                    'quantity' => 1,
                    'preparation_location' => 'kitchen',
                ],
            ],
        ])
        ->post(route('waiter.pos.checkout'), ['session_id' => $session->id])
        ->assertOk()
        ->assertJsonPath('success', true);

    $kitchenOrder = KitchenOrder::query()->latest('id')->first();

    expect($kitchenOrder)->not->toBeNull()
        ->and((int) $kitchenOrder->customer_user_id)->toBe((int) $customerUser->id);
});

test('waiter checkout auto prints one menu to multiple assigned target printers', function () {
    $waiter = posWaiter();
    $customer = User::factory()->create();
    $area = posArea();
    $table = posTable($area, 'P-21');
    $session = posSession($table, $customer, $waiter);

    $targetPrinterOne = Printer::create([
        'name' => 'Waiter Kitchen A',
        'location' => 'kitchen',
        'connection_type' => 'log',
        'port' => 9100,
        'timeout' => 30,
        'header' => '126 Club',
        'footer' => 'Thank you',
        'width' => 42,
        'is_active' => true,
    ]);

    $targetPrinterTwo = Printer::create([
        'name' => 'Waiter Kitchen B',
        'location' => 'kitchen',
        'connection_type' => 'log',
        'port' => 9100,
        'timeout' => 30,
        'header' => '126 Club',
        'footer' => 'Thank you',
        'width' => 42,
        'is_active' => true,
    ]);

    PosCategorySetting::updateOrCreate(
        ['category_type' => 'food'],
        [
            'show_in_pos' => true,
            'is_menu' => true,
            'is_item_group' => false,
            'preparation_location' => 'kitchen',
            'source' => 'inventory',
        ]
    );
    PosCategorySetting::clearCache();

    $menuItem = InventoryItem::create([
        'name' => 'Waiter Multi Printer Menu',
        'code' => 'MENU-WAITER-MULTI',
        'accurate_id' => 55001,
        'category_type' => 'food',
        'price' => 35000,
        'stock_quantity' => 100,
        'is_active' => true,
    ]);

    $menuItem->printers()->sync([$targetPrinterOne->id, $targetPrinterTwo->id]);

    mock(PrinterService::class, function (MockInterface $mock) use ($targetPrinterOne, $targetPrinterTwo): void {
        $mock->shouldReceive('printKitchenTicket')
            ->twice()
            ->withArgs(function ($order, $printer) use ($targetPrinterOne, $targetPrinterTwo): bool {
                if (! in_array($printer->id, [$targetPrinterOne->id, $targetPrinterTwo->id], true)) {
                    return false;
                }

                return (int) ($order->items->count() ?? 0) === 1;
            })
            ->andReturnTrue();

        $mock->shouldReceive('printBarTicket')->never();
    });

    $productId = 'item_'.$menuItem->id;

    actingAs($waiter)
        ->withSession([
            'accurate_database' => 'test',
            WaiterPosController::CART_KEY => [
                $productId => [
                    'id' => $productId,
                    'name' => $menuItem->name,
                    'price' => 35000,
                    'quantity' => 1,
                    'preparation_location' => 'kitchen',
                ],
            ],
        ])
        ->post(route('waiter.pos.checkout'), ['session_id' => $session->id])
        ->assertOk()
        ->assertJsonPath('success', true);
});

test('waiter checkout with checker-only item does not create kitchen or bar orders', function () {
    $waiter = posWaiter();
    $customer = User::factory()->create();
    $area = posArea();
    $table = posTable($area, 'P-CHK-ONLY');
    $session = posSession($table, $customer, $waiter);

    PosCategorySetting::updateOrCreate(
        ['category_type' => 'food'],
        [
            'show_in_pos' => true,
            'is_menu' => true,
            'is_item_group' => false,
            'preparation_location' => 'kitchen',
            'source' => 'inventory',
        ]
    );
    PosCategorySetting::clearCache();

    $checkerPrinter = Printer::create([
        'name' => 'Waiter Checker Only',
        'location' => 'checker',
        'printer_type' => 'checker',
        'connection_type' => 'log',
        'port' => 9100,
        'timeout' => 30,
        'header' => '126 Club',
        'footer' => 'Thank you',
        'width' => 42,
        'is_active' => true,
    ]);

    $menuItem = InventoryItem::create([
        'name' => 'Waiter Checker Only Menu',
        'code' => 'MENU-WAITER-CHECKER-ONLY-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'category_type' => 'food',
        'price' => 35000,
        'stock_quantity' => 100,
        'is_active' => true,
    ]);

    $menuItem->printers()->sync([$checkerPrinter->id]);

    mock(PrinterService::class, function (MockInterface $mock) use ($checkerPrinter): void {
        $mock->shouldReceive('printCheckerTicket')
            ->once()
            ->withArgs(function ($order, $printer) use ($checkerPrinter): bool {
                return (int) $printer->id === (int) $checkerPrinter->id
                    && (int) ($order->items->count() ?? 0) === 1;
            })
            ->andReturnTrue();

        $mock->shouldReceive('printKitchenTicket')->never();
        $mock->shouldReceive('printBarTicket')->never();
        $mock->shouldReceive('printCashierTicket')->never();
    });

    $productId = 'item_'.$menuItem->id;

    actingAs($waiter)
        ->withSession([
            'accurate_database' => 'test',
            WaiterPosController::CART_KEY => [
                $productId => [
                    'id' => $productId,
                    'name' => $menuItem->name,
                    'price' => 35000,
                    'quantity' => 1,
                    'preparation_location' => 'kitchen',
                ],
            ],
        ])
        ->post(route('waiter.pos.checkout'), ['session_id' => $session->id])
        ->assertOk()
        ->assertJsonPath('success', true);

    $order = Order::query()->where('table_session_id', $session->id)->latest('id')->first();

    expect($order)->not->toBeNull()
        ->and(KitchenOrder::query()->where('order_id', $order->id)->exists())->toBeFalse()
        ->and(BarOrder::query()->where('order_id', $order->id)->exists())->toBeFalse();
});

test('waiter checkout requires selected checker printers when can_choose_checker is enabled and multiple checker printers are assigned', function () {
    GeneralSetting::instance()->update(['can_choose_checker' => true]);

    $waiter = posWaiter();
    $customer = User::factory()->create();
    $area = posArea();
    $table = posTable($area, 'P-CHK-REQ');
    $session = posSession($table, $customer, $waiter);

    PosCategorySetting::updateOrCreate(
        ['category_type' => 'food'],
        [
            'show_in_pos' => true,
            'is_menu' => true,
            'is_item_group' => false,
            'preparation_location' => 'kitchen',
            'source' => 'inventory',
        ]
    );
    PosCategorySetting::clearCache();

    $checkerPrinterOne = Printer::create([
        'name' => 'Waiter Checker Required 1',
        'location' => 'checker',
        'printer_type' => 'checker',
        'connection_type' => 'log',
        'port' => 9100,
        'timeout' => 30,
        'header' => '126 Club',
        'footer' => 'Thank you',
        'width' => 42,
        'is_active' => true,
    ]);

    $checkerPrinterTwo = Printer::create([
        'name' => 'Waiter Checker Required 2',
        'location' => 'checker',
        'printer_type' => 'checker',
        'connection_type' => 'log',
        'port' => 9100,
        'timeout' => 30,
        'header' => '126 Club',
        'footer' => 'Thank you',
        'width' => 42,
        'is_active' => true,
    ]);

    $menuItem = InventoryItem::create([
        'name' => 'Waiter Checker Required Menu',
        'code' => 'MENU-WAITER-CHECKER-REQ-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'category_type' => 'food',
        'price' => 35000,
        'stock_quantity' => 100,
        'is_active' => true,
    ]);

    $menuItem->printers()->sync([$checkerPrinterOne->id, $checkerPrinterTwo->id]);

    $productId = 'item_'.$menuItem->id;

    actingAs($waiter)
        ->withSession([
            'accurate_database' => 'test',
            WaiterPosController::CART_KEY => [
                $productId => [
                    'id' => $productId,
                    'name' => $menuItem->name,
                    'price' => 35000,
                    'quantity' => 1,
                    'preparation_location' => 'kitchen',
                    'assigned_checker_printer_ids' => [$checkerPrinterOne->id, $checkerPrinterTwo->id],
                ],
            ],
        ])
        ->post(route('waiter.pos.checkout'), ['session_id' => $session->id])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Pilih minimal satu printer checker.');
});

test('waiter checkout prints checker ticket only to selected checker printers when can_choose_checker is enabled', function () {
    GeneralSetting::instance()->update(['can_choose_checker' => true]);

    $waiter = posWaiter();
    $customer = User::factory()->create();
    $area = posArea();
    $table = posTable($area, 'P-CHK-SEL');
    $session = posSession($table, $customer, $waiter);

    PosCategorySetting::updateOrCreate(
        ['category_type' => 'food'],
        [
            'show_in_pos' => true,
            'is_menu' => true,
            'is_item_group' => false,
            'preparation_location' => 'kitchen',
            'source' => 'inventory',
        ]
    );
    PosCategorySetting::clearCache();

    $checkerPrinterOne = Printer::create([
        'name' => 'Waiter Checker Selected 1',
        'location' => 'checker',
        'printer_type' => 'checker',
        'connection_type' => 'log',
        'port' => 9100,
        'timeout' => 30,
        'header' => '126 Club',
        'footer' => 'Thank you',
        'width' => 42,
        'is_active' => true,
    ]);

    $checkerPrinterTwo = Printer::create([
        'name' => 'Waiter Checker Selected 2',
        'location' => 'checker',
        'printer_type' => 'checker',
        'connection_type' => 'log',
        'port' => 9100,
        'timeout' => 30,
        'header' => '126 Club',
        'footer' => 'Thank you',
        'width' => 42,
        'is_active' => true,
    ]);

    $menuItem = InventoryItem::create([
        'name' => 'Waiter Checker Selected Menu',
        'code' => 'MENU-WAITER-CHECKER-SEL-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'category_type' => 'food',
        'price' => 35000,
        'stock_quantity' => 100,
        'is_active' => true,
    ]);

    $menuItem->printers()->sync([$checkerPrinterOne->id, $checkerPrinterTwo->id]);

    mock(PrinterService::class, function (MockInterface $mock) use ($checkerPrinterTwo): void {
        $mock->shouldReceive('printCheckerTicket')
            ->once()
            ->withArgs(function ($order, $printer) use ($checkerPrinterTwo): bool {
                return (int) $printer->id === (int) $checkerPrinterTwo->id
                    && (int) ($order->items->count() ?? 0) === 1;
            })
            ->andReturnTrue();

        $mock->shouldReceive('printKitchenTicket')->never();
        $mock->shouldReceive('printBarTicket')->never();
        $mock->shouldReceive('printCashierTicket')->never();
    });

    $productId = 'item_'.$menuItem->id;

    actingAs($waiter)
        ->withSession([
            'accurate_database' => 'test',
            WaiterPosController::CART_KEY => [
                $productId => [
                    'id' => $productId,
                    'name' => $menuItem->name,
                    'price' => 35000,
                    'quantity' => 1,
                    'preparation_location' => 'kitchen',
                    'assigned_checker_printer_ids' => [$checkerPrinterOne->id, $checkerPrinterTwo->id],
                ],
            ],
        ])
        ->post(route('waiter.pos.checkout'), [
            'session_id' => $session->id,
            'checker_printer_ids' => [$checkerPrinterTwo->id],
        ])
        ->assertOk()
        ->assertJsonPath('success', true);
});

test('waiter cannot checkout for a session belonging to another waiter', function () {
    $waiterA = posWaiter('Waiter A');
    $waiterB = posWaiter('Waiter B');
    $customer = User::factory()->create();
    $area = posArea();
    $table = posTable($area, 'P-02');
    $sessionB = posSession($table, $customer, $waiterB);
    $product = posProduct('food');
    $productId = 'item_'.$product->id;

    $cartData = [
        $productId => [
            'id' => $productId,
            'name' => $product->name,
            'price' => 50000.00,
            'quantity' => 1,
            'preparation_location' => 'kitchen',
        ],
    ];

    actingAs($waiterA)
        ->withSession([
            'accurate_database' => 'test',
            WaiterPosController::CART_KEY => $cartData,
        ])
        ->post(route('waiter.pos.checkout'), ['session_id' => $sessionB->id])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('waiter checkout keeps printing to other assigned printers when one target printer fails', function () {
    $waiter = posWaiter();
    $customer = User::factory()->create();
    $area = posArea();
    $table = posTable($area, 'P-FAILOVER');
    $session = posSession($table, $customer, $waiter);

    $networkPrinter = Printer::create([
        'name' => 'Waiter Kitchen Network Fail',
        'location' => 'kitchen',
        'connection_type' => 'network',
        'ip' => '10.10.10.10',
        'port' => 9100,
        'timeout' => 30,
        'header' => '126 Club',
        'footer' => 'Thank you',
        'width' => 42,
        'is_active' => true,
    ]);

    $logPrinter = Printer::create([
        'name' => 'Waiter Kitchen Log Success',
        'location' => 'kitchen',
        'connection_type' => 'log',
        'port' => 9100,
        'timeout' => 30,
        'header' => '126 Club',
        'footer' => 'Thank you',
        'width' => 42,
        'is_active' => true,
    ]);

    PosCategorySetting::updateOrCreate(
        ['category_type' => 'food'],
        [
            'show_in_pos' => true,
            'is_menu' => true,
            'is_item_group' => false,
            'preparation_location' => 'kitchen',
            'source' => 'inventory',
        ]
    );
    PosCategorySetting::clearCache();

    $menuItem = InventoryItem::create([
        'name' => 'Waiter Multi Printer Failover Menu',
        'code' => 'MENU-WAITER-FAILOVER',
        'accurate_id' => 55002,
        'category_type' => 'food',
        'price' => 35000,
        'stock_quantity' => 100,
        'is_active' => true,
    ]);

    $menuItem->printers()->sync([$networkPrinter->id, $logPrinter->id]);

    mock(PrinterService::class, function (MockInterface $mock) use ($networkPrinter, $logPrinter): void {
        $mock->shouldReceive('printKitchenTicket')
            ->twice()
            ->andReturnUsing(function ($order, $printer) use ($networkPrinter, $logPrinter): bool {
                expect((int) ($order->items->count() ?? 0))->toBe(1);

                if ($printer->id === $networkPrinter->id) {
                    throw new RuntimeException('Network printer unreachable');
                }

                expect($printer->id)->toBe($logPrinter->id);

                return true;
            });

        $mock->shouldReceive('printBarTicket')->never();
    });

    $productId = 'item_'.$menuItem->id;

    actingAs($waiter)
        ->withSession([
            'accurate_database' => 'test',
            WaiterPosController::CART_KEY => [
                $productId => [
                    'id' => $productId,
                    'name' => $menuItem->name,
                    'price' => 35000,
                    'quantity' => 1,
                    'preparation_location' => 'kitchen',
                ],
            ],
        ])
        ->post(route('waiter.pos.checkout'), ['session_id' => $session->id])
        ->assertOk()
        ->assertJsonPath('success', true);
});

test('waiter cannot checkout non booking session even if assigned', function () {
    $waiter = posWaiter();
    $customer = User::factory()->create();
    $area = posArea();
    $table = posTable($area, 'P-04');
    $nonBookingSession = posSession($table, $customer, $waiter, false);
    $product = posProduct('food');
    $productId = 'item_'.$product->id;

    actingAs($waiter)
        ->withSession([
            'accurate_database' => 'test',
            WaiterPosController::CART_KEY => [
                $productId => [
                    'id' => $productId,
                    'name' => $product->name,
                    'price' => 50000,
                    'quantity' => 1,
                    'preparation_location' => 'kitchen',
                ],
            ],
        ])
        ->post(route('waiter.pos.checkout'), ['session_id' => $nonBookingSession->id])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('waiter pos page lists products from pos category settings including custom types', function () {
    $waiter = posWaiter();
    $customer = User::factory()->create();
    $area = posArea();
    $table = posTable($area, 'P-03');
    posSession($table, $customer, $waiter);

    $customCategory = 'snack_special';
    $product = posProduct($customCategory);

    $response = actingAs($waiter)
        ->withSession(['accurate_database' => 'test'])
        ->get(route('waiter.pos'))
        ->assertOk();

    $products = collect($response->viewData('products'));

    expect($products->pluck('id')->all())->toContain('item_'.$product->id)
        ->and($products->where('id', 'item_'.$product->id)->first()['category'])->toBe($customCategory);
});

test('waiter pos page uses inventory pos_name as product display name', function () {
    $waiter = posWaiter();
    $customer = User::factory()->create();
    $area = posArea();
    $table = posTable($area, 'P-03-NAME');
    posSession($table, $customer, $waiter);

    $product = posProduct('food');
    $product->update([
        'name' => 'Inventory Base Name',
        'pos_name' => 'POS Display Name',
    ]);

    $response = actingAs($waiter)
        ->withSession(['accurate_database' => 'test'])
        ->get(route('waiter.pos'))
        ->assertOk();

    $products = collect($response->viewData('products'));
    $productPayload = $products->firstWhere('id', 'item_'.$product->id);

    expect($productPayload)->not->toBeNull()
        ->and($productPayload['name'])->toBe('POS Display Name');
});

test('waiter checkout keeps FOC billed and makes only Compliment free', function (string $categoryMain, float $expectedPrice) {
    $waiter = posWaiter();
    $customer = User::factory()->create();
    $area = posArea();
    $table = posTable($area, 'P-FREE-'.strtoupper($categoryMain));
    $session = posSession($table, $customer, $waiter);
    $product = posProduct('food');
    $product->update([
        'category_main' => $categoryMain,
        'price' => 50000,
        'stock_quantity' => 10,
    ]);
    $productId = 'item_'.$product->id;

    $page = actingAs($waiter)
        ->withSession(['accurate_database' => 'test'])
        ->get(route('waiter.pos'))
        ->assertOk();

    expect((float) collect($page->viewData('products'))->firstWhere('id', $productId)['price'])->toBe($expectedPrice);

    actingAs($waiter)
        ->withSession([
            'accurate_database' => 'test',
            WaiterPosController::CART_KEY => [
                $productId => [
                    'id' => $productId,
                    'name' => $product->name,
                    'price' => 50000,
                    'quantity' => 2,
                    'preparation_location' => 'kitchen',
                ],
            ],
        ])
        ->post(route('waiter.pos.checkout'), ['session_id' => $session->id])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $order = Order::query()->where('table_session_id', $session->id)->latest('id')->firstOrFail();
    $orderItem = $order->items()->firstOrFail();

    expect((float) $orderItem->price)->toBe($expectedPrice)
        ->and((float) $orderItem->subtotal)->toBe($expectedPrice * 2)
        ->and((float) $order->total)->toBe($expectedPrice * 2)
        ->and($product->fresh()->stock_quantity)->toBe(8);
})->with([
    'FOC remains billed' => ['foc', 50000.0],
    'Compliment is free' => ['compliment', 0.0],
]);

test('waiter pos page hides inventory items marked invisible in pos', function () {
    $waiter = posWaiter();
    $customer = User::factory()->create();
    $area = posArea();
    $table = posTable($area, 'P-03-HIDDEN');
    posSession($table, $customer, $waiter);

    $product = posProduct('food');
    $product->update([
        'name' => 'Waiter Hidden Product',
        'pos_name' => 'Waiter Hidden POS',
        'is_visible_in_pos' => false,
    ]);

    $response = actingAs($waiter)
        ->withSession(['accurate_database' => 'test'])
        ->get(route('waiter.pos'))
        ->assertOk();

    $products = collect($response->viewData('products'));

    expect($products->pluck('id'))->not->toContain('item_'.$product->id);
});

test('waiter add to cart rejects inventory items hidden from pos', function () {
    $waiter = posWaiter();
    $customer = User::factory()->create();
    $area = posArea();
    $table = posTable($area, 'P-03-CART-HIDDEN');
    posSession($table, $customer, $waiter);

    $product = posProduct('food');
    $product->update([
        'is_visible_in_pos' => false,
    ]);

    actingAs($waiter)
        ->withSession(['accurate_database' => 'test'])
        ->post(route('waiter.pos.add-to-cart', 'item_'.$product->id))
        ->assertStatus(404)
        ->assertJsonPath('success', false);
});

test('waiter add to cart allows item group when ingredient portions are sufficient', function () {
    $waiter = posWaiter();

    PosCategorySetting::updateOrCreate(
        ['category_type' => 'food'],
        [
            'show_in_pos' => true,
            'is_menu' => true,
            'is_item_group' => true,
            'preparation_location' => 'kitchen',
            'source' => 'inventory',
        ]
    );
    PosCategorySetting::clearCache();

    $menuItem = InventoryItem::create([
        'name' => 'Nasi Goreng Special',
        'code' => 'MENU-NG-001',
        'accurate_id' => 5001,
        'category_type' => 'food',
        'price' => 35000,
        'stock_quantity' => 0,
        'is_item_group' => true,
        'is_count_portion_possible' => true,
        'is_active' => true,
    ]);

    InventoryItem::create([
        'name' => 'Waiter Ingredient Group',
        'code' => 'ING-WAITER-GRP-001',
        'accurate_id' => 6001,
        'category_type' => 'ingredient',
        'price' => 1000,
        'stock_quantity' => 10,
        'is_active' => true,
    ]);

    mock(AccurateService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('getItemGroupComponents')
            ->once()
            ->with(5001)
            ->andReturn([
                [
                    'itemId' => 6001,
                    'quantity' => 2,
                ],
            ]);
    });

    actingAs($waiter)
        ->withSession(['accurate_database' => 'test'])
        ->post(route('waiter.pos.add-to-cart', 'item_'.$menuItem->id))
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('cart.item_'.$menuItem->id.'.qty', 1);
});

test('waiter add to cart allows detail group menu when sold item stock is zero', function () {
    $waiter = posWaiter();

    PosCategorySetting::updateOrCreate(
        ['category_type' => 'food'],
        [
            'show_in_pos' => true,
            'is_menu' => true,
            'is_item_group' => true,
            'preparation_location' => 'kitchen',
            'source' => 'inventory',
        ]
    );
    PosCategorySetting::clearCache();

    $menuItem = InventoryItem::create([
        'name' => 'Waiter Detail Group Menu',
        'code' => 'MENU-WAITER-DG-001',
        'accurate_id' => 5002,
        'category_type' => 'food',
        'price' => 35000,
        'stock_quantity' => 0,
        'is_item_group' => true,
        'is_count_portion_possible' => true,
        'is_active' => true,
    ]);

    InventoryItem::create([
        'name' => 'Waiter Ingredient',
        'code' => 'ING-WAITER-001',
        'accurate_id' => 6002,
        'category_type' => 'ingredient',
        'price' => 1000,
        'stock_quantity' => 10,
        'is_active' => true,
    ]);

    mock(AccurateService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('getItemGroupComponents')
            ->once()
            ->with(5002)
            ->andReturn([
                [
                    'itemId' => 6002,
                    'quantity' => 2,
                ],
            ]);
    });

    actingAs($waiter)
        ->withSession(['accurate_database' => 'test'])
        ->post(route('waiter.pos.add-to-cart', 'item_'.$menuItem->id))
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('cart.item_'.$menuItem->id.'.qty', 1);
});

test('waiter skips possible portions when is count portion possible is off and still allows add to cart', function () {
    $waiter = posWaiter();

    PosCategorySetting::updateOrCreate(
        ['category_type' => 'food'],
        [
            'show_in_pos' => true,
            'is_menu' => true,
            'is_item_group' => true,
            'preparation_location' => 'kitchen',
            'source' => 'inventory',
        ]
    );
    PosCategorySetting::clearCache();

    $menuItem = InventoryItem::create([
        'name' => 'Waiter No Count Portion',
        'code' => 'MENU-WAITER-NCP-001',
        'accurate_id' => 50022,
        'category_type' => 'food',
        'price' => 35000,
        'stock_quantity' => 0,
        'is_item_group' => true,
        'is_count_portion_possible' => false,
        'is_active' => true,
    ]);

    mock(AccurateService::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('getItemGroupComponents');
    });

    actingAs($waiter)
        ->withSession(['accurate_database' => 'test'])
        ->post(route('waiter.pos.add-to-cart', 'item_'.$menuItem->id))
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('cart.item_'.$menuItem->id.'.qty', 1);
});

test('waiter can add non group item when is count portion possible is off even if stock is zero', function () {
    $waiter = posWaiter();

    PosCategorySetting::updateOrCreate(
        ['category_type' => 'food'],
        [
            'show_in_pos' => true,
            'is_menu' => true,
            'is_item_group' => false,
            'preparation_location' => 'kitchen',
            'source' => 'inventory',
        ]
    );
    PosCategorySetting::clearCache();

    $menuItem = InventoryItem::create([
        'name' => 'Waiter Non Group No Count',
        'code' => 'MENU-WAITER-NGNCP-001',
        'accurate_id' => 50023,
        'category_type' => 'food',
        'price' => 35000,
        'stock_quantity' => 0,
        'is_item_group' => false,
        'is_count_portion_possible' => false,
        'is_active' => true,
    ]);

    mock(AccurateService::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('getItemGroupComponents');
    });

    actingAs($waiter)
        ->withSession(['accurate_database' => 'test'])
        ->post(route('waiter.pos.add-to-cart', 'item_'.$menuItem->id))
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('cart.item_'.$menuItem->id.'.qty', 1);
});

test('waiter add to cart allows non menu detail group item when sold item stock is zero', function () {
    $waiter = posWaiter();

    PosCategorySetting::updateOrCreate(
        ['category_type' => 'warehouse-group'],
        [
            'show_in_pos' => true,
            'is_menu' => false,
            'is_item_group' => true,
            'preparation_location' => 'bar',
            'source' => 'inventory',
        ]
    );
    PosCategorySetting::clearCache();

    $groupItem = InventoryItem::create([
        'name' => 'Waiter Warehouse Group',
        'code' => 'WAIT-GROUP-001',
        'accurate_id' => 5003,
        'category_type' => 'warehouse-group',
        'price' => 35000,
        'stock_quantity' => 0,
        'is_item_group' => true,
        'is_count_portion_possible' => true,
        'is_active' => true,
    ]);

    InventoryItem::create([
        'name' => 'Waiter Warehouse Ingredient',
        'code' => 'WAIT-ING-003',
        'accurate_id' => 6003,
        'category_type' => 'ingredient',
        'price' => 1000,
        'stock_quantity' => 10,
        'is_active' => true,
    ]);

    mock(AccurateService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('getItemGroupComponents')
            ->once()
            ->with(5003)
            ->andReturn([
                [
                    'itemId' => 6003,
                    'quantity' => 2,
                ],
            ]);
    });

    actingAs($waiter)
        ->withSession(['accurate_database' => 'test'])
        ->post(route('waiter.pos.add-to-cart', 'item_'.$groupItem->id))
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('cart.item_'.$groupItem->id.'.qty', 1);
});

