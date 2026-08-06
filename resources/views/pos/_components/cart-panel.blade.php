<div class="w-full lg:w-96 bg-white border-t lg:border-t-0 lg:border-l border-gray-100 flex flex-col h-64 lg:h-full">
  <!-- Cart Header -->
  <div class="px-5 pt-5 pb-3 border-b border-gray-100 flex-shrink-0">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-gray-900 rounded-xl flex items-center justify-center">
          <svg class="w-5 h-5 text-white"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
          </svg>
        </div>
        <div>
          <h3 class="font-bold text-gray-900 text-base leading-tight">Keranjang</h3>
          <p class="text-sm text-gray-400"><span x-text="cart.length"></span> item</p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <a href="JavaScript:void(0)"
           @click="openHistoryModal()"
           class="flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 font-medium transition">
          <svg class="w-4 h-4"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Riwayat
        </a>
        <button x-show="cart.length > 0"
                @click="clearCart()"
                :disabled="isProcessing"
                style="display: none;"
                class="flex items-center gap-1 text-sm text-red-400 hover:text-red-600 font-medium transition disabled:opacity-50">
          <svg class="w-4 h-4"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
          </svg>
          Kosongkan
        </button>
      </div>
    </div>
  </div>

  <!-- Cart Items -->
  <div class="flex-1 overflow-y-auto px-5 py-4 space-y-3">
    <div x-show="cart.length === 0"
         class="flex flex-col items-center justify-center h-full text-center py-12">
      <svg class="w-16 h-16 text-gray-200 mb-4"
           fill="none"
           stroke="currentColor"
           viewBox="0 0 24 24">
        <path stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="1.5"
              d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
      </svg>
      <p class="font-semibold text-gray-400 text-base">Keranjang Kosong</p>
      <p class="text-sm text-gray-300 mt-1">Pilih produk untuk memulai</p>
    </div>
    <template x-for="item in cart"
              :key="item.id">
      <div class="flex items-center gap-3 bg-gray-50 rounded-xl p-3">
        <button type="button"
                @click="toggleDiscountItem(item.id)"
                :aria-pressed="selectedDiscountItemIds.includes(inventoryId(item.id))"
                :class="selectedDiscountItemIds.includes(inventoryId(item.id)) ? 'border-amber-500 bg-amber-100 text-amber-700' : 'border-gray-200 bg-white text-gray-400'"
                class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-lg border text-xs font-bold transition"
                title="Pilih item untuk diskon">%</button>
        <div :class="getItemBgColor(item.id)"
             class="w-11 h-11 rounded-xl flex-shrink-0 flex items-center justify-center">
          <svg class="w-5 h-5 text-white"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="1.5"
                  d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
        </div>
        <div class="flex-1 min-w-0">
          <h4 class="text-sm font-semibold text-gray-900 truncate"
              x-text="item.name"></h4>
           <p class="text-xs text-gray-400 mt-0.5"
              x-text="formatCurrency(item.price)"></p>
          <p x-show="selectedDiscountItemIds.includes(inventoryId(item.id))"
             class="mt-1 text-[11px] font-semibold text-amber-600">Dipilih untuk diskon</p>
          <div class="flex items-center gap-2 mt-2">
            <button type="button"
                    @click="updateCartQuantity(item.id, 'decrease')"
                    :disabled="isProcessing"
                    class="w-6 h-6 bg-white border border-gray-200 hover:bg-gray-100 rounded-lg flex items-center justify-center text-gray-700 font-bold text-sm disabled:opacity-50 transition">&#x2212;</button>
            <span class="text-sm font-semibold text-gray-900 w-6 text-center"
                  x-text="item.quantity"></span>
            <button type="button"
                    @click="updateCartQuantity(item.id, 'increase')"
                    :disabled="isProcessing"
                    class="w-6 h-6 bg-white border border-gray-200 hover:bg-gray-100 rounded-lg flex items-center justify-center text-gray-700 font-bold text-sm disabled:opacity-50 transition">+</button>
          </div>
          <div class="mt-2 relative">
            <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-xs">📝</span>
            <input type="text"
                   :id="`note-${item.id}`"
                   x-model="cartNotes[item.id]"
                   placeholder="Tambah catatan item..."
                   maxlength="200"
                   class="w-full text-xs border-2 border-gray-300 rounded-lg pl-6 pr-2 py-1.5 focus:ring-2 focus:ring-gray-500 focus:border-gray-500 outline-none bg-white placeholder-gray-400 text-gray-800 font-medium" />
          </div>
          <div x-show="hasMenuAvailabilityIssue(item.id)"
               style="display: none;"
               class="mt-2 rounded-lg border border-red-200 bg-red-50 px-2.5 py-2">
            <p class="text-[11px] font-semibold text-red-700"
               x-text="getMenuAvailabilityLabel(item.id)"></p>
            <p class="mt-0.5 text-[11px] text-red-600"
               x-text="getMenuAvailabilityMessage(item.id)"></p>
          </div>
        </div>
        <button type="button"
                @click="removeFromCart(item.id)"
                :disabled="isProcessing"
                class="flex-shrink-0 text-red-400 hover:text-red-600 transition disabled:opacity-50">
          <svg class="w-4 h-4"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
          </svg>
        </button>
      </div>
    </template>
  </div>

  <!-- Cart Footer -->
  <div class="px-5 pb-5 pt-3 border-t border-gray-100 flex-shrink-0 space-y-3">
    <div class="flex items-center justify-between text-sm text-gray-500">
      <span>Subtotal</span>
      <span x-text="formatCurrency(cartTotal)"></span>
    </div>
    <div class="flex items-center justify-between font-bold text-gray-900">
      <span class="text-base">Total</span>
      <span class="text-base"
            x-text="formatCurrency(cartTotal)"></span>
    </div>
    <button type="button"
            @click="openCustomerTypeModal()"
            :disabled="isProcessing || cart.length === 0 || !canProceedToCheckout()"
            :class="cart.length > 0 && canProceedToCheckout() ? 'bg-gray-900 hover:bg-gray-700 text-white' : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
            class="w-full py-3 rounded-xl font-semibold text-sm flex items-center justify-center gap-2 transition disabled:opacity-70">
      <svg class="w-5 h-5"
           fill="none"
           stroke="currentColor"
           viewBox="0 0 24 24">
        <path stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
      </svg>
      Checkout
    </button>
  </div>
</div>
