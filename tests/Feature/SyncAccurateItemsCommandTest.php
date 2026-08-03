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
                'id' => 178,
                'detailName' => 'Bawang Bombay',
                'quantity' => 100,
            ],
            [
                'id' => 179,
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

test('accurate sync items updates existing item when accurate code already exists', function () {
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
        $mock->shouldReceive('getStockItems')
            ->once()
            ->withArgs(function ($request): bool {
                return $request instanceof \Illuminate\Http\Request
                    && $request->get('warehouse_name') === 'Room 126';
            })
            ->andReturn(collect([
                [
                    'no' => '100337',
                    'quantity' => 100,
                ],
            ]));

        $mock->shouldReceive('getItems')
            ->once()
            ->andReturn(collect([$itemPayload]));
    });

    $this->artisan('accurate:sync-items --force')->assertExitCode(0);

    expect(InventoryItem::query()->where('code', '100337')->count())->toBe(1)
        ->and((int) $existing->fresh()->accurate_id)->toBe(1650)
        ->and($existing->fresh()->name)->toBe('Test Menu 1')
        ->and((float) $existing->fresh()->stock_quantity)->toBe(100.0);
});
