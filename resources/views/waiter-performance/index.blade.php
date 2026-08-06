<x-app-layout title="Kinerja Waiter">
  <div class="p-4 sm:p-6">

    <!-- Header -->
    <div class="flex items-center gap-3 mb-6 bg-white border border-slate-200 rounded-xl p-5">
      <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center flex-shrink-0">
        <svg class="w-5 h-5 text-white"
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
        <h1 class="text-xl font-bold text-slate-800">Waiter Performance</h1>
        <p class="text-sm text-slate-500">Monitor performa dan penjualan setiap waiter</p>
      </div>
    </div>

    <!-- Realtime summary stats (polled) -->
    @php
      $summaryQuery = array_merge(['summary' => 1], $selectedAreaId ? ['area_id' => $selectedAreaId] : []);
    @endphp
    <div id="waiterStats"
         x-data="realtimePoll({ url: '{{ route('admin.waiter-performance.index', $summaryQuery) }}', target: 'waiterStats', interval: 15000 })">
      @include('waiter-performance._partials.stats')
    </div>

    <!-- Area Tabs (multi-area header) -->
    @if (($areas ?? collect())->count() > 1 && (! session('active_area_id') || session('active_area_id') === 'all'))
      <div class="flex gap-2 overflow-x-auto pb-2 mb-5">
        <a href="{{ route('admin.waiter-performance.index', request()->except('area_id')) }}"
           class="px-4 py-2 rounded-lg whitespace-nowrap transition {{ is_null($selectedAreaId) ? 'bg-slate-800 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
          Semua Area
        </a>
        @foreach ($areas as $area)
          <a href="{{ route('admin.waiter-performance.index', array_merge(request()->except('area_id'), ['area_id' => $area->id])) }}"
             class="px-4 py-2 rounded-lg whitespace-nowrap transition {{ $selectedAreaId === $area->id ? 'bg-slate-800 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
            {{ $area->name }}
          </a>
        @endforeach
      </div>
    @endif

    <form method="GET"
          action="{{ route('admin.waiter-performance.index') }}"
          id="filterForm">

      <!-- Period & Mode -->
      <div class="bg-white border border-slate-200 rounded-xl p-5 mb-5 flex flex-col sm:flex-row sm:items-center gap-4">
        <!-- Date info -->
        <div class="flex items-center gap-3 flex-1">
          <svg class="w-5 h-5 text-blue-500 flex-shrink-0"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
          <div>
            <p class="text-xs text-slate-500">Periode</p>
            <p class="text-sm font-semibold text-slate-800">{{ $periodLabel }}</p>
          </div>
        </div>

        <!-- Period buttons -->
        <div class="flex flex-wrap gap-2">
          @foreach (['today' => 'Hari Ini', 'week' => 'Minggu Ini', 'month' => 'Bulan Ini'] as $value => $label)
            <button type="submit"
                    name="period"
                    value="{{ $value }}"
                    onclick="document.getElementById('periodInput').value='{{ $value }}'"
                    class="px-4 py-1.5 rounded-lg text-sm font-medium transition-colors
                      {{ $period === $value ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
              {{ $label }}
            </button>
          @endforeach
        </div>
      </div>
      <input type="hidden"
             name="period"
             id="periodInput"
             value="{{ $period }}">
      @if ($selectedAreaId)
        <input type="hidden" name="area_id" value="{{ $selectedAreaId }}">
      @endif

      <!-- Mode Toggle -->
      <div class="flex flex-wrap gap-2 mb-5">
        <button type="submit"
                name="mode"
                value="individual"
                onclick="document.getElementById('modeInput').value='individual'"
                class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium border transition-colors
                  {{ $mode === 'individual' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-50' }}">
          <svg class="w-4 h-4"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
          </svg>
          Individual Waiter
        </button>
        <button type="submit"
                name="mode"
                value="all"
                onclick="document.getElementById('modeInput').value='all'"
                class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium border transition-colors
                  {{ $mode === 'all' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-50' }}">
          <svg class="w-4 h-4"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
          Semua Waiter
        </button>
      </div>

      <input type="hidden"
             name="mode"
             id="modeInput"
             value="{{ $mode }}">
      <input type="hidden"
             name="history_per_page"
             id="historyPerPageInput"
             value="{{ (int) request('history_per_page', 10) }}">

      @if ($mode === 'individual')
        <!-- Waiter Selector -->
        <div class="bg-white border border-slate-200 rounded-xl p-5 mb-5">
          <div class="flex items-center gap-3">
            <svg class="w-5 h-5 text-slate-400 flex-shrink-0"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <div class="flex-1">
              <label class="text-xs text-slate-500 block mb-1">Pilih Waiter</label>
              <select name="waiter_id"
                      onchange="this.form.submit()"
                      class="w-full border-0 bg-transparent text-sm text-slate-800 focus:outline-none cursor-pointer">
                @foreach ($waiters as $waiter)
                  <option value="{{ $waiter->id }}"
                          {{ $selectedWaiter && $selectedWaiter->id === $waiter->id ? 'selected' : '' }}>
                    {{ $waiter->name }}
                  </option>
                @endforeach
              </select>
            </div>
          </div>
        </div>

        @if ($selectedWaiter && $stats)
          <!-- Waiter Profile Card -->
          <div class="bg-gradient-to-r from-slate-50 to-slate-100 border border-slate-200 rounded-xl p-5 mb-5 flex items-center gap-4">
            <div class="w-14 h-14 bg-blue-600 rounded-full flex items-center justify-center flex-shrink-0">
              <span class="text-white text-2xl font-bold">{{ strtoupper(substr($selectedWaiter->name, 0, 1)) }}</span>
            </div>
            <div>
              <h2 class="text-lg font-bold text-slate-800">{{ $selectedWaiter->name }}</h2>
              <p class="text-sm text-slate-500">
                ID: {{ $selectedWaiter->internalUser?->accurate_id ? 'W' . str_pad($selectedWaiter->internalUser->accurate_id, 3, '0', STR_PAD_LEFT) : 'W' . str_pad($selectedWaiter->id, 3, '0', STR_PAD_LEFT) }}
                @if ($selectedWaiter->internalUser?->area)
                  · {{ $selectedWaiter->internalUser->area->name }}
                @endif
              </p>
            </div>
          </div>

          <!-- Stat Cards -->
          <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <div class="bg-blue-600 rounded-xl p-5 text-white">
              <div class="flex items-center gap-2 mb-3">
                <svg class="w-5 h-5 opacity-80"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <p class="text-sm text-blue-200 mb-1">Total Revenue Sesi</p>
              <p class="text-2xl font-bold">Rp {{ number_format($stats['sessionRevenue'], 0, ',', '.') }}</p>
              <p class="text-xs text-blue-300 mt-1">Termasuk min. charge</p>
            </div>

            <div class="bg-teal-600 rounded-xl p-5 text-white">
              <div class="flex items-center gap-2 mb-3">
                <svg class="w-5 h-5 opacity-80"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
              </div>
              <p class="text-sm text-teal-200 mb-1">Customer Ditangani</p>
              <p class="text-2xl font-bold">{{ $stats['customersHandled'] }}</p>
              <p class="text-xs text-teal-300 mt-1">{{ $stats['completedSessions'] }} selesai</p>
            </div>

            <div class="bg-purple-600 rounded-xl p-5 text-white">
              <div class="flex items-center gap-2 mb-3">
                <svg class="w-5 h-5 opacity-80"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
              </div>
              <p class="text-sm text-purple-200 mb-1">Total Order</p>
              <p class="text-2xl font-bold">{{ number_format($stats['totalTransactions']) }}</p>
              <p class="text-xs text-purple-300 mt-1">Rp {{ number_format($stats['totalOrderRevenue'], 0, ',', '.') }}</p>
            </div>

            <div class="bg-amber-500 rounded-xl p-5 text-white">
              <div class="flex items-center gap-2 mb-3">
                <svg class="w-5 h-5 opacity-80"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
              </div>
              <p class="text-sm text-amber-100 mb-1">Rata-rata / Customer</p>
              @php
                $avgPerCustomer = $stats['customersHandled'] > 0 ? $stats['sessionRevenue'] / $stats['customersHandled'] : 0;
              @endphp
              <p class="text-2xl font-bold">Rp {{ number_format($avgPerCustomer, 0, ',', '.') }}</p>
            </div>

            <div class="bg-indigo-600 rounded-xl p-5 text-white">
              <div class="flex items-center gap-2 mb-3">
                <svg class="w-5 h-5 opacity-80"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <p class="text-sm text-indigo-200 mb-1">Avg Durasi Sesi</p>
              @php
                $avgH = (int) floor($stats['avgDurationMinutes'] / 60);
                $avgM = $stats['avgDurationMinutes'] % 60;
              @endphp
              <p class="text-2xl font-bold">
                @if ($stats['avgDurationMinutes'] === 0)
                  —
                @elseif ($avgH > 0)
                  {{ $avgH }}j {{ $avgM }}m
                @else
                  {{ $avgM }}m
                @endif
              </p>
            </div>

            <div class="bg-green-600 rounded-xl p-5 text-white">
              <div class="flex items-center gap-2 mb-3">
                <svg class="w-5 h-5 opacity-80"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                </svg>
              </div>
              <p class="text-sm text-green-200 mb-1">Peringkat</p>
              <p class="text-2xl font-bold">#{{ $rank }}</p>
            </div>
          </div>

          <!-- Bottom: Top 5 & Recent Transactions -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <!-- Top 5 Produk -->
            <div class="bg-white border border-slate-200 rounded-xl p-5">
              <div class="flex items-center gap-2 mb-4">
                <svg class="w-4 h-4 text-green-500"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <h3 class="font-bold text-slate-800">Top 5 Produk</h3>
              </div>
              <p class="text-xs text-slate-500 mb-4">Produk terlaris periode ini</p>

              @if ($topProducts->isEmpty())
                <div class="flex flex-col items-center justify-center py-12 text-slate-400">
                  <svg class="w-12 h-12 mb-3 opacity-30"
                       fill="none"
                       stroke="currentColor"
                       viewBox="0 0 24 24">
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="1.5"
                          d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                  </svg>
                  <p class="text-sm">Belum ada data produk</p>
                </div>
              @else
                <div class="space-y-3">
                  @foreach ($topProducts as $i => $product)
                    <div class="flex items-center gap-3">
                      <span class="w-6 h-6 rounded-full bg-slate-100 text-slate-600 text-xs font-bold flex items-center justify-center flex-shrink-0">{{ $i + 1 }}</span>
                      <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-slate-800 truncate">{{ $product->item_name }}</p>
                        <p class="text-xs text-slate-500">{{ number_format($product->total_qty) }}x terjual</p>
                      </div>
                      <span class="text-sm font-semibold text-slate-700 flex-shrink-0">
                        Rp {{ number_format($product->total_revenue, 0, ',', '.') }}
                      </span>
                    </div>
                  @endforeach
                </div>
              @endif
            </div>

            <!-- Sesi Terakhir Ditangani -->
            <div class="bg-white border border-slate-200 rounded-xl p-5">
              <div class="flex items-center gap-2 mb-4">
                <svg class="w-4 h-4 text-blue-500"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3 class="font-bold text-slate-800">Sesi Terakhir Ditangani</h3>
              </div>
              <p class="text-xs text-slate-500 mb-4">10 sesi terbaru</p>

              @if ($recentSessions->isEmpty())
                <div class="flex flex-col items-center justify-center py-12 text-slate-400">
                  <svg class="w-12 h-12 mb-3 opacity-30"
                       fill="none"
                       stroke="currentColor"
                       viewBox="0 0 24 24">
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="1.5"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                  </svg>
                  <p class="text-sm">Belum ada sesi</p>
                </div>
              @else
                <div class="space-y-2">
                  @foreach ($recentSessions as $sess)
                    @php
                      $sessDuration = $sess->checked_in_at && $sess->checked_out_at ? abs(\Carbon\Carbon::parse($sess->checked_out_at)->diffInMinutes(\Carbon\Carbon::parse($sess->checked_in_at))) : null;
                      $sessDurationStr = $sessDuration !== null ? ($sessDuration >= 60 ? floor($sessDuration / 60) . 'j ' . $sessDuration % 60 . 'm' : $sessDuration . 'm') : null;

                      // Use finalized grand_total for paid, else compute live from orders_total
                      if ($sess->billing_status === 'paid' && $sess->grand_total) {
                          $displayTotal = (float) $sess->grand_total;
                      } elseif ($sess->orders_total !== null) {
                          $ot = (float) $sess->orders_total;
                          $displayTotal = $ot - (float) ($sess->discount_amount ?? 0);
                      } else {
                          $displayTotal = null;
                      }
                    @endphp
                    <div class="flex items-center justify-between py-2 border-b border-slate-100 last:border-0">
                      <div>
                        <p class="text-sm font-medium text-slate-800">{{ $sess->customer_name ?? 'Tamu' }}</p>
                        <p class="text-xs text-slate-500">
                          Meja {{ $sess->table_number }}
                          · {{ \Carbon\Carbon::parse($sess->checked_in_at)->setTimezone('Asia/Jakarta')->format('d M, H:i') }}
                          @if ($sessDurationStr)
                            · {{ $sessDurationStr }}
                          @endif
                        </p>
                      </div>
                      <div class="text-right">
                        <p class="text-sm font-semibold text-slate-800">
                          {{ $displayTotal !== null ? 'Rp ' . number_format($displayTotal, 0, ',', '.') : '—' }}
                        </p>
                        @php
                          $statusMap = [
                              'active' => ['bg-blue-100 text-blue-700', 'Aktif'],
                              'completed' => ['bg-green-100 text-green-700', 'Selesai'],
                              'pending' => ['bg-yellow-100 text-yellow-700', 'Pending'],
                          ];
                          [$badgeCls, $badgeLabel] = $statusMap[$sess->status] ?? ['bg-gray-100 text-gray-600', ucfirst($sess->status)];
                        @endphp
                        <span class="text-xs px-1.5 py-0.5 rounded-full {{ $badgeCls }}">{{ $badgeLabel }}</span>
                      </div>
                    </div>
                  @endforeach
                </div>
              @endif
            </div>
          </div>

          <div class="bg-white border border-slate-200 rounded-xl p-5 mt-5">
            <div class="flex items-center justify-between gap-3 mb-4">
              <div>
                <h3 class="font-bold text-slate-800">Riwayat Harian (End Day)</h3>
                <p class="text-xs text-slate-500 mt-0.5">Performa berdasarkan window operasional end day</p>
              </div>
              <div class="flex items-center gap-2">
                <label for="monthlyMonth"
                       class="text-xs text-slate-500">Bulan</label>
                <input id="monthlyMonth"
                       type="month"
                       name="month"
                       value="{{ request('month', now('Asia/Jakarta')->format('Y-m')) }}"
                       class="text-xs border border-slate-300 rounded-lg px-2 py-1 bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <button type="submit"
                        formaction="{{ route('admin.waiter-performance.monthly-history', ['waiter' => $selectedWaiter->id]) }}"
                        formmethod="GET"
                        class="text-xs font-medium px-3 py-1.5 rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 transition-colors">
                  Tarik Per Bulan
                </button>
                <label for="history_per_page"
                       class="text-xs text-slate-500">Rows</label>
                <select id="history_per_page"
                        onchange="document.getElementById('historyPerPageInput').value=this.value; document.getElementById('modeInput').value='individual'; document.getElementById('filterForm').submit();"
                        class="text-sm border border-slate-300 rounded-lg px-2 py-1 bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                  @foreach ([1, 5, 10, 20, 50] as $perPageOption)
                    <option value="{{ $perPageOption }}"
                            {{ (int) request('history_per_page', 10) === $perPageOption ? 'selected' : '' }}>
                      {{ $perPageOption }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>

            @if (count($dailyHistory ?? []) === 0)
              <div class="rounded-lg border border-dashed border-slate-200 p-8 text-center text-sm text-slate-400">
                Belum ada data history untuk waiter ini.
              </div>
            @else
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead class="bg-slate-50">
                    <tr>
                      <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">End Day</th>
                      <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Window</th>
                      <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Transaksi</th>
                      <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Revenue Sesi</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100"
                         x-data="{ openHistory: null }">
                    @foreach ($dailyHistory as $history)
                      <tr @click="openHistory = openHistory === {{ $loop->index }} ? null : {{ $loop->index }}"
                          class="hover:bg-slate-50 transition-colors cursor-pointer">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $history->end_day }}</td>
                        <td class="px-4 py-3 text-slate-600">
                          {{ $history->window_start->format('d M H:i') }} - {{ $history->window_end->format('d M H:i') }}
                        </td>
                        <td class="px-4 py-3 text-right text-slate-700">{{ number_format($history->total_transactions) }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-slate-800">
                          Rp {{ number_format($history->session_revenue, 0, ',', '.') }}
                          <span class="inline-flex ml-2 text-slate-400">
                            <svg class="w-4 h-4 transition-transform"
                                 fill="none"
                                 stroke="currentColor"
                                 viewBox="0 0 24 24"
                                 :class="openHistory === {{ $loop->index }} ? 'rotate-180' : ''">
                              <path stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M19 9l-7 7-7-7" />
                            </svg>
                          </span>
                        </td>
                      </tr>
                      <tr x-show="openHistory === {{ $loop->index }}"
                          x-cloak
                          class="bg-slate-50/70">
                        <td colspan="6"
                            class="px-4 py-4">
                          <div class="rounded-lg border border-slate-200 bg-white p-4"
                               x-data="{ openOrder: null }">
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Detail Order (Klik per Order)</p>

                            @if (($history->orders ?? collect())->isEmpty())
                              <p class="text-sm text-slate-400">Tidak ada order pada window ini.</p>
                            @else
                              <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                  <thead class="bg-slate-50">
                                    <tr>
                                      <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Order</th>
                                      <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Customer</th>
                                      <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Meja</th>
                                      <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Waktu</th>
                                      <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Total</th>
                                    </tr>
                                  </thead>
                                  <tbody class="divide-y divide-slate-100">
                                    @foreach ($history->orders as $order)
                                      @php
                                        $historySearchKey = $order->transaction_code ?: $order->order_number ?? 'ORD-' . $order->id;
                                        $historyBookingUrl = route('admin.bookings.index', [
                                            'tab' => 'history',
                                            'search' => $historySearchKey,
                                            'session_id' => $order->table_session_id,
                                        ]);
                                      @endphp
                                      <tr @click="openOrder = openOrder === {{ $loop->index }} ? null : {{ $loop->index }}"
                                          class="cursor-pointer hover:bg-slate-50 transition-colors">
                                        <td class="px-3 py-2 text-slate-800 font-medium">
                                          <a href="{{ $historyBookingUrl }}"
                                             @click.stop
                                             class="hover:text-blue-600 hover:underline transition-colors">
                                            {{ $order->order_number ?? '#' . $order->id }}
                                          </a>
                                        </td>
                                        <td class="px-3 py-2 text-slate-700">{{ $order->customer_name ?? 'Tamu' }}</td>
                                        <td class="px-3 py-2 text-slate-700">{{ $order->table_number ?? '-' }}</td>
                                        <td class="px-3 py-2 text-slate-700">{{ $order->ordered_at->format('d M Y H:i') }}</td>
                                        <td class="px-3 py-2 text-right text-slate-800 font-semibold">
                                          Rp {{ number_format($order->grand_total, 0, ',', '.') }}
                                          <span class="inline-flex ml-2 text-slate-400">
                                            <svg class="w-4 h-4 transition-transform"
                                                 fill="none"
                                                 stroke="currentColor"
                                                 viewBox="0 0 24 24"
                                                 :class="openOrder === {{ $loop->index }} ? 'rotate-180' : ''">
                                              <path stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M19 9l-7 7-7-7" />
                                            </svg>
                                          </span>
                                        </td>
                                      </tr>
                                      <tr x-show="openOrder === {{ $loop->index }}"
                                          x-cloak
                                          class="bg-slate-50/60">
                                        <td colspan="5"
                                            class="px-3 py-3">
                                          <div class="rounded-lg border border-slate-200 bg-white p-3"
                                               x-data="{ detailTab: 'billing' }">
                                            <div class="flex items-center justify-between gap-3 mb-3">
                                              <div class="flex items-center gap-2">
                                                <p class="text-xs text-slate-500">Reference</p>
                                                <p class="font-medium text-slate-800">{{ $order->reference_source }}</p>

                                                @if ($order->is_booking)
                                                  <span class="inline-flex items-center rounded-full bg-blue-100 text-blue-700 px-2 py-0.5 text-[11px] font-semibold">BOOKING</span>
                                                  <span class="inline-flex items-center rounded-full bg-emerald-100 text-emerald-700 px-2 py-0.5 text-[11px] font-semibold">BILLING</span>
                                                @elseif ($order->is_walk_in)
                                                  <span class="inline-flex items-center rounded-full bg-amber-100 text-amber-700 px-2 py-0.5 text-[11px] font-semibold">WALK-IN</span>
                                                  <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-700 px-2 py-0.5 text-[11px] font-semibold">RIWAYAT TRANSAKSI</span>
                                                @endif
                                              </div>

                                              @if ($order->is_booking)
                                                <div class="inline-flex rounded-lg border border-slate-200 overflow-hidden">
                                                  <button type="button"
                                                          @click="detailTab = 'billing'"
                                                          :class="detailTab === 'billing' ? 'bg-blue-600 text-white' : 'bg-white text-slate-700 hover:bg-slate-50'"
                                                          class="px-3 py-1.5 text-xs font-medium transition-colors">
                                                    Hasil Billing
                                                  </button>
                                                  <button type="button"
                                                          @click="detailTab = 'split'"
                                                          :class="detailTab === 'split' ? 'bg-blue-600 text-white' : 'bg-white text-slate-700 hover:bg-slate-50'"
                                                          class="px-3 py-1.5 text-xs font-medium border-l border-slate-200 transition-colors">
                                                    Pecahan Transaksi
                                                  </button>
                                                </div>
                                              @endif
                                            </div>

                                            <div x-show="detailTab === 'billing'"
                                                 class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm mb-3">
                                              <div>
                                                <p class="text-xs text-slate-500">Subtotal</p>
                                                <p class="font-medium text-slate-800">Rp {{ number_format($order->subtotal, 0, ',', '.') }}</p>
                                              </div>
                                              <div>
                                                <p class="text-xs text-slate-500">Diskon</p>
                                                <p class="font-medium text-slate-800">Rp {{ number_format($order->discount_amount, 0, ',', '.') }}</p>
                                              </div>
                                              @if ($order->down_payment_amount > 0)
                                                <div>
                                                  <p class="text-xs text-slate-500">DP</p>
                                                  <p class="font-medium text-slate-800">Rp {{ number_format($order->down_payment_amount, 0, ',', '.') }}</p>
                                                </div>
                                              @endif
                                              <div>
                                                <p class="text-xs text-slate-500">PPN</p>
                                                <p class="font-medium text-slate-800">Rp {{ number_format($order->tax, 0, ',', '.') }} @if ($order->tax_percentage > 0)
                                                    <span class="text-slate-500">({{ rtrim(rtrim(number_format($order->tax_percentage, 2, '.', ''), '0'), '.') }}%)</span>
                                                  @endif
                                                </p>
                                              </div>
                                              <div>
                                                <p class="text-xs text-slate-500">Service Charge</p>
                                                <p class="font-medium text-slate-800">Rp {{ number_format($order->service_charge, 0, ',', '.') }} @if ($order->service_charge_percentage > 0)
                                                    <span class="text-slate-500">({{ rtrim(rtrim(number_format($order->service_charge_percentage, 2, '.', ''), '0'), '.') }}%)</span>
                                                  @endif
                                                </p>
                                              </div>
                                              <div>
                                                <p class="text-xs text-slate-500">Payment Method</p>
                                                <p class="font-medium text-slate-800">{{ strtoupper((string) ($order->payment_method ?? '-')) }}</p>
                                              </div>
                                              <div>
                                                <p class="text-xs text-slate-500">Payment Mode</p>
                                                <p class="font-medium text-slate-800">{{ strtoupper((string) ($order->payment_mode ?? 'normal')) }}</p>
                                              </div>
                                              <div>
                                                <p class="text-xs text-slate-500">Reference Number</p>
                                                <p class="font-medium text-slate-800">{{ $order->payment_reference_number ?: '-' }}</p>
                                              </div>
                                              <div>
                                                <p class="text-xs text-slate-500">Order Number</p>
                                                <p class="font-medium text-slate-800">{{ $order->order_number ?? '#' . $order->id }}</p>
                                              </div>
                                              <div>
                                                <p class="text-xs text-slate-500">Grand Total</p>
                                                <p class="font-semibold text-slate-800">Rp {{ number_format($order->grand_total, 0, ',', '.') }}</p>
                                              </div>
                                              <div>
                                                <p class="text-xs text-slate-500">Paid Amount</p>
                                                <p class="font-semibold text-slate-800">Rp {{ number_format($order->paid_amount, 0, ',', '.') }}</p>
                                              </div>
                                            </div>

                                            <div x-show="detailTab === 'split'"
                                                 x-cloak
                                                 class="mb-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
                                              @if (($order->payment_mode ?? 'normal') === 'split')
                                                <div class="space-y-2">
                                                  @if ($order->split_cash_amount > 0)
                                                    <div class="flex items-center justify-between gap-3">
                                                      <span class="text-slate-600">Cash</span>
                                                      <span class="font-medium text-slate-800">Rp {{ number_format($order->split_cash_amount, 0, ',', '.') }}</span>
                                                    </div>
                                                  @endif

                                                  @if ($order->split_debit_amount > 0)
                                                    <div class="flex items-center justify-between gap-3">
                                                      <span class="text-slate-600">{{ strtoupper((string) ($order->split_non_cash_method ?: 'non-cash')) }}</span>
                                                      <span class="font-medium text-slate-800">Rp {{ number_format($order->split_debit_amount, 0, ',', '.') }}</span>
                                                    </div>
                                                    @if (!empty($order->split_non_cash_reference_number))
                                                      <p class="text-xs text-slate-500">Ref 1: {{ $order->split_non_cash_reference_number }}</p>
                                                    @endif
                                                  @endif

                                                  @if ($order->split_second_non_cash_amount > 0)
                                                    <div class="flex items-center justify-between gap-3">
                                                      <span class="text-slate-600">{{ strtoupper((string) ($order->split_second_non_cash_method ?: 'non-cash 2')) }}</span>
                                                      <span class="font-medium text-slate-800">Rp {{ number_format($order->split_second_non_cash_amount, 0, ',', '.') }}</span>
                                                    </div>
                                                    @if (!empty($order->split_second_non_cash_reference_number))
                                                      <p class="text-xs text-slate-500">Ref 2: {{ $order->split_second_non_cash_reference_number }}</p>
                                                    @endif
                                                  @endif

                                                  <div class="pt-1 mt-2 border-t border-slate-200 flex items-center justify-between gap-3">
                                                    <span class="text-slate-600">Total Paid</span>
                                                    <span class="font-semibold text-slate-800">Rp {{ number_format($order->paid_amount, 0, ',', '.') }}</span>
                                                  </div>
                                                </div>
                                              @else
                                                <div class="space-y-1">
                                                  <p class="text-slate-600">Tidak ada pecahan transaksi (payment mode normal).</p>
                                                  <p class="text-xs text-slate-500">Method: {{ strtoupper((string) ($order->payment_method ?? '-')) }}</p>
                                                  @if (!empty($order->payment_reference_number))
                                                    <p class="text-xs text-slate-500">Reference: {{ $order->payment_reference_number }}</p>
                                                  @endif
                                                </div>
                                              @endif
                                            </div>

                                            @if (($order->items ?? collect())->isEmpty())
                                              <p class="text-xs text-slate-400">Tidak ada item aktif pada order ini.</p>
                                            @else
                                              <div class="overflow-x-auto">
                                                <table class="w-full text-xs">
                                                  <thead class="bg-slate-50">
                                                    <tr>
                                                      <th class="px-2 py-1.5 text-left font-semibold text-slate-500 uppercase tracking-wider">Item</th>
                                                      <th class="px-2 py-1.5 text-right font-semibold text-slate-500 uppercase tracking-wider">Qty</th>
                                                      <th class="px-2 py-1.5 text-right font-semibold text-slate-500 uppercase tracking-wider">Subtotal</th>
                                                    </tr>
                                                  </thead>
                                                  <tbody class="divide-y divide-slate-100">
                                                    @foreach ($order->items as $item)
                                                      <tr>
                                                        <td class="px-2 py-1.5 text-slate-700">{{ $item->item_name }}</td>
                                                        <td class="px-2 py-1.5 text-right text-slate-700">{{ number_format((int) $item->quantity) }}</td>
                                                        <td class="px-2 py-1.5 text-right text-slate-800">Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
                                                      </tr>
                                                    @endforeach
                                                  </tbody>
                                                </table>
                                              </div>
                                            @endif
                                          </div>
                                        </td>
                                      </tr>
                                    @endforeach
                                  </tbody>
                                </table>
                              </div>
                            @endif
                          </div>
                        </td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>

              @if (method_exists($dailyHistory, 'links'))
                <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                  <p class="text-xs text-slate-500">
                    Menampilkan {{ $dailyHistory->firstItem() ?? 0 }}-{{ $dailyHistory->lastItem() ?? 0 }} dari {{ $dailyHistory->total() }} hari
                  </p>
                  @if ($dailyHistory->hasPages())
                    <nav class="inline-flex items-center gap-1"
                         role="navigation"
                         aria-label="Pagination">
                      @if ($dailyHistory->onFirstPage())
                        <span class="px-3 py-1.5 text-sm rounded-lg border border-slate-200 bg-slate-100 text-slate-400 cursor-not-allowed">Prev</span>
                      @else
                        <a href="{{ $dailyHistory->previousPageUrl() }}"
                           class="px-3 py-1.5 text-sm rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">Prev</a>
                      @endif

                      @foreach ($dailyHistory->getUrlRange(max(1, $dailyHistory->currentPage() - 1), min($dailyHistory->lastPage(), $dailyHistory->currentPage() + 1)) as $page => $url)
                        @if ($page == $dailyHistory->currentPage())
                          <span class="px-3 py-1.5 text-sm rounded-lg border border-blue-600 bg-blue-600 text-white">{{ $page }}</span>
                        @else
                          <a href="{{ $url }}"
                             class="px-3 py-1.5 text-sm rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">{{ $page }}</a>
                        @endif
                      @endforeach

                      @if ($dailyHistory->hasMorePages())
                        <a href="{{ $dailyHistory->nextPageUrl() }}"
                           class="px-3 py-1.5 text-sm rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">Next</a>
                      @else
                        <span class="px-3 py-1.5 text-sm rounded-lg border border-slate-200 bg-slate-100 text-slate-400 cursor-not-allowed">Next</span>
                      @endif
                    </nav>
                  @endif
                </div>
              @endif
            @endif
          </div>
        @elseif ($waiters->isEmpty())
          <div class="bg-white border border-slate-200 rounded-xl p-12 text-center text-slate-400">
            <svg class="w-12 h-12 mx-auto mb-3 opacity-30"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.5"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <p class="text-sm">Tidak ada waiter aktif ditemukan.</p>
          </div>
        @endif
      @else
        <!-- All Waiters Table -->
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
          <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
            <div>
              <h3 class="font-bold text-slate-800">Performa Semua Waiter</h3>
              <p class="text-sm text-slate-500 mt-0.5">
                {{ $periodLabel }}
              </p>
            </div>
            <div>
              <label class="text-xs text-slate-500 block mb-1">Rows per page</label>
              <select name="all_waiters_per_page"
                      onchange="this.form.submit()"
                      class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @foreach ([5, 10, 20, 50] as $perPageOption)
                  <option value="{{ $perPageOption }}"
                          {{ (int) request('all_waiters_per_page', 20) === $perPageOption ? 'selected' : '' }}>
                    {{ $perPageOption }}
                  </option>
                @endforeach
              </select>
            </div>
          </div>

          @if ($allWaitersStats->isEmpty())
            <div class="p-12 text-center text-slate-400">
              <p class="text-sm">Tidak ada data.</p>
            </div>
          @else
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead class="bg-slate-50">
                  <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider w-8">#</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Waiter</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Customer</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Order</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Avg / Customer</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Revenue Sesi</th>
                    <th class="px-5 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider w-24">Detail</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  @foreach ($allWaitersStats as $i => $ws)
                    @php
                      $rankNumber = method_exists($allWaitersStats, 'firstItem') ? ($allWaitersStats->firstItem() ?? 1) + $i : $i + 1;
                    @endphp
                    <tr class="hover:bg-slate-50 transition-colors">
                      <td class="px-5 py-4">
                        @if ($rankNumber === 1)
                          <span class="text-base">🥇</span>
                        @elseif ($rankNumber === 2)
                          <span class="text-base">🥈</span>
                        @elseif ($rankNumber === 3)
                          <span class="text-base">🥉</span>
                        @else
                          <span class="text-slate-500 font-medium">{{ $rankNumber }}</span>
                        @endif
                      </td>
                      <td class="px-5 py-4">
                        <div class="flex items-center gap-3">
                          <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center flex-shrink-0">
                            <span class="text-white text-xs font-bold">{{ strtoupper(substr($ws->user->name, 0, 1)) }}</span>
                          </div>
                          <div>
                            <p class="font-medium text-slate-800">{{ $ws->user->name }}</p>
                          </div>
                        </div>
                      </td>
                      <td class="px-5 py-4 text-right text-slate-700 font-medium">{{ number_format($ws->customersHandled) }}</td>
                      <td class="px-5 py-4 text-right text-slate-700">{{ number_format($ws->totalTransactions) }}</td>
                      <td class="px-5 py-4 text-right text-slate-700">Rp {{ number_format($ws->avgPerCustomer, 0, ',', '.') }}</td>
                      <td class="px-5 py-4 text-right font-semibold text-slate-800">Rp {{ number_format($ws->sessionRevenue, 0, ',', '.') }}</td>
                      <td class="px-5 py-4 text-center">
                        <a href="{{ route('admin.waiter-performance.index', ['mode' => 'individual', 'period' => $period, 'waiter_id' => $ws->user->id, 'history_per_page' => request('history_per_page', 10)]) }}"
                           class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                          Lihat →
                        </a>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>

            @if (method_exists($allWaitersStats, 'links'))
              <div class="mt-4 px-5 pb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <p class="text-xs text-slate-500">
                  Menampilkan {{ $allWaitersStats->firstItem() ?? 0 }}-{{ $allWaitersStats->lastItem() ?? 0 }} dari {{ $allWaitersStats->total() }} waiter
                </p>
                @if ($allWaitersStats->hasPages())
                  <nav class="inline-flex items-center gap-1"
                       role="navigation"
                       aria-label="Pagination">
                    @if ($allWaitersStats->onFirstPage())
                      <span class="px-3 py-1.5 text-sm rounded-lg border border-slate-200 bg-slate-100 text-slate-400 cursor-not-allowed">Prev</span>
                    @else
                      <a href="{{ $allWaitersStats->previousPageUrl() }}"
                         class="px-3 py-1.5 text-sm rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">Prev</a>
                    @endif

                    @foreach ($allWaitersStats->getUrlRange(max(1, $allWaitersStats->currentPage() - 1), min($allWaitersStats->lastPage(), $allWaitersStats->currentPage() + 1)) as $page => $url)
                      @if ($page == $allWaitersStats->currentPage())
                        <span class="px-3 py-1.5 text-sm rounded-lg border border-blue-600 bg-blue-600 text-white">{{ $page }}</span>
                      @else
                        <a href="{{ $url }}"
                           class="px-3 py-1.5 text-sm rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">{{ $page }}</a>
                      @endif
                    @endforeach

                    @if ($allWaitersStats->hasMorePages())
                      <a href="{{ $allWaitersStats->nextPageUrl() }}"
                         class="px-3 py-1.5 text-sm rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">Next</a>
                    @else
                      <span class="px-3 py-1.5 text-sm rounded-lg border border-slate-200 bg-slate-100 text-slate-400 cursor-not-allowed">Next</span>
                    @endif
                  </nav>
                @endif
              </div>
            @endif
          @endif
        </div>
      @endif

    </form>
  </div>
</x-app-layout>
