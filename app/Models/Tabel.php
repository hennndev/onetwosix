<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tabel extends Model
{
    protected $table = 'tables';

    protected $fillable = [
        'area_id',
        'table_number',
        'qr_code',
        'capacity',
        'minimum_charge',
        'status',
        'is_active',
        'notes',
        'position_x',
        'position_y',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'minimum_charge' => 'decimal:2',
        'capacity' => 'integer',
        'position_x' => 'float',
        'position_y' => 'float',
    ];

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function reservations()
    {
        return $this->hasMany(TableReservation::class, 'table_id');
    }
}
