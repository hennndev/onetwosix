<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\DailySequence;
use App\Models\Order;

class OrderNumberGenerator
{
    /**
     * Generate the next daily order number, e.g. "ORD-20260921-0053".
     *
     * Numbers come from a locked daily sequence so concurrent checkouts
     * (cashier POS, waiter POS, move-order) can never draw the same one.
     *
     * @param  string  $prefix  "ORD" for booking orders, "WALKIN" for walk-in.
     * @param  string  $scope  "booking" or "walk-in".
     */
    public function orderNumber(string $prefix, string $scope): string
    {
        $date = today()->toDateString();

        $sequence = DailySequence::next(
            scope: "orders:{$scope}",
            date: $date,
            // Seed from the legacy count-based scheme so the transition
            // keeps numbers rising instead of restarting at 0001.
            initialValue: $this->legacyOrderCount($scope, $date),
        );

        return sprintf('%s-%s-%04d', $prefix, today()->format('Ymd'), $sequence);
    }

    /**
     * Generate the next walk-in billing transaction code, e.g. "WALKIN-000052".
     */
    public function walkInTransactionCode(): string
    {
        $date = today()->toDateString();

        $sequence = DailySequence::next(
            scope: 'billing:walk-in',
            date: $date,
            initialValue: Billing::query()
                ->where('is_walk_in', true)
                ->whereDate('created_at', $date)
                ->count(),
        );

        return 'WALKIN-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate the next booking billing transaction code, e.g. "BILLING-000123".
     *
     * Used when a booking's bill is closed. The code doubles as the reference
     * sent to Accurate, so duplicates must not happen.
     */
    public function bookingTransactionCode(): string
    {
        $date = today()->toDateString();

        $sequence = DailySequence::next(
            scope: 'billing:booking',
            date: $date,
            initialValue: Billing::query()
                ->where('is_booking', true)
                ->whereDate('created_at', $date)
                ->count(),
        );

        return 'BILLING-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    private function legacyOrderCount(string $scope, string $date): int
    {
        return Order::query()
            ->whereDate('created_at', $date)
            ->when($scope === 'walk-in', fn ($query) => $query->whereNull('table_session_id'))
            ->count();
    }
}
