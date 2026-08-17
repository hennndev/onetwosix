<div x-show="showPaymentTypeModal"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     style="display: none;"
     class="fixed inset-0 bg-black/50 flex items-center justify-center z-[60]"
     @click.self="showPaymentTypeModal = false">
  <div class="bg-white rounded-2xl w-full max-w-md mx-4 shadow-xl max-h-[90vh] overflow-y-auto"
       @click.stop>
    <!-- Header -->
    <div class="flex items-start justify-between p-6 pb-4">
      <div>
        <h3 class="text-lg font-bold text-gray-900">Metode Pembayaran</h3>
        <p class="text-sm text-gray-500 mt-0.5">Pilih jenis pembayaran untuk menyelesaikan transaksi</p>
      </div>
      <button type="button"
              @click="showPaymentTypeModal = false"
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

    <div class="px-6 pb-6 space-y-3">
      <button type="button"
              @click="selectWalkInPaymentType('')"
              class="w-full p-4 border-2 border-gray-100 rounded-xl hover:border-green-400 hover:bg-green-50/50 transition text-left">
        <h4 class="font-bold text-gray-900 text-sm">Pembayaran Biasa</h4>
        <p class="text-xs text-gray-500 mt-0.5">Tunai, Debit, QRIS, Transfer, Split, Parsial / Hutang</p>
      </button>

      @if ($generalSettings->foc_enabled)
        <button type="button"
                @click="selectWalkInPaymentType('FOC')"
                class="w-full p-4 border-2 border-gray-100 rounded-xl hover:border-green-400 hover:bg-green-50/50 transition text-left">
          <h4 class="font-bold text-gray-900 text-sm">FOC</h4>
          @if ((int) $generalSettings->foc_discount_percentage > 0)
            <p class="text-xs text-gray-500 mt-0.5">Diskon {{ (int) $generalSettings->foc_discount_percentage }}% {{ $generalSettings->foc_requires_auth_code ? '— wajib auth code' : '— tanpa auth code' }}</p>
          @else
            <p class="text-xs text-gray-500 mt-0.5">Free of charge — seluruh transaksi dibebaskan{{ $generalSettings->foc_requires_auth_code ? ' — wajib auth code' : ' — tanpa auth code' }}</p>
          @endif
        </button>
      @endif

      @if ($generalSettings->compliment_enabled)
        <button type="button"
                @click="selectWalkInPaymentType('Compliment')"
                class="w-full p-4 border-2 border-gray-100 rounded-xl hover:border-green-400 hover:bg-green-50/50 transition text-left">
          <h4 class="font-bold text-gray-900 text-sm">Compliment</h4>
          <p class="text-xs text-gray-500 mt-0.5">Diskon {{ (int) $generalSettings->compliment_discount_percentage }}% {{ $generalSettings->compliment_requires_auth_code ? '— wajib auth code' : '— tanpa auth code' }}</p>
        </button>
      @endif
    </div>
  </div>
</div>
