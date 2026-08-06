<x-waiter-mobile-layout>
  <div class="p-5">

    <!-- Header -->
    <div class="mb-5">
      <h1 class="text-2xl font-bold">Active Tables</h1>
      <p class="text-slate-700 text-sm mt-0.5">{{ $sessions->count() }} meja aktif saat ini</p>
    </div>

    @if ($sessions->isEmpty())
      <div class="bg-white rounded-2xl p-10 text-center shadow-sm border border-slate-100">
        <svg class="w-12 h-12 mx-auto mb-3 text-slate-600"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        <p class="text-slate-700 font-medium">Tidak ada meja aktif</p>
        <p class="text-slate-600 text-sm mt-1">Semua meja sedang kosong</p>
      </div>
    @else
      <div class="space-y-3">
        @foreach ($sessions as $session)
          @php
            $checkedInAt = $session->checked_in_at ? \Carbon\Carbon::parse($session->checked_in_at)->setTimezone('Asia/Jakarta') : null;
            $duration = $checkedInAt ? $checkedInAt->diffForHumans(now(), \Carbon\CarbonInterface::DIFF_ABSOLUTE) : '—';

            $billing = $session->billing;
            $chargePreview = $sessionChargePreviews[$session->id] ?? null;
            $ordersTotal = (float) ($chargePreview['orders_total'] ?? ($session->total_spent ?? 0));
            $minimumCharge = (float) ($billing?->minimum_charge ?? 0);
            $discountAmount = (float) ($chargePreview['discount_amount'] ?? ($billing?->discount_amount ?? 0));
            $serviceChargeAmount = (float) ($chargePreview['service_charge'] ?? 0);
            $taxAmount = (float) ($chargePreview['tax'] ?? 0);
            $serviceChargePercentage = (float) ($chargePreview['service_charge_percentage'] ?? 0);
            $taxPercentage = (float) ($chargePreview['tax_percentage'] ?? 0);
            $estimatedTotal = (float) ($chargePreview['grand_total'] ?? $ordersTotal - $discountAmount);
            $belowMinCharge = $minimumCharge > 0 && $ordersTotal < $minimumCharge;
            $gap = $belowMinCharge ? $minimumCharge - $ordersTotal : 0;
          @endphp
          <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100">
            <div class="flex items-start justify-between mb-3">
              <div>
                <div class="flex items-center gap-2">
                  <span class="font-bold text-lg text-slate-900">Meja {{ $session->table?->table_number ?? '?' }}</span>
                  <span class="px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-xs font-medium">Aktif</span>
                </div>
                <p class="text-slate-700 text-xs mt-0.5">{{ $session->table?->area?->name ?? '—' }}</p>
              </div>
              <div class="text-right">
                <p class="text-xs text-slate-600">Durasi</p>
                <p class="text-sm font-semibold text-slate-900">{{ $duration }}</p>
              </div>
            </div>

            <div class="flex items-center gap-3 mb-3">
              <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-slate-600"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
              </div>
              <div>
                <p class="font-medium text-sm text-slate-900">{{ $session->customer?->name ?? 'Tamu' }}</p>
                <p class="text-slate-700 text-xs">
                  Check-in {{ $checkedInAt ? $checkedInAt->format('H:i') : '—' }}
                </p>
              </div>
            </div>

            {{-- Pax editor --}}
            <div class="flex items-center justify-between py-2.5 border-t border-slate-100"
                 x-data="{
                     editing: false,
                     saving: false,
                     pax: {{ $session->pax ?? 'null' }},
                     inputVal: '{{ $session->pax ?? '' }}',
                     capacity: {{ $session->table?->capacity ?? 'null' }},
                     get displayPax() {
                         return this.pax !== null ? this.pax : (this.capacity !== null ? this.capacity + ' (default)' : '—');
                     },
                     async save() {
                         const val = parseInt(this.inputVal);
                         if (!val || val < 1) return;
                         this.saving = true;
                         try {
                             const res = await fetch('{{ route('waiter.active-tables.updatePax', $session->id) }}', {
                                 method: 'PATCH',
                                 headers: {
                                     'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                     'Content-Type': 'application/json',
                                     'Accept': 'application/json',
                                 },
                                 body: JSON.stringify({ pax: val }),
                             });
                             const data = await res.json();
                             if (data.success) {
                                 this.pax = data.pax;
                                 this.editing = false;
                             }
                         } finally { this.saving = false; }
                     }
                 }">
              <span class="text-slate-700 text-sm">Pax</span>
              <div class="flex items-center gap-2">
                <span x-show="!editing"
                      class="text-sm font-semibold text-slate-900"
                      x-text="displayPax"></span>
                <input x-show="editing"
                       x-ref="paxInput"
                       x-model="inputVal"
                       type="number"
                       min="1"
                       max="9999"
                       @keydown.enter="save()"
                       @keydown.escape="editing = false"
                       class="w-16 text-sm text-center border border-slate-300 rounded-lg px-2 py-0.5 focus:outline-none focus:ring-2 focus:ring-teal-400">
                <button x-show="!editing"
                        @click="editing = true; inputVal = pax ?? capacity ?? ''; $nextTick(() => $refs.paxInput.select())"
                        class="text-xs text-teal-600 font-medium hover:text-teal-800 transition">Edit</button>
                <button x-show="editing"
                        @click="save()"
                        :disabled="saving"
                        class="text-xs bg-teal-500 text-white px-2 py-0.5 rounded-lg font-medium hover:bg-teal-600 transition">Simpan</button>
                <button x-show="editing"
                        @click="editing = false"
                        class="text-xs text-slate-500 hover:text-slate-700 transition">Batal</button>
              </div>
            </div>

            <div class="pt-2.5 border-t border-slate-100 space-y-1.5">

              {{-- Minimum charge + gap warning --}}
              @if ($minimumCharge > 0)
                <div class="flex items-center justify-between text-sm">
                  <span class="text-slate-500">Minimum Charge</span>
                  <span class="text-slate-700">Rp {{ number_format($minimumCharge, 0, ',', '.') }}</span>
                </div>
                @if ($belowMinCharge)
                  <div class="flex items-center gap-1.5 bg-amber-50 border border-amber-200 rounded-lg px-2.5 py-1.5 text-xs text-amber-700">
                    <svg class="w-3.5 h-3.5 flex-shrink-0"
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24">
                      <path stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    Kurang Rp {{ number_format($gap, 0, ',', '.') }} dari minimum charge
                  </div>
                @endif
              @endif

              {{-- Orders total --}}
              <div class="flex items-center justify-between text-sm">
                <span class="text-slate-500">Total Pesanan</span>
                <span class="text-slate-700">Rp {{ number_format($ordersTotal, 0, ',', '.') }}</span>
              </div>

              {{-- Discount --}}
              @if ($discountAmount > 0)
                <div class="flex items-center justify-between text-sm">
                  <span class="text-slate-500">Diskon</span>
                  <span class="text-green-600">- Rp {{ number_format($discountAmount, 0, ',', '.') }}</span>
                </div>
              @endif

              {{-- Service charge --}}
              @if ($serviceChargeAmount > 0)
                <div class="flex items-center justify-between text-sm">
                  <span class="text-slate-500">Service Charge ({{ $serviceChargePercentage }}%)</span>
                  <span class="text-slate-700">Rp {{ number_format($serviceChargeAmount, 0, ',', '.') }}</span>
                </div>
              @endif

              {{-- Tax --}}
              @if ($taxAmount > 0)
                <div class="flex items-center justify-between text-sm">
                  <span class="text-slate-500">PB1 ({{ $taxPercentage }}%)</span>
                  <span class="text-slate-700">Rp {{ number_format($taxAmount, 0, ',', '.') }}</span>
                </div>
              @endif

              {{-- Estimated total --}}
              <div class="flex items-center justify-between pt-1.5 border-t border-slate-100">
                <span class="text-slate-700 text-sm font-semibold">Estimasi Total</span>
                <span class="font-bold text-slate-900">Rp {{ number_format($estimatedTotal, 0, ',', '.') }}</span>
              </div>

            </div>
          </div>
        @endforeach
      </div>
    @endif

  </div>
</x-waiter-mobile-layout>
