<?php

use App\Models\InventoryItem;
use App\Services\AccurateService;
use Mockery\MockInterface;

use function Pest\Laravel\mock;

test('accurate sync items saves detail group from list payload without detail fallback', function () {
    config(['accurate.api_token' => 'dummy-token']);
    \App\Models\GeneralSetting::instance()->update(['accurate_stock_warehouse_name' => 'Room 126']);

    $itemPayload = [
        'id' => 919,
        'name' => 'Saus Bangkok',
        'no' => 'BRG-919',
        'unit1Name' => 'Pcs',
        'itemCategory' => ['name' => 'Bahan Baku'],
        'unitPrice' => 12000,
        'suspended' => false,
        'detailGroup' => [
            [
                'id' => 555001,
                'itemId' => 178,
                'detailName' => 'Bawang Bombay',
                'quantity' => 100,
            ],
            [
                'id' => 555002,
                'itemId' => 179,
                'detailName' => 'Bawang Merah',
                'quantity' => 80,
            ],
        ],
    ];

    mock(AccurateService::class, function (MockInterface $mock) use ($itemPayload): void {
        $mock->shouldReceive('getStockItems')
            ->once()
            ->withArgs(function ($request): bool {
                return $request instanceof \Illuminate\Http\Request
                    && $request->get('warehouse_name') === 'Room 126';
            })
            ->andReturn(collect([
                [
                    'no' => 'BRG-919',
                    'quantity' => 11,
                ],
            ]));

        $mock->shouldReceive('getItems')
            ->once()
            ->andReturn(collect([$itemPayload]));
    });

    $this->artisan('accurate:sync-items --force')->assertExitCode(0);

    $item = InventoryItem::query()->where('accurate_id', 919)->first();

    expect($item)->not->toBeNull();
    expect((float) $item->stock_quantity)->toBe(11.0);
    expect($item->detail_group)->toBe([
        [
            'accurate_id' => 178,
            'name' => 'Bawang Bombay',
            'quantity' => 100,
        ],
        [
            'accurate_id' => 179,
            'name' => 'Bawang Merah',
            'quantity' => 80,
        ],
    ]);
});

test('accurate sync items deletes local items removed from accurate', function () {
    config(['accurate.api_token' => 'dummy-token']);
    \App\Models\GeneralSetting::instance()->update(['accurate_stock_warehouse_name' => 'Room 126']);

    InventoryItem::create([
        'accurate_id' => 1001,
        'name' => 'Masih Ada di Accurate',
        'code' => 'BRG-1001',
        'unit' => 'Pcs',
        'category_type' => 'Bahan Baku',
        'price' => 10000,
        'stock_quantity' => 5,
        'is_active' => true,
    ]);

    InventoryItem::create([
        'accurate_id' => 1002,
        'name' => 'Sudah Dihapus di Accurate',
        'code' => 'BRG-1002',
        'unit' => 'Pcs',
        'category_type' => 'Bahan Baku',
        'price' => 12000,
        'stock_quantity' => 4,
        'is_active' => true,
    ]);

    $itemPayload = [
        'id' => 1001,
        'name' => 'Masih Ada di Accurate',
        'no' => 'BRG-1001',
        'unit1Name' => 'Pcs',
        'itemCategory' => ['name' => 'Bahan Baku'],
        'unitPrice' => 10000,
        'allQuantity' => 7,
        'suspended' => false,
        'detailGroup' => [],
    ];

    mock(AccurateService::class, function (MockInterface $mock) use ($itemPayload): void {
        $mock->shouldReceive('getStockItems')
            ->once()
            ->withArgs(function ($request): bool {
                return $request instanceof \Illuminate\Http\Request
                    && $request->get('warehouse_name') === 'Room 126';
            })
            ->andReturn(collect([
                [
                    'no' => 'BRG-1001',
                    'quantity' => 4,
                ],
            ]));

        $mock->shouldReceive('getItems')
            ->once()
            ->andReturn(collect([$itemPayload]));
    });

    $this->artisan('accurate:sync-items --force')->assertExitCode(0);

    expect((float) InventoryItem::query()->where('accurate_id', 1001)->value('stock_quantity'))->toBe(4.0)
        ->and(InventoryItem::query()->where('accurate_id', 1001)->exists())->toBeTrue()
        ->and(InventoryItem::query()->where('accurate_id', 1002)->exists())->toBeFalse();
});

test('accurate sync items skips item when its code belongs to a different accurate id', function () {
    config(['accurate.api_token' => 'dummy-token']);
    \App\Models\GeneralSetting::instance()->update(['accurate_stock_warehouse_name' => 'Room 126']);

    $existing = InventoryItem::create([
        'accurate_id' => 9999,
        'name' => 'Menu Lama',
        'code' => '100337',
        'unit' => 'PCS',
        'category_type' => 'Main Course',
        'price' => 5000,
        'stock_quantity' => 1,
        'is_active' => true,
    ]);

    // Beri riwayat order supaya prune hanya menonaktifkan, bukan menghapus baris.
    $order = \App\Models\Order::create([
        'order_number' => 'SYNC-CFL-001',
        'status' => 'completed',
        'items_total' => 5000,
        'discount_amount' => 0,
        'total' => 5000,
        'ordered_at' => now(),
    ]);
    \App\Models\OrderItem::create([
        'order_id' => $order->id,
        'inventory_item_id' => $existing->id,
        'item_name' => $existing->name,
        'item_code' => $existing->code,
        'quantity' => 1,
        'price' => 5000,
        'subtotal' => 5000,
        'status' => 'completed',
    ]);

    $itemPayload = [
        'id' => 1650,
        'name' => 'Test Menu 1',
        'no' => '100337',
        'unit1Name' => 'PCS',
        'itemCategory' => ['name' => 'Main Course'],
        'unitPrice' => 10000,
        'allQuantity' => 100,
        'suspended' => false,
        'detailGroup' => [],
    ];

    mock(AccurateService::class, function (MockInterface $mock) use ($itemPayload): void {
        $mock->shouldReceive('getStockItems')->once()->andReturn(collect([
            ['no' => '100337', 'quantity' => 100],
        ]));
        $mock->shouldReceive('getItems')->once()->andReturn(collect([$itemPayload]));
    });

    $this->artisan('accurate:sync-items --force')->assertExitCode(0);

    // Tidak ada pencurian identitas: baris lama tetap memegang accurate_id-nya,
    // item masukan (1650) tidak dibuat karena code-nya dimiliki baris lain.
    expect((int) $existing->fresh()->accurate_id)->toBe(9999)
        ->and($existing->fresh()->name)->toBe('Menu Lama')
        ->and(InventoryItem::query()->where('accurate_id', 1650)->exists())->toBeFalse()
        ->and($existing->fresh()->is_active)->toBeFalse();
});

test('accurate sync items replaces item data when accurate id matches including stock', function () {
    config(['accurate.api_token' => 'dummy-token']);
    \App\Models\GeneralSetting::instance()->update(['accurate_stock_warehouse_name' => 'Room 126']);

    $existing = InventoryItem::create([
        'accurate_id' => 1001,
        'name' => 'Nama Lama',
        'code' => 'BRG-1001',
        'unit' => 'Pcs',
        'category_type' => 'Bahan Baku',
        'price' => 10000,
        'stock_quantity' => 7,
        'is_active' => true,
    ]);

    $itemPayload = [
        'id' => 1001,
        'name' => 'Nama Baru dari Accurate',
        'no' => 'BRG-1001',
        'unit1Name' => 'Pcs',
        'itemCategory' => ['name' => 'Bahan Baku'],
        'unitPrice' => 12000,
        'allQuantity' => 999,
        'suspended' => false,
        'detailGroup' => [],
    ];

    mock(AccurateService::class, function (MockInterface $mock) use ($itemPayload): void {
        $mock->shouldReceive('getStockItems')->once()->andReturn(collect([
            ['no' => 'BRG-1001', 'quantity' => 999],
        ]));
        $mock->shouldReceive('getItems')->once()->andReturn(collect([$itemPayload]));
    });

    $this->artisan('accurate:sync-items --force')->assertExitCode(0);

    $fresh = $existing->fresh();

    // accurate_id sama → nama/harga/stok di-replace dari Accurate.
    expect($fresh->name)->toBe('Nama Baru dari Accurate')
        ->and((float) $fresh->price)->toBe(12000.0)
        ->and((int) $fresh->stock_quantity)->toBe(999)
        ->and(InventoryItem::query()->where('accurate_id', 1001)->count())->toBe(1);
});

test('accurate sync items invalidates cached recipes after sync', function () {
    config(['accurate.api_token' => 'dummy-token']);
    \App\Models\GeneralSetting::instance()->update(['accurate_stock_warehouse_name' => 'Room 126']);

    $itemPayload = [
        'id' => 1651,
        'name' => 'Menu Dengan Resep',
        'no' => 'BRG-1651',
        'unit1Name' => 'Pcs',
        'itemCategory' => ['name' => 'Main Course'],
        'unitPrice' => 50000,
        'allQuantity' => 10,
        'suspended' => false,
        'detailGroup' => [
            ['id' => 555004, 'itemId' => 1701, 'detailName' => 'Bahan A', 'quantity' => 2],
        ],
    ];

    // Racuni cache resep lama — harus terlupakan setelah sync.
    \Illuminate\Support\Facades\Cache::put('accurate_item_group_1651', [['itemId' => 999, 'quantity' => 1]], now()->addHour());
    \Illuminate\Support\Facades\Cache::put('accurate_item_group_1701', [['itemId' => 888, 'quantity' => 1]], now()->addHour());

    mock(AccurateService::class, function (MockInterface $mock) use ($itemPayload): void {
        $mock->shouldReceive('getStockItems')->once()->andReturn(collect([
            ['no' => 'BRG-1651', 'quantity' => 10],
        ]));
        $mock->shouldReceive('getItems')->once()->andReturn(collect([$itemPayload]));
    });

    $this->artisan('accurate:sync-items --force')->assertExitCode(0);

    expect(\Illuminate\Support\Facades\Cache::get('accurate_item_group_1651'))->toBeNull()
        ->and(\Illuminate\Support\Facades\Cache::get('accurate_item_group_1701'))->toBeNull();
});
