<div x-show="showCustomerTypeModal"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     style="display: none;"
     class="fixed inset-0 bg-black/50 flex items-center justify-center z-[60]"
     @click.self="showCustomerTypeModal = false; bookingStep = 'type'">
  <div class="bg-white rounded-2xl w-full max-w-md mx-4 overflow-hidden shadow-xl"
       @click.stop>
    <div class="flex items-start justify-between p-6 pb-4">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <svg class="w-5 h-5 text-gray-700"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
          <h3 class="text-lg font-bold text-gray-900">Pilih Pelanggan</h3>
        </div>
        <p class="text-sm text-gray-500">Pilih tipe pelanggan untuk melanjutkan transaksi</p>
      </div>
      <button @click="showCustomerTypeModal = false; bookingStep = 'type'"
              class="text-gray-400 hover:text-gray-600 transition mt-0.5">
        <svg class="w-5 h-5"
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

    <!-- Step: Type Selection -->
    <div x-show="bookingStep === 'type'"
         class="px-6 pb-6">
      <div class="grid grid-cols-2 gap-3">
        <button @click="bookingStep = 'list'"
                class="p-5 border-2 border-gray-100 rounded-xl hover:border-blue-400 hover:bg-blue-50/50 transition group text-center">
          <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center mx-auto mb-3 group-hover:scale-105 transition-transform">
            <svg class="w-6 h-6 text-white"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </div>
          <h4 class="font-bold text-gray-900 mb-1">Booking</h4>
          <p class="text-xs text-gray-500 mb-3">Pelanggan dengan reservasi</p>
          <span class="inline-block px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full">
            {{ $tableSessions->count() }} Booking Aktif
          </span>
        </button>
        <button @click="bookingStep = 'walkin-customer'; $dispatch('walk-in-reset');"
                class="p-5 border-2 border-gray-100 rounded-xl hover:border-gray-300 hover:bg-gray-50 transition group text-center">
          <div class="w-12 h-12 bg-gray-900 rounded-xl flex items-center justify-center mx-auto mb-3 group-hover:scale-105 transition-transform">
            <svg class="w-6 h-6 text-white"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
          </div>
          <h4 class="font-bold text-gray-900 mb-1">Walk-in</h4>
          <p class="text-xs text-gray-500 mb-3">Pelanggan tanpa reservasi</p>
          <span class="inline-block px-3 py-1 bg-gray-100 text-gray-700 text-xs font-semibold rounded-full">
            Pilih Customer
          </span>
        </button>
      </div>
    </div>

    <!-- Step: Booking List -->
    <div x-show="bookingStep === 'list'"
         style="display: none;">
      <div class="flex items-center justify-between px-6 pb-3">
        <span class="font-semibold text-gray-900">Pilih Booking</span>
        <button @click="bookingStep = 'type'"
                class="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 transition font-medium">
          <svg class="w-4 h-4"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M15 19l-7-7 7-7" />
          </svg>
          Kembali
        </button>
      </div>
      <div class="px-6 pb-6 space-y-2 max-h-80 overflow-y-auto">
        @forelse($tableSessions as $session)
          @php
            $areaNameRaw = $session->table?->area?->name ?? 'N/A';
            $areaNameLower = strtolower($areaNameRaw);
            $avatarBg = match (true) {
                str_contains($areaNameLower, 'room') => 'bg-blue-600',
                str_contains($areaNameLower, 'balcon') => 'bg-violet-600',
                str_contains($areaNameLower, 'lounge') => 'bg-cyan-600',
                default => 'bg-gray-600',
            };
            $badgeCls = match (true) {
                str_contains($areaNameLower, 'room') => 'bg-green-100 text-green-700',
                str_contains($areaNameLower, 'balcon') => 'bg-violet-100 text-violet-700',
                str_contains($areaNameLower, 'lounge') => 'bg-cyan-100 text-cyan-700',
                default => 'bg-gray-100 text-gray-600',
            };
            $customerName = $session->customer?->name ?? 'Unknown';
            $initial = strtoupper(substr($customerName, 0, 1));
            $phone = $session->customer?->profile?->phone ?? '-';
            $tableName = $session->table?->table_number ?? 'N/A';
            $minCharge = (float) ($session->billing?->minimum_charge ?? ($session->table?->minimum_charge ?? 0));
            $ordersTotal = (float) ($session->billing?->orders_total ?? 0);
            $lifetimeSpending = (float) ($session->customer?->customerUser?->lifetime_spending ?? 0);
            $customerTier = $tiers->first(fn($t) => $t->minimum_spent <= $lifetimeSpending);
            $tierName = $customerTier?->name ?? null;
            $tierDiscount = $customerTier?->discount_percentage ?? 0;
            $sessionData = [
                'customerId' => $session->customer_id,
                'tableId' => $session->table_id,
                'areaName' => $areaNameRaw,
                'tableName' => $tableName,
                'customerName' => $customerName,
                'customerInitial' => $initial,
                'customerPhone' => $phone,
                'minimumCharge' => $minCharge,
                'ordersTotal' => $ordersTotal,
                'downPaymentAmount' => (float) ($session->reservation?->down_payment_amount ?? 0),
                'tierName' => $tierName,
                'discountPercentage' => $tierDiscount,
                'tierBadge' => $customerTier?->colorClasses('badge'),
                'waiterName' => $session->waiter?->profile?->name ?? ($session->waiter?->name ?? null),
                'reservationId' => $session->table_reservation_id,
            ];
          @endphp
          <button type="button"
                  @click="selectBookingSession({{ json_encode($sessionData) }})"
                  class="w-full flex items-center gap-3 p-3 rounded-xl border-2 border-transparent hover:border-blue-300 hover:bg-blue-50/50 transition text-left">
            <div class="w-10 h-10 {{ $avatarBg }} rounded-full flex-shrink-0 flex items-center justify-center">
              <span class="text-white font-bold text-sm">{{ $initial }}</span>
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-semibold text-gray-900 text-sm">{{ $customerName }}</span>
                <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $badgeCls }}">
                  {{ $areaNameRaw }}
                </span>
                @if ($tierName)
                  <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $customerTier->colorClasses('badge') }}">{{ $tierName }}</span>
                @endif
              </div>
              <p class="text-xs text-gray-500 mt-0.5">{{ $phone }}</p>
              <p class="text-xs text-gray-400 mt-0.5">
                Meja {{ $tableName }}
                @if ($minCharge > 0)
                  &bull; Min: Rp {{ number_format($minCharge, 0, ',', '.') }}
                @endif
              </p>
            </div>
          </button>
        @empty
          <div class="text-center py-8">
            <p class="text-sm text-gray-400">Tidak ada booking aktif saat ini</p>
          </div>
        @endforelse
      </div>
    </div>

    <!-- Step: Walk-in Customer — managed by separate walkInCheckout Alpine component -->
    <div x-show="bookingStep === 'walkin-customer'"
         style="display: none;">
      <div x-data="walkInCheckout()"
           @walk-in-reset.window="reset()">
        <div class="flex items-center justify-between px-6 pb-3">
          <span class="font-semibold text-gray-900">Customer Walk-in</span>
          <button @click="bookingStep = 'type'"
                  class="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 transition font-medium">
            <svg class="w-4 h-4"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M15 19l-7-7 7-7" />
            </svg>
            Kembali
          </button>
        </div>
        <div class="px-6 pb-6 space-y-3">
          <!-- Selected customer chip -->
          <div x-show="walkInSelected !== null"
               style="display:none;"
               class="flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-xl">
            <div>
              <p class="font-semibold text-green-800 text-sm"
                 x-text="walkInSelected?.name ?? ''"></p>
              <p class="text-xs text-green-600"
                 x-text="walkInSelected?.phone || 'Tidak ada nomor'"></p>
            </div>
            <button @click="walkInSelected = null; walkInSearch = ''"
                    class="text-green-400 hover:text-green-700 transition">
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

          <!-- Search input (when no selection and not create mode) -->
          <div x-show="walkInSelected === null && !walkInCreateMode"
               style="display:none;"
               class="space-y-2">
            <div class="relative">
              <input type="text"
                     x-model="walkInSearch"
                     @input.debounce.300ms="searchWalkInCustomers()"
                     placeholder="Cari nama atau nomor HP..."
                     class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-gray-400 focus:outline-none pr-10">
              <svg x-show="walkInSearching"
                   class="w-4 h-4 text-gray-400 absolute right-3 top-3 animate-spin"
                   fill="none"
                   viewBox="0 0 24 24">
                <circle class="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        stroke-width="4"></circle>
                <path class="opacity-75"
                      fill="currentColor"
                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
              </svg>
            </div>
            <div x-show="walkInFoundCustomers.length > 0"
                 class="border border-gray-100 rounded-xl overflow-hidden">
              <template x-for="c in walkInFoundCustomers"
                        :key="c.id">
                <button type="button"
                        @click="selectWalkInCustomer(c)"
                        class="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 transition text-left">
                  <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center shrink-0">
                    <span class="text-xs font-bold text-gray-600"
                          x-text="c.name[0].toUpperCase()"></span>
                  </div>
                  <div class="flex-1">
                    <p class="text-sm font-medium text-gray-900"
                       x-text="c.name"></p>
                    <p class="text-xs text-gray-400"
                       x-text="c.phone || 'Tidak ada nomor'"></p>
                    <template x-if="c.customer_code">
                      <p class="text-xs text-gray-500 mt-1"
                         x-text="'Code: ' + c.customer_code"></p>
                    </template>
                  </div>
                </button>
              </template>
            </div>
            <p x-show="walkInSearch.length >= 2 && walkInFoundCustomers.length === 0 && !walkInSearching"
               class="text-xs text-gray-400 text-center py-1">Customer tidak ditemukan.</p>
            <button type="button"
                    @click="walkInCreateMode = true; walkInNewName = walkInSearch"
                    class="w-full flex items-center justify-center gap-2 py-2.5 border-2 border-dashed border-gray-200 rounded-xl text-sm text-gray-500 hover:border-gray-400 hover:text-gray-700 transition font-medium">
              <svg class="w-4 h-4"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M12 4v16m8-8H4" />
              </svg>
              Buat Customer Baru
            </button>
          </div>

          <!-- Create new customer form -->
          <div x-show="walkInCreateMode"
               style="display:none;"
               class="space-y-3">
            <div>
              <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Nama *</label>
              <input type="text"
                     x-model="walkInNewName"
                     placeholder="Nama customer"
                     class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-gray-400 focus:outline-none">
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">No. HP (opsional)</label>
              <input type="tel"
                     x-model="walkInNewPhone"
                     placeholder="08xxxxxxxxxx"
                     class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-gray-400 focus:outline-none">
            </div>
            <div class="flex gap-2">
              <button type="button"
                      @click="walkInCreateMode = false"
                      class="flex-1 py-2 border border-gray-200 rounded-xl text-sm text-gray-500 hover:bg-gray-50 transition">
                Batal
              </button>
              <button type="button"
                      @click="createWalkInCustomer()"
                      :disabled="!walkInNewName.trim() || walkInCreating"
                      class="flex-1 py-2 bg-gray-900 text-white rounded-xl text-sm font-semibold hover:bg-gray-700 transition disabled:opacity-50">
                <span x-show="!walkInCreating">Buat Customer</span>
                <span x-show="walkInCreating">Membuat...</span>
              </button>
            </div>
          </div>

          <!-- Proceed button shown when customer is selected -->
          <div x-show="walkInSelected !== null"
               style="display:none;">
            <button type="button"
                    @click="proceedToCheckout()"
                    class="w-full py-3 bg-gray-900 text-white rounded-xl text-sm font-bold hover:bg-gray-700 transition">
              Lanjut → Checkout
            </button>
          </div>
        </div>{{-- /x-data="walkInCheckout()" --}}
      </div>
    </div>

  </div>
</div>
