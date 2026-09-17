<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    protected $guarded;

    protected $casts = [
        'price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'threshold' => 'integer',
        'is_active' => 'boolean',
        'is_visible_in_pos' => 'boolean',
        'include_tax' => 'boolean',
        'include_service_charge' => 'boolean',
        'is_item_group' => 'boolean',
        'is_group_sold_out' => 'boolean',
        'is_count_portion_possible' => 'boolean',
        'detail_group' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function printers(): BelongsToMany
    {
        return $this->belongsToMany(Printer::class)->withTimestamps();
    }

    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->threshold;
    }

    /**
     * Resep (BOM) menu item group dari data lokal `detail_group` — disinkronkan
     * oleh accurate:sync-items / diisi via Menu Management. Sumber tunggal untuk
     * hitung porsi dan konsumsi bahan; runtime POS tidak memanggil Accurate.
     *
     * @return array<int, array{itemId: int, quantity: float, detailName: string|null}>
     */
    public function recipeComponents(): array
    {
        return collect($this->detail_group ?? [])
            ->map(function ($component): array {
                return [
                    'itemId' => (int) ($component['accurate_id'] ?? ($component['itemId'] ?? 0)),
                    'quantity' => (float) ($component['quantity'] ?? 0),
                    'detailName' => $component['name'] ?? ($component['detailName'] ?? null),
                ];
            })
            ->filter(fn (array $component): bool => $component['itemId'] > 0 && $component['quantity'] > 0)
            ->values()
            ->all();
    }

    /**
     * Estimasi stok jadi (porsi) menu group berdasarkan BOM lokal dan stok bahan
     * saat ini: MIN antar-bahan dari floor(stok ÷ qty resep). Null bila bukan
     * group dengan count portion aktif.
     */
    public function possiblePortions(): ?int
    {
        if (! $this->is_item_group || ! $this->is_count_portion_possible) {
            return null;
        }

        $components = $this->recipeComponents();

        if ($components === []) {
            return 0;
        }

        $ingredients = InventoryItem::query()
            ->whereIn('accurate_id', array_column($components, 'itemId'))
            ->pluck('stock_quantity', 'accurate_id');

        $min = null;

        foreach ($components as $component) {
            $stock = max((float) ($ingredients[$component['itemId']] ?? 0), 0);
            $portions = (int) floor($stock / $component['quantity']);
            $min = $min === null ? $portions : min($min, $portions);
        }

        return $min ?? 0;
    }

    public function getStockStatusAttribute(): string
    {
        return $this->isLowStock() ? 'low' : 'normal';
    }
}
