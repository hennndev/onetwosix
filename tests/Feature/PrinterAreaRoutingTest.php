<?php

use App\Models\Area;
use App\Models\InternalUser;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Printer;
use App\Models\Tabel;
use App\Services\PrinterService;
use Mockery\MockInterface;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

test('order ticket routing prioritizes printer matching user assigned area', function () {
    $loungeArea = Area::create([
        'code' => 'LNG',
        'name' => 'Lounge',
        'capacity' => 20,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $roomArea = Area::create([
        'code' => 'ROOM',
        'name' => 'Room',
        'capacity' => 10,
        'is_active' => true,
        'sort_order' => 2,
    ]);

    $loungeUser = adminUser();
    $profile = \App\Models\UserProfile::create([
        'user_id' => $loungeUser->id,
        'full_name' => 'Lounge Waiter',
        'is_active' => true,
    ]);
    InternalUser::updateOrCreate(
        ['user_id' => $loungeUser->id],
        ['user_profile_id' => $profile->id, 'area_id' => $loungeArea->id, 'is_active' => true]
    );

    $table = Tabel::create([
        'area_id' => $loungeArea->id,
        'table_number' => 'T-LNG-01',
        'qr_code' => 'QR-LNG-'.uniqid(),
        'capacity' => 4,
        'status' => 'occupied',
        'is_active' => true,
    ]);

    $loungePrinter = Printer::create([
        'name' => 'Bar Printer Lounge',
        'location' => 'LNG',
        'area_id' => $loungeArea->id,
        'printer_type' => 'bar',
        'connection_type' => 'log',
        'port' => 9100,
        'timeout' => 30,
        'is_active' => true,
    ]);

    $roomPrinter = Printer::create([
        'name' => 'Bar Printer Room',
        'location' => 'ROOM',
        'area_id' => $roomArea->id,
        'printer_type' => 'bar',
        'connection_type' => 'log',
        'port' => 9100,
        'timeout' => 30,
        'is_active' => true,
    ]);

    $inventoryItem = InventoryItem::create([
        'code' => 'DRINK-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Whisky Bottle',
        'category_type' => 'beverage',
        'price' => 500000,
        'stock_quantity' => 50,
        'threshold' => 5,
        'unit' => 'bottle',
        'is_active' => true,
    ]);

    $inventoryItem->printers()->attach([$loungePrinter->id, $roomPrinter->id]);

    $order = Order::create([
        'table_session_id' => null,
        'customer_user_id' => null,
        'created_by' => $loungeUser->id,
        'order_number' => 'ORD-LNG-'.uniqid(),
        'status' => 'pending',
        'items_total' => 500000,
        'discount_amount' => 0,
        'total' => 500000,
        'ordered_at' => now(),
    ]);

    $order->items()->create([
        'inventory_item_id' => $inventoryItem->id,
        'item_code' => $inventoryItem->code,
        'item_name' => $inventoryItem->name,
        'quantity' => 1,
        'price' => 500000,
        'subtotal' => 500000,
    ]);

    mock(PrinterService::class, function (MockInterface $mock) use ($loungePrinter): void {
        $mock->shouldReceive('printBarTicket')
            ->once()
            ->withArgs(fn ($barOrderArg, Printer $printerArg): bool => (int) $printerArg->id === (int) $loungePrinter->id)
            ->andReturnTrue();
    });

    actingAs($loungeUser);

    // Call checkoutWalkIn or routeOrderToPreparation via PosController
    $controller = app(\App\Http\Controllers\PosController::class);
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('routeOrderToPreparation');
    $method->setAccessible(true);

    $method->invoke($controller, $order, null, $order->order_number, null, null);
});

test('menu item with cashier target printer does not resolve to kitchen preparation location or create kitchen order', function () {
    $cashierPrinter = Printer::create([
        'name' => 'Kasir Printer',
        'location' => 'cashier',
        'printer_type' => 'cashier',
        'connection_type' => 'log',
        'is_active' => true,
    ]);

    $inventoryItem = InventoryItem::create([
        'code' => 'CIGAR-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'Cigarette Pack',
        'category_type' => 'food',
        'price' => 50000,
        'stock_quantity' => 100,
        'threshold' => 5,
        'unit' => 'pack',
        'is_active' => true,
    ]);

    $inventoryItem->printers()->attach([$cashierPrinter->id]);

    $user = adminUser();
    actingAs($user);

    $controller = app(\App\Http\Controllers\PosController::class);
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('resolvePreparationLocationFromPrinters');
    $method->setAccessible(true);

    $location = $method->invoke($controller, $inventoryItem);
    expect($location)->toBeNull();

    $order = Order::create([
        'created_by' => $user->id,
        'order_number' => 'ORD-CSH-'.uniqid(),
        'status' => 'pending',
        'items_total' => 50000,
        'discount_amount' => 0,
        'total' => 50000,
        'ordered_at' => now(),
    ]);

    $order->items()->create([
        'inventory_item_id' => $inventoryItem->id,
        'item_code' => $inventoryItem->code,
        'item_name' => $inventoryItem->name,
        'quantity' => 1,
        'price' => 50000,
        'subtotal' => 50000,
        'preparation_location' => $location,
    ]);

    $syncMethod = $reflection->getMethod('routeOrderToPreparation');
    $syncMethod->setAccessible(true);
    $syncMethod->invoke($controller, $order, null, $order->order_number, null, null);

    expect(\App\Models\KitchenOrder::where('order_id', $order->id)->exists())->toBeFalse();
});
