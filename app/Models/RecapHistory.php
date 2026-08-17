<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecapHistory extends Model
{
    protected $table = 'recap_history';

    protected $fillable = [
        'area_id',
        'end_day',
        'opened_at',
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
        'total_foc_amount',
        'total_compliment_amount',
        'total_kitchen_items',
        'total_bar_items',
        'total_transactions',
        'last_synced_at',
    ];

    protected $casts = [
        'area_id' => 'integer',
        'end_day' => 'date',
        'opened_at' => 'datetime',
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

    public function area(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * Jam mulai operasional dari GeneralSetting (fallback '09:00').
     *
     * Hanya dipakai sebagai fallback window saat recap belum ada pada tanggal yang
     * bersangkutan; tanggal yang sudah di-close selalu dipandu oleh opened_at/created_at recap.
     *
     * @return \Illuminate\Support\Carbon Carbon $base dengan jam mulai operasional (Asia/Jakarta)
     */
    public static function resolveOperationalAnchor(\Illuminate\Support\Carbon $base): \Illuminate\Support\Carbon
    {
        [$hour, $minute] = array_map('intval', explode(':', \App\Models\GeneralSetting::instance()->operationalAnchorTime()));

        return $base->copy()->setTime($hour, $minute, 0);
    }

    /**
     * Determine the end_day date label for the next closing.
     *
     * Uses the latest RecapHistory to calculate the next day sequentially,
     * avoiding collision bugs caused by hardcoded hour-based heuristics.
     */
    public static function resolveNextEndDay(?int $areaId = null): string
    {
        $now = now('Asia/Jakarta');
        $maxOperationalDay = $now->lt(self::resolveOperationalAnchor($now))
            ? $now->copy()->subDay()->toDateString()
            : $now->toDateString();

        $latestRecap = self::query()
            ->when($areaId, fn ($q) => $q->where('area_id', $areaId))
            ->latest('end_day')
            ->first();

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
     * End_day berikutnya untuk consumer yang butuh label hari operasi berjalan
     * (kitchen, bar, waiter performance).
     *
     * Jika recap terakhir menutup kemarin lebih awal (created_at di bawah anchor
     * hari ini), hari operasi baru sudah berjalan sejak created_at tersebut —
     * end_day = hari ini, bukan hasil clamp resolveNextEndDay() yang masih
     * menunjuk kemarin (sudah ditutup). Mencegah order dini-hari (mis. jam 7
     * pagi) jatuh di luar window.
     */
    public static function resolveNextEndDayForEarlyClose(?int $areaId = null): string
    {
        $now = now('Asia/Jakarta');
        $latestRecap = self::query()
            ->when($areaId, fn ($q) => $q->where('area_id', $areaId))
            ->latest('end_day')
            ->first();

        if ($latestRecap
            && $latestRecap->created_at
            && $latestRecap->created_at->timezone('Asia/Jakarta')->lt(self::resolveOperationalAnchor($now))
            && $latestRecap->end_day->toDateString() === $now->copy()->subDay()->toDateString()
        ) {
            return $now->toDateString();
        }

        return self::resolveNextEndDay($areaId);
    }

    /**
     * Window end_day untuk consumer kitchen/bar hari berjalan.
     *
     * Preventif gap: jika recap utama terakhir menutup lebih dari sehari sebelum
     * hari operasi aktif (libur/off), collapse end_day ke hari aktif dan extend
     * window dari opened_at recap terakhir sampai anchor(now)+1d — konsisten
     * dengan resolveActiveWindow (dashboard). Kasus normal memakai
     * resolveNextEndDayForEarlyClose + resolveWindowForDate (perilaku existing).
     *
     * @return array{0: string, 1: \Illuminate\Support\Carbon, 2: \Illuminate\Support\Carbon} [endDay, startAt, endAt]
     */
    public static function resolveEndDayWindowForToday(?int $areaId = null): array
    {
        $now = now('Asia/Jakarta');
        $latestRecap = self::query()
            ->when($areaId, fn ($q) => $q->where('area_id', $areaId))
            ->latest('end_day')
            ->first();

        if (! $latestRecap) {
            $endDay = self::resolveNextEndDayForEarlyClose($areaId);
            [$startAt, $endAt] = self::resolveWindowForDate(\Illuminate\Support\Carbon::parse($endDay, 'Asia/Jakarta'), $areaId);

            return [$endDay, $startAt, $endAt];
        }

        $lastEndDay = $latestRecap->end_day->copy()->timezone('Asia/Jakarta')->startOfDay();
        $activeDay = ($now->lt(self::resolveOperationalAnchor($now))
                ? $now->copy()->subDay()
                : $now->copy())->startOfDay();

        // Gap ≥ 1 hari penuh (libur/off): hari operasi aktif lebih dari 1 hari setelah end_day terakhir
        if ($lastEndDay->diffInDays($activeDay) > 1) {
            $endDay = $activeDay->toDateString();
            $startAt = ($latestRecap->opened_at ?? $latestRecap->created_at)?->copy()->timezone('Asia/Jakarta')
                ?? self::resolveOperationalAnchor($endDay);
            $endAt = self::resolveOperationalAnchor($now)->addDay()->subSecond();

            return [$endDay, $startAt, $endAt];
        }

        // Normal (no gap): perilaku existing
        $endDay = self::resolveNextEndDayForEarlyClose($areaId);
        [$startAt, $endAt] = self::resolveWindowForDate(\Illuminate\Support\Carbon::parse($endDay, 'Asia/Jakarta'), $areaId);

        return [$endDay, $startAt, $endAt];
    }

    /**
     * Resolve the active operational window based on the latest closed recap.
     *
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}
     */
    public static function resolveActiveWindow(?int $areaId = null): array
    {
        $now = now('Asia/Jakarta');
        $defaultAnchor = self::resolveOperationalAnchor($now);

        // Find the latest closed recap
        $latestRecap = self::query()
            ->when($areaId, fn ($q) => $q->where('area_id', $areaId))
            ->latest('end_day')
            ->first();

        if (! $latestRecap) {
            // Fallback to default operational window if no history exists
            if ($now->lt($defaultAnchor)) {
                return [
                    $defaultAnchor->copy()->subDay(),
                    $defaultAnchor->copy()->subSecond(),
                ];
            }

            $hasUnclosedPreAnchorBillings = \App\Models\Billing::query()
                ->where('billing_status', 'paid')
                ->when($areaId, fn ($q) => $q->where(fn ($sub) => $sub->where('area_id', $areaId)->orWhereHas('tableSession.table', fn ($t) => $t->where('area_id', $areaId))))
                ->where(function ($q) use ($defaultAnchor): void {
                    $q->where(function ($paidAtQuery) use ($defaultAnchor): void {
                        $paidAtQuery->whereNotNull('paid_at')
                            ->where('paid_at', '>=', $defaultAnchor->copy()->subDay())
                            ->where('paid_at', '<', $defaultAnchor);
                    })->orWhere(function ($fallbackQuery) use ($defaultAnchor): void {
                        $fallbackQuery->whereNull('paid_at')
                            ->where('updated_at', '>=', $defaultAnchor->copy()->subDay())
                            ->where('updated_at', '<', $defaultAnchor);
                    });
                })
                ->exists();

            if ($hasUnclosedPreAnchorBillings) {
                return [
                    $defaultAnchor->copy()->subDay(),
                    $defaultAnchor->copy()->addDay()->subSecond(),
                ];
            }

            return [
                $defaultAnchor,
                $defaultAnchor->copy()->addDay()->subSecond(),
            ];
        }

        // Start time is the exact time the previous recap was closed (stored opened_at, or created_at fallback)
        $startAt = ($latestRecap->opened_at ?? $latestRecap->created_at)->copy()->timezone('Asia/Jakarta');

        // Calculate expected end time based on next day to close
        $nextDayToClose = $latestRecap->end_day->copy()->addDay()->timezone('Asia/Jakarta');
        $expectedEndAt = self::resolveOperationalAnchor($nextDayToClose)->addDay()->subSecond();

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
    public static function resolveWindowForDate(\Illuminate\Support\Carbon $endDay, ?int $areaId = null): array
    {
        $endDay = $endDay->copy()->timezone('Asia/Jakarta');

        // Find if the current day itself was closed
        $currentRecap = self::query()
            ->when($areaId, fn ($q) => $q->where('area_id', $areaId))
            ->whereDate('end_day', $endDay->toDateString())
            ->first();

        // Find if the previous day was closed
        $previousRecap = self::query()
            ->when($areaId, fn ($q) => $q->where('area_id', $areaId))
            ->whereDate('end_day', $endDay->copy()->subDay()->toDateString())
            ->first();

        // Start time is the current day's stored opened_at, else the previous day's closing time, else default anchor
        $startAt = $currentRecap?->opened_at
            ?? $previousRecap?->created_at?->copy()->timezone('Asia/Jakarta')
            ?? self::resolveOperationalAnchor($endDay);

        // End time is the current day's closing time, or fallback to next day's default operational anchor minus 1s
        $endAt = $currentRecap
            ? $currentRecap->created_at->copy()->timezone('Asia/Jakarta')
            : self::resolveOperationalAnchor($endDay)->addDay()->subSecond();

        return [$startAt, $endAt];
    }
}
