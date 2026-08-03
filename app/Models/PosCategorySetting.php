<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PosCategorySetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'show_in_pos' => 'boolean',
        'is_menu' => 'boolean',
    ];

    /** Returns all settings keyed by category_type, cached for 5 minutes. */
    public static function allKeyed(): Collection
    {
        return Cache::remember('pos_category_settings', 300, fn () => static::all()->keyBy('category_type'));
    }

    public static function clearCache(): void
    {
        Cache::forget('pos_category_settings');
    }
}
