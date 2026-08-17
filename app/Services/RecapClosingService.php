<?php

namespace App\Services;

use App\Models\Dashboard;
use App\Models\RecapHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecapClosingService
{
    /**
     * @return array{status: string, end_day: string, recap_history: ?RecapHistory}
     */
    public function closeDay(?Carbon $closingAt = null, ?int $areaId = null): array
    {
        $closingAt ??= now('Asia/Jakarta');
        $closingAt = $closingAt->copy()->timezone('Asia/Jakarta');
        $endDay = RecapHistory::resolveNextEndDay($areaId);

        // Tolak close kedua dalam rentang pre-anchor: jika sudah ada recap dengan
        // created_at pada kalender hari yang sama untuk end_day yang berbeda,
        // berarti close dini-hari sudah terjadi — jangan seal hari baru prematur.
        $earlyClosedToday = RecapHistory::query()
            ->when($areaId, fn ($q) => $q->where('area_id', $areaId), fn ($q) => $q->whereNull('area_id'))
            ->whereDate('created_at', $closingAt->toDateString())
            ->whereDate('end_day', '<>', $endDay)
            ->exists();

        if ($earlyClosedToday) {
            return [
                'status' => 'already_closed',
                'end_day' => $endDay,
                'recap_history' => RecapHistory::query()
                    ->when($areaId, fn ($q) => $q->where('area_id', $areaId), fn ($q) => $q->whereNull('area_id'))
                    ->whereDate('created_at', $closingAt->toDateString())
                    ->whereDate('end_day', '<>', $endDay)
                    ->latest('created_at')
                    ->first(),
            ];
        }

        return DB::transaction(function () use ($endDay, $areaId): array {
            $dashboardQuery = Dashboard::query();
            if ($areaId) {
                $dashboardQuery->where('area_id', $areaId);
            } else {
                $dashboardQuery->whereNull('area_id');
            }
            $dashboard = $dashboardQuery->first();

            if (! $dashboard) {
                $dashboard = Dashboard::query()->create([
                    'area_id' => $areaId,
                    ...$this->zeroedDashboardPayload(),
                ]);
            }

            $existingHistory = RecapHistory::query()
                ->whereDate('end_day', $endDay)
                ->when($areaId, fn ($q) => $q->where('area_id', $areaId), fn ($q) => $q->whereNull('area_id'))
                ->first();

            if ($existingHistory !== null) {
                return [
                    'status' => 'already_closed',
                    'end_day' => $endDay,
                    'recap_history' => $existingHistory,
                ];
            }

            if (! $this->hasDashboardData($dashboard)) {
                return [
                    'status' => 'no_data',
                    'end_day' => $endDay,
                    'recap_history' => null,
                ];
            }

            // Window hari ini dibuka saat recap sebelumnya ditutup (atau anchor utk recap pertama)
            $previousRecap = RecapHistory::query()
                ->when($areaId, fn ($q) => $q->where('area_id', $areaId), fn ($q) => $q->whereNull('area_id'))
                ->whereDate('end_day', '<', $endDay)
                ->orderByDesc('end_day')
                ->first();

            $recapHistory = RecapHistory::query()->create([
                'area_id' => $areaId,
                'end_day' => $endDay,
                'opened_at' => $previousRecap?->created_at ?? RecapHistory::resolveOperationalAnchor(Carbon::parse($endDay, 'Asia/Jakarta')),
                'total_amount' => (float) $dashboard->total_amount,
                'total_food' => (float) $dashboard->total_food,
                'total_alcohol' => (float) $dashboard->total_alcohol,
                'total_beverage' => (float) $dashboard->total_beverage,
                'total_cigarette' => (float) $dashboard->total_cigarette,
                'total_breakage' => (float) $dashboard->total_breakage,
                'total_room' => (float) $dashboard->total_room,
                'total_staff_meal' => (float) $dashboard->total_staff_meal,
                'total_compliment_quantity' => (int) $dashboard->total_compliment_quantity,
                'total_foc_quantity' => (int) $dashboard->total_foc_quantity,
                'total_foc_amount' => (float) $dashboard->total_foc_amount,
                'total_compliment_amount' => (float) $dashboard->total_compliment_amount,
                'total_ld' => (float) $dashboard->total_ld,
                'total_ld_quantity' => (int) $dashboard->total_ld_quantity,
                'total_penjualan_rokok' => (float) $dashboard->total_penjualan_rokok,
                'total_tax' => (float) $dashboard->total_tax,
                'total_service_charge' => (float) $dashboard->total_service_charge,
                'total_dp' => (float) $dashboard->total_dp,
                'total_cash' => (float) $dashboard->total_cash,
                'total_transfer' => (float) $dashboard->total_transfer,
                'total_debit' => (float) $dashboard->total_debit,
                'total_kredit' => (float) $dashboard->total_kredit,
                'total_qris' => (float) $dashboard->total_qris,
                'total_kitchen_items' => (int) $dashboard->total_kitchen_items,
                'total_bar_items' => (int) $dashboard->total_bar_items,
                'total_transactions' => (int) $dashboard->total_transactions,
                'last_synced_at' => $dashboard->last_synced_at,
            ]);

            $dashboard->update($this->zeroedDashboardPayload());

            return [
                'status' => 'closed',
                'end_day' => $endDay,
                'recap_history' => $recapHistory,
            ];
        });
    }

    private function hasDashboardData(Dashboard $dashboard): bool
    {
        return (float) $dashboard->total_amount > 0
            || (float) $dashboard->total_food > 0
            || (float) $dashboard->total_alcohol > 0
            || (float) $dashboard->total_beverage > 0
            || (float) $dashboard->total_cigarette > 0
            || (float) $dashboard->total_breakage > 0
            || (float) $dashboard->total_room > 0
            || (float) $dashboard->total_staff_meal > 0
            || (int) $dashboard->total_compliment_quantity > 0
            || (int) $dashboard->total_foc_quantity > 0
            || (float) $dashboard->total_foc_amount > 0
            || (float) $dashboard->total_compliment_amount > 0
            || (float) $dashboard->total_ld > 0
            || (int) $dashboard->total_ld_quantity > 0
            || (float) $dashboard->total_penjualan_rokok > 0
            || (float) $dashboard->total_tax > 0
            || (float) $dashboard->total_service_charge > 0
            || (float) $dashboard->total_dp > 0
            || (float) $dashboard->total_cash > 0
            || (float) $dashboard->total_transfer > 0
            || (float) $dashboard->total_debit > 0
            || (float) $dashboard->total_kredit > 0
            || (float) $dashboard->total_qris > 0
            || (int) $dashboard->total_kitchen_items > 0
            || (int) $dashboard->total_bar_items > 0
            || (int) $dashboard->total_transactions > 0;
    }

    /**
     * @return array<string, int|float|null>
     */
    private function zeroedDashboardPayload(): array
    {
        return [
            'total_amount' => 0,
            'total_food' => 0,
            'total_alcohol' => 0,
            'total_beverage' => 0,
            'total_cigarette' => 0,
            'total_breakage' => 0,
            'total_room' => 0,
            'total_staff_meal' => 0,
            'total_compliment_quantity' => 0,
            'total_foc_quantity' => 0,
            'total_foc_amount' => 0,
            'total_compliment_amount' => 0,
            'total_ld' => 0,
            'total_ld_quantity' => 0,
            'total_penjualan_rokok' => 0,
            'total_tax' => 0,
            'total_service_charge' => 0,
            'total_dp' => 0,
            'total_cash' => 0,
            'total_transfer' => 0,
            'total_debit' => 0,
            'total_kredit' => 0,
            'total_qris' => 0,
            'total_kitchen_items' => 0,
            'total_bar_items' => 0,
            'total_transactions' => 0,
            'last_synced_at' => null,
        ];
    }
}
