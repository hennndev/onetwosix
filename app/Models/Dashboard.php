<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dashboard extends Model
{
    protected $table = 'dashboard';

    protected $fillable = [
        'area_id',
        'total_amount',
        'total_food',
        'total_alcohol',
        'total_beverage',
        'total_cigarette',
        'total_breakage',
        'total_room',
        'total_staff_meal',
        'total_compliment_quantity',
        'total_foc_quantity',
        'total_ld',
        'total_ld_quantity',
        'total_penjualan_rokok',
        'total_tax',
        'total_service_charge',
        'total_dp',
        'total_cash',
        'total_transfer',
        'total_debit',
        'total_kredit',
        'total_qris',
        'total_foc_amount',
        'total_compliment_amount',
        'total_kitchen_items',
        'total_bar_items',
        'total_transactions',
        'last_synced_at',
    ];

    protected $casts = [
        'area_id' => 'integer',
        'total_amount' => 'decimal:2',
        'total_food' => 'decimal:2',
        'total_alcohol' => 'decimal:2',
        'total_beverage' => 'decimal:2',
        'total_cigarette' => 'decimal:2',
        'total_breakage' => 'decimal:2',
        'total_room' => 'decimal:2',
        'total_staff_meal' => 'decimal:2',
        'total_compliment_quantity' => 'integer',
        'total_foc_quantity' => 'integer',
        'total_ld' => 'decimal:2',
        'total_ld_quantity' => 'integer',
        'total_penjualan_rokok' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'total_service_charge' => 'decimal:2',
        'total_dp' => 'decimal:2',
        'total_cash' => 'decimal:2',
        'total_transfer' => 'decimal:2',
        'total_debit' => 'decimal:2',
        'total_kredit' => 'decimal:2',
        'total_qris' => 'decimal:2',
        'total_kitchen_items' => 'integer',
        'total_bar_items' => 'integer',
        'total_transactions' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }
}
