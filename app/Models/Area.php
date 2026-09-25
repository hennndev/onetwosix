<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    protected $fillable = [
        'code',
        'name',
        'capacity',
        'description',
        'image',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tables()
    {
        return $this->hasMany(Tabel::class);
    }

    public function internalUsers()
    {
        return $this->hasMany(InternalUser::class);
    }

    /**
     * Get SO Number Prefix for Accurate / POS invoices (e.g. ROOM- or LOUNGE-).
     */
    public function getSoPrefixAttribute(): string
    {
        $code = strtoupper(trim($this->code ?? ''));

        if (in_array($code, ['LOUNGE', 'LNG'])) {
            return 'LOUNGE-';
        }

        return 'ROOM-';
    }
}
