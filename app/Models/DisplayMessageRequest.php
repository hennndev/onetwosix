<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisplayMessageRequest extends Model
{
    protected $fillable = [
        'customer_id',
        'table_session_id',
        'message',
        'tip',
        'status',
    ];

    protected $casts = [
        'tip' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function tableSession()
    {
        return $this->belongsTo(TableSession::class);
    }
}
