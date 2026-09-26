<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthOtpChallenge extends Model
{
    /** @use HasFactory<\Database\Factories\AuthOtpChallengeFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'type',
        'user_id',
        'target_phone',
        'payload',
        'code_hash',
        'attempts',
        'expires_at',
        'consumed_at',
    ];

    protected $hidden = [
        'code_hash',
        'payload',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
