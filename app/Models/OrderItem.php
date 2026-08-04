<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'inventory_item_id',
        'item_name',
        'item_code',
        'quantity',
        'price',
        'subtotal',
        'discount_amount',
        'discount_reason',
        'discount_approval_id',
        'tax_amount',
        'service_charge_amount',
        'preparation_location',
        'status',
        'prepared_at',
        'ready_at',
        'served_at',
        'cancelled_at',
        'notes',
        'cancellation_reason',
        'cancelled_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'service_charge_amount' => 'decimal:2',
        'prepared_at' => 'datetime',
        'ready_at' => 'datetime',
        'served_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // Relationships
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function tableSession()
    {
        return $this->hasOneThrough(TableSession::class, Order::class, 'id', 'id', 'order_id', 'table_session_id');
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function cancelledByUser()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // Helper methods
    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isPreparing()
    {
        return $this->status === 'preparing';
    }

    public function isReady()
    {
        return $this->status === 'ready';
    }

    public function isServed()
    {
        return $this->status === 'served';
    }

    public function isCancelled()
    {
        return $this->status === 'cancelled';
    }

    public function canBeCancelled()
    {
        return in_array($this->status, ['pending', 'preparing']);
    }

    // Scope untuk kitchen/bar
    public function scopeForKitchen($query)
    {
        return $query->where('preparation_location', 'kitchen')
            ->whereIn('status', ['pending', 'preparing', 'ready']);
    }

    public function scopeForBar($query)
    {
        return $query->where('preparation_location', 'bar')
            ->whereIn('status', ['pending', 'preparing', 'ready']);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'preparing', 'ready']);
    }
}
