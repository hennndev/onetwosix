<?php

namespace App\Services;

use App\Models\DailyAuthCode;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\PosDiscountApproval;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosDiscountService
{
    /**
     * @param  array<string, array<string, mixed>>  $cart
     * @param  array<string, mixed>  $data
     * @return array{token:string, approval:PosDiscountApproval}
     */
    public function issue(User $cashier, array $cart, array $data): array
    {
        $authCode = DailyAuthCode::forDate(now()->format('Y-m-d'));
        if (! hash_equals($authCode->active_code, (string) $data['manager_auth_code'])) {
            throw ValidationException::withMessages(['manager_auth_code' => 'Auth code manager tidak valid.']);
        }

        $intent = $this->buildIntent($cashier, $cart, $data);
        $token = Str::random(64);
        $approval = PosDiscountApproval::query()->create([
            'daily_auth_code_id' => $authCode->id,
            'cashier_id' => $cashier->id,
            'token_hash' => hash('sha256', $token),
            'fingerprint' => $this->fingerprint($intent),
            'intent' => $intent,
            'approved_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);

        return ['token' => $token, 'approval' => $approval];
    }

    /**
     * @param  array<string, array<string, mixed>>  $cart
     * @param  array<string, mixed>  $context
     * @return array{approval:PosDiscountApproval, lines:array<int, array{discount:float,reason:string}>}|null
     */
    public function consume(?string $token, User $cashier, array $cart, array $context): ?array
    {
        if (blank($token)) {
            return null;
        }

        $approval = PosDiscountApproval::query()
            ->where('token_hash', hash('sha256', $token))
            ->lockForUpdate()
            ->first();

        if (! $approval || (int) $approval->cashier_id !== (int) $cashier->id) {
            throw ValidationException::withMessages(['discount_approval_token' => 'Approval diskon tidak valid.']);
        }
        if ($approval->consumed_at !== null) {
            throw ValidationException::withMessages(['discount_approval_token' => 'Approval diskon sudah digunakan.']);
        }
        if ($approval->expires_at->isPast()) {
            throw ValidationException::withMessages(['discount_approval_token' => 'Approval diskon sudah kedaluwarsa.']);
        }

        $stored = $approval->intent;
        $rebuilt = $this->buildIntent($cashier, $cart, [
            ...$context,
            'selected_item_ids' => collect($stored['discount']['lines'])->pluck('inventory_item_id')->all(),
            'discount_type' => $stored['discount']['type'],
            'discount_value' => $stored['discount']['value'],
            'reason' => $stored['discount']['reason'],
        ]);

        if (! hash_equals($approval->fingerprint, $this->fingerprint($rebuilt))) {
            throw ValidationException::withMessages(['discount_approval_token' => 'Cart berubah setelah approval. Ajukan approval ulang.']);
        }

        $lines = collect($stored['discount']['lines'])->mapWithKeys(fn (array $line): array => [
            (int) $line['inventory_item_id'] => [
                'discount' => (float) $line['discount_amount'],
                'discount_pct' => (float) ($line['discount_percentage'] ?? 0),
                'reason' => (string) $stored['discount']['reason'],
            ],
        ])->all();

        return ['approval' => $approval, 'lines' => $lines];
    }

    public function markConsumed(PosDiscountApproval $approval, Order $order): void
    {
        $approval->update(['consumed_at' => now(), 'consumed_order_id' => $order->id]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $cart
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildIntent(User $cashier, array $cart, array $data): array
    {
        $cartLines = collect($cart)->map(function (array $line, string $key): array {
            $id = (int) str_replace('item_', '', $key);
            $item = InventoryItem::query()->findOrFail($id);

            return [
                'inventory_item_id' => $id,
                'quantity' => (int) $line['quantity'],
                'unit_price' => round((float) $item->price, 2),
                'include_tax' => (bool) $item->include_tax,
                'include_service_charge' => (bool) $item->include_service_charge,
                'category_main' => strtolower(trim((string) $item->category_main)),
                'is_active' => (bool) $item->is_active,
                'is_visible_in_pos' => (bool) $item->is_visible_in_pos,
            ];
        })->sortBy('inventory_item_id')->values();

        $selectedIds = collect($data['selected_item_ids'])->map(fn ($id): int => (int) $id)->unique()->sort()->values();
        if ($selectedIds->diff($cartLines->pluck('inventory_item_id'))->isNotEmpty()) {
            throw ValidationException::withMessages(['selected_item_ids' => 'Item diskon harus berasal dari cart aktif.']);
        }

        $selected = $cartLines->whereIn('inventory_item_id', $selectedIds)->values();
        $grossTotal = (float) $selected->sum(fn (array $line): float => $line['unit_price'] * $line['quantity']);
        $type = (string) $data['discount_type'];
        $value = round((float) $data['discount_value'], 2);

        if ($type === 'percentage' && $value > 100) {
            throw ValidationException::withMessages(['discount_value' => 'Diskon persentase maksimal 100%.']);
        }
        if ($type === 'nominal' && $value > $grossTotal) {
            throw ValidationException::withMessages(['discount_value' => 'Diskon nominal melebihi subtotal item terpilih.']);
        }

        $remainingCents = $type === 'nominal' ? (int) round($value * 100) : 0;
        $lines = $selected->map(function (array $line, int $index) use ($selected, $grossTotal, $type, $value, &$remainingCents): array {
            $gross = round($line['unit_price'] * $line['quantity'], 2);
            if ($type === 'percentage') {
                $discount = round($gross * $value / 100, 2);
            } elseif ($index === $selected->count() - 1) {
                $discount = min($gross, max($remainingCents, 0) / 100);
            } else {
                $shareCents = min((int) round($gross * 100), (int) floor($value * 100 * ($gross / $grossTotal)));
                $shareCents = min($shareCents, max($remainingCents, 0));
                $discount = $shareCents / 100;
                $remainingCents -= $shareCents;
            }

            return [
                ...$line,
                'gross_amount' => $gross,
                'discount_amount' => $discount,
                'discount_percentage' => $type === 'percentage' ? $value : 0,
            ];
        })->all();

        return [
            'version' => 1,
            'cashier_id' => $cashier->id,
            'customer_type' => $data['customer_type'],
            'customer_id' => (int) ($data['customer_user_id'] ?? $data['walk_in_customer_id'] ?? 0),
            'table_id' => (int) ($data['table_id'] ?? 0),
            'cart' => $cartLines->all(),
            'discount' => [
                'type' => $type,
                'value' => $value,
                'reason' => trim((string) $data['reason']),
                'lines' => $lines,
            ],
        ];
    }

    /** @param array<string, mixed> $intent */
    private function fingerprint(array $intent): string
    {
        return hash('sha256', json_encode($intent, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
