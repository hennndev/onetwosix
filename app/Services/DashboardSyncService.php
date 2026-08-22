<?php

namespace App\Services;

use App\Models\BarOrderItem;
use App\Models\Billing;
use App\Models\Dashboard;
use App\Models\KitchenOrderItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RecapHistory;
use App\Models\TableSession;

class DashboardSyncService
{
    public function sync(?int $areaId = null): Dashboard
    {
        [$windowStart, $windowEnd] = RecapHistory::resolveActiveWindow($areaId);
        $lastCloseAt = RecapHistory::query()
            ->when($areaId, fn ($q) => $q->where('area_id', $areaId))
            ->latest('created_at')
            ->value('created_at');

        $totals = [
            'total_amount' => 0.0,
            'total_food' => 0.0,
            'total_alcohol' => 0.0,
            'total_beverage' => 0.0,
            'total_cigarette' => 0.0,
            'total_breakage' => 0.0,
            'total_room' => 0.0,
            'total_staff_meal' => 0.0,
            'total_compliment_quantity' => 0,
            'total_foc_quantity' => 0,
            'total_ld' => 0.0,
            'total_ld_quantity' => 0,
            'total_penjualan_rokok' => 0.0,
            'total_tax' => 0.0,
            'total_service_charge' => 0.0,
            'total_dp' => 0.0,
            'total_cash' => 0.0,
            'total_transfer' => 0.0,
            'total_debit' => 0.0,
            'total_kredit' => 0.0,
            'total_qris' => 0.0,
            'total_foc_amount' => 0.0,
            'total_compliment_amount' => 0.0,
            'total_kitchen_items' => 0,
            'total_bar_items' => 0,
            'total_transactions' => 0,
        ];

        $totals['total_kitchen_items'] = (int) KitchenOrderItem::query()
            ->whereHas('kitchenOrder', function ($query) use ($windowStart, $windowEnd, $areaId): void {
                $query->where('created_at', '>=', $windowStart)
                    ->where('created_at', '<', $windowEnd)
                    ->when($areaId, fn ($q) => $q->where(fn ($sub) => $sub->where('area_id', $areaId)->orWhereHas('order.tableSession.table', fn ($t) => $t->where('area_id', $areaId))));
            })
            ->when($lastCloseAt, fn ($query) => $query->whereHas('kitchenOrder', fn ($innerQuery) => $innerQuery->where('created_at', '>', $lastCloseAt)))
            ->sum('quantity');

        $totals['total_bar_items'] = (int) BarOrderItem::query()
            ->whereHas('barOrder', function ($query) use ($windowStart, $windowEnd, $areaId): void {
                $query->where('created_at', '>=', $windowStart)
                    ->where('created_at', '<', $windowEnd)
                    ->when($areaId, fn ($q) => $q->where(fn ($sub) => $sub->where('area_id', $areaId)->orWhereHas('order.tableSession.table', fn ($t) => $t->where('area_id', $areaId))));
            })
            ->when($lastCloseAt, fn ($query) => $query->whereHas('barOrder', fn ($innerQuery) => $innerQuery->where('created_at', '>', $lastCloseAt)))
            ->sum('quantity');

        $paidBillings = Billing::query()
            ->where('billing_status', 'paid')
            ->when($areaId, fn ($query) => $query->where(fn ($sub) => $sub->where('area_id', $areaId)->orWhereHas('tableSession.table', fn ($t) => $t->where('area_id', $areaId))))
            ->where(function ($query) use ($windowStart, $windowEnd): void {
                $query->where(function ($paidAtQuery) use ($windowStart, $windowEnd): void {
                    $paidAtQuery->whereNotNull('paid_at')
                        ->where('paid_at', '>=', $windowStart)
                        ->where('paid_at', '<', $windowEnd);
                })->orWhere(function ($fallbackQuery) use ($windowStart, $windowEnd): void {
                    $fallbackQuery->whereNull('paid_at')
                        ->where('updated_at', '>=', $windowStart)
                        ->where('updated_at', '<', $windowEnd);
                });
            })
            ->where(function ($query) {
                $query->where('is_booking', true)
                    ->orWhere('is_walk_in', true);
            })
            ->when($lastCloseAt, function ($query) use ($lastCloseAt): void {
                $query->where(function ($lastCloseQuery) use ($lastCloseAt): void {
                    $lastCloseQuery->where(function ($paidAtQuery) use ($lastCloseAt): void {
                        $paidAtQuery->whereNotNull('paid_at')
                            ->where('paid_at', '>', $lastCloseAt);
                    })->orWhere(function ($fallbackQuery) use ($lastCloseAt): void {
                        $fallbackQuery->whereNull('paid_at')
                            ->where('updated_at', '>', $lastCloseAt);
                    });
                });
            })
            ->get();

        $bookingSessionIds = $paidBillings
            ->filter(fn (Billing $billing): bool => (bool) $billing->is_booking)
            ->filter(fn (Billing $billing): bool => ! in_array((string) ($billing->foc_comp_payment_method ?? ''), ['FOC', 'Compliment'], true))
            ->pluck('table_session_id')
            ->filter()
            ->unique()
            ->values();

        $walkInOrderIds = $paidBillings
            ->filter(fn (Billing $billing): bool => (bool) $billing->is_walk_in)
            ->filter(fn (Billing $billing): bool => ! in_array((string) ($billing->foc_comp_payment_method ?? ''), ['FOC', 'Compliment'], true))
            ->pluck('order_id')
            ->filter()
            ->unique()
            ->values();

        $totals['total_dp'] = (float) TableSession::query()
            ->with('reservation:id,down_payment_amount')
            ->whereIn('id', $bookingSessionIds->all())
            ->get()
            ->sum(fn (TableSession $session): float => (float) ($session->reservation?->down_payment_amount ?? 0));

        $relatedOrderIds = collect();

        if ($walkInOrderIds->isNotEmpty() || $bookingSessionIds->isNotEmpty()) {
            $relatedOrderIds = Order::query()
                ->where(function ($query) use ($walkInOrderIds, $bookingSessionIds): void {
                    if ($walkInOrderIds->isNotEmpty()) {
                        $query->orWhereIn('id', $walkInOrderIds->all());
                    }

                    if ($bookingSessionIds->isNotEmpty()) {
                        $query->orWhereIn('table_session_id', $bookingSessionIds->all());
                    }
                })
                ->where('status', '!=', 'cancelled')
                ->pluck('id');
        }

        $totals['total_penjualan_rokok'] = (float) OrderItem::query()
            ->whereIn('order_id', $relatedOrderIds->all())
            ->where('status', '!=', 'cancelled')
            ->whereHas('inventoryItem', function ($query): void {
                $query->whereRaw('LOWER(TRIM(category_type)) like ?', ['%rokok%']);
            })
            ->sum('quantity');

        $categoryMainAmountMap = OrderItem::query()
            ->selectRaw('LOWER(TRIM(COALESCE(inventory_items.category_main, ""))) as category_key')
            ->selectRaw('SUM(order_items.subtotal) as total_amount')
            ->join('inventory_items', 'inventory_items.id', '=', 'order_items.inventory_item_id')
            ->whereIn('order_items.order_id', $relatedOrderIds->all())
            ->where('order_items.status', '!=', 'cancelled')
            ->groupBy('category_key')
            ->pluck('total_amount', 'category_key');

        $categoryMainQuantityMap = OrderItem::query()
            ->selectRaw('LOWER(TRIM(COALESCE(inventory_items.category_main, ""))) as category_key')
            ->selectRaw('SUM(order_items.quantity) as total_quantity')
            ->join('inventory_items', 'inventory_items.id', '=', 'order_items.inventory_item_id')
            ->whereIn('order_items.order_id', $relatedOrderIds->all())
            ->where('order_items.status', '!=', 'cancelled')
            ->groupBy('category_key')
            ->pluck('total_quantity', 'category_key');

        $totals['total_food'] = (float) ($categoryMainAmountMap['food'] ?? 0);
        $totals['total_alcohol'] = (float) ($categoryMainAmountMap['alcohol'] ?? 0);
        $totals['total_beverage'] = (float) ($categoryMainAmountMap['beverage'] ?? 0);
        $totals['total_cigarette'] = (float) ($categoryMainAmountMap['cigarette'] ?? 0);
        $totals['total_breakage'] = (float) ($categoryMainAmountMap['breakage'] ?? 0);
        $totals['total_room'] = (float) ($categoryMainAmountMap['room'] ?? 0);
        $totals['total_staff_meal'] = (float) ($categoryMainAmountMap['staff_meal'] ?? 0);
        // total_foc_quantity / total_compliment_quantity dihitung per billing di loop bawah.
        $totals['total_ld'] = (float) ($categoryMainAmountMap['ld'] ?? 0);
        $totals['total_ld_quantity'] = (int) ($categoryMainQuantityMap['ld'] ?? 0);

        foreach ($paidBillings as $billing) {
            $paidAmount = (float) ($billing->paid_amount ?? $billing->grand_total ?? 0);

            $totals['total_transactions']++;

            // FOC/Compliment dipisah dari gross/net — grouping sendiri, bukan revenue.
            $focCompMethod = (string) ($billing->foc_comp_payment_method ?? '');
            if (in_array($focCompMethod, ['FOC', 'Compliment'], true)) {
                // Qty & nilai FOC/Compliment dari order items billing FOC/Compliment.
                // Nilai = SUM(subtotal) — paid_amount/grand_total billing FOC selalu 0
                // karena diskon diterapkan ke semua item.
                // Walk-in pakai order_id; booking pakai table_session_id.
                // Tak andalkan is_booking (default DB bisa '1' walau walk-in).
                $focCompOrderId = filled($billing->order_id)
                    ? collect([$billing->order_id])
                    : (filled($billing->table_session_id)
                        ? Order::query()->where('table_session_id', $billing->table_session_id)->where('status', '!=', 'cancelled')->pluck('id')
                        : collect());

                $focCompSums = OrderItem::query()
                    ->whereIn('order_id', $focCompOrderId)
                    ->where('status', '!=', 'cancelled')
                    ->selectRaw('COALESCE(SUM(quantity), 0) as total_qty, COALESCE(SUM(subtotal), 0) as total_subtotal')
                    ->first();

                $focCompQty = (int) ($focCompSums->total_qty ?? 0);
                $focCompAmount = (float) ($focCompSums->total_subtotal ?? 0);

                if ($focCompMethod === 'FOC') {
                    $totals['total_foc_amount'] += $focCompAmount;
                    $totals['total_foc_quantity'] += $focCompQty;
                } else {
                    $totals['total_compliment_amount'] += $focCompAmount;
                    $totals['total_compliment_quantity'] += $focCompQty;
                }

                continue; // skip total_amount, tax, service, dan bucket metode.
            }

            $totals['total_amount'] += $paidAmount;
            $totals['total_tax'] += (float) ($billing->tax ?? 0);
            $totals['total_service_charge'] += (float) ($billing->service_charge ?? 0);

            if (strtolower((string) ($billing->payment_mode ?? 'normal')) === 'split') {
                $splitCashAmount = (float) ($billing->split_cash_amount ?? 0);
                $splitNonCashAmount = (float) ($billing->split_debit_amount ?? 0);
                $splitSecondNonCashAmount = (float) ($billing->split_second_non_cash_amount ?? 0);

                $totals['total_cash'] += $splitCashAmount;

                if ($splitNonCashAmount <= 0 && $splitSecondNonCashAmount <= 0) {
                    $splitNonCashAmount = max($paidAmount - $splitCashAmount, 0);
                }

                $splitNonCashMethod = $billing->split_non_cash_method ?: 'debit';
                $this->addPaymentAmount($totals, $this->normalizePaymentMethod($splitNonCashMethod), $splitNonCashAmount);

                if ($splitSecondNonCashAmount > 0) {
                    $splitSecondNonCashMethod = $billing->split_second_non_cash_method ?: 'debit';
                    $this->addPaymentAmount($totals, $this->normalizePaymentMethod($splitSecondNonCashMethod), $splitSecondNonCashAmount);
                }

                continue;
            }

            $this->addPaymentAmount($totals, $this->normalizePaymentMethod($billing->payment_method), $paidAmount);
        }

        $query = Dashboard::query();
        if ($areaId) {
            $query->where('area_id', $areaId);
        } else {
            $query->whereNull('area_id');
        }

        $existing = $query->first();

        if ($existing) {
            $existing->update([
                ...$totals,
                'last_synced_at' => now(),
            ]);

            return $existing;
        }

        return Dashboard::query()->create([
            'area_id' => $areaId,
            ...$totals,
            'last_synced_at' => now(),
        ]);
    }

    public function syncAll(): void
    {
        $this->sync(null);
        $areas = \App\Models\Area::where('is_active', true)->get();
        foreach ($areas as $area) {
            $this->sync($area->id);
        }
    }

    private function normalizePaymentMethod(?string $paymentMethod): ?string
    {
        return match (strtolower(trim((string) $paymentMethod))) {
            'cash' => 'total_cash',
            'transfer' => 'total_transfer',
            'debit', 'debit-card' => 'total_debit',
            'kredit', 'credit-card' => 'total_kredit',
            'qris' => 'total_qris',
            default => null,
        };
    }

    /**
     * @param  array<string, float|int>  $totals
     */
    private function addPaymentAmount(array &$totals, ?string $bucket, float $amount): void
    {
        if ($bucket === null || $amount <= 0 || ! array_key_exists($bucket, $totals)) {
            return;
        }

        $totals[$bucket] += $amount;
    }
}
