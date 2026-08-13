<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SongRequest extends Model
{
    protected $fillable = [
        'customer_user_id',
        'table_session_id',
        'song_title',
        'artist',
        'cover_image',
        'preview_url',
        'tip',
        'status',
    ];

    protected $casts = [
        'tip' => 'decimal:2',
    ];

    public function customerUser()
    {
        return $this->belongsTo(CustomerUser::class);
    }

    public function tableSession()
    {
        return $this->belongsTo(TableSession::class);
    }

    public function customer()
    {
        return $this->hasOneThrough(
            User::class,
            CustomerUser::class,
            'id',
            'id',
            'customer_user_id',
            'user_id'
        );
    }
}
