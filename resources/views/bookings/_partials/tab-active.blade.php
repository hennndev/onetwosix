{{-- ACTIVE TABLES TAB --}}

{{-- Stats row --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
  <div class="bg-blue-800 rounded-xl px-5 py-4 flex items-center gap-4">
    <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center shrink-0">
      <svg class="w-5 h-5 text-white"
           fill="none"
           stroke="currentColor"
           viewBox="0 0 24 24">
        <path stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
      </svg>
    </div>
    <div>
      <div class="text-2xl font-bold text-white">{{ $activeSessions->count() }}</div>
      <div class="text-sm font-semibold text-blue-200">Meja Aktif</div>
    </div>
  </div>
  <div class="bg-green-900 border border-green-100 rounded-xl px-5 py-4 flex items-center gap-4">
    <div class="w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center shrink-0">
      <svg class="w-5 h-5 text-white"
           fill="none"
           stroke="currentColor"
           viewBox="0 0 24 24">
        <path stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
    </div>
    <div>
      <div class="text-lg font-bold text-white">
        Rp {{ number_format($activeSessions->sum(fn($s) => (float) ($activeSessionChargePreviews[$s->id]['grand_total'] ?? 0)), 0, ',', '.') }}
      </div>
      <div class="text-base font-semibold text-white">Total Tagihan Berjalan</div>
    </div>
  </div>
  <div class="bg-amber-800 rounded-xl px-5 py-4 flex items-center gap-4">
    <div class="w-10 h-10 bg-amber-600 rounded-lg flex items-center justify-center shrink-0">
      <svg class="w-5 h-5 text-white"
           fill="none"
           stroke="currentColor"
           viewBox="0 0 24 24">
        <path stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
    </div>
    <div>
      <div class="text-lg font-bold text-white">
        @php
          $avgMin = $activeSessions->count() > 0 ? (int) round($activeSessions->avg(fn($s) => $s->checked_in_at ? abs(now()->diffInMinutes($s->checked_in_at)) : 0)) : null;
        @endphp
        @if ($avgMin !== null)
          {{ $avgMin >= 60 ? floor($avgMin / 60) . 'j ' . $avgMin % 60 . 'm' : $avgMin . ' mnt' }}
        @else
          —
        @endif
      </div>
      <div class="text-sm font-semibold text-amber-200">Rata-rata Durasi</div>
    </div>
  </div>
</div>

{{-- Sessions table --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
  @if ($activeSessions->isEmpty())
    <div class="flex flex-col items-center justify-center py-16 text-gray-400">
      <svg class="w-12 h-12 mb-3 text-gray-300"
           fill="none"
           stroke="currentColor"
           viewBox="0 0 24 24">
        <path stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
      </svg>
      <p class="text-sm font-medium">Tidak ada sesi aktif</p>
      <p class="text-xs text-gray-400 mt-1">Belum ada customer yang check-in</p>
    </div>
  @else
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-200">
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
            <th class="px-5 py-3 text-right text-sm font-semibold text-gray-600">Aksi</th>
            <th class="px-5 py-3 text-center text-sm font-semibold text-gray-600">Remove</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @foreach ($activeSessions as $session)
            @php
              $checkedInAt = $session->checked_in_at ? \Carbon\Carbon::parse($session->checked_in_at)->setTimezone('Asia/Jakarta') : null;

              $billing = $session->billing;
              $reservation = $session->reservation;
              $customerName = $reservation?->customer?->profile?->name ?? ($reservation?->customer?->customerUser?->name ?? ($reservation?->customer?->name ?? ($session->customer?->profile?->name ?? ($session->customer?->name ?? 'Tamu'))));
              $phone = $reservation?->customer?->profile?->phone ?? ($session->customer?->profile?->phone ?? null);
              $areaName = $session->table?->area?->name ?? '';
              $areaBadge = match (true) {
                  str_contains(strtolower($areaName), 'room') || str_contains(strtolower($areaName), 'vip') => 'bg-purple-100 text-purple-700',
                  str_contains(strtolower($areaName), 'balcony') => 'bg-violet-100 text-violet-700',
                  str_contains(strtolower($areaName), 'lounge') => 'bg-cyan-100 text-cyan-700',
                  strlen($areaName) > 0 => 'bg-gray-100 text-gray-600',
                  default => 'bg-gray-100 text-gray-500',
              };

              $chargePreview = $activeSessionChargePreviews[$session->id] ?? null;
              $ordersForEligibility = (float) ($chargePreview['orders_total'] ?? ($billing?->orders_total ?? 0));

              // Billing state: check both DP and minimum charge
              $downPaymentAmount = (float) ($reservation?->down_payment_amount ?? 0);
              $minimumCharge = (float) ($billing->minimum_charge ?? 0);
              $eventAdjustment = $activeSessionEventAdjustments[$session->id] ?? null;
              $eventAdjustedMinimumCharge = (float) ($eventAdjustment['adjusted_minimum_charge'] ?? $minimumCharge);
              $eventBaseMinimumCharge = (float) ($eventAdjustment['base_minimum_charge'] ?? $minimumCharge);
              $requiredAmount = $downPaymentAmount > 0 ? $downPaymentAmount : $minimumCharge;
              $canClose = $billing && in_array($billing->billing_status, ['draft', 'finalized']) && $ordersForEligibility >= $requiredAmount;
              $belowMinCharge = $billing && in_array($billing->billing_status, ['draft', 'finalized']) && $ordersForEligibility < $requiredAmount;

              // Waiter
              $waiterDisplayName = $session->waiter?->profile?->name ?? ($session->waiter?->name ?? null);
              $waiterId = $session->waiter_id;

              // Compute live total: orders - discount
              $ordersSubtotal = (float) ($chargePreview['orders_total'] ?? ($billing?->orders_total ?? 0));
              $computedGrandTotal = (float) ($chargePreview['grand_total'] ?? $ordersSubtotal - (float) ($billing?->discount_amount ?? 0));
              $checkerItems = $session->orders->flatMap->items->where('status', '!=', 'cancelled');
              $checkerTotalItems = $checkerItems->count();
              $checkerCheckedItems = $checkerItems->where('status', 'served')->count();
              $closeBillingDiscountItems = $checkerItems->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->item_name,
                'quantity' => $item->quantity,
                'subtotal' => (float) $item->subtotal,
                'discount_amount' => (float) $item->discount_amount,
                'include_tax' => (bool) ($item->inventoryItem?->include_tax ?? true),
                'include_service_charge' => (bool) ($item->inventoryItem?->include_service_charge ?? true),
              ])->values();
            @endphp
            <tr class="hover:bg-gray-50 transition-colors">

              {{-- Table --}}
              <td class="px-5 py-4 whitespace-nowrap">
                <div class="text-base font-semibold text-gray-900">{{ $session->table?->table_number ?? '—' }}</div>
                @if ($areaName)
                  <span class="inline-block mt-1 text-sm font-medium px-2 py-0.5 rounded-full {{ $areaBadge }}">
                    {{ $areaName }}
                  </span>
                @endif
              </td>

              {{-- Customer --}}
              <td class="px-5 py-4">
                <div class="text-base font-semibold text-gray-900">{{ $customerName }}</div>
                @if ($phone)
                  <div class="text-sm text-gray-400 mt-0.5">{{ $phone }}</div>
                @endif
              </td>

              {{-- Waiter --}}
              <td class="px-5 py-4 whitespace-nowrap">
                @if ($waiterDisplayName)
                  <div class="text-base font-medium text-gray-900">{{ $waiterDisplayName }}</div>
                @else
                  <span class="text-sm text-gray-400 italic">Belum di-assign</span>
                @endif
                @if ($reservation)
                  <button onclick="openAssignWaiterModal({{ $reservation->id }}, {{ $waiterId ?? 'null' }})"
                          class="mt-1 text-sm text-blue-600 hover:underline">
                    {{ $waiterDisplayName ? 'Ganti' : 'Assign' }}
                  </button>
                @endif
              </td>

              {{-- Check-in --}}
              <td class="px-5 py-4 whitespace-nowrap">
                <div class="text-base font-medium text-gray-900">
                  {{ $checkedInAt ? $checkedInAt->format('d M Y') : '—' }}
                </div>
                <div class="text-sm text-gray-400 mt-0.5">
                  {{ $checkedInAt ? $checkedInAt->format('H:i') : '' }}
                </div>
              </td>

              {{-- Pax --}}
              <td class="px-5 py-4 whitespace-nowrap text-center"
                  x-data="paxEditor({{ $session->id }}, {{ $session->pax ?? 'null' }}, '{{ route('admin.active-tables.updatePax', $session) }}')"
                  x-cloak>
                <div x-show="!editing"
                     class="flex items-center justify-center gap-1.5">
                  <span class="text-sm font-semibold text-gray-900"
                        x-text="pax !== null ? pax + ' org' : '—'"></span>
                  <button @click="editing = true; draft = pax ?? ''"
                          class="text-gray-400 hover:text-blue-600 transition"
                          title="Edit pax">
                    <svg class="w-3.5 h-3.5"
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24">
                      <path stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </button>
                </div>
                <div x-show="editing"
                     class="flex items-center justify-center gap-1">
                  <input type="number"
                         x-model="draft"
                         min="1"
                         class="w-16 px-2 py-1 text-sm border border-blue-400 rounded focus:outline-none focus:ring-1 focus:ring-blue-400"
                         @keydown.enter="save()"
                         @keydown.escape="editing = false">
                  <button @click="save()"
                          class="text-green-600 hover:text-green-800 transition">
                    <svg class="w-4 h-4"
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24">
                      <path stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M5 13l4 4L19 7" />
                    </svg>
                  </button>
                  <button @click="editing = false"
                          class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-4 h-4"
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24">
                      <path stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </td>

              {{-- Min charge --}}
              <td class="px-5 py-4 whitespace-nowrap text-right">
                <div class="text-base text-gray-700 font-semibold">
                  Rp {{ number_format($minimumCharge, 0, ',', '.') }}
                </div>
              </td>

              {{-- DP --}}
              <td class="px-5 py-4 whitespace-nowrap text-right">
                @if ($downPaymentAmount > 0)
                  <div class="text-base text-gray-700 font-semibold">
                    Rp {{ number_format($downPaymentAmount, 0, ',', '.') }}
                  </div>
                @else
                  <div class="text-sm text-gray-400">—</div>
                @endif
              </td>

              {{-- Event --}}
              <td class="px-5 py-4">
                @if ($eventAdjustment)
                  <div class="text-sm font-semibold text-purple-700">{{ $eventAdjustment['event_name'] }}</div>
                  <div class="text-xs text-purple-600 mt-0.5">{{ $eventAdjustment['adjustment_label'] }}</div>
                  @if ($eventAdjustedMinimumCharge > $eventBaseMinimumCharge)
                    <div class="text-xs text-gray-500 mt-0.5">
                      Base: Rp {{ number_format($eventBaseMinimumCharge, 0, ',', '.') }}
                    </div>
                  @endif
                  <div class="text-xs text-gray-500 mt-0.5">
                    Min Event: Rp {{ number_format($minimumCharge, 0, ',', '.') }}
                  </div>
                @else
                  <div class="text-sm text-gray-400">—</div>
                @endif
              </td>

              {{-- Orders total --}}
              <td class="px-5 py-4 whitespace-nowrap text-right">
                <div class="text-base text-gray-700">
                  Rp {{ number_format($ordersForEligibility, 0, ',', '.') }}
                </div>
              </td>

              {{-- Subtotal --}}
              <td class="px-5 py-4 whitespace-nowrap text-right">
                @php
                  $subtotalAmount = (float) ($activeBookingSubtotals[$reservation->id] ?? 0);
                @endphp
                <div class="text-base font-semibold text-gray-900">
                  Rp {{ number_format($subtotalAmount, 0, ',', '.') }}
                </div>
              </td>

              {{-- Service charge --}}
              <td class="px-5 py-4 whitespace-nowrap text-right">
                @php
                  $serviceChargeAmount = (float) ($chargePreview['service_charge'] ?? 0);
                  $serviceChargePercentage = (float) ($chargePreview['service_charge_percentage'] ?? 0);
                @endphp
                <div class="text-base text-gray-900 font-semibold">
                  Rp {{ number_format($serviceChargeAmount, 0, ',', '.') }}
                </div>
                @if ($serviceChargeAmount > 0)
                  <div class="text-xs text-gray-500 mt-0.5">{{ rtrim(rtrim(number_format($serviceChargePercentage, 2, '.', ''), '0'), '.') }}%</div>
                @endif
              </td>

              {{-- Tax / PB1 --}}
              <td class="px-5 py-4 whitespace-nowrap text-right">
                @php
                  $taxAmount = (float) ($chargePreview['tax'] ?? 0);
                  $taxPercentage = (float) ($chargePreview['tax_percentage'] ?? 0);
                @endphp
                <div class="text-base text-gray-900 font-semibold">
                  Rp {{ number_format($taxAmount, 0, ',', '.') }}
                </div>
                @if ($taxAmount > 0)
                  <div class="text-xs text-gray-500 mt-0.5">{{ rtrim(rtrim(number_format($taxPercentage, 2, '.', ''), '0'), '.') }}%</div>
                @endif
              </td>

              {{-- Actions --}}
              <td class="px-5 py-4 whitespace-nowrap">
                <div class="flex items-center justify-end gap-2">
                  <button onclick="openOrderHistoryModal('{{ $session->session_code }}')"
                          class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition">
                    <svg class="w-3.5 h-3.5"
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24">
                      <path stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    Orders
                  </button>
                  @if ($reservation)
                    <form action="{{ route('admin.bookings.printRunningReceipt', $reservation) }}"
                          method="POST"
                          class="inline">
                      @csrf
                      <button type="submit"
                              class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 transition">
                        <svg class="w-3.5 h-3.5"
                             fill="none"
                             stroke="currentColor"
                             viewBox="0 0 24 24">
                          <path stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z" />
                        </svg>
                        Cetak Struk
                      </button>
                    </form>
                    <button onclick="openMoveTableModal({{ $reservation->id }}, '{{ $session->table?->table_number ?? '-' }}')"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-amber-100 text-amber-800 hover:bg-amber-200 transition">
                      <svg class="w-3.5 h-3.5"
                           fill="none"
                           stroke="currentColor"
                           viewBox="0 0 24 24">
                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M7 16V4m0 0L3 8m4-4l4 4m6-1v12m0 0l4-4m-4 4l-4-4" />
                      </svg>
                      Pindah Meja
                    </button>
                    @if ($canClose)
                      <button type="button"
                              data-booking-id="{{ $reservation->id }}"
                              data-minimum-charge="{{ (float) $billing->minimum_charge }}"
                              data-orders-total="{{ (float) ($chargePreview['orders_total'] ?? 0) }}"
                              data-discount-amount="{{ (float) ($chargePreview['discount_amount'] ?? 0) }}"
                              data-down-payment-amount="{{ (float) ($reservation->down_payment_amount ?? 0) }}"
                              data-service-charge="{{ (float) ($chargePreview['service_charge'] ?? 0) }}"
                              data-tax="{{ (float) ($chargePreview['tax'] ?? 0) }}"
                              data-service-charge-percentage="{{ (float) ($chargePreview['service_charge_percentage'] ?? 0) }}"
                              data-tax-percentage="{{ (float) ($chargePreview['tax_percentage'] ?? 0) }}"
                              data-grand-total="{{ (float) $computedGrandTotal }}"
                              data-checker-checked="{{ $checkerCheckedItems }}"
                              data-checker-total="{{ $checkerTotalItems }}"
                              data-discount-items="{{ base64_encode($closeBillingDiscountItems->toJson()) }}"
                              data-discount-items-url="{{ route('admin.bookings.discountItems', $reservation) }}"
                              onclick="openCloseBillingModal(this)"
                              class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-green-600 text-white hover:bg-green-700 transition">
                        <svg class="w-3.5 h-3.5"
                             fill="none"
                             stroke="currentColor"
                             viewBox="0 0 24 24">
                          <path stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm0 0h10" />
                        </svg>
                        Tutup Billing
                      </button>
                    @endif
                  @endif
                </div>
              </td>

              <td class="px-5 py-4 whitespace-nowrap text-center">
                @if ($reservation)
                  <button type="button"
                          onclick="openActiveDeleteModal({{ $reservation->id }}, '{{ addslashes((string) ($session->table?->table_number ?? '-')) }}')"
                          class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition">
                    <svg class="w-3.5 h-3.5"
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24">
                      <path stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16" />
                    </svg>
                    Hapus
                  </button>
                @else
                  <span class="text-sm text-gray-400">—</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
