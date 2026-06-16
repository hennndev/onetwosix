<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthCodeRequest extends Model
{
    protected $fillable = [
        'user_id',
        'source',
        'status',
        'code',
        'manager_note',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
