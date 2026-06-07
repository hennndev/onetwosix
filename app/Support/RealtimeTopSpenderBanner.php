<?php

namespace App\Support;

use App\Models\TableSession;

class RealtimeTopSpenderBanner
{
    /**
     * @return array<string, mixed>|null
     */
    public function current(): ?array
    {
        /** @var TableSession|null $topSession */
        $topSession = TableSession::query()
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
            ->get()
            ->sortByDesc(fn (TableSession $session): float => $this->resolveRunningSubtotal($session))
            ->first();

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
