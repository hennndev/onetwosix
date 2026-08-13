<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Promo extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'is_active',
        'discount_type',
        'discount_value',
        'terms_conditions',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'discount_value' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($promo) {
            if (empty($promo->slug)) {
                $promo->slug = Str::slug($promo->name);
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

    public function getDiscountFormatted()
    {
        if ($this->discount_type === 'percentage') {
            return number_format($this->discount_value, 0).'%';
        }

        return 'Rp '.number_format($this->discount_value, 0, ',', '.');
    }

    public function getDiscountDescription()
    {
        if ($this->discount_type === 'percentage') {
            return 'Potongan persentase dari harga normal';
        }

        return 'Potongan harga tetap';
    }
}
