<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tier extends Model
{
    protected $guarded = [];

    // Palet warna badge (class literal agar Tailwind JIT tetap meng-generate).
    public const COLORS = [
        'slate' => ['bg' => 'bg-slate-100', 'text' => 'text-slate-700', 'badge' => 'bg-slate-100 text-slate-700'],
        'blue' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'badge' => 'bg-blue-100 text-blue-700'],
        'amber' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-700', 'badge' => 'bg-amber-100 text-amber-700'],
        'green' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'badge' => 'bg-green-100 text-green-700'],
        'violet' => ['bg' => 'bg-violet-100', 'text' => 'text-violet-700', 'badge' => 'bg-violet-100 text-violet-700'],
        'rose' => ['bg' => 'bg-rose-100', 'text' => 'text-rose-700', 'badge' => 'bg-rose-100 text-rose-700'],
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'discount_percentage' => 'integer',
            'minimum_spent' => 'integer',
            'is_first_tier' => 'boolean',
        ];
    }

    public function colorClasses(string $key = 'badge'): string
    {
        return self::COLORS[$this->color][$key] ?? self::COLORS['slate'][$key];
    }

    public function getFormattedMinimumSpentAttribute(): string
    {
        return 'Rp '.number_format($this->minimum_spent, 0, ',', '.');
    }
}
