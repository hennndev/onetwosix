<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Event extends Model
{
    protected $fillable = [
        'area_id',
        'name',
        'slug',
        'description',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'is_active',
        'price_adjustment_type',
        'price_adjustment_value',
    ];

    protected $casts = [
        'area_id' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'price_adjustment_value' => 'decimal:2',
    ];

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($event) {
            if (empty($event->slug)) {
                $event->slug = Str::slug($event->name);
            }
        });
    }

    public function isMultiDay()
    {
        return $this->start_date->format('Y-m-d') !== $this->end_date->format('Y-m-d');
    }

    public function isToday()
    {
        $today = Carbon::today();

        return $this->start_date->lte($today) && $this->end_date->gte($today);
    }

    public function isUpcoming()
    {
        return $this->start_date->isFuture();
    }

    public function isPast()
    {
        return $this->end_date->isPast();
    }

    public function getPriceAdjustmentFormatted()
    {
        if ($this->price_adjustment_type === 'percentage') {
            return '+'.number_format($this->price_adjustment_value, 0).'%';
        }

        return '+Rp '.number_format($this->price_adjustment_value, 0, ',', '.');
    }

    public function getPriceAdjustmentDescription()
    {
        if ($this->price_adjustment_type === 'percentage') {
            return 'Dari harga minimum charge normal';
        }

        return 'Ditambahkan ke harga minimum charge';
    }
}
