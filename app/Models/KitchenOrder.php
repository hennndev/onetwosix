<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KitchenOrder extends Model
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

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function customer()
    {
        return $this->belongsTo(CustomerUser::class, 'customer_user_id');
    }

    public function table()
    {
        return $this->belongsTo(Tabel::class, 'table_id');
    }

    public function items()
    {
        return $this->hasMany(KitchenOrderItem::class);
    }

    public function updateProgress()
    {
        $totalItems = $this->items()->count();
        if ($totalItems == 0) {
            $this->update(['progress' => 0]);

            return;
        }

        $completedItems = $this->items()->where('is_completed', true)->count();
        $progress = ($completedItems / $totalItems) * 100;

        $this->update(['progress' => round($progress)]);

        // Auto update status
        if ($progress == 100) {
            $this->update(['status' => 'selesai']);
        } elseif ($progress > 0) {
            $this->update(['status' => 'proses']);
        }
    }
}
