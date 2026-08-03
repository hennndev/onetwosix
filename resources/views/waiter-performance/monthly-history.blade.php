<x-app-layout title="Riwayat Performa Waiter">
  <div class="p-6 space-y-5">
    <div class="bg-white border border-slate-200 rounded-xl p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Riwayat Bulanan Waiter</h1>
        <p class="text-sm text-slate-500 mt-0.5">
          {{ $waiter->name }} · {{ $monthLabel }}
        </p>
      </div>
      <a href="{{ route('admin.waiter-performance.index', ['mode' => 'individual', 'waiter_id' => $waiter->id, 'period' => 'month']) }}"
         class="inline-flex items-center justify-center px-4 py-2 rounded-lg text-sm font-medium bg-slate-100 text-slate-700 hover:bg-slate-200 transition-colors">
        Kembali
      </a>
    </div>

    <form method="GET"
          action="{{ route('admin.waiter-performance.monthly-history', $waiter) }}"
          class="bg-white border border-slate-200 rounded-xl p-5 flex flex-col sm:flex-row sm:items-end gap-3">
      <div>
        <label for="month"
               class="text-xs text-slate-500 block mb-1">Bulan</label>
        <input id="month"
               type="month"
               name="month"
               value="{{ $monthInput }}"
               class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
      </div>
      <div>
        <label for="days"
               class="text-xs text-slate-500 block mb-1">Tarik Hari (default 31)</label>
        <input id="days"
               type="number"
               min="1"
               max="31"
               name="days"
               value="{{ $days }}"
               class="w-28 px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
      </div>
      <button type="submit"
              class="px-4 py-2 rounded-lg text-sm font-medium bg-blue-600 text-white hover:bg-blue-700 transition-colors">
        Tarik Data
      </button>
    </form>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      <div class="bg-white border border-slate-200 rounded-xl p-4">
        <p class="text-xs text-slate-500">Hari Berisi Data</p>
        <p class="mt-1 text-xl font-bold text-slate-800">{{ number_format($summary['days']) }}</p>
      </div>
      <div class="bg-white border border-slate-200 rounded-xl p-4">
        <p class="text-xs text-slate-500">Total Transaksi</p>
        <p class="mt-1 text-xl font-bold text-slate-800">{{ number_format($summary['transactions']) }}</p>
      </div>
      <div class="bg-white border border-slate-200 rounded-xl p-4">
        <p class="text-xs text-slate-500">Customer Ditangani</p>
        <p class="mt-1 text-xl font-bold text-slate-800">{{ number_format($summary['customers']) }}</p>
      </div>
      <div class="bg-white border border-slate-200 rounded-xl p-4">
        <p class="text-xs text-slate-500">Revenue Sesi</p>
        <p class="mt-1 text-xl font-bold text-slate-800">Rp {{ number_format($summary['revenue'], 0, ',', '.') }}</p>
      </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-5">
      <div class="flex items-center justify-between mb-4">
        <h3 class="font-bold text-slate-800">Daftar Riwayat Harian</h3>
        <span class="text-xs text-slate-500">Window operasional End Day</span>
      </div>

      @if ($monthlyHistory->isEmpty())
        <div class="rounded-lg border border-dashed border-slate-200 p-8 text-center text-sm text-slate-400">
          Tidak ada riwayat pada periode ini.
        </div>
      @else
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">End Day</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Window Operasional</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Customer</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Transaksi</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Revenue Sesi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100"
                   x-data="{ openHistory: null }">
              @foreach ($monthlyHistory as $history)
                <tr @click="openHistory = openHistory === {{ $loop->index }} ? null : {{ $loop->index }}"
                    class="hover:bg-slate-50 transition-colors cursor-pointer">
                  <td class="px-4 py-3 font-medium text-slate-800">{{ $history->end_day }}</td>
                  <td class="px-4 py-3 text-slate-600">{{ $history->window_start->format('H:i') }} - {{ $history->window_end->format('H:i') }}</td>
                  <td class="px-4 py-3 text-right text-slate-700">{{ number_format($history->customers_handled) }}</td>
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
                  <td colspan="5"
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
      @endif
    </div>
  </div>
</x-app-layout>
