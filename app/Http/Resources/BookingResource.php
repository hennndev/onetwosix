<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TableReservation */
class BookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_code' => $this->booking_code,
            'booking_code_formatted' => $this->booking_code_formatted,
            'booking_name' => $this->booking_name,
            'reservation_date' => $this->reservation_date->format('Y-m-d'),
            'reservation_time' => $this->reservation_time,
            'status' => $this->status,
            'note' => $this->note,
            'down_payment_amount' => (float) $this->down_payment_amount,
            'check_in_qr_code' => $this->check_in_qr_code,
            'check_in_qr_expires_at' => $this->check_in_qr_expires_at?->toIso8601String(),
            'table' => new TableResource($this->whenLoaded('table')),
            'transaction_history' => $this->whenLoaded('tableSession', fn () => $this->transactionHistory()),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionHistory(): array
    {
        $tableSession = $this->tableSession;

        if (! $tableSession) {
            return [
                'table_session' => null,
                'billing' => null,
                'orders' => [],
                'song_requests' => [],
                'display_messages' => [],
            ];
        }

        return [
            'table_session' => [
                'id' => $tableSession->id,
                'session_code' => $tableSession->session_code,
                'status' => $tableSession->status,
                'checked_in_at' => $tableSession->checked_in_at?->toIso8601String(),
                'checked_out_at' => $tableSession->checked_out_at?->toIso8601String(),
            ],
            'billing' => $tableSession->relationLoaded('billing') && $tableSession->billing
                ? $this->billingPayload($tableSession->billing)
                : null,
            'orders' => $tableSession->relationLoaded('orders')
                ? $tableSession->orders->map(fn ($order): array => $this->orderPayload($order))->values()
                : [],
            'song_requests' => $tableSession->relationLoaded('songRequests')
                ? $tableSession->songRequests->map(fn ($songRequest): array => [
                    'id' => $songRequest->id,
                    'song_title' => $songRequest->song_title,
                    'artist' => $songRequest->artist,
                    'tip' => (float) $songRequest->tip,
                    'status' => $songRequest->status,
                    'created_at' => $songRequest->created_at?->toIso8601String(),
                ])->values()
                : [],
            'display_messages' => $tableSession->relationLoaded('displayMessageRequests')
                ? $tableSession->displayMessageRequests->map(fn ($displayMessage): array => [
                    'id' => $displayMessage->id,
                    'message' => $displayMessage->message,
                    'tip' => (int) $displayMessage->tip,
                    'status' => $displayMessage->status,
                    'created_at' => $displayMessage->created_at?->toIso8601String(),
                ])->values()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function billingPayload($billing): array
    {
        $songTip = (float) $billing->song_tip;
        $displayTip = (float) $billing->display_tip;
        $billingDiscountAmount = (float) $billing->discount_amount;
        $discountAmount = $billingDiscountAmount + $this->orderDiscountAmount();
        $baseGrandTotal = max(
            ((float) $billing->subtotal + (float) $billing->tax + (float) $billing->service_charge - $billingDiscountAmount) - (float) $this->down_payment_amount,
            0,
        );
        $grandTotalWithTips = $baseGrandTotal + $songTip + $displayTip;

        return [
            'id' => $billing->id,
            'transaction_code' => $billing->transaction_code,
            'status' => $billing->billing_status,
            'minimum_charge' => (float) $billing->minimum_charge,
            'orders_total' => (float) $billing->orders_total,
            'subtotal' => (float) $billing->subtotal,
            'tax' => (float) $billing->tax,
            'service_charge' => (float) $billing->service_charge,
            'discount' => $discountAmount,
            'discount_amount' => $billingDiscountAmount,
            'order_discount_amount' => $this->orderDiscountAmount(),
            'song_tip' => $songTip,
            'display_tip' => $displayTip,
            'grand_total' => $grandTotalWithTips,
            'paid_amount' => (float) $billing->paid_amount,
            'payment_mode' => $billing->payment_mode,
            'payment_method' => $billing->payment_method,
            'payment_reference_number' => $billing->payment_reference_number,
            'paid_at' => $billing->paid_at?->toIso8601String(),
        ];
    }

    private function orderDiscountAmount(): float
    {
        $tableSession = $this->tableSession;

        if (! $tableSession || ! $tableSession->relationLoaded('orders')) {
            return 0.0;
        }

        return (float) $tableSession->orders
            ->where('status', '!=', 'cancelled')
            ->sum(fn ($order): float => (float) $order->discount_amount);
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPayload($order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'items_total' => (float) $order->items_total,
            'discount_amount' => (float) $order->discount_amount,
            'total' => (float) $order->total,
            'ordered_at' => $order->ordered_at?->toIso8601String(),
            'completed_at' => $order->completed_at?->toIso8601String(),
            'notes' => $order->notes,
            'items' => $order->relationLoaded('items')
                ? $order->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'inventory_item_id' => $item->inventory_item_id,
                    'item_name' => $item->item_name,
                    'item_code' => $item->item_code,
                    'quantity' => (int) $item->quantity,
                    'price' => (float) $item->price,
                    'subtotal' => (float) $item->subtotal,
                    'discount_amount' => (float) $item->discount_amount,
                    'preparation_location' => $item->preparation_location,
                    'status' => $item->status,
                ])->values()
                : [],
        ];
    }
}
