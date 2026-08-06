<x-app-layout title="Riwayat Transaksi">
  <div class="p-4 sm:p-6"
       x-data="transactionHistory()">

    <!-- Header -->
    <div class="flex items-start justify-between mb-6">
      <div class="flex items-center gap-3">
        <div class="w-12 h-12 bg-slate-800 rounded-xl flex items-center justify-center">
          <svg class="w-6 h-6 text-white"
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
          <h1 class="text-2xl font-bold text-gray-900">Riwayat Transaksi</h1>
          <p class="text-sm text-gray-500">Lihat semua transaksi yang telah dilakukan</p>
        </div>
      </div>

    </div>

    <!-- Area Tabs (multi-area header) -->
    @if (($areas ?? collect())->count() > 1 && (! session('active_area_id') || session('active_area_id') === 'all'))
      @php
        $txBaseQuery = request()->except(['area_id', 'per_page', 'search']);
      @endphp
      <div class="flex gap-2 overflow-x-auto pb-2 mb-6">
        <a href="{{ route('admin.transaction-history.index', $txBaseQuery) }}"
           class="px-4 py-2 rounded-lg whitespace-nowrap transition {{ is_null($selectedAreaId ?? null) ? 'bg-slate-800 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
          Semua Area
        </a>
        @foreach ($areas as $area)
          <a href="{{ route('admin.transaction-history.index', array_merge($txBaseQuery, ['area_id' => $area->id])) }}"
             class="px-4 py-2 rounded-lg whitespace-nowrap transition {{ ($selectedAreaId ?? null) === $area->id ? 'bg-slate-800 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
            {{ $area->name }}
          </a>
        @endforeach
      </div>
    @endif

    <!-- Realtime stats cards (polled) -->
    @php
      $txLiveQuery = request()->only(['area_id', 'transaction_mode', 'date_from', 'date_to', 'search', 'per_page']);
    @endphp
    <div id="txStats"
         x-data="realtimePoll({ url: '{{ route('admin.transaction-history.index', $txLiveQuery) }}', target: 'txStats', interval: 30000 })">
      @include('transaction-history._partials.stats')
    </div>

    <!-- Table Card -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <!-- Table Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-4 border-b border-gray-100">
        <h2 class="font-semibold text-gray-800">Daftar Transaksi</h2>
        <div class="flex flex-wrap items-center gap-3">
          <!-- Per Page + Search -->
          <form method="GET"
                action="{{ route('admin.transaction-history.index') }}"
                class="flex items-center gap-2">
            <select name="per_page"
                    onchange="this.form.submit()"
                    class="text-sm border border-gray-200 rounded-lg px-2.5 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400 bg-white text-gray-700">
              @foreach ([10, 25, 50, 100] as $option)
                <option value="{{ $option }}"
                        {{ $perPage === $option ? 'selected' : '' }}>{{ $option }} per halaman</option>
              @endforeach
            </select>
            @if (request('search'))
              <input type="hidden"
                     name="search"
                     value="{{ request('search') }}">
            @endif
            @if (! is_null($selectedAreaId ?? null))
              <input type="hidden"
                     name="area_id"
                     value="{{ $selectedAreaId }}">
            @endif
          </form>
          <form method="GET"
                action="{{ route('admin.transaction-history.index') }}">
            <div class="relative">
              <input type="text"
                     name="search"
                     value="{{ request('search') }}"
                     placeholder="Cari transaksi atau customer..."
                     class="pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-400 w-full sm:w-64">
              @if (request('per_page'))
                <input type="hidden"
                       name="per_page"
                       value="{{ request('per_page') }}">
              @endif
              @if (! is_null($selectedAreaId ?? null))
                <input type="hidden"
                       name="area_id"
                       value="{{ $selectedAreaId }}">
              @endif
              <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-2.5"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
          </form>
          <span class="text-sm text-gray-400" data-tx-count>{{ $orders->total() }} transaksi</span>
        </div>
      </div>

      <!-- Realtime order list (polled) -->
      @php
        $txListQuery = request()->only(['area_id', 'transaction_mode', 'date_from', 'date_to', 'search', 'per_page']);
      @endphp
      <div id="txListWrap">
        <div class="overflow-x-auto">
          <table class="w-full text-sm min-w-[800px]">
            <thead>
              <tr class="bg-gray-50">
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Waktu</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">No. Transaksi</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Pelanggan</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Tipe / Meja</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Items</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Total</th>
                <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Bayar</th>
              </tr>
            </thead>
            <tbody id="txList"
                   x-data="txListPoll({ url: '{{ route('admin.transaction-history.refresh', $txListQuery) }}', target: 'txList' })">
              @include('transaction-history._partials.list')
            </tbody>
          </table>
        </div>
      </div>

        @if ($orders->hasPages())
          <div class="px-5 py-4 border-t border-gray-100">
            <div class="flex items-center justify-between gap-4 text-sm">
              <p class="text-gray-500">
                Menampilkan
                <span class="font-semibold text-gray-700">{{ $orders->firstItem() }}</span>
                -
                <span class="font-semibold text-gray-700">{{ $orders->lastItem() }}</span>
                dari
                <span class="font-semibold text-gray-700">{{ $orders->total() }}</span>
                transaksi
              </p>

              <div class="flex items-center gap-1.5">
                @if ($orders->onFirstPage())
                  <span class="inline-flex items-center px-3 py-1.5 rounded-lg border border-gray-200 bg-gray-50 text-gray-300 cursor-not-allowed">Prev</span>
                @else
                  <a href="{{ $orders->previousPageUrl() }}"
                     class="inline-flex items-center px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 transition">Prev</a>
                @endif

                @php
                  $historyCurrentPage = (int) $orders->currentPage();
                  $historyLastPage = (int) $orders->lastPage();
                  $historyVisiblePages = collect([1, $historyCurrentPage - 1, $historyCurrentPage, $historyCurrentPage + 1, $historyLastPage])
                      ->filter(fn($page) => $page >= 1 && $page <= $historyLastPage)
                      ->unique()
                      ->sort()
                      ->values();

                  $historyPreviousVisiblePage = null;
                @endphp

                @foreach ($historyVisiblePages as $page)
                  @if ($historyPreviousVisiblePage !== null && $page - $historyPreviousVisiblePage > 1)
                    <span class="pagination-ellipsis inline-flex items-center justify-center w-9 h-9 text-gray-400 select-none">...</span>
                  @endif

                  @if ($page === $historyCurrentPage)
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-slate-800 text-white font-semibold">{{ $page }}</span>
                  @else
                    <a href="{{ $orders->url($page) }}"
                       class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 transition">{{ $page }}</a>
                  @endif

                  @php
                    $historyPreviousVisiblePage = $page;
                  @endphp
                @endforeach

                @if ($orders->hasMorePages())
                  <a href="{{ $orders->nextPageUrl() }}"
                     class="inline-flex items-center px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 transition">Next</a>
                @else
                  <span class="inline-flex items-center px-3 py-1.5 rounded-lg border border-gray-200 bg-gray-50 text-gray-300 cursor-not-allowed">Next</span>
                @endif
              </div>
            </div>
          </div>
        @endif
    </div>

    <div x-show="showErrorModal"
         x-transition.opacity
         style="display: none;"
         class="fixed inset-0 z-[70] flex items-center justify-center bg-black/60 px-4"
         @click.self="closeErrorModal()">
      <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <h3 class="text-base font-semibold text-gray-900">Error Message</h3>
          <button type="button"
                  @click="closeErrorModal()"
                  class="text-gray-400 hover:text-gray-600 transition">✕</button>
        </div>
        <div class="px-5 py-4">
          <p class="text-sm text-red-600 whitespace-pre-wrap break-words"
             x-text="selectedErrorMessage || '-' "></p>
        </div>
        <div class="px-5 py-4 border-t border-gray-100 flex justify-end">
          <button type="button"
                  @click="closeErrorModal()"
                  class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200 transition">Tutup</button>
        </div>
      </div>
    </div>

    <!-- Order Detail Modal (Row Click) -->
    <div x-show="showOrderDetailModal"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center"
         style="display: none;">
      <div class="absolute inset-0 bg-black/50"
           @click="closeOrderDetailModal()"></div>

      <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
          <h3 class="font-semibold text-gray-900">Detail Pesanan</h3>
          <button @click="closeOrderDetailModal()"
                  class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition">
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

        <div class="px-6 py-5 space-y-4">
          <div class="grid grid-cols-2 gap-3 text-sm bg-gray-50 rounded-xl p-4">
            <div>
              <p class="text-xs text-gray-400 mb-0.5">No. Transaksi</p>
              <p class="font-semibold text-gray-800"
                 x-text="selectedDetailOrder?.displayId"></p>
            </div>
            <div>
              <p class="text-xs text-gray-400 mb-0.5">Waktu</p>
              <p class="font-semibold text-gray-800"
                 x-text="selectedDetailOrder?.time"></p>
            </div>
            <div>
              <p class="text-xs text-gray-400 mb-0.5">Pelanggan</p>
              <p class="font-semibold text-gray-800"
                 x-text="selectedDetailOrder?.customer"></p>
            </div>
            <div>
              <p class="text-xs text-gray-400 mb-0.5">Meja</p>
              <p class="font-semibold text-gray-800"
                 x-text="selectedDetailOrder?.table"></p>
            </div>
          </div>

          <div class="border border-gray-100 rounded-xl overflow-hidden">
            <template x-if="!selectedDetailOrder?.items?.length">
              <p class="text-sm text-gray-400 p-4">Tidak ada item.</p>
            </template>
            <template x-if="selectedDetailOrder?.items?.length">
              <table class="w-full text-sm">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Item</th>
                    <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 uppercase">Qty</th>
                    <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">Subtotal</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <template x-for="item in (selectedDetailOrder?.items ?? [])"
                            :key="item.name + '-' + item.qty">
                    <tr>
                      <td class="px-4 py-2.5 text-gray-700"
                          x-text="item.name"></td>
                      <td class="px-4 py-2.5 text-center text-gray-600"
                          x-text="item.qty"></td>
                      <td class="px-4 py-2.5 text-right text-gray-700"
                          x-text="item.subtotal"></td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </template>
          </div>

          <div class="flex items-center justify-between">
            <p class="text-sm text-gray-500">Total</p>
            <p class="text-base font-bold text-gray-900"
               x-text="selectedDetailOrder?.total"></p>
          </div>

          <template x-if="selectedDetailOrder?.billing?.isDebt || selectedDetailOrder?.billing?.billingStatus === 'partial_paid'">
            <div class="rounded-xl border border-red-200 bg-red-50/50 p-4 space-y-2">
              <div class="flex items-center justify-between text-xs font-semibold text-red-800">
                <span>STATUS PEMBAYARAN:</span>
                <span class="rounded bg-red-100 px-2 py-0.5 text-red-700">HUTANG / PARSIAL</span>
              </div>
              <div class="flex items-center justify-between text-sm">
                <span class="text-red-700 font-medium">Sudah Dibayar:</span>
                <span class="font-bold text-red-900"
                      x-text="selectedDetailOrder?.billing?.paidAmountFormatted"></span>
              </div>
              <div class="flex items-center justify-between text-sm">
                <span class="text-red-700 font-medium">Sisa Hutang:</span>
                <span class="font-bold text-red-600 text-base"
                      x-text="selectedDetailOrder?.billing?.remainingBalanceFormatted"></span>
              </div>
              <button @click="openDebtModalFromDetail()"
                      type="button"
                      class="w-full mt-2 py-2.5 px-3 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold text-xs tracking-wide shadow-sm transition flex items-center justify-center gap-2">
                <svg class="w-4 h-4"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                PELUNASAN SISA HUTANG
              </button>
            </div>
          </template>

          <div class="flex gap-3">
            <button @click="openPrintFromDetail()"
                    class="flex-1 py-2.5 rounded-xl border border-slate-200 text-slate-700 text-sm font-medium hover:bg-slate-50 transition">
              Print Ulang
            </button>
            <button @click="closeOrderDetailModal()"
                    class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-600 text-sm font-medium hover:bg-gray-50 transition">
              Tutup
            </button>
          </div>
        </div>
      </div>
    </div>



    <!-- Print Modal -->
    <div x-show="showPrintModal"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center"
         style="display: none;">
      <!-- Backdrop -->
      <div class="absolute inset-0 bg-black/50"
           @click="closePrintModal()"></div>

      <!-- Modal -->
      <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 overflow-hidden"
           x-transition:enter="transition ease-out duration-200"
           x-transition:enter-start="opacity-0 scale-95"
           x-transition:enter-end="opacity-100 scale-100">
        <!-- Modal Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
          <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-gray-700"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            <h3 class="font-semibold text-gray-900">Cetak Transaksi</h3>
          </div>
          <button @click="closePrintModal()"
                  class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition">
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

        <div class="px-6 py-5">
          <p class="text-sm text-gray-500 mb-4">Pilih printer tujuan untuk transaksi ini</p>

          <!-- Transaction Info Card -->
          <div class="bg-gray-50 rounded-xl p-4 grid grid-cols-2 gap-3 mb-5 text-sm">
            <div>
              <p class="text-xs text-gray-400 mb-0.5">No. Transaksi</p>
              <p class="font-semibold text-gray-800"
                 x-text="selectedOrder?.displayId"></p>
            </div>
            <div>
              <p class="text-xs text-gray-400 mb-0.5">Total</p>
              <p class="font-semibold text-gray-800"
                 x-text="selectedOrder?.total"></p>
            </div>
            <div>
              <p class="text-xs text-gray-400 mb-0.5">Pelanggan</p>
              <p class="font-semibold text-gray-800"
                 x-text="selectedOrder?.customer"></p>
            </div>
            <div>
              <p class="text-xs text-gray-400 mb-0.5">Waktu</p>
              <p class="font-semibold text-gray-800"
                 x-text="selectedOrder?.time"></p>
            </div>
          </div>

          <p class="text-sm font-medium text-gray-700 mb-3">Pilih printer:</p>

          <div class="grid grid-cols-2 gap-3 mb-4"
               x-show="printablePrinters.length > 0"
               style="display: none;">
            <template x-for="printer in printablePrinters"
                      :key="`print-printer-${printer.id}`">
              <button @click="printToPrinter(printer)"
                      :disabled="printing"
                      :class="[
                          getPrinterButtonColor(printer),
                          hasBeenPrinted(resolvePrintTypeFromPrinter(printer)) ? 'ring-2 ring-amber-400' : ''
                      ]"
                      class="relative flex flex-col items-center justify-center gap-2 text-white rounded-xl py-5 px-4 font-semibold transition disabled:opacity-40 disabled:cursor-not-allowed">
                <svg class="w-7 h-7"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                <span x-text="printer.name"></span>
                <span class="text-xs text-white/80"
                      x-text="printTypeLabel(resolvePrintTypeFromPrinter(printer))"></span>
                <span x-show="hasBeenPrinted(resolvePrintTypeFromPrinter(printer))"
                      class="text-amber-300 text-xs font-bold">↺ Cetak Ulang</span>
              </button>
            </template>
          </div>

          <div x-show="printablePrinters.length === 0"
               class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800"
               style="display: none;">
            Tidak ada printer aktif yang bisa dipakai untuk cetak.
          </div>

          <!-- Toast message -->
          <div x-show="toastMessage"
               x-transition
               :class="toastSuccess ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700'"
               class="rounded-lg border px-4 py-2.5 text-sm mb-3"
               style="display: none;">
            <span x-text="toastMessage"></span>
          </div>

          <!-- Close -->
          <button @click="closePrintModal()"
                  class="w-full flex items-center justify-center gap-2 py-2.5 rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm font-medium transition">
            <svg class="w-4 h-4"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M6 18L18 6M6 6l12 12" />
            </svg>
            Tutup
          </button>
        </div>
      </div>
    </div>


    {{-- Auth Modal for Reprint --}}
    <div x-show="showAuthModal"
         x-transition.opacity
         style="display: none;"
         class="fixed inset-0 z-[70] flex items-center justify-center bg-black/60 px-4"
         @click.self="showAuthModal = false; authCode = ''; authError = '';">
      <div class="w-full max-w-sm rounded-2xl bg-white shadow-2xl">
        <div class="px-6 pt-6 pb-4">
          <div class="mb-4 flex items-center gap-3">
            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-amber-100">
              <svg class="h-5 w-5 text-amber-600"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
              </svg>
            </div>
            <div>
              <h3 class="text-base font-semibold text-gray-900">Autentikasi Diperlukan</h3>
              <p class="text-xs text-gray-500">Masukkan kode harian untuk cetak ulang</p>
            </div>
          </div>

          <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
            Dokumen ini sudah pernah dicetak sebelumnya. Cetak ulang memerlukan kode otorisasi harian.
          </div>

          <div class="mb-4 space-y-1.5 rounded-lg bg-gray-50 p-3 text-xs">
            <div class="flex justify-between">
              <span class="text-gray-500">No. Transaksi</span>
              <span class="font-medium text-gray-800"
                    x-text="selectedOrder?.displayId ?? '-'"></span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">Printer</span>
              <span class="font-medium text-gray-800"
                    x-text="pendingPrinterName ?? '-'"></span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">Jenis Cetak</span>
              <span class="font-medium capitalize text-gray-800"
                    x-text="printTypeLabel(pendingPrintType)"></span>
            </div>
          </div>

          <div class="mb-1">
            <label class="mb-1.5 block text-xs font-medium text-gray-700">Kode Harian (4 digit)</label>
            <input x-model="authCode"
                   @keydown.enter="verifyAndPrint()"
                   type="password"
                   inputmode="numeric"
                   maxlength="4"
                   placeholder="••••"
                   class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-center text-2xl tracking-[0.5em] focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none" />
          </div>
          <p x-show="authError"
             x-text="authError"
             style="display: none;"
             class="mb-2 text-center text-xs font-medium text-red-600"></p>
        </div>

        <div class="flex gap-2 border-t border-gray-100 px-6 pb-6 pt-4">
          <button @click="showAuthModal = false; authCode = ''; authError = '';"
                  class="flex-1 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
            Batal
          </button>
          <button @click="verifyAndPrint()"
                  :disabled="authCode.length !== 4 || isVerifyingAuth"
                  class="flex-1 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">
            <span x-show="!isVerifyingAuth">Verifikasi & Cetak</span>
            <span x-show="isVerifyingAuth">Memverifikasi...</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Debt Settlement Modal -->
    <div x-show="showDebtModal"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center"
         style="display: none;">
      <div class="absolute inset-0 bg-black/50"
           @click="closeDebtModal()"></div>

      <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 overflow-hidden"
           x-transition:enter="transition ease-out duration-200"
           x-transition:enter-start="opacity-0 scale-95"
           x-transition:enter-end="opacity-100 scale-100">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
          <div class="flex items-center gap-2">
            <div class="p-1.5 rounded-lg bg-red-100 text-red-600">
              <svg class="w-5 h-5"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
            </div>
            <h3 class="font-bold text-gray-900">Pelunasan Piutang / Hutang</h3>
          </div>
          <button type="button"
                  @click="closeDebtModal()"
                  class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition">✕</button>
        </div>

        <form @submit.prevent="submitDebtSettlement()">
          <div class="px-6 py-4 space-y-4">
            <div class="rounded-xl bg-slate-50 p-3.5 space-y-1.5 text-xs">
              <div class="flex justify-between">
                <span class="text-slate-500">No. Transaksi</span>
                <span class="font-semibold text-slate-800"
                      x-text="selectedDetailOrder?.displayId"></span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-500">Pelanggan</span>
                <span class="font-semibold text-slate-800"
                      x-text="selectedDetailOrder?.customer"></span>
              </div>
              <div class="flex justify-between border-t border-slate-200 pt-1.5">
                <span class="text-red-600 font-bold">Sisa Piutang</span>
                <span class="font-bold text-red-600 text-sm"
                      x-text="selectedDetailOrder?.billing?.remainingBalanceFormatted"></span>
              </div>
            </div>

            <div class="space-y-3">
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Nominal Pelunasan (Rp)</label>
                <input type="number"
                       x-model.number="debtForm.amount_paid"
                       min="1"
                       :max="selectedDetailOrder?.billing?.remainingBalance"
                       required
                       class="w-full rounded-xl border-gray-300 font-bold text-slate-900 focus:border-red-500 focus:ring-red-500">
              </div>

              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Metode Pembayaran</label>
                <select x-model="debtForm.payment_method"
                        required
                        class="w-full rounded-xl border-gray-300 text-sm focus:border-red-500 focus:ring-red-500">
                  <option value="cash">Cash / Tunai</option>
                  <option value="qris">QRIS</option>
                  <option value="debit">Kartu Debit</option>
                  <option value="kredit">Kartu Kredit</option>
                  <option value="transfer">Bank Transfer</option>
                </select>
              </div>

              <div x-show="debtForm.payment_method !== 'cash'">
                <label class="block text-xs font-medium text-gray-700 mb-1">Nomor Referensi / Approval Code</label>
                <input type="text"
                       x-model="debtForm.payment_reference_number"
                       placeholder="Contoh: REF-12345"
                       class="w-full rounded-xl border-gray-300 text-sm focus:border-red-500 focus:ring-red-500">
              </div>

              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Catatan (Opsional)</label>
                <input type="text"
                       x-model="debtForm.notes"
                       placeholder="Contoh: Pelunasan sisa via Transfer"
                       class="w-full rounded-xl border-gray-300 text-sm focus:border-red-500 focus:ring-red-500">
              </div>
            </div>

            <p x-show="debtError"
               x-text="debtError"
               class="text-xs text-red-600 font-medium"
               style="display: none;"></p>
          </div>

          <div class="flex gap-2 px-6 pb-5 border-t border-gray-100 pt-3">
            <button type="button"
                    @click="closeDebtModal()"
                    class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-600 text-sm font-medium hover:bg-gray-50 transition">Batal</button>
            <button type="submit"
                    :disabled="debtSaving"
                    class="flex-1 py-2.5 rounded-xl bg-red-600 text-white text-sm font-bold hover:bg-red-700 transition disabled:opacity-50">
              <span x-show="!debtSaving">Simpan Pelunasan</span>
              <span x-show="debtSaving">Menyimpan...</span>
            </button>
          </div>
        </form>
      </div>
    </div>

  </div>
  <script>
    let transactionHistoryOrderPayloads = @js($orderPrintPayloads);
    let transactionHistoryOrderDetailPayloads = @js($orderDetailPayloads);

    // Realtime order list polling: swap tbody rows + refresh detail/print payloads.
    window.txListPoll = function (opts) {
      return {
        init() {
          this.tick();
          this._timer = setInterval(() => this.tick(), 30000);
        },
        destroy() {
          if (this._timer) clearInterval(this._timer);
        },
        async tick() {
          try {
            const res = await fetch(opts.url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' });
            if (!res.ok) return;
            const data = await res.json();
            if (!data.success) return;

            const target = document.getElementById(opts.target);
            if (target && data.listHtml) {
              target.innerHTML = data.listHtml;
              if (window.Alpine) window.Alpine.initTree(target);
            }

            if (data.detailPayloads) transactionHistoryOrderDetailPayloads = data.detailPayloads;
            if (data.printPayloads) transactionHistoryOrderPayloads = data.printPayloads;

            const countEl = document.querySelector('[data-tx-count]');
            if (countEl && data.totalCount !== undefined) countEl.textContent = data.totalCount + ' transaksi';
          } catch (e) {
            // transient failure — keep last rendered list
          }
        },
      };
    };

    function transactionHistory() {
      return {
        showPrintModal: false,
        showOrderDetailModal: false,
        showPaymentEditModal: false,
        showErrorModal: false,
        showDebtModal: false,
        debtSaving: false,
        debtError: '',
        debtForm: {
          amount_paid: 0,
          payment_method: 'cash',
          payment_reference_number: '',
          notes: '',
        },
        selectedOrder: null,
        selectedDetailOrder: null,
        selectedErrorMessage: '',
        printing: false,
        toastMessage: '',
        toastSuccess: false,
        toastTimer: null,
        paymentEditError: '',
        paymentEditSaving: false,
        paymentEditSubtitle: '-',
        paymentEditForm: {
          payment_mode: 'normal',
          payment_method: 'cash',
          payment_reference_number: '',
          split_cash_amount: 0,
          split_cash_display: 'Rp 0',
          split_non_cash_amount: 0,
          split_non_cash_display: 'Rp 0',
          split_non_cash_method: '',
          split_non_cash_reference_number: '',
          split_second_non_cash_amount: 0,
          split_second_non_cash_display: 'Rp 0',
          split_second_non_cash_method: '',
          split_second_non_cash_reference_number: '',
        },

        availableLocations: @json($printerLocations),
        hasAnyActivePrinter: @json($hasAnyActivePrinter),

        showAuthModal: false,
        authCode: '',
        authError: '',
        isVerifyingAuth: false,
        pendingPrintType: null,
        pendingPrinterId: null,
        pendingPrinterName: null,
        activePrinterOptions: @js($activePrinterOptions),

        get printablePrinters() {
          return (this.activePrinterOptions ?? []).filter((printer) => {
            const type = this.resolvePrintTypeFromPrinter(printer);

            return ['resmi', 'kitchen', 'bar', 'checker'].includes(type);
          });
        },

        openPrintModal(order) {
          this.selectedOrder = order;
          this.toastMessage = '';
          this.showPrintModal = true;
        },

        openOrderDetailById(orderId) {
          const payload = transactionHistoryOrderDetailPayloads[String(orderId)] ?? transactionHistoryOrderDetailPayloads[orderId] ?? null;

          if (!payload) {
            return;
          }

          this.showPrintModal = false;
          this.selectedOrder = null;
          this.selectedDetailOrder = payload;
          this.showOrderDetailModal = true;
        },

        openPaymentEditModalById(orderId) {
          const payload = transactionHistoryOrderDetailPayloads[String(orderId)] ?? transactionHistoryOrderDetailPayloads[orderId] ?? null;

          if (!payload) {
            return;
          }

          this.selectedDetailOrder = payload;
          this.showOrderDetailModal = false;
          this.preparePaymentEditModal();
          this.showPaymentEditModal = true;
        },

        openPaymentEditModal() {
          if (!this.selectedDetailOrder) {
            return;
          }

          this.showOrderDetailModal = false;
          this.preparePaymentEditModal();
          this.showPaymentEditModal = true;
        },

        closeOrderDetailModal() {
          this.showOrderDetailModal = false;
          this.selectedDetailOrder = null;
        },

        closePaymentEditModal() {
          this.showPaymentEditModal = false;
          this.paymentEditError = '';
        },

        openErrorModal(message) {
          this.selectedErrorMessage = String(message || '');
          this.showErrorModal = true;
        },

        closeErrorModal() {
          this.showErrorModal = false;
          this.selectedErrorMessage = '';
        },

        openPrintFromDetail() {
          const orderId = this.selectedDetailOrder?.id;

          if (!orderId) {
            return;
          }

          this.closeOrderDetailModal();
          this.openPrintModalById(orderId);
        },

        openPrintModalById(orderId) {
          const payload = transactionHistoryOrderPayloads[String(orderId)] ?? transactionHistoryOrderPayloads[orderId] ?? null;

          if (!payload) {
            return;
          }

          this.openPrintModal(payload);
        },

        closePrintModal() {
          this.showPrintModal = false;
          this.selectedOrder = null;
          this.toastMessage = '';
          this.pendingPrinterId = null;
          this.pendingPrinterName = null;
        },

        formatCurrency(value) {
          return `Rp ${Number(value || 0).toLocaleString('id-ID')}`;
        },

        parseCurrency(value) {
          const digits = String(value ?? '').replace(/[^0-9]/g, '');

          return digits ? Number(digits) : 0;
        },

        paymentEditBilling() {
          return this.selectedDetailOrder?.billing ?? null;
        },

        preparePaymentEditModal() {
          const billing = this.paymentEditBilling();

          this.paymentEditSubtitle = this.selectedDetailOrder?.customer && this.selectedDetailOrder?.table ?
            `${this.selectedDetailOrder.customer} — ${this.selectedDetailOrder.table}` :
            this.selectedDetailOrder?.displayId ?? '-';

          this.paymentEditForm.payment_mode = billing?.paymentMode ?? 'normal';
          this.paymentEditForm.payment_method = billing?.paymentMethod ?? 'cash';
          this.paymentEditForm.payment_reference_number = billing?.paymentReferenceNumber ?? '';
          this.paymentEditForm.split_cash_amount = Number(billing?.splitCashAmount ?? 0);
          this.paymentEditForm.split_cash_display = this.formatCurrency(this.paymentEditForm.split_cash_amount);
          this.paymentEditForm.split_non_cash_amount = Number(billing?.splitNonCashAmount ?? 0);
          this.paymentEditForm.split_non_cash_display = this.formatCurrency(this.paymentEditForm.split_non_cash_amount);
          this.paymentEditForm.split_non_cash_method = billing?.splitNonCashMethod ?? '';
          this.paymentEditForm.split_non_cash_reference_number = billing?.splitNonCashReferenceNumber ?? '';
          this.paymentEditForm.split_second_non_cash_amount = Number(billing?.splitSecondNonCashAmount ?? 0);
          this.paymentEditForm.split_second_non_cash_display = this.formatCurrency(this.paymentEditForm.split_second_non_cash_amount);
          this.paymentEditForm.split_second_non_cash_method = billing?.splitSecondNonCashMethod ?? '';
          this.paymentEditForm.split_second_non_cash_reference_number = billing?.splitSecondNonCashReferenceNumber ?? '';

          this.paymentEditError = '';
        },

        togglePaymentEditFields() {
          if (this.paymentEditForm.payment_mode === 'split' && this.paymentEditForm.split_cash_amount === 0 && this.paymentEditForm.split_non_cash_amount === 0 && this.paymentEditForm.split_second_non_cash_amount === 0) {
            const grandTotal = Number(this.paymentEditBilling()?.grandTotal ?? 0);
            this.paymentEditForm.split_cash_amount = 0;
            this.paymentEditForm.split_cash_display = this.formatCurrency(0);
            this.paymentEditForm.split_non_cash_amount = grandTotal;
            this.paymentEditForm.split_non_cash_display = this.formatCurrency(grandTotal);
            this.paymentEditForm.split_second_non_cash_amount = 0;
            this.paymentEditForm.split_second_non_cash_display = this.formatCurrency(0);
          }
        },

        onPaymentSplitInput(which, event) {
          const grandTotal = Number(this.paymentEditBilling()?.grandTotal ?? 0);
          const value = this.parseCurrency(event?.target?.value);

          if (which === 'cash') {
            const cash = Math.min(Math.max(value, 0), grandTotal);
            this.paymentEditForm.split_cash_amount = cash;
            this.paymentEditForm.split_cash_display = this.formatCurrency(cash);
            this.paymentEditForm.split_non_cash_amount = Math.max(grandTotal - cash, 0);
            this.paymentEditForm.split_non_cash_display = this.formatCurrency(this.paymentEditForm.split_non_cash_amount);
            this.paymentEditForm.split_second_non_cash_amount = 0;
            this.paymentEditForm.split_second_non_cash_display = this.formatCurrency(0);
          }

          if (which === 'first') {
            const first = Math.min(Math.max(value, 0), grandTotal);
            this.paymentEditForm.split_non_cash_amount = first;
            this.paymentEditForm.split_non_cash_display = this.formatCurrency(first);
            this.paymentEditForm.split_second_non_cash_amount = Math.max(grandTotal - this.paymentEditForm.split_cash_amount - first, 0);
            this.paymentEditForm.split_second_non_cash_display = this.formatCurrency(this.paymentEditForm.split_second_non_cash_amount);
          }

          if (which === 'second') {
            const second = Math.min(Math.max(value, 0), grandTotal);
            this.paymentEditForm.split_second_non_cash_amount = second;
            this.paymentEditForm.split_second_non_cash_display = this.formatCurrency(second);
            this.paymentEditForm.split_cash_amount = 0;
            this.paymentEditForm.split_cash_display = this.formatCurrency(0);
            this.paymentEditForm.split_non_cash_amount = Math.max(grandTotal - second, 0);
            this.paymentEditForm.split_non_cash_display = this.formatCurrency(this.paymentEditForm.split_non_cash_amount);
          }
        },

        paymentMethodNeedsReference(method) {
          const normalized = String(method || '').trim().toLowerCase();

          return normalized !== '' && !['cash', 'tunai'].includes(normalized);
        },

        async submitPaymentEdit() {
          const billing = this.paymentEditBilling();

          if (!billing?.updatePaymentUrl) {
            return;
          }

          const payload = {
            payment_mode: this.paymentEditForm.payment_mode,
            payment_method: this.paymentEditForm.payment_method,
            payment_reference_number: this.paymentEditForm.payment_reference_number,
            split_cash_amount: Number(this.paymentEditForm.split_cash_amount ?? 0),
            split_non_cash_amount: Number(this.paymentEditForm.split_non_cash_amount ?? 0),
            split_non_cash_method: this.paymentEditForm.split_non_cash_method,
            split_non_cash_reference_number: this.paymentEditForm.split_non_cash_reference_number,
            split_second_non_cash_amount: Number(this.paymentEditForm.split_second_non_cash_amount ?? 0),
            split_second_non_cash_method: this.paymentEditForm.split_second_non_cash_method,
            split_second_non_cash_reference_number: this.paymentEditForm.split_second_non_cash_reference_number,
          };

          if (payload.payment_mode === 'normal' && payload.payment_method !== 'cash' && !String(payload.payment_reference_number || '').trim()) {
            this.paymentEditError = 'Nomor referensi pembayaran non-cash wajib diisi.';
            return;
          }

          if (payload.payment_mode === 'split') {
            if (payload.split_non_cash_amount > 0 && this.paymentMethodNeedsReference(payload.split_non_cash_method) && !String(payload.split_non_cash_reference_number || '').trim()) {
              this.paymentEditError = 'Nomor referensi non-cash pertama untuk split bill wajib diisi.';
              return;
            }

            if (payload.split_second_non_cash_amount > 0 && this.paymentMethodNeedsReference(payload.split_second_non_cash_method) && !String(payload.split_second_non_cash_reference_number || '').trim()) {
              this.paymentEditError = 'Nomor referensi non-cash kedua untuk split bill wajib diisi.';
              return;
            }
          }

          this.paymentEditSaving = true;
          this.paymentEditError = '';

          try {
            const response = await fetch(billing.updatePaymentUrl, {
              method: 'POST',
              headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
              },
              body: JSON.stringify(payload),
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
              throw new Error(result.message || 'Gagal memperbarui payment.');
            }

            window.location.reload();
          } catch (error) {
            this.paymentEditError = error?.message || 'Gagal memperbarui payment.';
          } finally {
            this.paymentEditSaving = false;
          }
        },

        normalizePrinterType(printer) {
          const printerType = String(printer?.printer_type ?? '').trim().toLowerCase();
          const location = String(printer?.location ?? '').trim().toLowerCase();

          if (['kitchen', 'bar', 'checker', 'cashier'].includes(printerType)) {
            return printerType;
          }

          if (['kitchen', 'bar', 'checker', 'cashier'].includes(location)) {
            return location;
          }

          return 'cashier';
        },

        resolvePrintTypeFromPrinter(printer) {
          const normalizedType = this.normalizePrinterType(printer);

          return normalizedType === 'cashier' ? 'resmi' : normalizedType;
        },

        printTypeLabel(type) {
          return {
            resmi: 'Struk Resmi',
            kitchen: 'Kitchen',
            bar: 'Bar',
            checker: 'Checker',
          } [type] ?? '-';
        },

        getPrinterButtonColor(printer) {
          const type = this.resolvePrintTypeFromPrinter(printer);

          if (type === 'kitchen') {
            return 'bg-orange-500 hover:bg-orange-400';
          }

          if (type === 'bar') {
            return 'bg-blue-600 hover:bg-blue-500';
          }

          if (type === 'checker') {
            return 'bg-purple-600 hover:bg-purple-500';
          }

          return 'bg-slate-800 hover:bg-slate-700';
        },

        hasBeenPrinted(type) {
          if (!this.selectedOrder || !this.selectedOrder.printCounts) {
            return false;
          }

          return Number(this.selectedOrder.printCounts[type] ?? 0) > 0;
        },

        async printToPrinter(printer) {
          if (this.printing || !this.selectedOrder) {
            return;
          }

          const type = this.resolvePrintTypeFromPrinter(printer);
          const printerId = Number(printer?.id ?? 0);

          if (!printerId) {
            this.toastSuccess = false;
            this.toastMessage = 'Printer tidak valid.';

            return;
          }

          if (this.hasBeenPrinted(type)) {
            this.pendingPrintType = type;
            this.pendingPrinterId = printerId;
            this.pendingPrinterName = String(printer?.name ?? '-');
            this.authCode = '';
            this.authError = '';
            this.showAuthModal = true;
            return;
          }

          await this._doPrint(type, false, printerId);
        },

        async verifyAndPrint() {
          if (this.authCode.length !== 4 || this.isVerifyingAuth) return;
          this.isVerifyingAuth = true;
          this.authError = '';

          try {
            const res = await fetch('{{ route('admin.settings.daily-auth-code.verify') }}', {
              method: 'POST',
              headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
              },
              body: JSON.stringify({
                code: this.authCode
              }),
            });
            const data = await res.json();
            if (data.valid) {
              this.showAuthModal = false;
              this.authCode = '';
              const type = this.pendingPrintType;
              const printerId = this.pendingPrinterId;
              this.pendingPrintType = null;
              this.pendingPrinterId = null;
              this.pendingPrinterName = null;
              await this._doPrint(type, true, printerId);
            } else {
              this.authError = 'Kode tidak valid. Coba lagi.';
            }
          } catch (e) {
            this.authError = 'Terjadi kesalahan. Coba lagi.';
          } finally {
            this.isVerifyingAuth = false;
          }
        },

        async _doPrint(type, isReprint = false, printerId = null) {
          this.printing = true;
          this.toastMessage = '';

          try {
            const url = `{{ url('admin/transaction-history') }}/${this.selectedOrder.id}/print`;
            const res = await fetch(url, {
              method: 'POST',
              headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
              },
              body: JSON.stringify({
                type,
                is_reprint: isReprint,
                printer_id: printerId ? Number(printerId) : undefined,
              }),
            });
            const data = await res.json();
            this.toastSuccess = data.success;
            this.toastMessage = data.message;

            if (data.success) {
              if (!this.selectedOrder.printCounts) {
                this.selectedOrder.printCounts = {
                  resmi: 0,
                  kitchen: 0,
                  bar: 0,
                  checker: 0,
                };
              }

              this.selectedOrder.printCounts[type] = Number(this.selectedOrder.printCounts[type] ?? 0) + 1;

              if (this.toastTimer) clearTimeout(this.toastTimer);
              this.toastTimer = setTimeout(() => {
                this.toastMessage = '';
              }, 3000);
            }
          } catch (e) {
            this.toastSuccess = false;
            this.toastMessage = 'Terjadi kesalahan. Coba lagi.';
          } finally {
            this.printing = false;
          }
        },

        openDebtModalFromDetail() {
          if (!this.selectedDetailOrder || !this.selectedDetailOrder.billing) return;
          const remaining = Number(this.selectedDetailOrder.billing.remainingBalance || 0);
          this.debtForm.amount_paid = remaining;
          this.debtForm.payment_method = 'cash';
          this.debtForm.payment_reference_number = '';
          this.debtForm.notes = '';
          this.debtError = '';
          this.showDebtModal = true;
        },

        closeDebtModal() {
          this.showDebtModal = false;
          this.debtError = '';
        },

        async submitDebtSettlement() {
          if (!this.selectedDetailOrder || !this.selectedDetailOrder.billing) return;
          this.debtSaving = true;
          this.debtError = '';

          try {
            const url = this.selectedDetailOrder.billing.settleDebtUrl;
            const response = await fetch(url, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
              },
              body: JSON.stringify(this.debtForm),
            });

            const data = await response.json();
            if (!response.ok || !data.success) {
              throw new Error(data.message || 'Gagal menyimpan pelunasan piutang.');
            }

            this.showToast(data.message, true);
            this.closeDebtModal();
            this.closeOrderDetailModal();
            window.location.reload();
          } catch (err) {
            this.debtError = err.message;
          } finally {
            this.debtSaving = false;
          }
        },
      };
    }
  </script>
</x-app-layout>
