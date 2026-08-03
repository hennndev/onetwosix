<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'area_id',
        'table_session_id',
        'customer_user_id',
        'created_by',
        'order_number',
        'status',
        'items_total',
        'discount_amount',
        'total',
        'ordered_at',
        'completed_at',
        'cancelled_at',
        'notes',
        'cancellation_reason',
        'cancelled_by',
        'payment_method',
        'payment_mode',
        'payment_reference_number',
        'accurate_so_number',
        'accurate_inv_number',
        'receipt_print_count',
        'kitchen_print_count',
        'bar_print_count',
        'checker_print_count',
    ];

    protected $casts = [
        'area_id' => 'integer',
        'items_total' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'ordered_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'receipt_print_count' => 'integer',
        'kitchen_print_count' => 'integer',
        'bar_print_count' => 'integer',
        'checker_print_count' => 'integer',
    ];

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    // Relationships
    public function tableSession()
    {
        return $this->belongsTo(TableSession::class);
    }

    public function customer()
    {
        return $this->belongsTo(CustomerUser::class, 'customer_user_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function kitchenOrder()
    {
        return $this->hasOne(KitchenOrder::class);
    }

    public function barOrder()
    {
        return $this->hasOne(BarOrder::class);
    }

    public function billing()
    {
        return $this->hasOne(Billing::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
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

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isCancelled()
    {
        return $this->status === 'cancelled';
    }

    public function canBeCancelled()
    {
        return in_array($this->status, ['pending', 'preparing']);
    }

    // Update order total from items
    public function updateTotals()
    {
        $this->items_total = $this->items()->sum('subtotal');
        $this->total = $this->items_total - $this->discount_amount;
        $this->save();
    }

    // Update order status based on items
    public function updateStatus()
    {
        $itemStatuses = $this->items()->pluck('status')->unique();

        if ($itemStatuses->contains('cancelled') && $itemStatuses->count() === 1) {
            $this->status = 'cancelled';
        } elseif ($itemStatuses->every(fn ($s) => $s === 'served')) {
            $this->status = 'completed';
            $this->completed_at = now();
        } elseif ($itemStatuses->contains('served') || $itemStatuses->contains('ready')) {
            $this->status = 'ready';
        } elseif ($itemStatuses->contains('preparing')) {
            $this->status = 'preparing';
        } else {
            $this->status = 'pending';
        }

        $this->save();
    }
}
