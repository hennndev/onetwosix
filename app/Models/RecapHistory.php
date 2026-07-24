<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecapHistory extends Model
{
    protected $table = 'recap_history';

    protected $fillable = [
        'end_day',
        'total_amount',
        'total_food',
        'total_alcohol',
        'total_beverage',
        'total_cigarette',
        'total_breakage',
        'total_room',
        'total_staff_meal',
        'total_compliment_quantity',
        'total_foc_quantity',
        'total_ld',
        'total_ld_quantity',
        'total_penjualan_rokok',
        'total_tax',
        'total_service_charge',
        'total_dp',
        'total_cash',
        'total_transfer',
        'total_debit',
        'total_kredit',
        'total_qris',
        'total_kitchen_items',
        'total_bar_items',
        'total_transactions',
        'last_synced_at',
    ];

    protected $casts = [
        'end_day' => 'date',
        'total_amount' => 'decimal:2',
        'total_food' => 'decimal:2',
        'total_alcohol' => 'decimal:2',
        'total_beverage' => 'decimal:2',
        'total_cigarette' => 'decimal:2',
        'total_breakage' => 'decimal:2',
        'total_room' => 'decimal:2',
        'total_staff_meal' => 'decimal:2',
        'total_compliment_quantity' => 'integer',
        'total_foc_quantity' => 'integer',
        'total_ld' => 'decimal:2',
        'total_ld_quantity' => 'integer',
        'total_penjualan_rokok' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'total_service_charge' => 'decimal:2',
        'total_dp' => 'decimal:2',
        'total_cash' => 'decimal:2',
        'total_transfer' => 'decimal:2',
        'total_debit' => 'decimal:2',
        'total_kredit' => 'decimal:2',
        'total_qris' => 'decimal:2',
        'total_kitchen_items' => 'integer',
        'total_bar_items' => 'integer',
        'total_transactions' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Determine the end_day date label for the next closing.
     *
     * Uses the latest RecapHistory to calculate the next day sequentially,
     * avoiding collision bugs caused by hardcoded hour-based heuristics.
     */
    public static function resolveNextEndDay(): string
    {
        $now = now('Asia/Jakarta');
        $maxOperationalDay = $now->hour < 9
            ? $now->copy()->subDay()->toDateString()
            : $now->toDateString();

        $latestRecap = self::query()->latest('end_day')->first();

        if ($latestRecap) {
            $nextDay = $latestRecap->end_day->copy()->addDay()->toDateString();

            if ($nextDay > $maxOperationalDay) {
                return $maxOperationalDay;
            }

            return $nextDay;
        }

        return $maxOperationalDay;
    }

    /**
     * Resolve the active operational window based on the latest closed recap.
     *
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}
     */
    public static function resolveActiveWindow(): array
    {
        $now = now('Asia/Jakarta');
        $defaultAnchor = $now->copy()->setTime(9, 0, 0);

        // Find the latest closed recap
        $latestRecap = self::query()->latest('end_day')->first();

        if (! $latestRecap) {
            // Fallback to default operational window if no history exists
            if ($now->lt($defaultAnchor)) {
                return [
                    $defaultAnchor->copy()->subDay(),
                    $defaultAnchor->copy()->subSecond(),
                ];
            }

            return [
                $defaultAnchor,
                $defaultAnchor->copy()->addDay()->subSecond(),
            ];
        }

        // Start time is the exact time the previous recap was closed
        $startAt = $latestRecap->created_at->copy()->timezone('Asia/Jakarta');

        // Calculate expected end time based on next day to close
        $nextDayToClose = $latestRecap->end_day->copy()->addDay()->timezone('Asia/Jakarta');
        $expectedEndAt = $nextDayToClose->copy()->setTime(9, 0, 0)->addDay()->subSecond();

        // If now() has passed the expected end (e.g., outlet was closed for holidays),
        // extend the window to cover today's operational cycle
        if ($now->gt($expectedEndAt)) {
            $endAt = $now->lt($defaultAnchor)
                ? $defaultAnchor->copy()->subSecond()
                : $defaultAnchor->copy()->addDay()->subSecond();
        } else {
            $endAt = $expectedEndAt;
        }

        return [$startAt, $endAt];
    }

    /**
     * Resolve the exact start and end times for any given calendar date's operational cycle.
     *
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}
     */
    public static function resolveWindowForDate(\Illuminate\Support\Carbon $endDay): array
    {
        $endDay = $endDay->copy()->timezone('Asia/Jakarta');

        // Find if the previous day was closed
        $previousRecap = self::query()
            ->whereDate('end_day', $endDay->copy()->subDay()->toDateString())
            ->first();

        // Start time is the previous day's closing time, or fallback to default anchor (09:00 AM on $endDay)
        $startAt = $previousRecap
            ? $previousRecap->created_at->copy()->timezone('Asia/Jakarta')
            : $endDay->copy()->setTime(9, 0, 0);

        // Find if the current day itself was closed
        $currentRecap = self::query()
            ->whereDate('end_day', $endDay->toDateString())
            ->first();

        // End time is the current day's closing time, or fallback to default anchor (08:59:59 AM next day)
        $endAt = $currentRecap
            ? $currentRecap->created_at->copy()->timezone('Asia/Jakarta')
            : $endDay->copy()->setTime(9, 0, 0)->addDay()->subSecond();

        return [$startAt, $endAt];
    }
}
