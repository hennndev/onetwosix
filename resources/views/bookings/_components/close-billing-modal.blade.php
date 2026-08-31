<!-- Close Billing Modal -->
<div id="closeBillingModal"
     class="fixed inset-0 z-50 hidden flex items-end justify-center overflow-y-auto bg-black/50 sm:items-center">
  <div class="flex max-h-[90vh] w-full flex-col rounded-t-2xl bg-white shadow-xl sm:mx-4 sm:max-w-md sm:rounded-2xl"
       onclick="event.stopPropagation()">

    <!-- Header -->
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 bg-green-100 rounded-xl flex items-center justify-center">
          <svg class="w-5 h-5 text-green-600"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
          </svg>
        </div>
        <div>
          <div class="flex items-center gap-2 flex-wrap">
            <h3 class="font-bold text-gray-900 text-sm">Tutup Billing</h3>
            <span id="cbFocBadge"
                  class="hidden px-2 py-0.5 bg-green-100 text-green-700 text-xs font-bold rounded-full"></span>
          </div>
          <p class="text-xs text-gray-500 mt-0.5"
             id="cbModalSubtitle">Konfirmasi pembayaran customer</p>
        </div>
      </div>
      <button onclick="closeCloseBillingModal()"
              class="text-gray-400 hover:text-gray-600 transition">
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

    <!-- Body -->
    <div class="overflow-y-auto px-5 py-4 space-y-4">

      <!-- Billing Summary -->
      <div class="bg-gray-50 rounded-xl p-4 space-y-2 text-sm">
        <div class="flex justify-between text-gray-600">
          <span>Total Bill</span>
          <span id="cbTotalBill">Rp 0</span>
        </div>
        <div class="flex justify-between text-gray-600"
             id="cbTaxRow">
          <span id="cbTaxLabel">PB1</span>
          <span id="cbTax">Rp 0</span>
        </div>
        <div class="flex justify-between text-gray-600"
             id="cbServiceChargeRow">
          <span id="cbServiceChargeLabel">Service Charge</span>
          <span id="cbServiceCharge">Rp 0</span>
        </div>
        <div class="flex justify-between text-gray-600">
          <span>Sub Total</span>
          <span id="cbSubTotal">Rp 0</span>
        </div>
        <div class="flex justify-between text-gray-600"
             id="cbDownPaymentRow"
             style="display:none!important">
          <span>DP</span>
          <span id="cbDownPayment"
                class="text-green-600">Rp 0</span>
        </div>
        <div class="flex justify-between text-gray-600"
             id="cbDiscountRow"
             style="display:none!important">
          <span>Diskon</span>
          <span id="cbDiscount"
                class="text-green-600">- Rp 0</span>
        </div>
        <div class="border-t border-gray-200 pt-2 flex justify-between font-bold text-gray-900 text-base">
          <span>Sisa Bayar</span>
          <span id="cbGrandTotal">Rp 0</span>
        </div>
      </div>

      <!-- Minimum charge warning -->
      <div id="cbMinWarning"
           class="hidden flex items-start gap-2 bg-amber-50 border border-amber-200 rounded-xl p-3">
        <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <p class="text-xs text-amber-700"
           id="cbMinWarningText"></p>
      </div>

      <div id="cbCheckerWarning"
           class="hidden flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-3">
        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600">
          <svg class="h-4 w-4"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 12l2 2 4-4m5 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="min-w-0 flex-1">
          <p class="text-sm font-semibold text-amber-900">Transaction Checker belum lengkap</p>
          <p class="mt-1 text-xs text-amber-800"
             id="cbCheckerProgressText"></p>
          <p class="mt-1 text-xs text-amber-700"
             id="cbCheckerWarningText"></p>
        </div>
      </div>

      <!-- Discount request (hidden for FOC/Compliment — diskon otomatis dari setting) -->
      <div id="cbDiscountRequestBlock"
           class="rounded-xl border border-gray-200 bg-gray-50 p-3 space-y-3">
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-2">Discount (Opsional)</label>
          <div class="grid grid-cols-2 gap-2">
            <label class="flex items-center justify-center gap-2 p-2.5 rounded-lg border cursor-pointer has-[:checked]:border-green-500 has-[:checked]:bg-green-50 border-gray-200 hover:border-gray-300 transition">
              <input type="radio"
                     name="cb_discount_type"
                     value="none"
                     class="sr-only"
                     checked>
              <span class="text-xs font-semibold text-gray-700">Tanpa</span>
            </label>
            <label class="flex items-center justify-center gap-2 p-2.5 rounded-lg border cursor-pointer has-[:checked]:border-green-500 has-[:checked]:bg-green-50 border-gray-200 hover:border-gray-300 transition">
              <input type="radio"
                     name="cb_discount_type"
                     value="item"
                     class="sr-only">
              <span class="text-xs font-semibold text-gray-700">Item</span>
            </label>
          </div>
        </div>

        <div id="cbDiscountItemsBlock"
             class="hidden space-y-3">
          <div>
            <p class="block text-xs font-semibold text-gray-600 mb-2">Pilih item yang mendapat diskon</p>
            <div id="cbDiscountItems"
                 class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-gray-200 bg-white p-2"></div>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Jenis Diskon</label>
            <div class="grid grid-cols-2 gap-2">
              <label class="flex items-center justify-center gap-2 p-2 rounded-lg border cursor-pointer has-[:checked]:border-green-500 has-[:checked]:bg-green-50 border-gray-200 hover:border-gray-300 transition">
                <input type="radio"
                       name="cb_discount_item_type"
                       value="percentage"
                       class="sr-only"
                       checked>
                <span class="text-xs font-semibold text-gray-700">%</span>
              </label>
              <label class="flex items-center justify-center gap-2 p-2 rounded-lg border cursor-pointer has-[:checked]:border-green-500 has-[:checked]:bg-green-50 border-gray-200 hover:border-gray-300 transition">
                <input type="radio"
                       name="cb_discount_item_type"
                       value="nominal"
                       class="sr-only">
                <span class="text-xs font-semibold text-gray-700">Nominal</span>
              </label>
            </div>
          </div>
        </div>

        <div id="cbDiscountAuthBlock"
             class="hidden">
          <label for="cb_discount_auth_code"
                 class="block text-xs font-semibold text-gray-600 mb-1.5">Auth Code Diskon (4 digit)</label>
          <input id="cb_discount_auth_code"
                 type="password"
                 inputmode="numeric"
                 maxlength="4"
                 placeholder="Masukkan auth code"
                 class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
          <button type="button"
                  onclick="requestAuthCodeEmailBooking()"
                  id="cbRequestAuthCodeBtn"
                  class="mt-2 inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-amber-100 text-amber-700 hover:bg-amber-200 transition disabled:opacity-60 disabled:cursor-not-allowed"
                  disabled>
            Request Auth Code
          </button>
        </div>
      </div>

      {{-- Auth code khusus FOC/Compliment (wajib jika setting requires_auth_code) — sibling di luar blok discount,
           karena blok discount di-hide saat FOC/Compliment. --}}
      <div id="cbFocAuthBlock"
           class="hidden rounded-xl border border-gray-200 bg-green-50 p-3 space-y-3">
        <label for="cb_foc_comp_auth_code"
               class="block text-xs font-semibold text-gray-600 mb-1.5">Auth Code FOC / Compliment (4 digit)</label>
        <input id="cb_foc_comp_auth_code"
               type="password"
               inputmode="numeric"
               maxlength="4"
               placeholder="Masukkan auth code"
               class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
        <button type="button"
                onclick="requestFocAuthCodeEmailBooking()"
                id="cbRequestFocAuthCodeBtn"
                class="mt-2 inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-amber-100 text-amber-700 hover:bg-amber-200 transition disabled:opacity-60 disabled:cursor-not-allowed"
                disabled>
          Request Auth Code
        </button>
      </div>

      <!-- Payment mode (hidden for FOC/Compliment) -->
      <div id="cbPaymentModeBlock">
        <label class="block text-xs font-semibold text-gray-600 mb-2">Mode Pembayaran</label>
        <div class="grid grid-cols-3 gap-2">
          @foreach (['normal' => 'Payment Biasa', 'split' => 'Split Bill', 'partial' => 'Parsial / Hutang'] as $val => $label)
            <label class="flex items-center justify-center gap-2 p-3 rounded-xl border cursor-pointer
                          has-[:checked]:border-green-500 has-[:checked]:bg-green-50 border-gray-200 hover:border-gray-300 transition">
              <input type="radio"
                     name="cb_payment_mode"
                     value="{{ $val }}"
                     class="sr-only"
                     {{ $val === 'normal' ? 'checked' : '' }}>
              <span class="text-xs font-semibold text-gray-700">{{ $label }}</span>
            </label>
          @endforeach
        </div>
      </div>

      <!-- Payment method (normal mode) -->
      <div id="cbNormalMethodBlock">
        <label class="block text-xs font-semibold text-gray-600 mb-2">Metode Pembayaran</label>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
          @foreach (['cash' => 'Tunai', 'kredit' => 'Kredit', 'debit' => 'Debit', 'qris' => 'QRIS', 'transfer' => 'Transfer'] as $val => $label)
            <label class="flex flex-col items-center gap-1.5 p-3 rounded-xl border cursor-pointer
                          has-[:checked]:border-green-500 has-[:checked]:bg-green-50 border-gray-200 hover:border-gray-300 transition">
              <input type="radio"
                     name="cb_payment_method"
                     value="{{ $val }}"
                     class="sr-only"
                     {{ $val === 'cash' ? 'checked' : '' }}>
              <svg class="w-5 h-5 text-gray-500"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                @if ($val === 'cash')
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z" />
                @elseif ($val === 'qris')
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M7 7h.01M7 12h.01M7 17h.01M12 7h5M12 12h5M12 17h5M4 4h16v16H4z" />
                @else
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                @endif
              </svg>
              <span class="text-xs font-medium text-gray-700">{{ $label }}</span>
            </label>
          @endforeach
        </div>
        <div id="cbNormalReferenceBlock"
             class="mt-3 hidden">
          <label for="cb_payment_reference_number"
                 class="block text-xs font-semibold text-gray-600 mb-1.5">Nomor Referensi</label>
          <input id="cb_payment_reference_number"
                 type="text"
                 placeholder="Nomor kartu / approval / referensi QRIS"
                 class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
        </div>
      </div>

      <!-- Partial payment mode: nominal DP / partial payment -->
      <div id="cbPartialBlock"
           class="hidden rounded-xl border border-blue-200 bg-blue-50/60 p-3 space-y-2">
        <label for="cb_partial_paid_amount_display"
               class="block text-xs font-semibold text-blue-900">Nominal Diterima Saat Ini (DP / Parsial)</label>
        <input id="cb_partial_paid_amount_display"
               type="text"
               inputmode="numeric"
               value="Rp 0"
               oninput="const val = extractNumber(this.value); document.getElementById('cb_partial_paid_amount').value = val; this.value = formatRupiah(val);"
               class="w-full px-3 py-2 rounded-lg border border-blue-300 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white">
        <input id="cb_partial_paid_amount"
               type="hidden"
               value="0">
        <p class="text-xs text-blue-700">Sisa tagihan akan tercatat sebagai hutang/piutang atas nama customer.</p>
      </div>

      <!-- Split mode: payment 1 + payment 2 (+ optional cash) -->
      <div id="cbSplitBlock"
           class="hidden space-y-3">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <div>
            <label for="cb_split_cash"
                   class="block text-xs font-semibold text-gray-600 mb-1.5">Cash (Opsional)</label>
            <input id="cb_split_cash_display"
                   type="text"
                   inputmode="numeric"
                   value="Rp 0"
                   class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
            <input id="cb_split_cash"
                   type="hidden"
                   value="0">
          </div>
          <div>
            <label for="cb_split_non_cash_amount"
                   class="block text-xs font-semibold text-gray-600 mb-1.5">Nominal Non-Cash 1</label>
            <input id="cb_split_non_cash_amount_display"
                   type="text"
                   inputmode="numeric"
                   value="Rp 0"
                   class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
            <input id="cb_split_non_cash_amount"
                   type="hidden"
                   value="0">
          </div>
          <div>
            <label for="cb_split_second_non_cash_amount"
                   class="block text-xs font-semibold text-gray-600 mb-1.5">Nominal Non-Cash 2</label>
            <input id="cb_split_second_non_cash_amount_display"
                   type="text"
                   inputmode="numeric"
                   value="Rp 0"
                   class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
            <input id="cb_split_second_non_cash_amount"
                   type="hidden"
                   value="0">
          </div>
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div>
            <label for="cb_split_non_cash_method"
                   class="block text-xs font-semibold text-gray-600 mb-1.5">Metode Non-Cash 1</label>
            <select id="cb_split_non_cash_method"
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
            <label for="cb_split_second_non_cash_method"
                   class="block text-xs font-semibold text-gray-600 mb-1.5">Metode Non-Cash 2</label>
            <select id="cb_split_second_non_cash_method"
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
            <label for="cb_split_non_cash_reference_number"
                   class="block text-xs font-semibold text-gray-600 mb-1.5">Referensi Non-Cash 1</label>
            <input id="cb_split_non_cash_reference_number"
                   type="text"
                   placeholder="Nomor kartu / approval / referensi"
                   class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
          </div>
          <div>
            <label for="cb_split_second_non_cash_reference_number"
                   class="block text-xs font-semibold text-gray-600 mb-1.5">Referensi Non-Cash 2</label>
            <input id="cb_split_second_non_cash_reference_number"
                   type="text"
                   placeholder="Nomor kartu / approval / referensi"
                   class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
          </div>
        </div>
        <div id="cbSplitSummary"
             class="rounded-xl border border-gray-200 bg-gray-50 p-3 text-xs text-gray-700 space-y-1">
          <div class="flex items-center justify-between">
            <span>Total Split</span>
            <span id="cbSplitTotal">Rp 0</span>
          </div>
          <div class="flex items-center justify-between font-semibold">
            <span>Sisa / Selisih</span>
            <span id="cbSplitDiff">Rp 0</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div class="flex gap-3 px-5 pb-5">
      <button onclick="closeCloseBillingModal()"
              class="flex-1 px-4 py-2.5 border border-gray-200 text-gray-600 rounded-xl hover:bg-gray-50 font-medium text-sm transition">
        Batal
      </button>
      <button id="cbSubmitBtn"
              onclick="submitCloseBilling()"
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
        Tutup & Cetak Struk
      </button>
    </div>
  </div>
</div>

<!-- Payment Type Chooser (Step 0) -->
<div id="cbPaymentTypeModal"
     class="fixed inset-0 z-[60] hidden flex items-end justify-center overflow-y-auto bg-black/50 sm:items-center">
  <div class="w-full max-w-sm rounded-t-2xl bg-white shadow-xl sm:rounded-2xl"
       onclick="event.stopPropagation()">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
      <h3 class="font-bold text-gray-900 text-sm">Pilih Metode Pembayaran</h3>
      <button type="button"
              onclick="closePaymentTypeModal()"
              class="text-gray-400 hover:text-gray-600 transition">
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
    <div class="px-5 py-4 space-y-2">
      <p class="text-xs text-gray-500 mb-3">Pilih jenis pembayaran sebelum detail billing ditampilkan.</p>
      <button type="button"
              onclick="proceedCloseBilling('')"
              class="w-full p-3 rounded-xl border border-gray-200 hover:border-green-500 hover:bg-green-50 transition text-left">
        <p class="text-sm font-semibold text-gray-800">Pembayaran Biasa</p>
        <p class="text-xs text-gray-500 mt-0.5">Tunai, Debit, QRIS, Transfer, Split, Parsial / Hutang</p>
      </button>
      @if ($generalSettings->foc_enabled)
        <button type="button"
                onclick="proceedCloseBilling('FOC')"
                class="w-full p-3 rounded-xl border border-gray-200 hover:border-green-500 hover:bg-green-50 transition text-left">
          <p class="text-sm font-semibold text-gray-800">FOC</p>
          @if ((int) $generalSettings->foc_discount_percentage > 0)
            <p class="text-xs text-gray-500 mt-0.5">Diskon {{ (int) $generalSettings->foc_discount_percentage }}% {{ $generalSettings->foc_requires_auth_code ? '— wajib auth code' : '— tanpa auth code' }}</p>
          @else
            <p class="text-xs text-gray-500 mt-0.5">Free of charge — seluruh billing dibebaskan{{ $generalSettings->foc_requires_auth_code ? ' — wajib auth code' : ' — tanpa auth code' }}</p>
          @endif
        </button>
      @endif
      @if ($generalSettings->compliment_enabled)
        <button type="button"
                onclick="proceedCloseBilling('Compliment')"
                class="w-full p-3 rounded-xl border border-gray-200 hover:border-green-500 hover:bg-green-50 transition text-left">
          <p class="text-sm font-semibold text-gray-800">Compliment</p>
          <p class="text-xs text-gray-500 mt-0.5">Diskon {{ (int) $generalSettings->compliment_discount_percentage }}% {{ $generalSettings->compliment_requires_auth_code ? '— wajib auth code' : '— tanpa auth code' }}</p>
        </button>
      @endif
    </div>
  </div>
</div>

<script>
  const cbVerifyAuthCodeUrl = @json(route('admin.settings.daily-auth-code.verify'));
  // Apakah tipe FOC/Compliment butuh auth code (dari setting).
  function cbFocRequiresAuth(type) {
    if (type === 'FOC') return Boolean(cbFocSettings.focRequiresAuthCode);
    if (type === 'Compliment') return Boolean(cbFocSettings.complimentRequiresAuthCode);

    return false;
  }

  const cbFocSettings = {
    focEnabled: @json((bool) $generalSettings->foc_enabled),
    complimentEnabled: @json((bool) $generalSettings->compliment_enabled),
    focRequiresAuthCode: @json((bool) $generalSettings->foc_requires_auth_code),
    complimentRequiresAuthCode: @json((bool) $generalSettings->compliment_requires_auth_code),
    focDiscountPercentage: @json((int) $generalSettings->foc_discount_percentage),
    complimentDiscountPercentage: @json((int) $generalSettings->compliment_discount_percentage),
  };
  const cbSendAuthCodeEmailUrl = @json(route('admin.settings.daily-auth-code.send-email'));
  let closeBillingBookingId = null;
  let cbCurrentGrandTotal = 0;
  let cbCheckerIncomplete = false;
  let isRequestingAuthCodeEmailBooking = false;
  let isRequestingFocAuthCodeEmailBooking = false;
  let cbCurrentSubTotal = 0;
  let cbCurrentDownPayment = 0;

  function formatRupiah(value) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(value || 0);
  }

  function extractNumber(inputValue) {
    const digits = String(inputValue || '').replace(/[^0-9]/g, '');
    return digits ? Number(digits) : 0;
  }

  function setSplitInput(which, amount) {
    const hidden = document.getElementById(`cb_split_${which}`);
    const display = document.getElementById(`cb_split_${which}_display`);
    hidden.value = String(amount || 0);
    display.value = formatRupiah(amount || 0);
  }

  function getDiscountType() {
    return document.querySelector('input[name="cb_discount_type"]:checked')?.value || 'none';
  }

  function updateDiscountUI() {
    const discountType = getDiscountType();
    const focComp = cbFocCompType;
    const authBlock = document.getElementById('cbDiscountAuthBlock');
    const focAuthBlock = document.getElementById('cbFocAuthBlock');
    const requestBtn = document.getElementById('cbRequestAuthCodeBtn');
    const focRequestBtn = document.getElementById('cbRequestFocAuthCodeBtn');
    const discountRequestBlock = document.getElementById('cbDiscountRequestBlock');

    // FOC/Compliment → diskon otomatis dari setting, sembunyikan seluruh blok discount.
    const isFocComp = ['FOC', 'Compliment'].includes(focComp);
    if (discountRequestBlock) {
      discountRequestBlock.classList.toggle('hidden', isFocComp);
    }

    const itemsBlock = document.getElementById('cbDiscountItemsBlock');
    if (itemsBlock) {
      itemsBlock.classList.toggle('hidden', discountType !== 'item');
    }

    // Auth split: FOC/Compliment pakai blok sendiri; diskon biasa pakai blok diskon.
    const focAuthVisible = isFocComp && cbFocRequiresAuth(focComp);
    const authVisible = isFocComp ? focAuthVisible : discountType !== 'none';

    if (focAuthBlock) {
      focAuthBlock.classList.toggle('hidden', !focAuthVisible);
    }
    authBlock.classList.toggle('hidden', !authVisible);

    // Kosongkan auth code saat blok benar-benar tersembunyi.
    if (authBlock.classList.contains('hidden')) {
      document.getElementById('cb_discount_auth_code').value = '';
    }
    if (focAuthBlock && focAuthBlock.classList.contains('hidden')) {
      document.getElementById('cb_foc_comp_auth_code').value = '';
    }

    if (requestBtn) {
      requestBtn.disabled = false;
    }
    if (focRequestBtn) {
      focRequestBtn.disabled = false;
    }
  }

  function onSplitInput(which, event) {
    const enteredAmount = extractNumber(event.target.value);
    const maxAmount = Math.max(cbCurrentGrandTotal, 0);
    const normalizedAmount = Math.min(Math.max(enteredAmount, 0), maxAmount);

    let splitCashAmount = Number(document.getElementById('cb_split_cash')?.value || 0);
    let splitNonCashAmount = Number(document.getElementById('cb_split_non_cash_amount')?.value || 0);
    let splitSecondNonCashAmount = Number(document.getElementById('cb_split_second_non_cash_amount')?.value || 0);

    if (which === 'cash') {
      splitCashAmount = normalizedAmount;
      splitNonCashAmount = Math.max(maxAmount - splitCashAmount, 0);
      splitSecondNonCashAmount = 0;
    }

    if (which === 'non_cash_amount') {
      splitNonCashAmount = normalizedAmount;

      if (splitCashAmount > maxAmount - splitNonCashAmount) {
        splitCashAmount = Math.max(maxAmount - splitNonCashAmount, 0);
      }

      splitSecondNonCashAmount = Math.max(maxAmount - splitCashAmount - splitNonCashAmount, 0);
    }

    if (which === 'second_non_cash_amount') {
      splitSecondNonCashAmount = normalizedAmount;

      if (splitCashAmount > maxAmount - splitSecondNonCashAmount) {
        splitCashAmount = Math.max(maxAmount - splitSecondNonCashAmount, 0);
      }

      splitNonCashAmount = Math.max(maxAmount - splitCashAmount - splitSecondNonCashAmount, 0);
    }

    setSplitInput('cash', splitCashAmount);
    setSplitInput('non_cash_amount', splitNonCashAmount);
    setSplitInput('second_non_cash_amount', splitSecondNonCashAmount);

    updateSplitSummary();
  }

  function updateCloseBillingSubmitButton() {
    const btn = document.getElementById('cbSubmitBtn');

    if (!btn) {
      return;
    }

    btn.disabled = cbCheckerIncomplete;
    btn.classList.toggle('bg-green-600', !cbCheckerIncomplete);
    btn.classList.toggle('hover:bg-green-500', !cbCheckerIncomplete);
    btn.classList.toggle('text-white', !cbCheckerIncomplete);
    btn.classList.toggle('bg-gray-300', cbCheckerIncomplete);
    btn.classList.toggle('text-gray-600', cbCheckerIncomplete);
    btn.classList.toggle('cursor-not-allowed', cbCheckerIncomplete);

    btn.innerHTML = cbCheckerIncomplete ?
      '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg> Selesaikan Checker Dulu' :
      '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg> Tutup & Cetak Struk';
  }

  let cbPendingCloseTrigger = null;
  let cbFocCompType = '';
  let cbDiscountItems = [];

  function renderCloseBillingDiscountItems() {
    const container = document.getElementById('cbDiscountItems');
    if (!container) return;

    container.innerHTML = cbDiscountItems.length === 0
      ? '<p class="px-2 py-3 text-center text-xs text-gray-500">Tidak ada order item aktif pada billing ini.</p>'
      : cbDiscountItems.map(item => `
          <label class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 hover:bg-amber-50">
            <input type="checkbox" name="cb_discount_order_item_ids" value="${item.id}" onchange="updateGrandTotalFromDiscount()" class="rounded border-gray-300 text-green-600 focus:ring-green-500">
            <span class="min-w-0 flex-1">
              <span class="block truncate text-xs text-gray-700">${item.quantity}x ${item.name}</span>
              <span class="block text-[11px] text-red-600" id="cbDiscountAmount_${item.id}"></span>
            </span>
            <span class="text-xs font-semibold text-gray-700">${formatRupiah(item.subtotal)}</span>
            <input type="number" name="cb_discount_item_value_${item.id}" data-item-id="${item.id}" min="0" max="100" step="0.01"
                   placeholder="%" oninput="updateGrandTotalFromDiscount()"
                   class="w-20 rounded-md border border-gray-300 px-2 py-1 text-xs text-right focus:ring-2 focus:ring-green-500">
          </label>
        `).join('');
  }

  function openCloseBillingModal(trigger) {
    cbPendingCloseTrigger = trigger;
    cbFocCompType = '';
    document.getElementById('cbPaymentTypeModal').classList.remove('hidden');
  }

  function closePaymentTypeModal() {
    document.getElementById('cbPaymentTypeModal').classList.add('hidden');
    cbPendingCloseTrigger = null;
  }

  function proceedCloseBilling(type) {
    const trigger = cbPendingCloseTrigger;
    closePaymentTypeModal();
    if (!trigger) {
      return;
    }

    showCloseBillingDetail(trigger).then(() => {
      cbFocCompType = type || '';

      // FOC/Compliment: diskon dihitung otomatis oleh backend dari settings.
      updateFocBadge();
      updateDiscountUI();
      updateGrandTotalFromDiscount();
      updatePaymentModeUI();
    });
  }

  async function showCloseBillingDetail(trigger) {
    const bookingId = Number(trigger?.dataset?.bookingId || 0);
    const minimumCharge = Number(trigger?.dataset?.minimumCharge || 0);
    const ordersTotal = Number(trigger?.dataset?.ordersTotal || 0);
    const discountAmount = Number(trigger?.dataset?.discountAmount || 0);
    const downPaymentAmount = Number(trigger?.dataset?.downPaymentAmount || 0);
    const serviceChargeAmount = Number(trigger?.dataset?.serviceCharge || 0);
    const taxAmount = Number(trigger?.dataset?.tax || 0);
    const serviceChargePercentage = Number(trigger?.dataset?.serviceChargePercentage || 0);
    const taxPercentage = Number(trigger?.dataset?.taxPercentage || 0);
    const checkerChecked = Number(trigger?.dataset?.checkerChecked || 0);
    const checkerTotal = Number(trigger?.dataset?.checkerTotal || 0);

    closeBillingBookingId = bookingId;

    // Data item untuk diskon per item (dari data-items-json pada tombol trigger).
    cbDiscountItems = (() => {
      try {
        const raw = trigger?.dataset?.itemsJson;
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed : [];
      } catch (e) {
        return [];
      }
    })();
    renderCloseBillingDiscountItems();

    const fmt = v => formatRupiah(v || 0);

    // Total Bill adalah max(minimum_charge, orders_total) tanpa diskon
    const totalBill = Math.max(minimumCharge, ordersTotal);
    document.getElementById('cbTotalBill').textContent = fmt(totalBill);

    const subTotal = totalBill + taxAmount + serviceChargeAmount;

    // Final amount setelah diskon dipotong dari subtotal
    const subTotalAfterDiscount = subTotal - discountAmount;

    // Sisa Bayar = SubTotal setelah diskon - DP
    const sistaBayar = Math.max(Math.round(subTotalAfterDiscount - downPaymentAmount), 0);

    const discountRow = document.getElementById('cbDiscountRow');
    if (discountAmount > 0) {
      document.getElementById('cbDiscount').textContent = '- ' + fmt(discountAmount);
      discountRow.style.removeProperty('display');
    } else {
      discountRow.style.setProperty('display', 'none', 'important');
    }

    const downPaymentRow = document.getElementById('cbDownPaymentRow');
    if (downPaymentAmount > 0) {
      document.getElementById('cbDownPayment').textContent = fmt(downPaymentAmount);
      downPaymentRow.style.removeProperty('display');
    } else {
      downPaymentRow.style.setProperty('display', 'none', 'important');
    }

    const serviceLabel = serviceChargePercentage > 0 ?
      `Service Charge (${serviceChargePercentage}%)` :
      'Service Charge';
    const taxLabel = taxPercentage > 0 ?
      `PB1 (${taxPercentage}%)` :
      'PB1';

    document.getElementById('cbServiceChargeLabel').textContent = serviceLabel;
    document.getElementById('cbTaxLabel').textContent = taxLabel;
    document.getElementById('cbServiceCharge').textContent = fmt(serviceChargeAmount);
    document.getElementById('cbTax').textContent = fmt(taxAmount);
    document.getElementById('cbSubTotal').textContent = fmt(subTotal);

    // Store current values for discount recalculation
    cbCurrentSubTotal = subTotal;
    cbCurrentDownPayment = downPaymentAmount;

    cbCurrentGrandTotal = sistaBayar;
    document.getElementById('cbGrandTotal').textContent = fmt(sistaBayar);

    document.getElementById('cbServiceChargeRow').style.display = serviceChargeAmount > 0 ? 'flex' : 'none';
    document.getElementById('cbTaxRow').style.display = taxAmount > 0 ? 'flex' : 'none';

    // Minimum charge warning
    const warning = document.getElementById('cbMinWarning');
    if (minimumCharge > 0 && ordersTotal < minimumCharge) {
      document.getElementById('cbMinWarningText').textContent =
        'Orders total belum memenuhi minimum charge. Selisih: ' + fmt(minimumCharge - ordersTotal);
      warning.classList.remove('hidden');
    } else {
      warning.classList.add('hidden');
    }

    const checkerWarning = document.getElementById('cbCheckerWarning');
    cbCheckerIncomplete = checkerTotal > 0 && checkerChecked < checkerTotal;

    if (cbCheckerIncomplete) {
      const checkerRemaining = checkerTotal - checkerChecked;
      document.getElementById('cbCheckerProgressText').textContent =
        `${checkerChecked} dari ${checkerTotal} item sudah dichecklist.`;
      document.getElementById('cbCheckerWarningText').textContent =
        `Masih ada ${checkerRemaining} item yang belum dichecklist di Transaction Checker.`;
      checkerWarning.classList.remove('hidden');
    } else {
      checkerWarning.classList.add('hidden');
    }

    // Reset payment mode + method defaults
    document.querySelector('input[name="cb_payment_mode"][value="normal"]').checked = true;
    document.querySelector('input[name="cb_payment_method"][value="cash"]').checked = true;
    document.getElementById('cb_partial_paid_amount').value = '0';
    document.getElementById('cb_partial_paid_amount_display').value = 'Rp 0';
    cbFocCompType = '';
    updateFocBadge();
    document.getElementById('cb_payment_reference_number').value = '';
    document.querySelector('input[name="cb_discount_type"][value="none"]').checked = true;
    document.getElementById('cb_discount_auth_code').value = '';
    setSplitInput('cash', 0);
    setSplitInput('non_cash_amount', sistaBayar);
    setSplitInput('second_non_cash_amount', 0);
    document.getElementById('cb_split_non_cash_method').value = 'debit';
    document.getElementById('cb_split_second_non_cash_method').value = 'debit';
    document.getElementById('cb_split_non_cash_reference_number').value = '';
    document.getElementById('cb_split_second_non_cash_reference_number').value = '';

    updatePaymentModeUI();
    updateDiscountUI();
    updateSplitSummary();
    updateCloseBillingSubmitButton();

    document.getElementById('closeBillingModal').classList.remove('hidden');
  }

  function closeCloseBillingModal() {
    document.getElementById('closeBillingModal').classList.add('hidden');
    closeBillingBookingId = null;
    cbCurrentGrandTotal = 0;
    cbCheckerIncomplete = false;
    cbFocCompType = '';
    updateFocBadge();
    updateCloseBillingSubmitButton();
  }

  function updateFocBadge() {
    const badge = document.getElementById('cbFocBadge');
    if (!badge) {
      return;
    }

    if (cbFocCompType) {
      badge.textContent = cbFocCompType === 'FOC' ? 'FOC (free of charge)' : cbFocCompType;
      badge.classList.remove('hidden');
    } else {
      badge.classList.add('hidden');
    }
  }

  function updatePaymentModeUI() {
    const mode = document.querySelector('input[name="cb_payment_mode"]:checked')?.value || 'normal';
    const paymentMethod = document.querySelector('input[name="cb_payment_method"]:checked')?.value || 'cash';
    const focComp = cbFocCompType;
    const normalBlock = document.getElementById('cbNormalMethodBlock');
    const splitBlock = document.getElementById('cbSplitBlock');
    const normalReferenceBlock = document.getElementById('cbNormalReferenceBlock');
    const paymentModeBlock = document.getElementById('cbPaymentModeBlock');
    const partialBlock = document.getElementById('cbPartialBlock');

    // FOC/Compliment → payment_method otomatis, sembunyikan semua metode.
    if (['FOC', 'Compliment'].includes(focComp)) {
      normalBlock.classList.add('hidden');
      splitBlock.classList.add('hidden');
      normalReferenceBlock.classList.add('hidden');
      if (partialBlock) partialBlock.classList.add('hidden');
      if (paymentModeBlock) {
        paymentModeBlock.classList.add('hidden');
      }
      return;
    }

    if (paymentModeBlock) {
      paymentModeBlock.classList.remove('hidden');
    }

    if (mode === 'split') {
      normalBlock.classList.add('hidden');
      splitBlock.classList.remove('hidden');
      normalReferenceBlock.classList.add('hidden');
      if (partialBlock) partialBlock.classList.add('hidden');
    } else if (mode === 'partial') {
      normalBlock.classList.remove('hidden');
      splitBlock.classList.add('hidden');
      if (partialBlock) partialBlock.classList.remove('hidden');
      if (paymentMethod === 'cash') {
        normalReferenceBlock.classList.add('hidden');
      } else {
        normalReferenceBlock.classList.remove('hidden');
      }
    } else {
      splitBlock.classList.add('hidden');
      normalBlock.classList.remove('hidden');
      if (partialBlock) partialBlock.classList.add('hidden');
      if (paymentMethod === 'cash') {
        normalReferenceBlock.classList.add('hidden');
      } else {
        normalReferenceBlock.classList.remove('hidden');
      }
    }
  }

  function updateSplitSummary() {
    const fmt = v => formatRupiah(v || 0);
    const splitCash = Number(document.getElementById('cb_split_cash')?.value || 0);
    const splitNonCash = Number(document.getElementById('cb_split_non_cash_amount')?.value || 0);
    const splitSecondNonCash = Number(document.getElementById('cb_split_second_non_cash_amount')?.value || 0);
    const splitTotal = splitCash + splitNonCash + splitSecondNonCash;
    const diff = cbCurrentGrandTotal - splitTotal;

    document.getElementById('cbSplitTotal').textContent = fmt(splitTotal);
    document.getElementById('cbSplitDiff').textContent = fmt(diff);

    const summary = document.getElementById('cbSplitSummary');
    summary.classList.remove('border-red-200', 'bg-red-50', 'text-red-700');
    summary.classList.add('border-gray-200', 'bg-gray-50', 'text-gray-700');

    if (Math.abs(diff) > 0.01) {
      summary.classList.remove('border-gray-200', 'bg-gray-50', 'text-gray-700');
      summary.classList.add('border-red-200', 'bg-red-50', 'text-red-700');
    }
  }

  function getSelectedDiscountItemIds() {
    return [...document.querySelectorAll('input[name="cb_discount_order_item_ids"]:checked')].map(input => Number(input.value));
  }

  function getDiscountItemType() {
    return document.querySelector('input[name="cb_discount_item_type"]:checked')?.value || 'percentage';
  }

  function updateGrandTotalFromDiscount() {
    const discountType = getDiscountType();
    let discountAmount = 0;

    // FOC/Compliment: diskon otomatis dari setting (mirip posApp.discountAmount()).
    if (cbFocCompType === 'Compliment') {
      discountAmount = Math.round(cbCurrentSubTotal * (cbFocSettings.complimentDiscountPercentage / 100));
    } else if (cbFocCompType === 'FOC') {
      discountAmount = Math.round(cbCurrentSubTotal * (cbFocSettings.focDiscountPercentage / 100));
    } else if (discountType === 'item') {
      const selectedIds = getSelectedDiscountItemIds();
      const selected = cbDiscountItems.filter(item => selectedIds.includes(Number(item.id)));
      const itemType = getDiscountItemType();
      discountAmount = selected.reduce((sum, item) => {
        const subtotal = Number(item.subtotal || 0);
        const v = Number(document.querySelector(`input[name="cb_discount_item_value_${item.id}"]`)?.value || 0);
        if (! v) return sum;
        if (itemType === 'nominal') return sum + Math.min(v, subtotal);
        return sum + Math.round(subtotal * (v / 100));
      }, 0);
    }

    // Keterangan potongan diskon per item (di bawah nama item).
    cbDiscountItems.forEach((item) => {
      const el = document.getElementById('cbDiscountAmount_' + item.id);
      if (! el) return;
      const subtotal = Number(item.subtotal || 0);
      let amt = 0;
      if (cbFocCompType === 'Compliment') {
        amt = Math.round(subtotal * (cbFocSettings.complimentDiscountPercentage / 100));
      } else if (cbFocCompType === 'FOC') {
        amt = Math.round(subtotal * (cbFocSettings.focDiscountPercentage / 100));
      } else if (discountType === 'item' && getSelectedDiscountItemIds().includes(Number(item.id))) {
        const v = Number(document.querySelector(`input[name="cb_discount_item_value_${item.id}"]`)?.value || 0);
        if (! v) return;
        amt = getDiscountItemType() === 'nominal' ? Math.min(v, subtotal) : Math.round(subtotal * (v / 100));
      }
      el.textContent = amt > 0 ? 'Diskon -' + formatRupiah(amt) : '';
    });

    const sistaBayar = Math.max(Math.round(cbCurrentSubTotal - cbCurrentDownPayment - discountAmount), 0);
    cbCurrentGrandTotal = sistaBayar;

    // Update Sisa Bayar display
    const fmt = v => formatRupiah(v || 0);
    document.getElementById('cbGrandTotal').textContent = fmt(sistaBayar);

    // Reset split payment to max amount
    setSplitInput('cash', 0);
    setSplitInput('non_cash_amount', sistaBayar);
    setSplitInput('second_non_cash_amount', 0);

    updateSplitSummary();
  }

  async function requestAuthCodeEmailBooking() {
    if (isRequestingAuthCodeEmailBooking) return;

    isRequestingAuthCodeEmailBooking = true;
    const btn = document.getElementById('cbRequestAuthCodeBtn');
    const originalText = btn.textContent;
    btn.textContent = 'Mengirim...';
    btn.disabled = true;

    try {
      const response = await fetch(cbSendAuthCodeEmailUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          source: 'booking-close-discount'
        }),
      });

      const data = await response.json();

      if (data.success) {
        alert('Auth code telah dikirim ke email yang terdaftar.');
      } else {
        alert(data.message || 'Gagal mengirim auth code.');
      }
    } catch (error) {
      alert('Error: ' + error.message);
    } finally {
      isRequestingAuthCodeEmailBooking = false;
      btn.textContent = originalText;
      btn.disabled = false;
    }
  }

  async function requestFocAuthCodeEmailBooking() {
    if (isRequestingFocAuthCodeEmailBooking) return;

    isRequestingFocAuthCodeEmailBooking = true;
    const btn = document.getElementById('cbRequestFocAuthCodeBtn');
    const originalText = btn.textContent;
    btn.textContent = 'Mengirim...';
    btn.disabled = true;

    try {
      const response = await fetch(cbSendAuthCodeEmailUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          source: 'booking-close-foc'
        }),
      });

      const data = await response.json();

      if (data.success) {
        alert('Auth code telah dikirim ke email yang terdaftar.');
      } else {
        alert(data.message || 'Gagal mengirim auth code.');
      }
    } catch (error) {
      alert('Error: ' + error.message);
    } finally {
      isRequestingFocAuthCodeEmailBooking = false;
      btn.textContent = originalText;
      btn.disabled = false;
    }
  }

  async function submitCloseBilling() {
    if (!closeBillingBookingId) {
      return;
    }

    if (cbCheckerIncomplete) {
      alert('Billing tidak bisa ditutup karena masih ada item di Transaction Checker yang belum selesai.');
      return;
    }

    const paymentMode = document.querySelector('input[name="cb_payment_mode"]:checked')?.value;
    if (!paymentMode) {
      return;
    }

    const payload = {
      payment_mode: paymentMode,
      foc_comp_payment_method: cbFocCompType || null,
    };

    const discountType = getDiscountType();
    const isFocComp = ['FOC', 'Compliment'].includes(payload.foc_comp_payment_method);
    if (discountType !== 'none' || cbFocRequiresAuth(payload.foc_comp_payment_method)) {
      payload.discount_type = discountType;

      if (discountType === 'item') {
        const selectedIds = getSelectedDiscountItemIds();
        const itemType = getDiscountItemType();

        if (selectedIds.length === 0) {
          alert('Pilih minimal satu item yang akan didiskon.');
          return;
        }

        const values = {};
        selectedIds.forEach(id => {
          const v = Number(document.querySelector(`input[name="cb_discount_item_value_${id}"]`)?.value || 0);
          if (v > 0) values[id] = v;
        });

        if (Object.keys(values).length !== selectedIds.length) {
          alert('Semua item terpilih wajib punya nilai diskon lebih dari 0.');
          return;
        }

        payload.discount_order_item_ids = selectedIds;
        payload.discount_item_type = itemType;
        payload.discount_items = values;
      }

      const discountAuthCode = document.getElementById('cb_discount_auth_code').value.trim();
      const focCompAuthCode = document.getElementById('cb_foc_comp_auth_code').value.trim();
      const focNeedsAuth = isFocComp && cbFocRequiresAuth(payload.foc_comp_payment_method);
      const needsDiscountAuth = discountType !== 'none' && !isFocComp;

      if (focNeedsAuth && !/^\d{4}$/.test(focCompAuthCode)) {
        alert('Auth code FOC / Compliment harus 4 digit.');
        return;
      }

      if (needsDiscountAuth && !/^\d{4}$/.test(discountAuthCode)) {
        alert('Auth code diskon harus 4 digit.');
        return;
      }

      const codeToVerify = isFocComp ? focCompAuthCode : discountAuthCode;
      const verifyRes = await fetch(cbVerifyAuthCodeUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          code: codeToVerify,
        }),
      });
      const verifyData = await verifyRes.json();

      if (!verifyData.valid) {
        alert(isFocComp ? 'Auth code FOC / Compliment tidak valid.' : 'Auth code diskon tidak valid.');
        return;
      }

      payload.discount_auth_code = discountAuthCode;
      payload.foc_comp_auth_code = focCompAuthCode;
    }

    if (paymentMode === 'normal' || paymentMode === 'partial') {
      const isFocComp = ['FOC', 'Compliment'].includes(payload.foc_comp_payment_method);

      // FOC/Compliment → payment_method otomatis, tanpa metode normal.
      if (isFocComp) {
        payload.payment_method = payload.foc_comp_payment_method;
      } else {
        const paymentMethod = document.querySelector('input[name="cb_payment_method"]:checked')?.value;
        if (!paymentMethod) {
          return;
        }
        payload.payment_method = paymentMethod;

        if (paymentMethod !== 'cash') {
          const paymentReferenceNumber = document.getElementById('cb_payment_reference_number').value.trim();
          if (!paymentReferenceNumber) {
            alert('Nomor referensi pembayaran non-cash wajib diisi.');
            return;
          }
          payload.payment_reference_number = paymentReferenceNumber;
        }
      }

      if (paymentMode === 'partial') {
        const partialPaidAmount = Number(document.getElementById('cb_partial_paid_amount').value || 0);
        if (partialPaidAmount <= 0 || partialPaidAmount >= cbCurrentGrandTotal) {
          alert('Nominal bayar sebagian (DP) harus lebih besar dari 0 dan kurang dari total tagihan.');
          return;
        }
        payload.partial_paid_amount = partialPaidAmount;
      }
    } else {
      const splitCashAmount = Number(document.getElementById('cb_split_cash').value || 0);
      const splitNonCashAmount = Number(document.getElementById('cb_split_non_cash_amount').value || 0);
      const splitSecondNonCashAmount = Number(document.getElementById('cb_split_second_non_cash_amount').value || 0);
      const splitNonCashMethod = document.getElementById('cb_split_non_cash_method').value;
      const splitSecondNonCashMethod = document.getElementById('cb_split_second_non_cash_method').value;
      const splitNonCashReferenceNumber = document.getElementById('cb_split_non_cash_reference_number').value.trim();
      const splitSecondNonCashReferenceNumber = document.getElementById('cb_split_second_non_cash_reference_number').value.trim();
      const splitTotal = splitCashAmount + splitNonCashAmount + splitSecondNonCashAmount;
      const nonCashCount = [splitNonCashAmount, splitSecondNonCashAmount].filter((amount) => amount > 0).length;

      if (splitCashAmount < 0 || splitNonCashAmount < 0 || splitSecondNonCashAmount < 0) {
        alert('Nominal split bill tidak boleh minus.');
        return;
      }

      if (splitCashAmount <= 0 && nonCashCount < 2) {
        alert('Untuk split non-cash + non-cash, isi dua nominal non-cash lebih dari 0.');
        return;
      }

      if (splitCashAmount > 0 && nonCashCount < 1) {
        alert('Untuk split cash + non-cash, minimal satu nominal non-cash harus lebih dari 0.');
        return;
      }

      if (splitNonCashAmount > 0 && !splitNonCashMethod) {
        alert('Metode non-cash pertama untuk split bill wajib dipilih.');
        return;
      }

      if (splitNonCashAmount > 0 && !splitNonCashReferenceNumber) {
        alert('Nomor referensi non-cash pertama untuk split bill wajib diisi.');
        return;
      }

      if (splitSecondNonCashAmount > 0 && !splitSecondNonCashMethod) {
        alert('Metode non-cash kedua untuk split bill wajib dipilih.');
        return;
      }

      if (splitSecondNonCashAmount > 0 && !splitSecondNonCashReferenceNumber) {
        alert('Nomor referensi non-cash kedua untuk split bill wajib diisi.');
        return;
      }

      if (Math.abs(splitTotal - cbCurrentGrandTotal) > 0.01) {
        alert('Total split harus sama dengan grand total.');
        return;
      }

      payload.split_cash_amount = splitCashAmount;
      payload.split_non_cash_amount = splitNonCashAmount;
      payload.split_non_cash_method = splitNonCashMethod;
      payload.split_non_cash_reference_number = splitNonCashReferenceNumber;
      payload.split_second_non_cash_amount = splitSecondNonCashAmount;
      payload.split_second_non_cash_method = splitSecondNonCashMethod;
      payload.split_second_non_cash_reference_number = splitSecondNonCashReferenceNumber;
    }

    const btn = document.getElementById('cbSubmitBtn');

    btn.disabled = true;
    btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Memproses...';

    try {
      const res = await fetch(`/admin/bookings/${closeBillingBookingId}/close-billing`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
          'Accept': 'application/json',
        },
        body: JSON.stringify(payload),
      });

      const data = await res.json();

      if (data.success) {
        if (data.receipt_printed === false) {
          if (data.receipt_url) {
            const previewTab = window.open(data.receipt_url, '_blank');

            if (previewTab) {
              alert('Auto-print gagal. Preview struk dibuka untuk cetak manual.');
            } else {
              alert('Auto-print gagal dan popup preview terblokir browser. Silakan izinkan popup lalu coba lagi untuk cetak manual.');
            }
          } else {
            alert('Auto-print gagal. URL preview struk tidak tersedia untuk cetak manual.');
          }
        }

        closeCloseBillingModal();
        // Reload page to update table map
        window.location.reload();
      } else {
        alert(data.message || 'Gagal menutup billing.');
        btn.disabled = false;
        btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg> Tutup & Cetak Struk';
      }
    } catch (e) {
      alert('Terjadi kesalahan. Silakan coba lagi.');
      btn.disabled = false;
      btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg> Tutup & Cetak Struk';
    }
  }

  // Close on backdrop click
  document.getElementById('closeBillingModal').addEventListener('click', function(e) {
    if (e.target === this) {
      closeCloseBillingModal();
    }
  });

  document.querySelectorAll('input[name="cb_payment_mode"]').forEach((radio) => {
    radio.addEventListener('change', updatePaymentModeUI);
  });

  document.querySelectorAll('input[name="cb_discount_type"]').forEach((radio) => {
    radio.addEventListener('change', updateDiscountUI);
  });

  document.querySelectorAll('input[name="cb_payment_method"]').forEach((radio) => {
    radio.addEventListener('change', updatePaymentModeUI);
  });

  document.getElementById('cb_split_cash_display').addEventListener('input', (event) => onSplitInput('cash', event));
  document.getElementById('cb_split_non_cash_amount_display').addEventListener('input', (event) => onSplitInput('non_cash_amount', event));
  document.getElementById('cb_split_second_non_cash_amount_display').addEventListener('input', (event) => onSplitInput('second_non_cash_amount', event));
</script>
