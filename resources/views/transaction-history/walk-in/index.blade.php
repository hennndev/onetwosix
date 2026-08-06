<x-app-layout title="Riwayat Walk-In">
  <div class="p-4 sm:p-6"
       x-data="walkInTransactionHistory()">

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
          <h1 class="text-2xl font-bold text-gray-900">Walk In</h1>
          <p class="text-sm text-gray-500">Lihat semua transaksi walk in yang telah dilakukan</p>
        </div>
      </div>

    </div>

    <!-- Area Tabs (multi-area header) -->
    @if (($areas ?? collect())->count() > 1 && (! session('active_area_id') || session('active_area_id') === 'all'))
      @php
        $wiBaseQuery = array_merge(request()->except(['area_id', 'per_page', 'search', 'date_from', 'date_to']), ['transaction_mode' => 'walk_in']);
      @endphp
      <div class="flex gap-2 overflow-x-auto pb-2 mb-6">
        <a href="{{ route('admin.transaction-history.index', $wiBaseQuery) }}"
           class="px-4 py-2 rounded-lg whitespace-nowrap transition {{ is_null($selectedAreaId ?? null) ? 'bg-slate-800 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
          Semua Area
        </a>
        @foreach ($areas as $area)
          <a href="{{ route('admin.transaction-history.index', array_merge($wiBaseQuery, ['area_id' => $area->id])) }}"
             class="px-4 py-2 rounded-lg whitespace-nowrap transition {{ ($selectedAreaId ?? null) === $area->id ? 'bg-slate-800 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
            {{ $area->name }}
          </a>
        @endforeach
      </div>
    @endif

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-4">
      <!-- Total Transaksi -->
      <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 bg-slate-800 rounded-xl flex items-center justify-center shrink-0">
          <svg class="w-6 h-6 text-white"
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
          <p class="text-xs text-gray-500 mb-0.5">Total Transaksi</p>
          <p class="text-2xl font-bold text-gray-900">{{ $totalOrders }}</p>
        </div>
      </div>

      <!-- Hari Ini -->
      <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm flex items-center gap-4"
           style="background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);">
        <div class="w-12 h-12 bg-green-500 rounded-xl flex items-center justify-center shrink-0">
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
        <div>
          <p class="text-xs text-gray-500 mb-0.5">Hari Ini</p>
          <p class="text-2xl font-bold text-gray-900">{{ $todayOrders }}</p>
        </div>
      </div>

      <!-- Revenue -->
      <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm flex items-center gap-4"
           style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);">
        <div class="w-12 h-12 bg-yellow-500 rounded-xl flex items-center justify-center shrink-0">
          <svg class="w-6 h-6 text-white"
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
          <p class="text-xs text-gray-500 mb-0.5">Revenue</p>
          <p class="text-2xl font-bold text-gray-900">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</p>
        </div>
      </div>

      <!-- Avg Transaction -->
      <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm flex items-center gap-4"
           style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);">
        <div class="w-12 h-12 bg-blue-500 rounded-xl flex items-center justify-center shrink-0">
          <svg class="w-6 h-6 text-white"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
        </div>
        <div>
          <p class="text-xs text-gray-500 mb-0.5">Avg Per Transaction</p>
          <p class="text-2xl font-bold text-gray-900">Rp {{ number_format($totalOrders > 0 ? $totalRevenue / $totalOrders : 0, 0, ',', '.') }}</p>
        </div>
      </div>
    </div>

    <!-- Content -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <!-- Header & Filter -->
      <div class="px-5 py-4 border-b border-gray-100 bg-gray-50">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
          <h2 class="font-semibold text-gray-800">Daftar Transaksi Walk In</h2>
          <div class="flex items-center gap-2">
            <select x-model="perPage"
                    @change="window.location.href = updatePerPage($el.value)"
                    class="px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-400 bg-white text-gray-700">
              <option value="10">10</option>
              <option value="20">20</option>
              <option value="50">50</option>
            </select>
          </div>
        </div>

        <form method="GET"
              action="{{ route('admin.transaction-history.index') }}"
              class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 mb-3">
          <input type="hidden"
                 name="transaction_mode"
                 value="walk_in">
          @if (! is_null($selectedAreaId ?? null))
            <input type="hidden"
                   name="area_id"
                   value="{{ $selectedAreaId }}">
          @endif
          <input type="text"
                 name="search"
                 placeholder="Cari nama, kontak, no. transaksi..."
                 value="{{ request('search') }}"
                 class="flex-1 px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-400 bg-white text-gray-700">
          <button type="submit"
                  class="px-4 py-1.5 text-sm font-medium bg-slate-800 text-white rounded-lg hover:bg-slate-900 transition">
            Cari
          </button>
          @if (request('search'))
            <a href="{{ route('admin.transaction-history.index', ['transaction_mode' => 'walk_in']) }}"
               class="px-4 py-1.5 text-sm font-medium text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
              Reset
            </a>
          @endif
        </form>

        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
          <span class="text-sm font-medium text-gray-500">Filter Tanggal:</span>
          <form method="GET"
                action="{{ route('admin.transaction-history.index') }}"
                class="flex flex-wrap items-center gap-2">
            @if (request('search'))
              <input type="hidden"
                     name="search"
                     value="{{ request('search') }}">
            @endif
            @if (request('per_page'))
              <input type="hidden"
                     name="per_page"
                     value="{{ request('per_page') }}">
            @endif
            <input type="hidden"
                   name="transaction_mode"
                   value="walk_in">
            @if (! is_null($selectedAreaId ?? null))
              <input type="hidden"
                     name="area_id"
                     value="{{ $selectedAreaId }}">
            @endif
            <input type="date"
                   name="date_from"
                   value="{{ request('date_from') }}"
                   class="px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-400 bg-white text-gray-700">
            <span class="text-gray-400">–</span>
            <input type="date"
                   name="date_to"
                   value="{{ request('date_to') }}"
                   class="px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-400 bg-white text-gray-700">
            <button type="submit"
                    class="px-3 py-1.5 text-xs font-medium bg-slate-800 text-white rounded-lg hover:bg-slate-900 transition">
              Filter
            </button>
            @if (request('date_from') || request('date_to'))
              <a href="{{ route('admin.transaction-history.index', ['transaction_mode' => 'walk_in']) }}"
                 class="px-3 py-1.5 text-xs font-medium text-gray-500 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                Reset
              </a>
            @endif
          </form>
        </div>
      </div>

      @if ($orders->isEmpty())
        <div class="flex flex-col items-center justify-center py-16 text-gray-400">
          <svg class="w-12 h-12 mb-3 text-gray-300"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2" />
          </svg>
          <p class="text-sm font-medium">Tidak ada transaksi ditemukan</p>
        </div>
      @else
        <div class="overflow-x-auto">
          <table class="w-full text-sm min-w-[800px]">
            <thead>
              <tr class="bg-gray-50 border-b border-gray-200">
                <th class="px-5 py-3 text-left text-sm font-semibold text-gray-600">Status</th>
                <th class="px-5 py-3 text-left text-sm font-semibold text-gray-600">Customer</th>
                <th class="px-5 py-3 text-left text-sm font-semibold text-gray-600">Kontak</th>
                <th class="px-5 py-3 text-left text-sm font-semibold text-gray-600">Category</th>
                <th class="px-5 py-3 text-left text-sm font-semibold text-gray-600">Table</th>
                <th class="px-5 py-3 text-left text-sm font-semibold text-gray-600">Date/Time</th>
                <th class="px-5 py-3 text-left text-sm font-semibold text-gray-600">Orders</th>
                <th class="px-5 py-3 text-right text-sm font-semibold text-gray-600">Total Spent</th>
                <th class="px-5 py-3 text-left text-sm font-semibold text-gray-600">Error Message</th>
                <th class="px-5 py-3 text-right text-sm font-semibold text-gray-600">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              @foreach ($orders as $order)
                @php
                  $billing = $order->billing ?? $order->tableSession?->billing;
                  $accurateSoNumber = $billing?->accurate_so_number ?: $order->accurate_so_number;
                  $accurateInvNumber = $billing?->accurate_inv_number ?: $order->accurate_inv_number;
                  $isAccurateMissing = blank($accurateSoNumber) || blank($accurateInvNumber);
                  $customerPhone = $order->tableSession?->customer?->profile?->phone ?? $order->customer?->user?->profile?->phone;
                  $customerEmail = $order->tableSession?->customer?->email ?? $order->customer?->user?->email;
                  $tableName = $order->tableSession?->table?->table_number;
                  $areaName = $order->tableSession?->table?->area?->name;
                  $orderedAt = $order->ordered_at;
                @endphp
                <tr x-on:click="openOrderDetailById({{ $order->id }})"
                    class="hover:bg-gray-50 transition-colors cursor-pointer">
                  <td class="px-5 py-4 whitespace-nowrap">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                      <svg class="w-3 h-3"
                           fill="none"
                           stroke="currentColor"
                           viewBox="0 0 24 24">
                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2.5"
                              d="M9 12l2 2 4-4" />
                      </svg>
                      Completed
                    </span>
                  </td>

                  <td class="px-5 py-4">
                    <div class="text-base font-semibold text-gray-900">{{ $order->tableSession?->customer?->name ?? ($order->customer?->user?->name ?? 'Walk In') }}</div>
                    <div class="text-sm text-gray-400 mt-0.5">{{ $order->order_number }}</div>
                  </td>

                  <td class="px-5 py-4">
                    <div class="text-sm text-gray-700">{{ $customerPhone ?: '-' }}</div>
                    <div class="text-sm text-gray-400 mt-0.5">{{ $customerEmail ?: '-' }}</div>
                  </td>

                  <td class="px-5 py-4 whitespace-nowrap">
                    @if ($areaName)
                      <span class="inline-flex items-center px-2.5 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-600">
                        {{ $areaName }}
                      </span>
                    @else
                      <span class="text-gray-400 text-sm">-</span>
                    @endif
                  </td>

                  <td class="px-5 py-4">
                    @if ($tableName)
                      <div class="text-base font-semibold text-gray-900">{{ $tableName }}</div>
                    @else
                      <span class="text-gray-400 text-sm">Walk In</span>
                    @endif
                  </td>

                  <td class="px-5 py-4 whitespace-nowrap">
                    @if ($orderedAt)
                      <div class="text-base font-medium text-gray-800">
                        {{ $orderedAt->format('d M Y') }}
                      </div>
                      <div class="text-sm text-gray-400 mt-0.5">
                        {{ $orderedAt->format('H:i') }}
                      </div>
                    @else
                      <span class="text-gray-400 text-sm">-</span>
                    @endif
                  </td>

                  <td class="px-5 py-4">
                    <button type="button"
                            x-on:click.stop="openOrderDetailById({{ $order->id }})"
                            class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200 transition">
                      Lihat Orders ({{ $order->items->count() }} item)
                    </button>
                  </td>

                  <td x-on:click.stop
                      class="px-5 py-4 whitespace-nowrap text-right">
                    <span class="text-base font-bold text-gray-900">
                      Rp {{ number_format($order->total, 0, ',', '.') }}
                    </span>
                  </td>

                  <td x-on:click.stop
                      class="px-5 py-4">
                    @if (filled($billing?->error_message))
                      <button type="button"
                              x-on:click.stop="openErrorModal(@js((string) $billing->error_message))"
                              class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-red-100 text-red-700 hover:bg-red-200 transition">
                        Lihat Error
                      </button>
                    @else
                      <span class="text-gray-300 text-sm">-</span>
                    @endif
                  </td>

                  <td x-on:click.stop
                      class="px-5 py-4 whitespace-nowrap text-right">
                    <div class="inline-flex items-center gap-2">
                      @if ($isAccurateMissing)
                        <form method="POST"
                              action="{{ route('admin.transaction-history.reSyncAccurate', $order) }}"
                              class="inline"
                              onsubmit="const button = this.querySelector('[data-transaction-resync-button]'); if (button) { button.textContent = 'Sync...'; setTimeout(() => { button.disabled = true; }, 0); }">
                          @csrf
                          <button type="submit"
                                  data-transaction-resync-button
                                  class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-amber-100 text-amber-700 hover:bg-amber-200 transition disabled:opacity-60 disabled:cursor-not-allowed">
                            Re-sync Accurate
                          </button>
                        </form>
                      @endif
                      <button x-on:click.stop="printDirect({{ $order->id }})"
                              :disabled="printing"
                              class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200 transition disabled:opacity-60 disabled:cursor-not-allowed">
                        <svg x-show="printing"
                             class="w-3.5 h-3.5 mr-1.5 animate-spin"
                             fill="none"
                             viewBox="0 0 24 24"
                             style="display: none;">
                          <circle class="opacity-25"
                                  cx="12"
                                  cy="12"
                                  r="10"
                                  stroke="currentColor"
                                  stroke-width="4"></circle>
                          <path class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span x-text="printing ? 'Print...' : 'Print Ulang'">Print Ulang</span>
                      </button>
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        @if ($orders->hasPages())
          <div class="px-5 py-4 border-t border-gray-100">
            <!-- Pagination -->
            <div class="flex items-center justify-between">
              <p class="text-sm text-gray-500">Menampilkan {{ $orders->firstItem() ?? 0 }} - {{ $orders->lastItem() ?? 0 }} dari {{ $orders->total() }} hasil</p>

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

              <nav class="flex items-center gap-1">
                @if ($orders->onFirstPage())
                  <span class="inline-flex items-center justify-center w-9 h-9 text-gray-400 select-none">
                    <svg class="w-4 h-4"
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24">
                      <path stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M15 19l-7-7 7-7" />
                    </svg>
                  </span>
                @else
                  <a href="{{ $orders->previousPageUrl() }}"
                     class="inline-flex items-center justify-center w-9 h-9 rounded-lg hover:bg-gray-100 transition text-gray-600 hover:text-gray-900">
                    <svg class="w-4 h-4"
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24">
                      <path stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M15 19l-7-7 7-7" />
                    </svg>
                  </a>
                @endif

                @foreach ($historyVisiblePages as $page)
                  @if ($historyPreviousVisiblePage !== null && $page - $historyPreviousVisiblePage > 1)
                    <span class="pagination-ellipsis inline-flex items-center justify-center w-9 h-9 text-gray-400 select-none">...</span>
                  @endif

                  @if ($page === $historyCurrentPage)
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-slate-800 text-white font-semibold">{{ $page }}</span>
                  @else
                    <a href="{{ $orders->url($page) }}"
                       class="inline-flex items-center justify-center w-9 h-9 rounded-lg hover:bg-gray-100 transition text-gray-600 hover:text-gray-900 font-medium">{{ $page }}</a>
                  @endif

                  @php $historyPreviousVisiblePage = $page; @endphp
                @endforeach

                @if ($orders->hasMorePages())
                  <a href="{{ $orders->nextPageUrl() }}"
                     class="inline-flex items-center justify-center w-9 h-9 rounded-lg hover:bg-gray-100 transition text-gray-600 hover:text-gray-900">
                    <svg class="w-4 h-4"
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24">
                      <path stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 5l7 7-7 7" />
                    </svg>
                  </a>
                @else
                  <span class="inline-flex items-center justify-center w-9 h-9 text-gray-400 select-none">
                    <svg class="w-4 h-4"
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24">
                      <path stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 5l7 7-7 7" />
                    </svg>
                  </span>
                @endif
              </nav>
            </div>
          </div>
        @endif
      @endif
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
              <p class="text-xs text-gray-400 mb-0.5">Pelanggan</p>
              <p class="font-semibold text-gray-800"
                 x-text="selectedDetailOrder?.customer"></p>
            </div>
            <div>
              <p class="text-xs text-gray-400 mb-0.5">Meja</p>
              <p class="font-semibold text-gray-800"
                 x-text="selectedDetailOrder?.table"></p>
            </div>
            <div>
              <p class="text-xs text-gray-400 mb-0.5">Waktu</p>
              <p class="font-semibold text-gray-800"
                 x-text="selectedDetailOrder?.time"></p>
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

          <div class="space-y-2">
            <template x-if="(selectedDetailOrder?.taxTotal ?? 0) > 0">
              <div class="flex items-center justify-between">
                <p class="text-sm text-gray-500">PB1</p>
                <p class="text-sm font-semibold text-gray-700"
                   x-text="selectedDetailOrder?.taxTotalFormatted"></p>
              </div>
            </template>

            <template x-if="(selectedDetailOrder?.serviceChargeTotal ?? 0) > 0">
              <div class="flex items-center justify-between">
                <p class="text-sm text-gray-500">Service Charge</p>
                <p class="text-sm font-semibold text-gray-700"
                   x-text="selectedDetailOrder?.serviceChargeTotalFormatted"></p>
              </div>
            </template>
          </div>

          <template x-if="selectedDetailOrder?.billing">
            <div class="grid grid-cols-2 gap-3 text-sm bg-gray-50 rounded-xl p-4">
              <div>
                <p class="text-xs text-gray-400 mb-0.5">Mode Pembayaran</p>
                <p class="font-semibold text-gray-800"
                   x-text="selectedDetailOrder?.billing?.paymentMode?.toUpperCase?.() ?? selectedDetailOrder?.billing?.paymentMode"></p>
              </div>
              <div>
                <p class="text-xs text-gray-400 mb-0.5">Metode Pembayaran</p>
                <p class="font-semibold text-gray-800"
                   x-text="selectedDetailOrder?.billing?.paymentMethodDisplay ?? selectedDetailOrder?.billing?.paymentMethod"></p>
              </div>
              <div>
                <p class="text-xs text-gray-400 mb-0.5">Reference Number</p>
                <p class="font-semibold text-gray-800 break-words"
                   x-text="selectedDetailOrder?.billing?.paymentReferenceNumber || '-' "></p>
              </div>
              <div>
                <p class="text-xs text-gray-400 mb-0.5">Transaction Code</p>
                <p class="font-semibold text-gray-800 break-words"
                   x-text="selectedDetailOrder?.billing?.transactionCode || '-' "></p>
              </div>
              <div>
                <p class="text-xs text-gray-400 mb-0.5">Grand Total Billing</p>
                <p class="font-semibold text-gray-800"
                   x-text="selectedDetailOrder?.billing?.grandTotalFormatted"></p>
              </div>
              <div>
                <p class="text-xs text-gray-400 mb-0.5">Paid Amount</p>
                <p class="font-semibold text-gray-800"
                   x-text="selectedDetailOrder?.billing?.paidAmountFormatted"></p>
              </div>
            </div>
          </template>

          <div class="flex items-center justify-between">
            <p class="text-sm text-gray-500">Total</p>
            <p class="text-base font-bold text-gray-900"
               x-text="selectedDetailOrder?.total"></p>
          </div>

          <div class="flex gap-3">
            <button @click="openPaymentEditModal()"
                    class="flex-1 py-2.5 rounded-xl border border-blue-200 text-blue-700 text-sm font-medium hover:bg-blue-50 transition">
              Edit Payment
            </button>
            <button @click="printDirect(selectedDetailOrder?.id)"
                    :disabled="printing"
                    class="flex-1 inline-flex items-center justify-center gap-1.5 py-2.5 rounded-xl border border-slate-200 text-slate-700 text-sm font-medium hover:bg-slate-50 transition disabled:opacity-60 disabled:cursor-not-allowed">
              <svg x-show="printing"
                   class="w-4 h-4 animate-spin"
                   fill="none"
                   viewBox="0 0 24 24"
                   style="display: none;">
                <circle class="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        stroke-width="4"></circle>
                <path class="opacity-75"
                      fill="currentColor"
                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span x-text="printing ? 'Print...' : 'Print Ulang'">Print Ulang</span>
            </button>
            <button @click="closeOrderDetailModal()"
                    class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-600 text-sm font-medium hover:bg-gray-50 transition">
              Tutup
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Payment Edit Modal -->
    <div x-show="showPaymentEditModal"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center"
         style="display: none;">
      <div class="absolute inset-0 bg-black/50"
           @click="closePaymentEditModal()"></div>

      <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-2xl mx-4 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
          <div>
            <h3 class="font-semibold text-gray-900">Edit Payment</h3>
            <p class="text-xs text-gray-500 mt-0.5"
               x-text="paymentEditSubtitle"></p>
          </div>
          <button @click="closePaymentEditModal()"
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

        <div class="px-6 py-5 space-y-4 text-sm">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 bg-gray-50 rounded-xl p-4">
            <div>
              <p class="text-xs text-gray-400 mb-0.5">No. Transaksi</p>
              <p class="font-semibold text-gray-800"
                 x-text="selectedDetailOrder?.displayId"></p>
            </div>
            <div>
              <p class="text-xs text-gray-400 mb-0.5">Grand Total</p>
              <p class="font-semibold text-gray-800"
                 x-text="selectedDetailOrder?.billing?.grandTotalFormatted ?? selectedDetailOrder?.total"></p>
            </div>
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Mode Pembayaran</label>
            <select x-model="paymentEditForm.payment_mode"
                    @change="togglePaymentEditFields()"
                    class="w-full px-3 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-slate-400 bg-white">
              <option value="normal">Normal</option>
              <option value="split">Split</option>
            </select>
          </div>

          <div x-show="paymentEditForm.payment_mode === 'normal'"
               class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Metode Pembayaran</label>
              <select x-model="paymentEditForm.payment_method"
                      class="w-full px-3 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-slate-400 bg-white">
                <option value="cash">CASH</option>
                <option value="kredit">KREDIT</option>
                <option value="debit">DEBIT</option>
                <option value="qris">QRIS</option>
                <option value="transfer">TRANSFER</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Reference Number</label>
              <input x-model="paymentEditForm.payment_reference_number"
                     type="text"
                     maxlength="100"
                     placeholder="Isi jika non-cash"
                     class="w-full px-3 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-slate-400 bg-white">
            </div>
          </div>

          <div x-show="paymentEditForm.payment_mode === 'split'"
               class="space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Split Cash Amount</label>
                <input x-model="paymentEditForm.split_cash_display"
                       @input="onPaymentSplitInput('cash', $event)"
                       :disabled="isSplitCashDisabled()"
                       type="text"
                       inputmode="numeric"
                       class="w-full px-3 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-slate-400 bg-white disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed">
                <input x-model="paymentEditForm.split_cash_amount"
                       type="hidden">
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Split Non-cash 1 Amount</label>
                <input x-model="paymentEditForm.split_non_cash_display"
                       @input="onPaymentSplitInput('first', $event)"
                       type="text"
                       inputmode="numeric"
                       class="w-full px-3 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-slate-400 bg-white">
                <input x-model="paymentEditForm.split_non_cash_amount"
                       type="hidden">
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Split Non-cash 1 Method</label>
                <select x-model="paymentEditForm.split_non_cash_method"
                        class="w-full px-3 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-slate-400 bg-white">
                  <option value="">Pilih metode</option>
                  <option value="debit">DEBIT</option>
                  <option value="kredit">KREDIT</option>
                  <option value="qris">QRIS</option>
                  <option value="transfer">TRANSFER</option>
                  <option value="ewallet">EWALLET</option>
                  <option value="lainnya">LAINNYA</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Split Non-cash 1 Reference</label>
                <input x-model="paymentEditForm.split_non_cash_reference_number"
                       type="text"
                       maxlength="100"
                       class="w-full px-3 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-slate-400 bg-white">
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Split Non-cash 2 Amount</label>
                <input x-model="paymentEditForm.split_second_non_cash_display"
                       @input="onPaymentSplitInput('second', $event)"
                       :disabled="isSplitSecondNonCashDisabled()"
                       type="text"
                       inputmode="numeric"
                       class="w-full px-3 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-slate-400 bg-white disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed">
                <input x-model="paymentEditForm.split_second_non_cash_amount"
                       type="hidden">
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Split Non-cash 2 Method</label>
                <select x-model="paymentEditForm.split_second_non_cash_method"
                        :disabled="isSplitSecondNonCashDisabled()"
                        class="w-full px-3 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-slate-400 bg-white disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed">
                  <option value="">Pilih metode</option>
                  <option value="debit">DEBIT</option>
                  <option value="kredit">KREDIT</option>
                  <option value="qris">QRIS</option>
                  <option value="transfer">TRANSFER</option>
                  <option value="ewallet">EWALLET</option>
                  <option value="lainnya">LAINNYA</option>
                </select>
              </div>
            </div>

            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Split Non-cash 2 Reference</label>
              <input x-model="paymentEditForm.split_second_non_cash_reference_number"
                     :disabled="isSplitSecondNonCashDisabled()"
                     type="text"
                     maxlength="100"
                     class="w-full px-3 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-slate-400 bg-white disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed">
            </div>
          </div>

          <p x-show="paymentEditError"
             x-text="paymentEditError"
             class="text-xs text-red-600"></p>
        </div>

        <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-end gap-2">
          <button @click="closePaymentEditModal()"
                  class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200 transition">
            Batal
          </button>
          <button @click="submitPaymentEdit()"
                  :disabled="paymentEditSaving"
                  class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-blue-600 text-white hover:bg-blue-700 transition disabled:opacity-60 disabled:cursor-not-allowed">
            <span x-show="!paymentEditSaving">Simpan Payment</span>
            <span x-show="paymentEditSaving">Menyimpan...</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Error Modal -->
    <div x-show="showErrorModal"
         x-transition
         class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 px-4"
         style="display: none;"
         @click.self="closeErrorModal()">
      <div class="w-full max-w-sm rounded-2xl bg-white shadow-2xl p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-3">Error Message</h3>
        <p class="text-sm text-gray-700 mb-4 whitespace-pre-wrap"
           x-text="selectedErrorMessage"></p>
        <button @click="closeErrorModal()"
                class="w-full py-2.5 rounded-lg bg-slate-800 text-white font-medium hover:bg-slate-900 transition">
          Tutup
        </button>
      </div>
    </div>

    <!-- Print Toast -->
    <div x-show="toastMessage"
         x-transition
         :class="toastSuccess ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700'"
         class="fixed bottom-6 right-6 rounded-lg border px-4 py-2.5 text-sm z-[80] max-w-sm shadow-xl"
         style="display: none;">
      <span x-text="toastMessage"></span>
    </div>

  </div>

  <script>
    const walkInOrderDetailPayloads = @js($orderDetailPayloads);

    function walkInTransactionHistory() {
      return {
        showOrderDetailModal: false,
        showPaymentEditModal: false,
        showErrorModal: false,
        selectedDetailOrder: null,
        selectedErrorMessage: '',
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
        printing: false,
        perPage: @js(request('per_page', 10)),

        openOrderDetailById(orderId) {
          const payload = walkInOrderDetailPayloads[String(orderId)] ?? walkInOrderDetailPayloads[orderId] ?? null;

          if (!payload) {
            return;
          }

          this.selectedDetailOrder = payload;
          this.showOrderDetailModal = true;
        },

        closeOrderDetailModal() {
          this.showOrderDetailModal = false;
          this.selectedDetailOrder = null;
        },

        openPaymentEditModal() {
          if (!this.selectedDetailOrder) {
            return;
          }

          this.showOrderDetailModal = false;
          this.preparePaymentEditModal();
          this.showPaymentEditModal = true;
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

        showToast(message, success = true, duration = 3000) {
          this.toastSuccess = success;
          this.toastMessage = message;

          if (this.toastTimer) {
            clearTimeout(this.toastTimer);
            this.toastTimer = null;
          }

          if (duration > 0) {
            this.toastTimer = setTimeout(() => {
              this.toastMessage = '';
              this.toastTimer = null;
            }, duration);
          }
        },

        updatePerPage(value) {
          const params = new URLSearchParams(window.location.search);
          params.set('per_page', value);
          return window.location.pathname + '?' + params.toString();
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

          if (this.isSplitSecondNonCashDisabled()) {
            this.paymentEditForm.split_second_non_cash_amount = 0;
            this.paymentEditForm.split_second_non_cash_display = this.formatCurrency(0);
            this.paymentEditForm.split_second_non_cash_method = '';
            this.paymentEditForm.split_second_non_cash_reference_number = '';
          }

          if (this.isSplitCashDisabled()) {
            this.paymentEditForm.split_cash_amount = 0;
            this.paymentEditForm.split_cash_display = this.formatCurrency(0);
          }
        },

        isSplitSecondNonCashDisabled() {
          return this.paymentEditForm.payment_mode === 'split' && Number(this.paymentEditForm.split_cash_amount ?? 0) > 0;
        },

        isSplitCashDisabled() {
          return this.paymentEditForm.payment_mode === 'split' && Number(this.paymentEditForm.split_second_non_cash_amount ?? 0) > 0;
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

            if (cash > 0) {
              this.paymentEditForm.split_second_non_cash_amount = 0;
              this.paymentEditForm.split_second_non_cash_display = this.formatCurrency(0);
              this.paymentEditForm.split_second_non_cash_method = '';
              this.paymentEditForm.split_second_non_cash_reference_number = '';
            }
          }

          if (which === 'first') {
            const first = Math.min(Math.max(value, 0), grandTotal);
            this.paymentEditForm.split_non_cash_amount = first;
            this.paymentEditForm.split_non_cash_display = this.formatCurrency(first);

            if (this.isSplitSecondNonCashDisabled()) {
              this.paymentEditForm.split_second_non_cash_amount = 0;
              this.paymentEditForm.split_second_non_cash_display = this.formatCurrency(0);
              this.paymentEditForm.split_second_non_cash_method = '';
              this.paymentEditForm.split_second_non_cash_reference_number = '';
              this.paymentEditForm.split_cash_amount = Math.max(grandTotal - first, 0);
              this.paymentEditForm.split_cash_display = this.formatCurrency(this.paymentEditForm.split_cash_amount);
            } else {
              this.paymentEditForm.split_second_non_cash_amount = Math.max(grandTotal - this.paymentEditForm.split_cash_amount - first, 0);
              this.paymentEditForm.split_second_non_cash_display = this.formatCurrency(this.paymentEditForm.split_second_non_cash_amount);
            }
          }

          if (which === 'second') {
            const second = Math.min(Math.max(value, 0), grandTotal);
            this.paymentEditForm.split_second_non_cash_amount = second;
            this.paymentEditForm.split_second_non_cash_display = this.formatCurrency(second);

            if (second > 0) {
              this.paymentEditForm.split_cash_amount = 0;
              this.paymentEditForm.split_cash_display = this.formatCurrency(0);
            }

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

          if (payload.payment_mode === 'split') {
            if (this.isSplitSecondNonCashDisabled()) {
              payload.split_second_non_cash_amount = 0;
              payload.split_second_non_cash_method = '';
              payload.split_second_non_cash_reference_number = '';
            }

            if (this.isSplitCashDisabled()) {
              payload.split_cash_amount = 0;
            }
          }

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

        async printDirect(orderId) {
          if (this.printing) {
            return;
          }

          this.printing = true;
          this.showToast('Mencetak ke kasir...', true, 0);

          try {
            const url = `{{ url('admin/transaction-history') }}/${orderId}/print`;
            const res = await fetch(url, {
              method: 'POST',
              headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
              },
              body: JSON.stringify({
                type: 'resmi',
                is_reprint: true,
              }),
            });
            const data = await res.json();

            this.showToast(data.message, data.success, 3000);
          } catch (e) {
            this.showToast('Terjadi kesalahan. Coba lagi.', false, 3000);
          } finally {
            this.printing = false;
          }
        },
      };
    }
  </script>
</x-app-layout>
