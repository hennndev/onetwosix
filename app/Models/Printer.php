<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Printer extends Model
{
    protected $fillable = [
        'name',
        'location',
        'area_id',
        'printer_type',
        'connection_type',
        'ip',
        'port',
        'path',
        'timeout',
        'header',
        'footer',
        'logo_path',
        'show_qr_code',
        'width',
        'enable_receiver',
        'is_default',
        'is_active',
    ];

    public function area(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function scopeForArea($query, ?int $areaId)
    {
        if (! $areaId) {
            return $query;
        }

        return $query->where(function ($q) use ($areaId) {
            $q->where('area_id', $areaId)
                ->orWhereNull('area_id');
        })
            // Printer milik area persis menang atas printer tanpa area (fallback global).
            ->orderByRaw('area_id IS NULL');
    }

    protected $casts = [
        'show_qr_code' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'enable_receiver' => 'boolean',
        'port' => 'integer',
        'timeout' => 'integer',
        'width' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeByLocation($query, string $location)
    {
        return $query->whereRaw('TRIM(LOWER(location)) = ?', [strtolower(trim($location))]);
    }

    public function scopeByType($query, string $type)
    {
        return $query->whereRaw('TRIM(LOWER(printer_type)) = ?', [strtolower(trim($type))]);
    }

    public static function getDefault(?int $areaId = null): ?self
    {
        return static::active()->forArea($areaId)->default()->first()
            ?? static::active()->forArea($areaId)->first();
    }

    public static function getByLocation(string $location, ?int $areaId = null): ?self
    {
        return static::active()->forArea($areaId)->byLocation($location)->first();
    }

    public static function getByType(string $type, ?int $areaId = null): ?self
    {
        return static::active()->forArea($areaId)->byType($type)->first();
    }

    /**
     * Get printer for a service location, preferring printer_type match over location string.
     *
     * Repo ini menggabungkan beberapa area (lounge + room) dalam satu instance, jadi
     * $areaId wajib diteruskan pemanggil bila konteks areanya diketahui — tanpa itu
     * printer area lain bisa terpilih dan struk tercetak di gedung yang salah.
     */
    public static function getForService(string $serviceLocation, ?int $areaId = null): ?self
    {
        $normalized = strtolower(trim($serviceLocation));

        $aliases = match ($normalized) {
            'cashier', 'kasir' => ['cashier', 'kasir'],
            'kitchen', 'dapur' => ['kitchen', 'dapur'],
            default => [$normalized],
        };

        foreach ($aliases as $alias) {
            $byType = static::getByType($alias, $areaId);
            if ($byType) {
                return $byType;
            }
        }

        foreach ($aliases as $alias) {
            $byLocation = static::getByLocation($alias, $areaId);
            if ($byLocation) {
                return $byLocation;
            }
        }

        return null;
    }

    public function inventoryItems(): BelongsToMany
    {
        return $this->belongsToMany(InventoryItem::class)->withTimestamps();
    }

    public function isNetwork(): bool
    {
        return $this->connection_type === 'network';
    }

    public function isFile(): bool
    {
        return $this->connection_type === 'file';
    }

    public function isWindows(): bool
    {
        return $this->connection_type === 'windows';
    }
}
