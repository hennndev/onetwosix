<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingPayment extends Model
{
    protected $fillable = [
        'billing_id',
        'amount_paid',
        'payment_method',
        'payment_reference_number',
        'payment_type',
        'accurate_sales_receipt_number',
        'accurate_sync_status',
        'notes',
        'created_by',
        'paid_at',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function billing(): BelongsTo
    {
        return $this->belongsTo(Billing::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
