<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\TableSession;
use Illuminate\Support\Facades\DB;

class SessionTipService
{
    /**
     * @return array{song_tip: float, display_tip: float, tip_total: float}
     */
    public function calculate(TableSession $session): array
    {
        $songTip = (float) $session->songRequests()
            ->whereIn('status', ['played', 'completed'])
            ->sum('tip');
        $displayTip = (float) $session->displayMessageRequests()
            ->whereIn('status', ['displayed', 'completed'])
            ->sum('tip');

        return [
            'song_tip' => $songTip,
            'display_tip' => $displayTip,
            'tip_total' => $songTip + $displayTip,
        ];
    }

    public function sync(int|TableSession|null $session): ?Billing
    {
        $sessionId = $session instanceof TableSession ? $session->getKey() : $session;

        if (! $sessionId) {
            return null;
        }

        return DB::transaction(function () use ($sessionId): ?Billing {
            $tableSession = TableSession::query()->find($sessionId);

            if (! $tableSession) {
                return null;
            }

            $billing = Billing::query()
                ->where('table_session_id', $tableSession->id)
                ->lockForUpdate()
                ->first();

            if (! $billing && $tableSession->billing_id) {
                $billing = Billing::query()->lockForUpdate()->find($tableSession->billing_id);
            }

            if (! $billing) {
                return null;
            }

            $tips = $this->calculate($tableSession);
            $grandTotalWithoutTips = max(
                (float) $billing->grand_total - (float) $billing->song_tip - (float) $billing->display_tip,
                0,
            );

            $billing->update([
                'song_tip' => $tips['song_tip'],
                'display_tip' => $tips['display_tip'],
                'grand_total' => $grandTotalWithoutTips + $tips['tip_total'],
            ]);
            $billing->recalculatePaymentStatus();

            return $billing->refresh();
        });
    }
}
