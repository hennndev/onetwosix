<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyBarSnapshot extends Model
{
    protected $fillable = [
        'end_day',
        'area_id',
        'total_items',
        'last_synced_at',
    ];

    protected $casts = [
        'end_day' => 'date',
        'area_id' => 'integer',
        'total_items' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function dailyItems(): HasMany
    {
        return $this->hasMany(DailyBarItem::class, 'daily_bar_snapshot_id');
    }
}
