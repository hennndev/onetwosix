<?php

namespace App\Jobs;

use App\Models\Billing;
use App\Models\Tabel;
use App\Models\TableSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NightlyTableReset implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        DB::transaction(function (): void {
            $activeSessions = TableSession::query()
                ->where('status', 'active')
                ->with(['table', 'reservation'])
                ->get();

            // Load all unpaid billings for active sessions keyed by table_session_id
            $unpaidBillings = Billing::query()
                ->whereIn('table_session_id', $activeSessions->pluck('id'))
                ->whereNotIn('billing_status', ['paid'])
                ->get()
                ->keyBy('table_session_id');

            foreach ($activeSessions as $session) {
                // Recalculate totals and force-close unpaid billing — it stays in history
                if ($unpaidBillings->has($session->id)) {
                    $billing = $unpaidBillings->get($session->id);
                    // Force-closed bills record only what was actually ordered —
                    // minimum charge is not enforced since the bill was never paid. Tax not yet implemented.
                    $ordersTotal = $session->orders()->sum('total');
                    $grandTotal = (float) $ordersTotal - (float) $billing->discount_amount;

                    $billing->update([
                        'orders_total' => $ordersTotal,
                        'subtotal' => $ordersTotal,
                        'tax' => 0,
                        'grand_total' => $grandTotal,
                        'billing_status' => 'force_closed',
                        'closing_notes' => 'Auto-closed by system at end of operating hours.',
                    ]);
                }

                $session->update([
                    'status' => 'force_closed',
                    'checked_out_at' => now(),
                ]);

                if ($session->reservation && ! in_array($session->reservation->status, ['completed', 'cancelled', 'rejected', 'force_closed'])) {
                    $session->reservation->update(['status' => 'force_closed']);
                }

                if ($session->table) {
                    $session->table->update(['status' => 'available']);
                }
            }

            // Reset any stray reserved/occupied tables that have no active session
            $activeTableIds = TableSession::query()
                ->where('status', 'active')
                ->pluck('table_id');

            Tabel::query()
                ->whereIn('status', ['reserved', 'occupied'])
                ->whereNotIn('id', $activeTableIds)
                ->update(['status' => 'available']);
        });
    }
}
