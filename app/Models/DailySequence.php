<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class DailySequence extends Model
{
    protected $fillable = [
        'date',
        'scope',
        'value',
    ];

    protected function casts(): array
    {
        return [
            // Kept as a plain "Y-m-d" string on purpose: the date cast
            // would store "Y-m-d 00:00:00" and break equality lookups.
            'value' => 'integer',
        ];
    }

    /**
     * Reserve the next number for a scope on a date.
     *
     * Row-level lock (lockForUpdate) makes concurrent reservations
     * serialize on the same row, so no two callers can receive the
     * same number. When called outside an explicit transaction the
     * reservation runs in its own short transaction.
     *
     * @param  string  $scope  Sequence namespace, e.g. "orders:booking".
     * @param  string  $date  Y-m-d the number is for.
     * @param  int|null  $initialValue  Seed used only when the row is first
     *                                  created — bridges the old count-based
     *                                  numbering so numbers keep rising.
     */
    public static function next(string $scope, string $date, ?int $initialValue = null): int
    {
        $reserve = function () use ($scope, $date, $initialValue): int {
            $sequence = self::query()
                ->where('scope', $scope)
                ->where('date', $date)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                try {
                    $sequence = self::query()->create([
                        'scope' => $scope,
                        'date' => $date,
                        'value' => max(0, (int) $initialValue),
                    ]);
                } catch (QueryException) {
                    // Row was created by a concurrent reservation; take
                    // over that row under lock instead of failing.
                    $sequence = self::query()
                        ->where('scope', $scope)
                        ->where('date', $date)
                        ->lockForUpdate()
                        ->firstOrFail();
                }
            }

            $sequence->value += 1;
            $sequence->save();

            return (int) $sequence->value;
        };

        if (DB::transactionLevel() > 0) {
            return $reserve();
        }

        return DB::transaction($reserve);
    }
}
