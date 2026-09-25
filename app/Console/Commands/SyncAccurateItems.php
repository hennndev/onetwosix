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
                // Baris non-array / tanpa 'no' adalah tanda gudang salah (mis. "Gudang tidak tepat").
                if (! is_array($stock) || ($stock['no'] ?? null) === null) {
                    Log::warning('Accurate stock list: baris tidak valid dilewati', ['row' => is_array($stock) ? null : (string) $stock]);

                    continue;
                }

                $map[$stock['no']] = $stock['quantity'] ?? 0;
            }

            $page++;
        } while ($stocks->count() >= $pageSize);

        if ($map === []) {
            Log::error('Accurate Stock Map KOSONG — kemungkinan besar nama gudang salah di Pengaturan Umum. Stok lokal tidak akan ditimpa (proteksi mass-zero).', [
                'warehouse_name' => $this->targetWarehouseName(),
            ]);
        }

        return $map;
    }

    protected function syncItems()
    {
        $stockMap = $this->fetchStockMap();
        $syncedAccurateIds = [];

        // Stok selalu mengikuti Accurate (sumber kebenaran gudang), KECUALI
        // stock map kosong — artinya nama gudang salah / API gagal — dan saat
        // itu stok lokal dipertahankan (proteksi mass-zero).
        $applyStock = $stockMap !== [];

        $page = 1;
        $pageSize = 100;
        $paginationCompleted = false;
        do {
            $request = new \Illuminate\Http\Request;
            $request->merge([
                'page' => $page,
                'pageSize' => $pageSize,
            ]);

            $items = $this->accurateService->getItems($request, $this->itemFields);

            if ($items->isEmpty()) {
                $paginationCompleted = true;
                break;
            }

            foreach ($items as $itemData) {
                try {
                    $accurateId = (int) ($itemData['id'] ?? 0);

                    if ($accurateId > 0) {
                        $syncedAccurateIds[] = $accurateId;
                    }

                    $this->syncSingleItem($itemData, $stockMap, $applyStock);
                } catch (Exception $e) {
                    Log::warning('Sync item failed', ['id' => $itemData['id'] ?? null, 'error' => $e->getMessage()]);
                }
            }

            if ($items->count() < $pageSize) {
                $paginationCompleted = true;
            }

            $page++;
        } while ($items->count() >= $pageSize);

        // Prune hanya bila pagination selesai penuh: satu halaman kosong karena
        // gangguan API tidak boleh memicu mass-disable/mass-delete item lokal.
        if ($paginationCompleted && $syncedAccurateIds !== []) {
            $this->pruneDeletedItems($syncedAccurateIds);
        } else {
            Log::warning('Accurate item sync: pagination incomplete, skipping prune to protect local items', [
                'synced_count' => count($syncedAccurateIds),
            ]);
        }
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

    protected function syncSingleItem(array $itemData, array $stockMap = [], bool $applyStock = true)
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

        // accurate_id adalah kunci tunggal pencocokan: cocok → replace data item.
        // Pencocokan by code DIHAPUS — dulu bikin baris salah menerima accurate_id
        // item lain (identitas tertukar). Tabrakan code kini hanya dilaporkan.
        $existingItem = InventoryItem::query()
            ->where('accurate_id', $accurateId)
            ->first();

        if (! $existingItem && $itemNo !== null) {
            $codeOwner = InventoryItem::query()->where('code', $itemNo)->first();

            if ($codeOwner && (int) $codeOwner->accurate_id !== (int) $accurateId) {
                Log::warning('Accurate sync: konflik identitas — code sudah dipakai item lain, item dilewati. Perbaiki manual (rename code salah satunya).', [
                    'incoming_accurate_id' => (int) $accurateId,
                    'incoming_code' => $itemNo,
                    'existing_item_id' => $codeOwner->id,
                    'existing_name' => $codeOwner->name,
                    'existing_accurate_id' => (int) $codeOwner->accurate_id,
                ]);
                $this->stats['failed']++;

                return;
            }
        }

        if ($price <= 0 && $existingItem && (float) $existingItem->price > 0) {
            $price = (float) $existingItem->price;
        }

        // Stok mengikuti Accurate. Hanya dipertahankan saat stock map kosong
        // (nama gudang salah / API gagal) — proteksi mass-zero.
        if ($existingItem && ! $applyStock) {
            $stockQuantity = (int) $existingItem->stock_quantity;
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
            // Item yang dinonaktifkan admin tidak boleh diaktifkan ulang diam-diam oleh sync.
            if ($existingItem->is_active === false) {
                $itemDataToSave['is_active'] = false;
            }

            $existingItem->update($itemDataToSave);
            $this->forgetRecipeCache((int) $accurateId, $detailGroup);

            return;
        }

        InventoryItem::create($itemDataToSave);
        $this->forgetRecipeCache((int) $accurateId, $detailGroup);
    }

    /**
     * Resep ter-cache harus mengikuti data terbaru: lupakan key lama agar
     * perhitungan porsi tidak memakai BOM basi setelah sync.
     */
    protected function forgetRecipeCache(int $accurateId, array $detailGroup): void
    {
        Cache::forget("accurate_item_group_{$accurateId}");

        foreach ($detailGroup as $component) {
            $componentAccurateId = (int) ($component['accurate_id'] ?? 0);

            if ($componentAccurateId > 0) {
                Cache::forget("accurate_item_group_{$componentAccurateId}");
            }
        }
    }

    protected function mapDetailGroup(array $detailGroup): array
    {
        // Simpan itemId BAHAN (bukan id baris detailGroup) — id inilah yang
        // dipakai hitung porsi & konsumsi bahan via inventory_items.accurate_id.
        return collect($detailGroup)
            ->map(function (array $detail): array {
                return [
                    'accurate_id' => $detail['itemId'] ?? null,
                    'name' => $detail['detailName'] ?? null,
                    'quantity' => $detail['quantity'] ?? 0,
                ];
            })
            ->filter(fn (array $detail): bool => filled($detail['accurate_id']))
            ->values()
            ->all();
    }
}
