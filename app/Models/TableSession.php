<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TableSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'billing_id',
        'table_reservation_id',
        'table_id',
        'customer_id',
        'waiter_id',
        'pax',
        'session_code',
        'check_in_qr_code',
        'check_in_qr_expires_at',
        'checked_in_at',
        'checked_out_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'check_in_qr_expires_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
    ];

    public function reservation()
    {
        return $this->belongsTo(TableReservation::class, 'table_reservation_id');
    }

    public function table()
    {
        return $this->belongsTo(Tabel::class, 'table_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function waiter()
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    public function billing()
    {
        return $this->belongsTo(Billing::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function orderItems()
    {
        return $this->hasManyThrough(OrderItem::class, Order::class);
    }

    public function songRequests()
    {
        return $this->hasMany(SongRequest::class);
    }

    public function displayMessageRequests()
    {
        return $this->hasMany(DisplayMessageRequest::class);
    }

    public function isQRValid()
    {
        return $this->check_in_qr_code
            && $this->check_in_qr_expires_at
            && $this->check_in_qr_expires_at->isFuture();
    }
}
