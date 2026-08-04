<?php

namespace App\Services;

use App\Models\GeneralSetting;
use App\Models\TableSession;

class SessionBillingCalculator
{
    /** @return array<string, float> */
    public function calculate(TableSession $session, float $discountAmount, float $minimumCharge): array
    {
        $session->loadMissing('orders.items.inventoryItem');
        $settings = GeneralSetting::instance();
        $orders = $session->orders->where('status', '!=', 'cancelled')->values();
        $ordersTotal = (float) $orders->sum(fn ($order): float => (float) $order->total);
        $subtotal = max($minimumCharge, $ordersTotal);
        $serviceChargeBase = 0.0;
        $taxBase = 0.0;
        $taxAndServiceBase = 0.0;

        foreach ($orders as $order) {
            $items = $order->items->where('status', '!=', 'cancelled')->values();
            $itemsSubtotal = (float) $items->sum(fn ($item): float => max((float) $item->subtotal - (float) $item->discount_amount, 0));
            $ratio = $itemsSubtotal > 0 ? max((float) $order->total, 0) / $itemsSubtotal : 0;

            if ($items->isEmpty()) {
                $serviceChargeBase += max((float) $order->total, 0);
                $taxBase += max((float) $order->total, 0);
                $taxAndServiceBase += max((float) $order->total, 0);

                continue;
            }

            foreach ($items as $item) {
                $netSubtotal = max((float) $item->subtotal - (float) $item->discount_amount, 0) * $ratio;
                $includeTax = (bool) ($item->inventoryItem?->include_tax ?? true);
                $includeServiceCharge = (bool) ($item->inventoryItem?->include_service_charge ?? true);
                $serviceChargeBase += $includeServiceCharge ? $netSubtotal : 0;
                $taxBase += $includeTax ? $netSubtotal : 0;
                $taxAndServiceBase += $includeTax && $includeServiceCharge ? $netSubtotal : 0;
            }
        }

        $tax = round($taxBase * ((float) $settings->tax_percentage / 100), 2);
        $serviceChargeTax = round($taxAndServiceBase * ((float) $settings->tax_percentage / 100), 2);
        $serviceCharge = round(($serviceChargeBase + $serviceChargeTax) * ((float) $settings->service_charge_percentage / 100), 2);
        $beforeDiscount = $subtotal + $serviceCharge + $tax;
        $discountAmount = min(max($discountAmount, 0), $beforeDiscount);

        return [
            'orders_total' => $ordersTotal,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_percentage' => (float) $settings->tax_percentage,
            'tax' => $tax,
            'service_charge_percentage' => (float) $settings->service_charge_percentage,
            'service_charge' => $serviceCharge,
            'grand_total' => max($beforeDiscount - $discountAmount, 0),
        ];
    }
}
