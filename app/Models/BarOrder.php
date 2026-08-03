<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BarOrder extends Model
{
    protected $fillable = [
        'area_id',
        'order_id',
        'order_number',
        'customer_user_id',
        'table_id',
        'total_amount',
        'payment_method',
        'status',
        'progress',
    ];

    protected $casts = [
        'area_id' => 'integer',
        'total_amount' => 'decimal:2',
        'progress' => 'integer',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustomerUser::class, 'customer_user_id');
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Tabel::class, 'table_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BarOrderItem::class);
    }

    /**
     * Update order progress based on completed items
     */
    public function updateProgress(): void
    {
        $totalItems = $this->items()->count();

        if ($totalItems === 0) {
            $this->progress = 0;
            $this->status = 'baru';
        } else {
            $completedItems = $this->items()->where('is_completed', true)->count();
            $progress = round(($completedItems / $totalItems) * 100);

            $this->progress = $progress;

            if ($progress === 0) {
                $this->status = 'baru';
            } elseif ($progress === 100) {
                $this->status = 'selesai';
            } else {
                $this->status = 'proses';
            }
        }

        $this->save();
    }
}
