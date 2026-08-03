<div x-show="showCheckoutModal"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     style="display: none;"
     class="fixed inset-0 bg-black/50 flex items-center justify-center z-[60]"
     @click.self="showCheckoutModal = false">
  <div class="bg-white rounded-2xl w-full max-w-md mx-4 shadow-xl max-h-[90vh] overflow-y-auto"
       @click.stop>
    <!-- Header -->
    <div class="flex items-start justify-between p-6 pb-4">
      <div>
        <h3 class="text-lg font-bold text-gray-900">Pembayaran</h3>
        <p class="text-sm text-gray-500 mt-0.5">Lengkapi detail pembayaran untuk menyelesaikan transaksi</p>
      </div>
      <button @click="showCheckoutModal = false"
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
    <form @submit.prevent="submitCheckout"
          class="px-6 pb-6 space-y-4">
      <input type="hidden"
             x-model="checkoutForm.customer_type">
      <input type="hidden"
             x-model="checkoutForm.customer_user_id">
      <input type="hidden"
             x-model="checkoutForm.table_id">

      <!-- Customer Card -->
      <div class="bg-blue-600 rounded-2xl p-4 flex items-center gap-3">
        <div class="w-12 h-12 bg-white/20 rounded-full flex-shrink-0 flex items-center justify-center">
          <span class="text-white font-bold text-lg"
                x-text="checkoutForm.customerInitial || '?'"></span>
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="text-white font-bold text-base"
                  x-text="checkoutForm.customerName || 'Customer'"></span>
            <span class="px-2 py-0.5 bg-white/20 text-white text-xs font-semibold rounded-full"
                  x-text="checkoutForm.customer_type === 'walk-in' ? 'Walk-in' : 'Booking'"></span>
          </div>
          <p class="text-blue-100 text-sm mt-0.5"
             x-text="checkoutForm.customerPhone || '-'"></p>
        </div>
      </div>

      <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1.5">FOC / Compliment</label>
        <select x-model="checkoutForm.foc_comp_payment_method"
                class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent bg-white">
          <option value="">-</option>
          <option value="FOC">FOC</option>
          <option value="Compliment">Compliment</option>
        </select>
      </div>

      <!-- Waiter Info (hidden for walk-in) -->
      <div x-show="checkoutForm.customer_type !== 'walk-in'"
           style="display: none;"
           class="space-y-0">
        <!-- Assigned: show chip -->
        <div x-show="checkoutForm.waiterName"
             style="display: none;"
             class="flex items-center gap-2.5 px-4 py-2.5 bg-indigo-50 border border-indigo-100 rounded-xl">
          <svg class="w-4 h-4 text-indigo-400 flex-shrink-0"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
          </svg>
          <span class="text-sm text-indigo-700 font-medium"
                x-text="'Waiter: ' + checkoutForm.waiterName"></span>
        </div>
        <!-- Not assigned + has booking: assign dropdown -->
        <div x-show="!checkoutForm.waiterName && checkoutForm.reservationId"
             style="display: none;"
             class="space-y-1.5">
          <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide">Assign Waiter</label>
          <div class="flex items-center gap-2">
            <select @change="assignWaiterFromPos($event.target.value)"
                    :disabled="checkoutForm.assigningWaiter"
                    class="flex-1 px-3 py-2 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-400 focus:outline-none bg-white disabled:opacity-50">
              <option value="">— Pilih Waiter —</option>
              <template x-for="w in posWaiters"
                        :key="w.id">
                <option :value="w.id"
                        x-text="w.name"></option>
              </template>
            </select>
            <svg x-show="checkoutForm.assigningWaiter"
                 class="w-5 h-5 text-indigo-500 animate-spin flex-shrink-0"
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
          <p x-show="checkoutForm.assignWaiterError"
             x-text="checkoutForm.assignWaiterError"
             class="text-xs text-red-500"></p>
          <p class="text-xs text-amber-600">Transaksi belum bisa diselesaikan sampai waiter dipilih.</p>
        </div>
        <!-- Not assigned + no reservation (walk-in): amber notice -->
        <div x-show="!checkoutForm.waiterName && !checkoutForm.reservationId"
             style="display: none;"
             class="flex items-center gap-2.5 px-4 py-2.5 bg-amber-50 border border-amber-100 rounded-xl">
          <svg class="w-4 h-4 text-amber-400 flex-shrink-0"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 9v2m0 4h.01M12 3a9 9 0 110 18A9 9 0 0112 3z" />
          </svg>
          <span class="text-sm text-amber-700 font-medium">Waiter belum di-assign untuk sesi ini</span>
        </div>
      </div>{{-- end waiter section --}}

      <div x-show="checkoutForm.customer_type === 'walk-in'"
           style="display: none;"
           class="space-y-4">
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 space-y-3">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-2">Discount (Opsional)</label>
            <div class="grid grid-cols-3 gap-2">
              <label class="flex items-center justify-center gap-2 p-2.5 rounded-lg border cursor-pointer transition"
                     :class="checkoutForm.discount_type === 'none' ? 'border-green-500 bg-green-50' : 'border-gray-200 hover:border-gray-300'">
                <input type="radio"
                       name="walk_in_discount_type"
                       value="none"
                       class="sr-only"
                       x-model="checkoutForm.discount_type">
                <span class="text-xs font-semibold text-gray-700">Tanpa</span>
              </label>
              <label class="flex items-center justify-center gap-2 p-2.5 rounded-lg border cursor-pointer transition"
                     :class="checkoutForm.discount_type === 'percentage' ? 'border-green-500 bg-green-50' : 'border-gray-200 hover:border-gray-300'">
                <input type="radio"
                       name="walk_in_discount_type"
                       value="percentage"
                       class="sr-only"
                       x-model="checkoutForm.discount_type">
                <span class="text-xs font-semibold text-gray-700">%</span>
              </label>
              <label class="flex items-center justify-center gap-2 p-2.5 rounded-lg border cursor-pointer transition"
                     :class="checkoutForm.discount_type === 'nominal' ? 'border-green-500 bg-green-50' : 'border-gray-200 hover:border-gray-300'">
                <input type="radio"
                       name="walk_in_discount_type"
                       value="nominal"
                       class="sr-only"
                       x-model="checkoutForm.discount_type">
                <span class="text-xs font-semibold text-gray-700">Nominal</span>
              </label>
            </div>
          </div>

          <div x-show="checkoutForm.discount_type === 'percentage'"
               style="display: none;">
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Diskon Persentase</label>
            <input type="number"
                   min="0"
                   max="100"
                   step="0.01"
                   placeholder="Contoh: 10"
                   x-model.number="checkoutForm.discount_percentage"
                   class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
          </div>

          <div x-show="checkoutForm.discount_type === 'nominal'"
               style="display: none;">
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Diskon Nominal</label>
            <input type="text"
                   inputmode="numeric"
                   :value="formatCurrency(checkoutForm.discount_nominal || 0)"
                   @input="onWalkInDiscountNominalInput($event)"
                   class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
          </div>

          <div x-show="checkoutForm.discount_type !== 'none' || ['FOC', 'Compliment'].includes(checkoutForm.foc_comp_payment_method)"
               style="display: none;">
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Auth Code Diskon (4 digit)</label>
            <input type="password"
                   inputmode="numeric"
                   maxlength="4"
                   x-model="checkoutForm.discount_auth_code"
                   placeholder="Masukkan auth code"
                   class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
            <button type="button"
                    @click="requestAuthCodeEmail()"
                    :disabled="isRequestingAuthCodeEmail"
                    class="mt-2 inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-amber-100 text-amber-700 hover:bg-amber-200 transition disabled:opacity-60 disabled:cursor-not-allowed"
                    x-text="isRequestingAuthCodeEmail ? 'Mengirim...' : 'Request Auth Code'">
            </button>
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-2">Mode Pembayaran</label>
          <div class="grid grid-cols-2 gap-2">
            <label class="flex items-center justify-center gap-2 p-3 rounded-xl border cursor-pointer transition"
                   :class="checkoutForm.payment_mode === 'normal' ? 'border-green-500 bg-green-50' : 'border-gray-200 hover:border-gray-300'">
              <input type="radio"
                     name="walk_in_payment_mode"
                     value="normal"
                     class="sr-only"
                     x-model="checkoutForm.payment_mode">
              <span class="text-xs font-semibold text-gray-700">Payment Biasa</span>
            </label>
            <label class="flex items-center justify-center gap-2 p-3 rounded-xl border cursor-pointer transition"
                   :class="checkoutForm.payment_mode === 'split' ? 'border-green-500 bg-green-50' : 'border-gray-200 hover:border-gray-300'">
              <input type="radio"
                     name="walk_in_payment_mode"
                     value="split"
                     class="sr-only"
                     x-model="checkoutForm.payment_mode">
              <span class="text-xs font-semibold text-gray-700">Split Bill</span>
            </label>
          </div>
        </div>

        <div x-show="checkoutForm.payment_mode === 'normal'"
             style="display: none;"
             class="space-y-3">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-2">Metode Pembayaran</label>
            <div class="grid grid-cols-2 gap-2">
              <template x-for="option in [
                    { value: 'cash', label: 'Tunai' },
                    { value: 'kredit', label: 'Kredit' },
                    { value: 'debit', label: 'Debit' },
                    { value: 'qris', label: 'QRIS' },
                    { value: 'transfer', label: 'Transfer' }
                  ]"
                        :key="option.value">
                <label class="flex items-center justify-center rounded-xl border px-3 py-2 text-sm font-medium cursor-pointer transition"
                       :class="checkoutForm.payment_method === option.value ? 'border-green-500 bg-green-50 text-green-700' : 'border-gray-200 text-gray-600 hover:border-gray-300'">
                  <input type="radio"
                         class="sr-only"
                         name="walk_in_payment_method"
                         :value="option.value"
                         x-model="checkoutForm.payment_method">
                  <span x-text="option.label"></span>
                </label>
              </template>
            </div>
          </div>

          <div x-show="isWalkInNonCashNormalMode()"
               style="display: none;">
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Nomor Referensi</label>
            <input type="text"
                   x-model="checkoutForm.payment_reference_number"
                   placeholder="Nomor kartu / approval / referensi QRIS"
                   class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
          </div>
        </div>

        <div x-show="checkoutForm.payment_mode === 'split'"
             style="display: none;"
             class="space-y-3">
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1.5">Cash (Opsional)</label>
              <input type="text"
                     inputmode="numeric"
                     :value="formatCurrency(checkoutForm.split_cash_amount || 0)"
                     @input="onWalkInSplitInput('cash', $event)"
                     class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1.5">Nominal Non-Cash 1</label>
              <input type="text"
                     inputmode="numeric"
                     :value="formatCurrency(checkoutForm.split_non_cash_amount || 0)"
                     @input="onWalkInSplitInput('non-cash', $event)"
                     class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1.5">Nominal Non-Cash 2</label>
              <input type="text"
                     inputmode="numeric"
                     :value="formatCurrency(checkoutForm.split_second_non_cash_amount || 0)"
                     @input="onWalkInSplitInput('second-non-cash', $event)"
                     class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
            </div>
          </div>

          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1.5">Metode Non-Cash 1</label>
              <select x-model="checkoutForm.split_non_cash_method"
                      class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                <option value="debit">Debit</option>
                <option value="kredit">Kredit</option>
                <option value="qris">QRIS</option>
                <option value="transfer">Transfer</option>
                <option value="ewallet">E-Wallet</option>
                <option value="lainnya">Lainnya</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1.5">Metode Non-Cash 2</label>
              <select x-model="checkoutForm.split_second_non_cash_method"
                      class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                <option value="debit">Debit</option>
                <option value="kredit">Kredit</option>
                <option value="qris">QRIS</option>
                <option value="transfer">Transfer</option>
                <option value="ewallet">E-Wallet</option>
                <option value="lainnya">Lainnya</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1.5">Referensi Non-Cash 1</label>
              <input type="text"
                     x-model="checkoutForm.split_non_cash_reference_number"
                     placeholder="Nomor kartu / approval / referensi"
                     class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1.5">Referensi Non-Cash 2</label>
              <input type="text"
                     x-model="checkoutForm.split_second_non_cash_reference_number"
                     placeholder="Nomor kartu / approval / referensi"
                     class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
            </div>
          </div>

          <div class="rounded-xl border p-3 text-xs space-y-1"
               :class="Math.abs(walkInSplitDiff()) > 0.01 ? 'border-red-200 bg-red-50 text-red-700' : 'border-gray-200 bg-gray-50 text-gray-700'">
            <div class="flex items-center justify-between">
              <span>Total Split</span>
              <span x-text="formatCurrency(walkInSplitTotal())"></span>
            </div>
            <div class="flex items-center justify-between font-semibold">
              <span>Sisa / Selisih</span>
              <span x-text="formatCurrency(walkInSplitDiff())"></span>
            </div>
          </div>
        </div>
      </div>

      <div x-show="hasMenuAvailabilityPreview()"
           style="display: none;"
           class="space-y-3 rounded-2xl border border-emerald-100 bg-emerald-50 p-4">
        <div>
          <p class="text-sm font-semibold text-emerald-900">Possible Portion Menu</p>
          <p class="mt-1 text-xs text-emerald-700">Stok bahan dihitung dari detail group Accurate dan inventory saat ini.</p>
        </div>

        <div class="space-y-2">
          <template x-for="menu in (menuAvailability && Array.isArray(menuAvailability.menu_items) ? menuAvailability.menu_items : [])"
                    :key="menu.product_id">
            <div class="rounded-xl border border-emerald-200 bg-white/80 px-3 py-2.5">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-sm font-semibold text-gray-900"
                     x-text="menu.name"></p>
                  <p class="mt-1 text-xs text-gray-600"
                     x-text="'Diminta ' + menu.requested_quantity + ' porsi • Possible ' + menu.possible_portions + ' porsi'"></p>
                </div>
                <span class="inline-flex rounded-full px-2 py-1 text-[11px] font-semibold"
                      :class="menu.is_available ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                      x-text="menu.is_available ? 'Cukup' : 'Tidak cukup'"></span>
              </div>
            </div>
          </template>
        </div>
      </div>

      <!-- Summary Card -->
      <div class="bg-gray-900 rounded-2xl p-4 space-y-2.5">
        <!-- Minimum Charge row -->
        <div x-show="checkoutForm.minimumCharge > 0"
             style="display: none;"
             class="flex justify-between text-sm text-gray-300">
          <span>Min. Charge</span>
          <span x-text="formatCurrency(checkoutForm.minimumCharge)"></span>
        </div>
        <!-- Min charge progress bar -->
        <div x-show="checkoutForm.minimumCharge > 0"
             style="display: none;"
             class="space-y-1">
          <div class="w-full bg-gray-700 rounded-full h-1.5">
            <div class="h-1.5 rounded-full transition-all"
                 :class="minimumChargeCoveredAmount() >= checkoutForm.minimumCharge ? 'bg-green-400' : 'bg-orange-400'"
                 :style="'width: ' + Math.min((minimumChargeCoveredAmount() / checkoutForm.minimumCharge) * 100, 100) + '%'"></div>
          </div>
          <p x-show="minimumChargeShortfall() > 0"
             class="text-xs text-orange-400 font-medium"
             x-text="'Kurang ' + formatCurrency(minimumChargeShortfall()) + ' dari min. charge'"></p>
        </div>
        <div x-show="checkoutForm.minimumCharge > 0"
             style="display: none;"
             class="border-t border-gray-700 pt-1 flex justify-between text-sm text-gray-300">
          <span>Total Bill</span>
          <span x-text="formatCurrency(finalTotal())"></span>
        </div>
        <div x-show="checkoutForm.minimumCharge === 0"
             style="display: none;"
             class="flex justify-between text-sm text-gray-300">
          <span>Total Bill</span>
          <span x-text="formatCurrency(finalTotal())"></span>
        </div>
        <div x-show="calculatedTax() > 0"
             style="display: none;"
             class="flex justify-between text-sm text-gray-300">
          <span x-text="'PB1 (' + posCharges.taxPercentage + '%)'"></span>
          <span x-text="formatCurrency(calculatedTax())"></span>
        </div>
        <div x-show="calculatedServiceCharge() > 0"
             style="display: none;"
             class="flex justify-between text-sm text-gray-300">
          <span x-text="'Service Charge (' + posCharges.serviceChargePercentage + '%)'"></span>
          <span x-text="formatCurrency(calculatedServiceCharge())"></span>
        </div>
        <div class="flex justify-between text-sm text-gray-300">
          <span>Sub Total</span>
          <span x-text="formatCurrency(subTotalBeforeDiscount())"></span>
        </div>
        <div x-show="discountAmount() > 0"
             style="display: none;"
             class="flex justify-between text-sm text-orange-400">
          <span x-text="checkoutForm.customer_type === 'walk-in'
                ? (checkoutForm.discount_type === 'percentage'
                  ? 'Diskon (' + getWalkInDiscountPercentage() + '%)'
                  : 'Diskon Nominal')
                : ('Diskon Tier ' + checkoutForm.tierName + ' (' + checkoutForm.discountPercentage + '%)')"></span>
          <span x-text="'-' + formatCurrency(discountAmount())"></span>
        </div>
        <div class="border-t border-gray-700 pt-2.5 flex justify-between font-bold text-white">
          <span class="text-base">Sisa Bayar</span>
          <span class="text-base"
                x-text="formatCurrency(payableTotal())"></span>
        </div>
        <div class="flex items-center justify-between text-xs text-gray-400 pt-1">
          <span x-text="cart.length + ' item'"></span>
          <span x-text="'Poin: +' + pointsEarned()"></span>
        </div>
      </div>

      <!-- Buttons -->
      <div class="flex gap-3 pt-1">
        <button type="button"
                @click="showCheckoutModal = false"
                class="flex-1 px-4 py-2.5 border border-gray-200 text-gray-600 rounded-xl hover:bg-gray-50 font-medium text-sm transition">
          Batal
        </button>
        <button type="button"
                @click="openConfirmModal()"
                :disabled="!canProceedToCheckout() || requiresWaiterSelection()"
                class="flex-1 px-4 py-2.5 bg-green-600 text-white rounded-xl hover:bg-green-500 font-semibold text-sm transition flex items-center justify-center gap-2">
          <svg class="w-4 h-4"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M5 13l4 4L19 7" />
          </svg>
          Selesaikan Transaksi
        </button>
      </div>
    </form>
  </div>
</div>
