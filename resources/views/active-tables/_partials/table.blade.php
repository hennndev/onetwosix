{{-- Shared: active tables list. Rendered for both full page and realtime poll (X-Live + live=table). --}}
<div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
  <div class="overflow-x-auto">
    <table class="w-full text-sm min-w-[1000px]">
      <thead class="border-b border-gray-200 bg-gray-50">
        <tr>
          <th class="px-5 py-3 text-left text-sm font-semibold text-gray-600">Meja</th>
          <th class="px-5 py-3 text-left text-sm font-semibold text-gray-600">Customer</th>
          <th class="px-5 py-3 text-left text-sm font-semibold text-gray-600">Waiter</th>
          <th class="px-5 py-3 text-left text-sm font-semibold text-gray-600">Check-in</th>
          <th class="px-5 py-3 text-center text-sm font-semibold text-gray-600">Pax</th>
          <th class="px-5 py-3 text-right text-sm font-semibold text-gray-600">Min. Charge</th>
          <th class="px-5 py-3 text-right text-sm font-semibold text-gray-600">DP</th>
          <th class="px-5 py-3 text-left text-sm font-semibold text-gray-600">Event</th>
          <th class="px-5 py-3 text-right text-sm font-semibold text-gray-600">Orders</th>
          <th class="px-5 py-3 text-right text-sm font-semibold text-gray-600">Subtotal</th>
          <th class="px-5 py-3 text-right text-sm font-semibold text-gray-600">Service Charge</th>
          <th class="px-5 py-3 text-right text-sm font-semibold text-gray-600">PB1</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        @forelse ($sessions as $session)
          @php
            $checkedInAt = $session->checked_in_at ? \Carbon\Carbon::parse($session->checked_in_at)->setTimezone('Asia/Jakarta') : null;
            $billing = $session->billing;
            $reservation = $session->reservation;
            $customerName = $reservation?->customer?->profile?->name
                ?? ($reservation?->customer?->customerUser?->name
                    ?? ($reservation?->customer?->name
                        ?? ($session->customer?->profile?->name
                            ?? ($session->customer?->customerUser?->name
                                ?? ($session->customer?->name ?? 'Tamu')))));
            $phone = $reservation?->customer?->profile?->phone ?? ($session->customer?->profile?->phone ?? null);
            $waiterDisplayName = $session->waiter?->profile?->name ?? ($session->waiter?->name ?? null);
            $chargePreview = $activeSessionChargePreviews[$session->id] ?? null;
            $eventAdjustment = $activeSessionEventAdjustments[$session->id] ?? null;
            $ordersForEligibility = (float) ($chargePreview['orders_total'] ?? ($billing?->orders_total ?? 0));
            $minimumCharge = (float) ($chargePreview['minimum_charge'] ?? ($billing?->minimum_charge ?? 0));
            $downPaymentAmount = (float) ($chargePreview['down_payment_amount'] ?? ($reservation?->down_payment_amount ?? 0));
            $eventAdjustedMinimumCharge = (float) ($eventAdjustment['adjusted_minimum_charge'] ?? $minimumCharge);
            $eventBaseMinimumCharge = (float) ($eventAdjustment['base_minimum_charge'] ?? $minimumCharge);
            $requiredAmount = $downPaymentAmount > 0 ? $downPaymentAmount : $minimumCharge;
            $subtotalAmount = (float) ($activeSessionSubtotals[$session->id] ?? 0);
            $serviceChargeAmount = (float) ($chargePreview['service_charge'] ?? 0);
            $serviceChargePercentage = (float) ($chargePreview['service_charge_percentage'] ?? 0);
            $taxAmount = (float) ($chargePreview['tax'] ?? 0);
            $taxPercentage = (float) ($chargePreview['tax_percentage'] ?? 0);
          @endphp
          <tr class="hover:bg-gray-50 transition-colors">
            <td class="whitespace-nowrap px-5 py-4">
              <div class="text-base font-semibold text-gray-900">{{ $session->table?->table_number ?? '—' }}</div>
              @if ($session->table?->area?->name)
                <span class="inline-block mt-1 text-sm font-medium px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">
                  {{ $session->table->area->name }}
                </span>
              @endif
            </td>
            <td class="px-5 py-4">
              <div>
                <p class="text-base font-semibold text-gray-900">{{ $customerName }}</p>
                @if ($phone)
                  <p class="text-sm text-gray-400 mt-0.5">{{ $phone }}</p>
                @endif
              </div>
            </td>
            <td class="whitespace-nowrap px-5 py-4">
              @if ($waiterDisplayName)
                <div class="text-base font-medium text-gray-900">{{ $waiterDisplayName }}</div>
              @else
                <span class="text-sm text-gray-400 italic">Belum di-assign</span>
              @endif
            </td>
            <td class="whitespace-nowrap px-5 py-4">
              <div class="text-base font-medium text-gray-900">
                {{ $checkedInAt ? $checkedInAt->format('d M Y') : '—' }}
              </div>
              <div class="text-sm text-gray-400 mt-0.5">
                {{ $checkedInAt ? $checkedInAt->format('H:i') : '' }}
              </div>
            </td>
            <td class="whitespace-nowrap px-5 py-4 text-center">
              <span class="text-sm font-semibold text-gray-900">{{ $session->pax ? $session->pax.' org' : '—' }}</span>
            </td>
            <td class="whitespace-nowrap px-5 py-4 text-right">
              <div class="text-base text-gray-700 font-semibold">
                Rp {{ number_format((float) ($eventAdjustedMinimumCharge ?? $minimumCharge), 0, ',', '.') }}
              </div>
              @if ($eventAdjustedMinimumCharge > $eventBaseMinimumCharge)
                <div class="text-xs text-gray-500 mt-0.5">
                  Base: Rp {{ number_format($eventBaseMinimumCharge, 0, ',', '.') }}
                </div>
              @endif
            </td>
            <td class="whitespace-nowrap px-5 py-4 text-right">
              @if ($downPaymentAmount > 0)
                <div class="text-base text-gray-700 font-semibold">
                  Rp {{ number_format($downPaymentAmount, 0, ',', '.') }}
                </div>
              @else
                <div class="text-sm text-gray-400">—</div>
              @endif
            </td>
            <td class="px-5 py-4">
              @if ($eventAdjustment)
                <div class="text-sm font-semibold text-purple-700">{{ $eventAdjustment['event_name'] }}</div>
                <div class="text-xs text-purple-600 mt-0.5">{{ $eventAdjustment['adjustment_label'] }}</div>
                @if ($eventAdjustedMinimumCharge > $eventBaseMinimumCharge)
                  <div class="text-xs text-gray-500 mt-0.5">
                    Base: Rp {{ number_format($eventBaseMinimumCharge, 0, ',', '.') }}
                  </div>
                @endif
              @else
                <div class="text-sm text-gray-400">—</div>
              @endif
            </td>
            <td class="whitespace-nowrap px-5 py-4 text-right">
              <div class="text-base text-gray-700">
                Rp {{ number_format($ordersForEligibility, 0, ',', '.') }}
              </div>
            </td>
            <td class="whitespace-nowrap px-5 py-4 text-right">
              <div class="text-base font-semibold text-gray-900">
                Rp {{ number_format($subtotalAmount, 0, ',', '.') }}
              </div>
            </td>
            <td class="whitespace-nowrap px-5 py-4 text-right">
              <div class="text-base text-gray-900 font-semibold">
                Rp {{ number_format($serviceChargeAmount, 0, ',', '.') }}
              </div>
              @if ($serviceChargeAmount > 0)
                <div class="text-xs text-gray-500 mt-0.5">
                  {{ rtrim(rtrim(number_format($serviceChargePercentage, 2, '.', ''), '0'), '.') }}%
                </div>
              @endif
            </td>
            <td class="whitespace-nowrap px-5 py-4 text-right">
              <div class="text-base text-gray-900 font-semibold">
                Rp {{ number_format($taxAmount, 0, ',', '.') }}
              </div>
              @if ($taxAmount > 0)
                <div class="text-xs text-gray-500 mt-0.5">
                  {{ rtrim(rtrim(number_format($taxPercentage, 2, '.', ''), '0'), '.') }}%
                </div>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="12"
                class="px-6 py-12 text-center">
              <div class="flex flex-col items-center justify-center text-gray-400">
                <svg class="mb-4 h-16 w-16"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                </svg>
                  <p class="text-lg font-medium">Tidak ada meja yang aktif</p>
                  <p class="text-sm">Semua meja sedang tidak digunakan</p>
              </div>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
