<x-app-layout>
  <div class="p-6">
    <div class="mb-6 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-800">
          <svg class="h-6 w-6 text-white"
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
          <h1 class="text-2xl font-bold text-gray-900">Active Tables</h1>
          <p class="text-sm text-gray-500">Readonly monitor meja aktif tanpa aksi edit, hapus, atau close bill.</p>
        </div>
      </div>
      <div class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">
        Readonly
      </div>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2">
      <div class="rounded-xl border border-gray-200 bg-white p-6">
        <div class="flex items-center gap-4">
          <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-green-100">
            <svg class="h-6 w-6 text-green-600"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <p class="text-sm text-gray-500">Total Active Tables</p>
            <p class="text-2xl font-bold text-slate-800">{{ $totalActiveSessions }}</p>
          </div>
        </div>
      </div>
      <div class="rounded-xl border border-gray-200 bg-white p-6">
        <div class="flex items-center gap-4">
          <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-100">
            <svg class="h-6 w-6 text-blue-600"
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
            <p class="text-sm text-gray-500">Total Revenue</p>
            <p class="text-2xl font-bold text-slate-800">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</p>
          </div>
        </div>
      </div>
    </div>

    @if (! empty($topSpenders))
      <div class="mb-6">
        <div class="mb-3 flex items-center justify-between">
          <div>
            <h2 class="text-lg font-bold text-gray-900">Top 3 Spender</h2>
            <p class="text-sm text-gray-500">Diurutkan berdasarkan subtotal realtime dari active tables.</p>
          </div>
          <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">
            Realtime
          </span>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
          @foreach ($topSpenders as $spender)
            @php
              $rankClasses = match ($spender['rank'] ?? 0) {
                  1 => 'border-amber-200 bg-amber-50/80',
                  2 => 'border-slate-200 bg-slate-50/80',
                  3 => 'border-orange-200 bg-orange-50/80',
                  default => 'border-gray-200 bg-white',
              };
              $badgeClasses = match ($spender['rank'] ?? 0) {
                  1 => 'bg-amber-500 text-white',
                  2 => 'bg-slate-500 text-white',
                  3 => 'bg-orange-500 text-white',
                  default => 'bg-gray-500 text-white',
              };
            @endphp
            <div class="rounded-2xl border p-5 shadow-sm {{ $rankClasses }}">
              <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                  <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl {{ $badgeClasses }} font-bold">
                    #{{ $spender['rank'] ?? '-' }}
                  </div>
                  <div class="min-w-0">
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-gray-500">Top Spender</p>
                    <p class="truncate text-base font-bold text-gray-900">{{ $spender['customer_name'] }}</p>
                  </div>
                </div>
                <div class="text-right">
                  <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500">Subtotal</p>
                  <p class="text-lg font-extrabold text-slate-900">Rp {{ number_format((float) ($spender['orders_subtotal'] ?? 0), 0, ',', '.') }}</p>
                </div>
              </div>
              <div class="mt-4 flex items-center justify-between text-sm text-gray-600">
                <span>Table {{ $spender['table_number'] ?? '-' }}</span>
                @if (filled($spender['area_name'] ?? null))
                  <span>{{ $spender['area_name'] }}</span>
                @endif
              </div>
            </div>
          @endforeach
        </div>
      </div>
    @endif

    <div class="mb-6 rounded-xl border border-gray-200 bg-white p-4">
      <form method="GET"
            action="{{ route('admin.active-tables.readonly') }}"
            class="flex flex-wrap gap-4">
        <div class="min-w-[200px] flex-1">
          <label class="mb-2 block text-sm font-medium text-gray-700">Cari</label>
          <input type="text"
                 name="search"
                 value="{{ request('search') }}"
                 placeholder="Session code, nomor meja, atau nama customer..."
                 class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-transparent focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="w-48">
          <label class="mb-2 block text-sm font-medium text-gray-700">Area</label>
          <select name="area_id"
                  class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-transparent focus:ring-2 focus:ring-blue-500">
            <option value="">Semua Area</option>
            @foreach ($areas as $area)
              <option value="{{ $area->id }}"
                      {{ request('area_id') == $area->id ? 'selected' : '' }}>
                {{ $area->name }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="flex items-end gap-2">
          <button type="submit"
                  class="rounded-lg bg-slate-800 px-4 py-2 text-white transition hover:bg-slate-900">
            Filter
          </button>
          <a href="{{ route('admin.active-tables.readonly') }}"
             class="rounded-lg bg-gray-200 px-4 py-2 text-gray-700 transition hover:bg-gray-300">
            Reset
          </a>
        </div>
      </form>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
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
                $canClose = $billing && in_array($billing->billing_status, ['draft', 'finalized']) && $ordersForEligibility >= $requiredAmount;
                $belowMinCharge = $billing && in_array($billing->billing_status, ['draft', 'finalized']) && $ordersForEligibility < $requiredAmount;
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
  </div>
</x-app-layout>
