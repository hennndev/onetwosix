<?php

namespace App\Console\Commands;

use App\Models\GeneralSetting;
use App\Models\InventoryItem;
use App\Services\AccurateService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncAccurateItems extends Command
{
    protected $signature = 'accurate:sync-items {--force : Force sync without confirmation}';

    protected $description = 'Sync items data from Accurate to local database';

    protected $accurateService;

    protected $stats = [
        'created' => 0,
        'updated' => 0,
        'deleted' => 0,
        'failed' => 0,
        'total' => 0,
    ];

    public function __construct(AccurateService $accurateService)
    {
        parent::__construct();
        $this->accurateService = $accurateService;
    }

    public function handle()
    {
        // API token mode: no OAuth session needed, AccurateService handles auth automatically
        if (! config('accurate.api_token')) {
            $accessToken = Cache::get('accurate_access_token') ?? session('accurate_access_token');
            $database = Cache::get('accurate_database') ?? session('accurate_database');

            if (! $accessToken || ! $database) {
                Log::error('Accurate Items Sync: missing access token or database');

                return 1;
            }
        }

        if (! $this->option('force')) {
            if (! $this->confirm('Apakah Anda yakin ingin melakukan sync items?', true)) {
                return 0;
            }
        }
        $startTime = now();
        try {
            DB::beginTransaction();

            $this->syncItems();

            DB::commit();

            return 0;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Accurate Items Sync Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }

    protected $itemFields = [
        'id',
        'name',
        'no',
        'unit1Name',
        'itemCategory',
        'unitPrice',
        'unit1Price',
        'unitPrice1',
        'itemType',
        'itemProduced',
        'materialProduced',
    ];

    protected function targetWarehouseName(): string
    {
        return GeneralSetting::instance()->getAccurateWarehouseName();
    }

    protected function fetchStockMap(): array
    {
        $map = [];
        $page = 1;
        $pageSize = 500;

        do {
            $request = new \Illuminate\Http\Request;
            $request->merge([
                'page' => $page,
                'pageSize' => $pageSize,
                'warehouse_name' => $this->targetWarehouseName(),
            ]);

            // list-stock.do with sp.warehouseName returns warehouse-scoped quantities
            $stocks = $this->accurateService->getStockItems($request);

            if ($stocks->isEmpty()) {
                break;
            }

            foreach ($stocks as $stock) {
                $no = $stock['no'] ?? null;
                if ($no !== null) {
                    $map[$no] = $stock['quantity']
                        ?? 0;
                }
            }

            $page++;
        } while ($stocks->count() >= $pageSize);

        Log::info('Accurate Stock Map fetched', [
            'warehouse_name' => $this->targetWarehouseName(),
            'total_items' => count($map),
        ]);

        return $map;
    }

    protected function syncItems()
    {
        $stockMap = $this->fetchStockMap();
        $syncedAccurateIds = [];

        $page = 1;
        $pageSize = 100;
        do {
            $request = new \Illuminate\Http\Request;
            $request->merge([
                'page' => $page,
                'pageSize' => $pageSize,
            ]);

            $items = $this->accurateService->getItems($request, $this->itemFields);

            Log::info('items', ['items' => $items]);

            if ($items->isEmpty()) {
                break;
            }

            foreach ($items as $itemData) {
                try {
                    $accurateId = (int) ($itemData['id'] ?? 0);

                    if ($accurateId > 0) {
                        $syncedAccurateIds[] = $accurateId;
                    }

                    $this->syncSingleItem($itemData, $stockMap);
                } catch (Exception $e) {
                    Log::warning('Sync item failed', ['id' => $itemData['id'] ?? null, 'error' => $e->getMessage()]);
                }
            }

            $page++;
        } while ($items->count() >= $pageSize);

        $this->pruneDeletedItems($syncedAccurateIds);
    }

    /**
     * @param  array<int, int>  $syncedAccurateIds
     */
    protected function pruneDeletedItems(array $syncedAccurateIds): void
    {
        $syncedAccurateIds = array_values(array_unique($syncedAccurateIds));

        if ($syncedAccurateIds === []) {
            return;
        }

        InventoryItem::query()
            ->whereNotIn('accurate_id', $syncedAccurateIds)
            ->has('orderItems')
            ->update(['is_active' => false]);

        $staleItems = InventoryItem::query()
            ->whereNotIn('accurate_id', $syncedAccurateIds)
            ->doesntHave('orderItems')
            ->get();

        foreach ($staleItems as $staleItem) {
            try {
                $staleItem->delete();
                $this->stats['deleted']++;
            } catch (\Throwable $e) {
                $this->stats['failed']++;

                Log::warning('Failed to delete stale Accurate item from local database', [
                    'inventory_item_id' => $staleItem->id,
                    'accurate_id' => $staleItem->accurate_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function syncSingleItem(array $itemData, array $stockMap = [])
    {
        $accurateId = $itemData['id'] ?? null;

        if (! $accurateId) {
            $this->stats['failed']++;

            return;
        }
        $itemNo = $itemData['no'] ?? null;
        $detailGroup = $this->mapDetailGroup($itemData['detailGroup'] ?? []);
        $stockQuantity = $itemNo !== null
            ? ($stockMap[$itemNo] ?? ($itemData['allQuantity'] ?? 0))
            : ($itemData['allQuantity'] ?? 0);

        $resolvedItemType = strtoupper((string) ($itemData['itemType'] ?? (! empty($detailGroup) ? 'GROUP' : 'INVENTORY')));

        $price = (float) ($itemData['unitPrice'] ?? $itemData['unit1Price'] ?? $itemData['unitPrice1'] ?? $itemData['price'] ?? 0);

        $existingItem = InventoryItem::query()
            ->where('accurate_id', $accurateId)
            ->orWhere('code', $itemNo ?? 'UNKNOWN-'.$accurateId)
            ->first();

        if ($price <= 0 && $existingItem && (float) $existingItem->price > 0) {
            $price = (float) $existingItem->price;
        }

        $itemDataToSave = [
            'accurate_id' => (int) $accurateId,
            'name' => $itemData['name'] ?? 'Unknown Item',
            'code' => $itemNo ?? 'UNKNOWN-'.$accurateId,
            'unit' => $itemData['unit1Name'] ?? 'Unit',
            'category_type' => $itemData['itemCategory']['name'] ?? 'Uncategorized',
            'price' => $price,
            'stock_quantity' => $stockQuantity,
            'item_type' => $resolvedItemType,
            'is_active' => ($itemData['suspended'] ?? false) === false,
            'detail_group' => $detailGroup,
        ];

        if ($existingItem) {
            $existingItem->update($itemDataToSave);

            return;
        }

        InventoryItem::create($itemDataToSave);
    }

    protected function mapDetailGroup(array $detailGroup): array
    {
        return collect($detailGroup)
            ->map(function (array $detail): array {
                return [
                    'accurate_id' => $detail['id'] ?? null,
                    'name' => $detail['detailName'] ?? null,
                    'quantity' => $detail['quantity'] ?? 0,
                ];
            })
            ->values()
            ->all();
    }
}
