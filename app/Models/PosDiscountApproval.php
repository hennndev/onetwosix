<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosDiscountApproval extends Model
{
    protected $fillable = [
        'daily_auth_code_id',
        'cashier_id',
        'token_hash',
        'fingerprint',
        'intent',
        'approved_at',
        'expires_at',
        'consumed_at',
        'consumed_order_id',
    ];

    protected function casts(): array
    {
        return [
            'intent' => 'array',
            'approved_at' => 'datetime',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function dailyAuthCode(): BelongsTo
    {
        return $this->belongsTo(DailyAuthCode::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function consumedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'consumed_order_id');
    }
}
