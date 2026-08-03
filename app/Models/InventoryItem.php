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

    public function getStockStatusAttribute(): string
    {
        return $this->isLowStock() ? 'low' : 'normal';
    }
}
