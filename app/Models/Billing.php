<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Billing extends Model
{
    protected $fillable = [
        'area_id',
        'table_session_id',
        'order_id',
        'is_walk_in',
        'is_booking',
        'minimum_charge',
        'orders_total',
        'subtotal',
        'tax',
        'tax_percentage',
        'service_charge',
        'service_charge_percentage',
        'discount_amount',
        'song_tip',
        'display_tip',
        'grand_total',
        'paid_amount',
        'remaining_balance',
        'is_debt',
        'is_parsial_payment',
        'billing_status',
        'paid_at',
        'transaction_code',
        'payment_method',
        'payment_reference_number',
        'payment_mode',
        'foc_comp_payment_method',
        'split_cash_amount',
        'split_debit_amount',
        'split_non_cash_method',
        'split_non_cash_reference_number',
        'split_second_non_cash_amount',
        'split_second_non_cash_method',
        'split_second_non_cash_reference_number',
        'notes',
        'closing_notes',
        'accurate_so_number',
        'accurate_inv_number',
        'error_message',
    ];

    protected $casts = [
        'area_id' => 'integer',
        'minimum_charge' => 'decimal:2',
        'orders_total' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'tax_percentage' => 'decimal:2',
        'service_charge' => 'decimal:2',
        'service_charge_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'song_tip' => 'decimal:2',
        'display_tip' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_balance' => 'decimal:2',
        'is_debt' => 'boolean',
        'is_parsial_payment' => 'boolean',
        'split_cash_amount' => 'decimal:2',
        'split_debit_amount' => 'decimal:2',
        'split_second_non_cash_amount' => 'decimal:2',
        'is_walk_in' => 'boolean',
        'is_booking' => 'boolean',
        'paid_at' => 'datetime',
    ];

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function tableSession()
    {
        return $this->belongsTo(TableSession::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function payments()
    {
        return $this->hasMany(BillingPayment::class);
    }

    public function recalculatePaymentStatus(): void
    {
        $totalPaid = (float) $this->payments()->sum('amount_paid');
        $grandTotal = (float) $this->grand_total;
        $remaining = max(0, $grandTotal - $totalPaid);

        $this->paid_amount = $totalPaid;
        $this->remaining_balance = $remaining;

        if ($remaining <= 0.01 && $totalPaid > 0) {
            $this->billing_status = 'paid';
            $this->is_debt = false;
            $this->is_parsial_payment = false;
        } elseif ($totalPaid > 0) {
            $this->billing_status = 'partially_paid';
            $this->is_debt = true;
            $this->is_parsial_payment = true;
        }

        $this->save();
    }
}
