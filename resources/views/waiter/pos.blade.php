<x-waiter-mobile-layout>
  <div x-data="waiterPos()"
       x-init="init()">

    <!-- Header -->
    <div class="px-5 pt-5 pb-3 sticky top-0 bg-slate-50 z-20">
      <div class="flex items-center justify-between mb-3">
        <div>
          <h1 class="text-xl font-bold flex items-center gap-2">
            POS
            <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-teal-600 bg-teal-50 border border-teal-200 rounded-full px-2 py-0.5">
              <span class="w-1.5 h-1.5 rounded-full bg-teal-500 animate-pulse"></span>
              Live
            </span>
          </h1>
          <p class="text-slate-700 text-xs mt-0.5">Pilih meja & tambah pesanan</p>
        </div>
        <!-- Cart badge -->
        <button @click="showCart = true"
                class="relative w-10 h-10 rounded-full bg-teal-500 text-white border border-teal-500 flex items-center justify-center shadow-sm">
          <svg class="w-5 h-5"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
          </svg>
          <span x-show="cartCount > 0"
                x-text="cartCount"
                class="absolute -top-1 -right-1 bg-teal-500 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center leading-none"></span>
        </button>
      </div>

      <!-- Session Selector -->
      <div class="flex gap-2 overflow-x-auto pb-1 scrollbar-hide">
        <template x-for="session in sessions" :key="session.id">
          <button @click="selectSession(session.id)"
                  :class="String(selectedSession) === String(session.id) ? 'bg-teal-500 text-white' : 'bg-white text-slate-600 border border-slate-200'"
                  class="flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold transition whitespace-nowrap">
            Meja <span x-text="session.table"></span>
            <span x-show="session.customer"> · <span x-text="session.customer"></span></span>
          </button>
        </template>
        <span x-show="sessions.length === 0"
              class="text-xs text-slate-500 py-1.5">Belum ada booking aktif yang di-assign ke Anda.</span>
      </div>
    </div>

    <!-- Search + Category Filter -->
    <div class="px-5 py-3 bg-slate-50 sticky top-[116px] z-10">
      <div class="relative mb-3">
        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-700"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <input type="text"
               x-model="search"
               placeholder="Cari menu..."
               class="w-full bg-white text-slate-900 border border-slate-200 rounded-xl pl-10 pr-4 py-3 text-sm placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-teal-400" />
      </div>

      <!-- Category pills -->
      <div class="flex gap-2 overflow-x-auto scrollbar-hide">
        <button @click="categoryFilter = ''"
                :class="categoryFilter === '' ? 'bg-teal-500 text-white' : 'bg-white text-slate-600 border border-slate-200'"
                class="flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold transition">
          Semua
        </button>
        <button @click="categoryFilter = 'food'"
                :class="categoryFilter === 'food' ? 'bg-teal-500 text-white' : 'bg-white text-slate-600 border border-slate-200'"
                class="flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold transition">
          Food
        </button>
        <button @click="categoryFilter = 'bar'"
                :class="categoryFilter === 'bar' ? 'bg-teal-500 text-white' : 'bg-white text-slate-600 border border-slate-200'"
                class="flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold transition">
          Bar
        </button>
        <button @click="categoryFilter = 'beverage'"
                :class="categoryFilter === 'beverage' ? 'bg-teal-500 text-white' : 'bg-white text-slate-600 border border-slate-200'"
                class="flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold transition">
          Beverage
        </button>
      </div>
    </div>

    <!-- Products Grid -->
    <div class="px-5 pb-5">
      <div x-show="filteredProducts().length === 0"
           class="py-8 text-center text-slate-700 text-sm">
        Tidak ada produk ditemukan.
      </div>
      <div class="grid grid-cols-2 gap-3">
        <template x-for="product in filteredProducts()"
                  :key="product.id">
          <button @click="addToCart(product)"
                  :disabled="addingToCart === product.id || isProductUnavailable(product)"
                  :class="isProductUnavailable(product) ? 'bg-gray-100 cursor-not-allowed' : 'bg-white'"
                  class="rounded-2xl p-4 text-left relative active:scale-95 transition-transform disabled:opacity-50 border border-slate-100 shadow-sm">
            <!-- Category badge -->
            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium mb-2"
                  :class="{
                      'bg-orange-100 text-orange-700': product.category === 'food',
                      'bg-purple-100 text-purple-700': product.category === 'bar',
                      'bg-blue-100 text-blue-700': product.category === 'beverage',
                  }"
                  x-text="product.category.charAt(0).toUpperCase() + product.category.slice(1)"></span>
            <p class="font-semibold text-sm leading-tight mb-2 text-slate-900"
               x-text="product.name"></p>
            <p class="text-teal-600 font-bold text-sm"
               x-text="'Rp ' + product.price.toLocaleString('id-ID')"></p>
            <!-- Sold out badge -->
            <span x-show="isProductUnavailable(product)"
                  class="absolute inset-0 flex items-center justify-center bg-white bg-opacity-60 rounded-2xl">
              <span class="bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-lg">Habis</span>
            </span>
            <!-- Portion availability -->
            <span x-show="!isProductUnavailable(product) && getPortionLabel(product)"
                  x-text="getPortionLabel(product)"
                  class="absolute bottom-2 right-2 text-[10px] font-medium text-amber-600"></span>
            <!-- Cart qty indicator -->
            <span x-show="getCartQty(product.id) > 0"
                  x-text="getCartQty(product.id)"
                  class="absolute top-3 right-3 bg-teal-500 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center leading-none"></span>
            <!-- Loading spinner -->
            <span x-show="addingToCart === product.id"
                  class="absolute inset-0 flex items-center justify-center bg-white bg-opacity-80 rounded-2xl">
              <svg class="animate-spin w-5 h-5 text-teal-400"
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
            </span>
          </button>
        </template>
      </div>
    </div>

    <!-- Cart Slideup Overlay -->
    <div x-show="showCart"
         x-transition.opacity
         style="display: none;"
         class="fixed inset-0 bg-black bg-opacity-60 z-[70]"
         @click="showCart = false">
    </div>
    <div x-show="showCart"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="transform translate-y-full"
         x-transition:enter-end="transform translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="transform translate-y-0"
         x-transition:leave-end="transform translate-y-full"
         style="display: none;"
         class="fixed bottom-0 left-0 right-0 bg-white rounded-t-3xl z-[80] max-h-[80vh] flex flex-col shadow-2xl border-t border-slate-200"
         @click.stop>

      <div class="flex items-center justify-between px-5 pt-5 pb-3">
        <h2 class="font-bold text-lg text-slate-900">Keranjang</h2>
        <button @click="showCart = false"
                class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center">
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

      <!-- Cart items -->
      <div class="flex-1 overflow-y-auto px-5">
        <div x-show="Object.keys(cart).length === 0"
             class="py-8 text-center text-slate-700 text-sm">
          Keranjang kosong.
        </div>
        <div class="space-y-3">
          <template x-for="(item, pid) in cart"
                    :key="pid">
            <div class="rounded-xl border border-slate-200 p-3">
              <div class="flex items-center gap-3">
                <div class="flex-1 min-w-0">
                  <p class="font-medium text-sm text-slate-900 truncate"
                     x-text="item.name"></p>
                  <p class="text-teal-600 text-xs"
                     x-text="'Rp ' + item.price.toLocaleString('id-ID')"></p>
                </div>
                <div class="flex items-center gap-2">
                  <button @click="updateQty(pid, item.qty - 1)"
                          class="w-7 h-7 rounded-full bg-slate-100 text-slate-700 flex items-center justify-center text-sm font-bold">
                    −
                  </button>
                  <span class="text-sm font-semibold w-6 text-center"
                        x-text="item.qty"></span>
                  <button @click="addToCartById(pid)"
                          class="w-7 h-7 rounded-full bg-teal-600 text-white flex items-center justify-center text-sm font-bold">
                    +
                  </button>
                </div>
              </div>
              <div class="mt-2">
                <input type="text"
                       :value="item.notes || ''"
                       @change="updateNote(pid, $event.target.value)"
                       placeholder="Note item (opsional)"
                       class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-teal-400">
              </div>
            </div>
          </template>
        </div>
      </div>

      <!-- Cart Footer -->
      <div class="px-5 py-4 border-t border-slate-200">
        <div class="flex items-center justify-between mb-3">
          <span class="text-slate-700 font-medium">Total</span>
          <span class="font-bold text-lg text-slate-900"
                x-text="'Rp ' + cartTotal.toLocaleString('id-ID')"></span>
        </div>
        <div x-show="selectedSession === null"
             class="mb-2 px-3 py-2 bg-yellow-50 border border-yellow-200 rounded-lg text-yellow-700 text-xs">
          Pilih meja booking terlebih dahulu sebelum checkout.
        </div>
        <button @click="openConfirmOrder()"
                :disabled="checkingOut || selectedSession === null || Object.keys(cart).length === 0"
                class="w-full bg-teal-500 text-white py-4 rounded-full font-bold text-sm disabled:opacity-40 flex items-center justify-center gap-2">
          <svg x-show="checkingOut"
               class="animate-spin w-4 h-4"
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
          <span x-text="checkingOut ? 'Memproses...' : 'Lanjutkan Konfirmasi'"></span>
        </button>
      </div>
    </div>

    <!-- Confirm Order Modal -->
    <div x-show="showConfirmOrder"
         x-transition.opacity
         style="display: none;"
         class="fixed inset-0 bg-black/60 z-[90]"
         @click="showConfirmOrder = false">
    </div>
    <div x-show="showConfirmOrder"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         style="display: none;"
         class="fixed inset-x-4 top-1/2 -translate-y-1/2 z-[100] bg-white rounded-2xl shadow-2xl border border-slate-200"
         @click.stop>
      <div class="px-5 pt-5 pb-3 border-b border-slate-100">
        <h3 class="text-base font-bold text-slate-900">Konfirmasi Order</h3>
        <p class="text-xs text-slate-600 mt-1">Periksa ringkasan sebelum kirim ke kitchen/bar.</p>
      </div>

      <div class="px-5 py-4 space-y-3">
        <div class="flex items-center justify-between text-sm">
          <span class="text-slate-600">Meja</span>
          <span class="font-semibold text-slate-900"
                x-text="selectedSessionLabel()"></span>
        </div>
        <div class="flex items-center justify-between text-sm">
          <span class="text-slate-600">Total Item</span>
          <span class="font-semibold text-slate-900"
                x-text="cartCount"></span>
        </div>
        <template x-if="shouldChooseCheckerOnCheckout()">
          <div class="rounded-xl border border-slate-200 p-3 space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-sm font-semibold text-slate-900">Pilih Printer Checker</span>
              <span class="text-xs text-slate-500"
                    x-text="`${getSelectedCheckerPrinterIds().length} dipilih`"></span>
            </div>
            <div class="space-y-1.5 max-h-32 overflow-y-auto">
              <template x-for="printer in getCheckerPrintersFromCart()"
                        :key="printer.id">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                  <input type="checkbox"
                         :value="printer.id"
                         x-model="selectedCheckerPrinterIds"
                         class="rounded border-slate-300 text-teal-500 focus:ring-teal-500">
                  <span x-text="printer.name"></span>
                </label>
              </template>
            </div>
          </div>
        </template>
        <div class="rounded-xl bg-slate-50 border border-slate-100 p-3 space-y-2">
          <div class="flex items-center justify-between text-sm">
            <span class="text-slate-600">Subtotal</span>
            <span class="font-semibold text-slate-900"
                  x-text="formatCurrency(cartTotal)"></span>
          </div>
          <div class="flex items-center justify-between text-sm">
            <span class="text-slate-600">Diskon</span>
            <span class="font-semibold text-slate-900">Rp 0</span>
          </div>
          <div class="pt-2 border-t border-slate-200 flex items-center justify-between">
            <span class="font-bold text-slate-900">Total</span>
            <span class="font-bold text-teal-600"
                  x-text="formatCurrency(cartTotal)"></span>
          </div>
        </div>
      </div>

      <div class="px-5 pb-5 pt-1 grid grid-cols-2 gap-2">
        <button @click="showConfirmOrder = false"
                :disabled="checkingOut"
                class="py-3 rounded-xl border border-slate-200 text-slate-700 font-semibold text-sm disabled:opacity-40">
          Batal
        </button>
        <button @click="checkout()"
                :disabled="checkingOut"
                class="py-3 rounded-xl bg-teal-500 text-white font-semibold text-sm disabled:opacity-40 flex items-center justify-center gap-2">
          <svg x-show="checkingOut"
               class="animate-spin w-4 h-4"
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
          <span x-text="checkingOut ? 'Memproses...' : 'Konfirmasi' "></span>
        </button>
      </div>
    </div>

    <!-- Checkout Success Toast -->
    <div x-show="toastMsg"
         x-transition
         style="display: none;"
         class="fixed top-5 left-5 right-5 z-50 flex items-center gap-3 px-4 py-4 rounded-2xl shadow-xl text-white"
         :class="toastSuccess ? 'bg-green-700' : 'bg-red-700'">
      <svg class="w-5 h-5 flex-shrink-0"
           fill="none"
           stroke="currentColor"
           viewBox="0 0 24 24">
        <path x-show="toastSuccess"
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M5 13l4 4L19 7" />
        <path x-show="!toastSuccess"
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M6 18L18 6M6 6l12 12" />
      </svg>
      <p class="text-sm font-medium"
         x-text="toastMsg"></p>
    </div>

  </div>

  @php
    $waiterSessionsPayload = $activeSessions
        ->map(function ($session) {
            return [
                'id' => (string) $session->id,
                'table' => (string) ($session->table?->table_number ?? '?'),
                'customer' => $session->customer?->name,
            ];
        })
        ->values();
  @endphp

  @push('scripts')
    <script>
      function waiterPos() {
        return {
          products: @json($products),
          search: '',
          categoryFilter: '',
          cart: @json($cart ?? []),
          sessions: @json($waiterSessionsPayload),
          checkerPrinters: @json($checkerPrinters ?? []),
          canChooseChecker: @json((bool) ($canChooseChecker ?? false)),
          selectedCheckerPrinterIds: [],
          selectedSession: @json($selectedSession ?? null),
          showCart: false,
          showConfirmOrder: false,
          addingToCart: null,
          checkingOut: false,
          checkoutToken: null,
          toastMsg: '',
          toastSuccess: true,
          availableMap: {},

          init() {
            this.hydrateCartCheckerAssignments();
            this.pollLive();
            this._liveTimer = setInterval(() => this.pollLive(), 30000);
          },

          destroy() {
            if (this._liveTimer) clearInterval(this._liveTimer);
          },

          async pollLive() {
            try {
              const res = await fetch('{{ route('waiter.pos.live') }}', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
              });
              if (!res.ok) return;
              const data = await res.json();

              if (Array.isArray(data.products)) {
                const map = {};
                data.products.forEach((p) => { map[p.id] = p; });
                this.availableMap = map;
              }

              if (Array.isArray(data.sessions) && data.sessions.length > 0) {
                this.sessions = data.sessions;

                // If the selected session disappeared (billing closed / check-out), reset it
                const selectedId = String(this.selectedSession ?? '');
                if (selectedId && !data.sessions.some((s) => String(s.id) === selectedId)) {
                  this.selectedSession = null;
                  this.showCart = false;
                }
              }
            } catch (_) {
              // transient failure — keep last known availability
            }
          },

          isProductUnavailable(product) {
            const live = this.availableMap[product.id];
            if (live) return live.is_available === false;
            // fallback: item group with known stock 0
            return product.is_item_group === false && (product.stock ?? 0) <= 0;
          },

          getPortionLabel(product) {
            const live = this.availableMap[product.id];
            if (live && live.possible_portions !== null && live.possible_portions !== undefined) {
              return `Tersisa ${live.possible_portions} porsi`;
            }
            return null;
          },

          get cartCount() {
            return Object.values(this.cart).reduce((s, i) => s + (i.qty || 0), 0);
          },

          get cartTotal() {
            return Object.values(this.cart).reduce((s, i) => s + (i.price * (i.qty || 0)), 0);
          },

          filteredProducts() {
            return this.products.filter(p => {
              const matchSearch = !this.search ||
                p.name.toLowerCase().includes(this.search.toLowerCase());
              const matchCat = !this.categoryFilter || p.category === this.categoryFilter;
              return matchSearch && matchCat;
            });
          },

          getCartQty(productId) {
            return this.cart[productId]?.qty ?? 0;
          },

          formatCurrency(value) {
            return 'Rp ' + Number(value || 0).toLocaleString('id-ID');
          },

          selectedSessionLabel() {
            const selectedId = String(this.selectedSession ?? '');
            const selected = this.sessions.find(session => session.id === selectedId);
            if (!selected) {
              return '-';
            }

            return selected.customer ?
              `Meja ${selected.table} · ${selected.customer}` :
              `Meja ${selected.table}`;
          },

          openConfirmOrder() {
            if (this.checkingOut || this.selectedSession === null || Object.keys(this.cart).length === 0) {
              return;
            }

            this.hydrateCartCheckerAssignments();

            this.prepareCheckerSelectionFromCart();

            if (this.shouldChooseCheckerOnCheckout() && this.getSelectedCheckerPrinterIds().length === 0) {
              this.flash('Pilih minimal satu printer checker.', false);
              return;
            }

            this.showConfirmOrder = true;
          },

          resolveCheckerPrinterIdsForCartItem(item, cartKey) {
            const fromCart = Array.isArray(item?.assigned_checker_printer_ids) ?
              item.assigned_checker_printer_ids.map((id) => Number(id)) : [];

            const normalizedFromCart = fromCart.filter((id) => Number.isInteger(id) && id > 0);

            if (normalizedFromCart.length > 0) {
              return normalizedFromCart;
            }

            const productId = String(item?.id || cartKey || '');
            const matchedProduct = (this.products || []).find((product) => String(product.id) === productId);
            const fromProduct = Array.isArray(matchedProduct?.assigned_checker_printers) ?
              matchedProduct.assigned_checker_printers.map((printer) => Number(printer?.id)) : [];

            return fromProduct.filter((id) => Number.isInteger(id) && id > 0);
          },

          hydrateCartCheckerAssignments() {
            const nextCart = {
              ...(this.cart || {}),
            };

            Object.entries(nextCart).forEach(([cartKey, item]) => {
              if (!item || !Array.isArray(item.assigned_checker_printer_ids) || item.assigned_checker_printer_ids.length === 0) {
                const resolvedIds = this.resolveCheckerPrinterIdsForCartItem(item, cartKey);

                if (resolvedIds.length > 0) {
                  nextCart[cartKey] = {
                    ...item,
                    assigned_checker_printer_ids: resolvedIds,
                  };
                }
              }
            });

            this.cart = nextCart;
          },

          getCheckerPrintersFromCart() {
            const checkerPrinterMap = new Map(
              (this.checkerPrinters || [])
              .map((printer) => [Number(printer.id), {
                id: Number(printer.id),
                name: String(printer.name || `Checker #${printer.id}`),
              }]),
            );

            Object.values(this.cart || {}).forEach((item) => {
              (Array.isArray(item.assigned_checker_printers) ? item.assigned_checker_printers : []).forEach((printer) => {
                const printerId = Number(printer?.id || 0);

                if (printerId > 0 && !checkerPrinterMap.has(printerId)) {
                  checkerPrinterMap.set(printerId, {
                    id: printerId,
                    name: String(printer?.name || `Checker #${printerId}`),
                  });
                }
              });
            });

            Object.values(this.cart || {}).forEach((item) => {
              const assignedIds = this.resolveCheckerPrinterIdsForCartItem(item, item?.id);

              assignedIds.forEach((printerId) => {
                if (printerId > 0 && !checkerPrinterMap.has(printerId)) {
                  checkerPrinterMap.set(printerId, {
                    id: printerId,
                    name: `Checker #${printerId}`,
                  });
                }
              });
            });

            const selectedFromCart = new Map();

            Object.entries(this.cart || {}).forEach(([cartKey, item]) => {
              const assignedIds = this.resolveCheckerPrinterIdsForCartItem(item, cartKey);

              assignedIds.forEach((printerId) => {
                const checkerPrinter = checkerPrinterMap.get(printerId);

                if (checkerPrinter) {
                  selectedFromCart.set(checkerPrinter.id, checkerPrinter);
                }
              });
            });

            return Array.from(selectedFromCart.values());
          },

          shouldChooseCheckerOnCheckout() {
            return this.canChooseChecker && this.getCheckerPrintersFromCart().length > 1;
          },

          getSelectedCheckerPrinterIds() {
            return Array.from(new Set((this.selectedCheckerPrinterIds || [])
              .map((value) => Number(value))
              .filter((value) => Number.isInteger(value) && value > 0)));
          },

          prepareCheckerSelectionFromCart() {
            const availableCheckerPrinters = this.getCheckerPrintersFromCart();
            const availableIds = availableCheckerPrinters.map((printer) => Number(printer.id));
            const retainedSelection = this.getSelectedCheckerPrinterIds().filter((id) => availableIds.includes(id));

            this.selectedCheckerPrinterIds = retainedSelection.length > 0 ? retainedSelection : availableIds;
          },

          async selectSession(sessionId) {
            this.selectedSession = sessionId;
            try {
              await fetch('{{ route('waiter.pos.select-session') }}', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                  session_id: sessionId
                }),
              });
            } catch (_) {}
          },

          async addToCart(product) {
            this.addingToCart = product.id;
            try {
              const res = await fetch(`{{ url('/waiter/pos') }}/${product.id}/add-to-cart`, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({}),
              });
              const data = await res.json();
              if (data.success) {
                this.cart = data.cart ?? this.cart;
                if (!this.cart[product.id]) {
                  const assignedCheckerPrinterIds = Array.isArray(product.assigned_checker_printers) ?
                    product.assigned_checker_printers.map((printer) => Number(printer?.id)).filter((id) => Number.isInteger(id) && id > 0) : [];

                  this.cart[product.id] = {
                    name: product.name,
                    price: product.price,
                    qty: 1,
                    notes: null,
                    assigned_checker_printer_ids: assignedCheckerPrinterIds,
                  };
                }
              } else {
                this.flash(data.message || 'Gagal menambahkan item ke keranjang.', false);
              }
            } catch (_) {
              // Optimistic UI fallback
              if (this.cart[product.id]) {
                this.cart[product.id].qty++;
              } else {
                const assignedCheckerPrinterIds = Array.isArray(product.assigned_checker_printers) ?
                  product.assigned_checker_printers.map((printer) => Number(printer?.id)).filter((id) => Number.isInteger(id) && id > 0) : [];

                this.cart[product.id] = {
                  name: product.name,
                  price: product.price,
                  qty: 1,
                  notes: null,
                  assigned_checker_printer_ids: assignedCheckerPrinterIds,
                };
              }
            } finally {
              this.addingToCart = null;
            }
          },

          async addToCartById(productId) {
            const product = this.products.find(p => p.id === productId);
            if (product) {
              await this.addToCart(product);
            }
          },

          async updateQty(productId, newQty) {
            if (newQty <= 0) {
              try {
                const res = await fetch(`{{ url('/waiter/pos') }}/${productId}/remove-from-cart`, {
                  method: 'DELETE',
                  headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                  },
                });
                const data = await res.json();
                if (data.success) {
                  this.cart = data.cart ?? {};
                }
              } catch (_) {
                delete this.cart[productId];
              }
              return;
            }
            try {
              const res = await fetch(`{{ url('/waiter/pos') }}/${productId}/update-cart`, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                  quantity: newQty
                }),
              });
              const data = await res.json();
              if (data.success) {
                this.cart = data.cart ?? this.cart;
              } else {
                this.flash(data.message || 'Gagal memperbarui keranjang.', false);
              }
            } catch (_) {
              if (this.cart[productId]) {
                this.cart[productId].qty = newQty;
              }
            }
          },

          async updateNote(productId, noteValue) {
            const currentQty = this.cart[productId]?.qty ?? 0;
            if (currentQty <= 0) {
              return;
            }

            try {
              const res = await fetch(`{{ url('/waiter/pos') }}/${productId}/update-cart`, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                  quantity: currentQty,
                  notes: noteValue,
                }),
              });

              const data = await res.json();
              if (data.success) {
                this.cart = data.cart ?? this.cart;
              } else {
                this.flash(data.message || 'Gagal menyimpan note item.', false);
              }
            } catch (_) {
              if (this.cart[productId]) {
                this.cart[productId].notes = noteValue;
              }
            }
          },

          async checkout() {
            if (!this.selectedSession || Object.keys(this.cart).length === 0) {
              return;
            }
            this.checkingOut = true;
            try {
              const res = await fetch('{{ route('waiter.pos.checkout') }}', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                  session_id: this.selectedSession,
                  checker_printer_ids: this.shouldChooseCheckerOnCheckout() ? this.getSelectedCheckerPrinterIds() : undefined,
                  idempotency_key: this.checkoutToken ??= crypto.randomUUID(),
                }),
              });
              const data = await res.json();
              if (data.success) {
                this.checkoutToken = null;
                this.cart = {};
                this.showCart = false;
                this.showConfirmOrder = false;
                this.flash('Pesanan berhasil dikirim ke dapur/bar!', true);
              } else {
                this.flash(data.message || 'Checkout gagal.', false);
              }
            } catch (_) {
              this.flash('Terjadi kesalahan jaringan.', false);
            } finally {
              this.checkingOut = false;
            }
          },

          flash(msg, success = true) {
            this.toastMsg = msg;
            this.toastSuccess = success;
            setTimeout(() => {
              this.toastMsg = '';
            }, 3500);
          },
        };
      }
    </script>
  @endpush
</x-waiter-mobile-layout>
