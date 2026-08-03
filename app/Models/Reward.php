<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reward extends Model
{
    /** @use HasFactory<\Database\Factories\RewardFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'points_required' => 'integer',
            'stock' => 'integer',
            'redeemed_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getFormattedPointsAttribute(): string
    {
        return number_format($this->points_required, 0, ',', '.');
    }

    public function getCategoryLabelAttribute(): string
    {
        return strtoupper($this->category);
    }

    public function getCategoryColorAttribute(): string
    {
        return match ($this->category) {
            'drink' => 'purple',
            'voucher' => 'blue',
            'vip' => 'yellow',
            default => 'gray',
        };
    }

    public function redemptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RewardRedemption::class);
    }
}
