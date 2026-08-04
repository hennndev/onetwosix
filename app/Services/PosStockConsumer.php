<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\PosCategorySetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosStockConsumer
{
    public function __construct(protected AccurateService $accurateService) {}

    /**
     * @param  array<string, array<string, mixed>>  $cart
     * @return array{products: array<int, int>, stock: array<int, int>}
     */
    public function requirements(array $cart): array
    {
        $productQuantities = [];
        $stockQuantities = [];

        foreach ($cart as $productId => $cartItem) {
            if (! preg_match('/^item_(\d+)$/', (string) $productId, $matches)) {
                throw ValidationException::withMessages(['cart' => 'Keranjang berisi produk yang tidak valid.']);
            }

            $quantity = filter_var($cartItem['quantity'] ?? null, FILTER_VALIDATE_INT);
            if ($quantity === false || $quantity <= 0) {
                throw ValidationException::withMessages(['cart' => 'Jumlah produk harus lebih dari nol.']);
            }

            $inventoryItem = InventoryItem::query()->find((int) $matches[1]);
            $this->validateProduct($inventoryItem);
            $productQuantities[$inventoryItem->id] = ($productQuantities[$inventoryItem->id] ?? 0) + $quantity;

            $components = $this->components($inventoryItem);
            if ($components === []) {
                if (! $this->isItemGroup($inventoryItem)) {
                    $stockQuantities[$inventoryItem->id] = ($stockQuantities[$inventoryItem->id] ?? 0) + $quantity;
                }

                continue;
            }

            foreach ($components as $component) {
                $accurateId = (int) ($component['itemId'] ?? 0);
                $componentQuantity = (float) ($component['quantity'] ?? 0);

                if ($accurateId <= 0 || $componentQuantity <= 0) {
                    throw ValidationException::withMessages(['stock' => "Komposisi {$inventoryItem->name} tidak valid."]);
                }

                $ingredient = InventoryItem::query()->where('accurate_id', $accurateId)->first();
                if (! $ingredient) {
                    throw ValidationException::withMessages(['stock' => "Bahan {$inventoryItem->name} tidak ditemukan."]);
                }

                $required = (int) ceil($componentQuantity * $quantity);
                $stockQuantities[$ingredient->id] = ($stockQuantities[$ingredient->id] ?? 0) + $required;
            }
        }

        ksort($productQuantities);
        ksort($stockQuantities);

        return ['products' => $productQuantities, 'stock' => $stockQuantities];
    }

    /**
     * @param  array{products: array<int, int>, stock: array<int, int>}  $requirements
     */
    public function consume(array $requirements): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('POS stock must be consumed inside a database transaction.');
        }

        $ids = collect(array_keys($requirements['products'] + $requirements['stock']))->sort()->values();
        $lockedItems = InventoryItem::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        foreach ($requirements['products'] as $id => $quantity) {
            $this->validateProduct($lockedItems->get($id));
        }

        foreach ($requirements['stock'] as $id => $quantity) {
            $item = $lockedItems->get($id);
            if (! $item || (int) $item->stock_quantity < $quantity) {
                $name = $item?->name ?? 'produk';
                $available = (int) ($item?->stock_quantity ?? 0);
                throw ValidationException::withMessages(['stock' => "Stok {$name} hanya tersisa {$available}."]);
            }
        }

        foreach ($requirements['stock'] as $id => $quantity) {
            $lockedItems->get($id)->decrement('stock_quantity', $quantity);
        }
    }

    private function validateProduct(?InventoryItem $inventoryItem): void
    {
        if (! $inventoryItem || ! $inventoryItem->is_active || ! $inventoryItem->is_visible_in_pos || ($this->isItemGroup($inventoryItem) && $inventoryItem->is_group_sold_out)) {
            throw ValidationException::withMessages(['cart' => 'Produk tidak tersedia lagi.']);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function components(InventoryItem $inventoryItem): array
    {
        if (! $this->isItemGroup($inventoryItem) || ! $inventoryItem->accurate_id) {
            return [];
        }

        try {
            return Cache::remember(
                "accurate_item_group_{$inventoryItem->accurate_id}",
                now()->addHour(),
                fn (): array => $this->accurateService->getItemGroupComponents((int) $inventoryItem->accurate_id),
            );
        } catch (\Throwable $exception) {
            if ($this->isItemGroup($inventoryItem)) {
                throw ValidationException::withMessages(['stock' => "Komposisi {$inventoryItem->name} tidak dapat diperiksa."]);
            }

            return [];
        }
    }

    private function isItemGroup(InventoryItem $inventoryItem): bool
    {
        $setting = PosCategorySetting::allKeyed()->get($inventoryItem->category_type);

        return (bool) $inventoryItem->is_item_group || (bool) $setting?->is_item_group;
    }
}
