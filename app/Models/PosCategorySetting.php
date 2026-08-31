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
        'area_ids' => 'array',
    ];

    /** Returns all settings keyed by category_type, cached for 5 minutes. */
    public static function allKeyed(): Collection
    {
        return Cache::remember('pos_category_settings', 300, fn () => static::all()->keyBy('category_type'));
    }

    /**
     * Settings visible in the given area, keyed by category_type.
     * Rule: show_in_pos OFF = hidden everywhere; ON + empty area_ids = visible everywhere;
     * ON + area_ids = visible only in those areas. Null area (multi-area user without
     * an active area) sees the union of all enabled categories.
     */
    public static function visibleInArea(?int $areaId): Collection
    {
        return static::allKeyed()->filter(fn ($s) => $s->isVisibleInArea($areaId));
    }

    public function isVisibleInArea(?int $areaId): bool
    {
        if (! $this->show_in_pos) {
            return false;
        }

        $areaIds = $this->area_ids ?? [];

        return $areaId === null || $areaIds === [] || in_array($areaId, $areaIds, true);
    }

    public static function clearCache(): void
    {
        Cache::forget('pos_category_settings');
    }
}
