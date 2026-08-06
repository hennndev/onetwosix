<?php

namespace App\Support;

use App\Models\TableSession;

class RealtimeTopSpenderBanner
{
    /**
     * @return array<string, mixed>|null
     */
    public function current(?int $areaId = null): ?array
    {
        $topSession = $this->topSessions(1, $areaId)->first();

        if (! $topSession) {
            return null;
        }

        return [
            'customer_name' => $this->resolveCustomerName($topSession),
            'table_number' => $topSession->table?->table_number,
            'area_name' => $topSession->table?->area?->name,
            'orders_subtotal' => $this->resolveRunningSubtotal($topSession),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function topSpenders(int $limit = 3, ?int $areaId = null): array
    {
        return $this->topSessions($limit, $areaId)
            ->values()
            ->map(function (TableSession $session, int $index): array {
                return [
                    'rank' => $index + 1,
                    'customer_name' => $this->resolveCustomerName($session),
                    'table_number' => $session->table?->table_number,
                    'area_name' => $session->table?->area?->name,
                    'orders_subtotal' => $this->resolveRunningSubtotal($session),
                ];
            })
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, TableSession>
     */
    protected function topSessions(int $limit, ?int $areaId = null): \Illuminate\Support\Collection
    {
        return TableSession::query()
            ->with([
                'table.area',
                'reservation.customer.profile',
                'reservation.customer.customerUser',
                'customer.profile',
                'customer.customerUser',
            ])
            ->withSum([
                'orderItems as realtime_items_subtotal' => fn ($query) => $query->where('order_items.status', '!=', 'cancelled'),
            ], 'subtotal')
            ->withSum([
                'orderItems as realtime_items_tax_total' => fn ($query) => $query->where('order_items.status', '!=', 'cancelled'),
            ], 'tax_amount')
            ->withSum([
                'orderItems as realtime_items_service_charge_total' => fn ($query) => $query->where('order_items.status', '!=', 'cancelled'),
            ], 'service_charge_amount')
            ->where('status', 'active')
            ->whereNotNull('table_reservation_id')
            ->when($areaId, fn ($q) => $q->whereHas('table', fn ($t) => $t->where('area_id', $areaId)))
            ->get()
            ->sortByDesc(fn (TableSession $session): float => $this->resolveRunningSubtotal($session))
            ->take($limit);
    }

    protected function resolveRunningSubtotal(TableSession $session): float
    {
        return (float) ($session->realtime_items_subtotal ?? 0)
            + (float) ($session->realtime_items_tax_total ?? 0)
            + (float) ($session->realtime_items_service_charge_total ?? 0);
    }

    protected function resolveCustomerName(TableSession $session): string
    {
        return (string) (
            $session->reservation?->booking_name
            ?? $session->reservation?->customer?->profile?->name
            ?? $session->reservation?->customer?->customerUser?->name
            ?? $session->reservation?->customer?->name
            ?? $session->customer?->profile?->name
            ?? $session->customer?->customerUser?->name
            ?? $session->customer?->name
            ?? 'Tamu'
        );
    }
}
