<x-app-layout title="Recap & End Day">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
      Rekapan End Day
    </h2>
  </x-slot>

  <div class="py-6"
       x-data="recapPage({ billingTransactions: @js($todayBillingTransactions), walkInTransactions: @js($todayWalkInTransactions) })">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
      @if (session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
          {{ session('error') }}
        </div>
      @endif

      @if (session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
          {{ session('success') }}
        </div>
      @endif

      <!-- Area Tabs (multi-area header) -->
      @if (($areas ?? collect())->count() > 1 && (! session('active_area_id') || session('active_area_id') === 'all'))
        @php
          $baseQuery = request()->except('area_id');
        @endphp
        <div class="flex gap-2 overflow-x-auto pb-2">
          <a href="{{ route('admin.recap.index', $baseQuery) }}"
             class="px-4 py-2 rounded-lg whitespace-nowrap transition {{ is_null($selectedAreaId) ? 'bg-slate-800 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
            Semua Area
          </a>
          @foreach ($areas as $area)
            <a href="{{ route('admin.recap.index', array_merge($baseQuery, ['area_id' => $area->id])) }}"
               class="px-4 py-2 rounded-lg whitespace-nowrap transition {{ $selectedAreaId === $area->id ? 'bg-slate-800 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
              {{ $area->name }}
            </a>
          @endforeach
        </div>
      @endif

      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <h3 class="text-base font-semibold text-gray-900">Rekap End Day</h3>
            <p class="text-sm text-gray-500 mt-1">Pantau recap hari ini dan lihat history closing otomatis dari dashboard.</p>
          </div>

          <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
            <a href="{{ route('admin.recap.close-preview', array_merge(['start_datetime' => $selectedStartDatetime, 'end_datetime' => $selectedEndDatetime], $selectedAreaId ? ['area_id' => $selectedAreaId] : [])) }}"
               target="_blank"
               rel="noopener noreferrer"
               class="inline-flex items-center justify-center px-4 py-2 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 text-sm font-medium min-h-[42px] whitespace-nowrap">
              Preview Print Struk
            </a>
          </div>
        </div>

        <div class="flex flex-col gap-4">
          <div class="flex border-b border-gray-200 gap-2 overflow-x-auto">
            <button type="button"
                    @click="activeTab = 'recap'"
                    :class="activeTab === 'recap' ? '-mb-px border-b-2 border-slate-800 text-slate-900 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                    class="px-5 py-3 text-sm transition whitespace-nowrap">
              Recap
            </button>
            <button type="button"
                    @click="activeTab = 'history'"
                    :class="activeTab === 'history' ? '-mb-px border-b-2 border-slate-800 text-slate-900 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                    class="px-5 py-3 text-sm transition whitespace-nowrap">
              History
            </button>
            <button type="button"
                    @click="activeTab = 'transactions-recap-today'"
                    :class="activeTab === 'transactions-recap-today' ? '-mb-px border-b-2 border-slate-800 text-slate-900 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                    class="px-5 py-3 text-sm transition whitespace-nowrap">
              Transactions Recap Hari Ini
            </button>
          </div>
        </div>
      </div>

      <div x-show="activeTab === 'recap'"
           class="space-y-6">
        <!-- Realtime summary cards (polled) -->
        @php
          $recapLiveQuery = array_merge(request()->only(['date', 'start_datetime', 'end_datetime', 'area_id']));
        @endphp
        <div id="recapSummary"
             x-data="realtimePoll({ url: '{{ route('admin.recap.index', $recapLiveQuery) }}', target: 'recapSummary', interval: 30000 })">
          @include('recap._partials.summary')
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-gray-900">Preview Dashboard (Akumulasi)</h3>
            <span class="text-xs text-gray-500">Semua transaksi booking + walk-in</span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="p-4 border border-gray-200 rounded-lg bg-lime-50">
              <p class="text-sm font-medium text-lime-700">Total Food</p>
              <p class="text-2xl font-bold text-lime-800 mt-1">Rp {{ number_format($dashboardPreview['total_food'] ?? 0, 0, ',', '.') }}</p>
            </div>

            <div class="p-4 border border-gray-200 rounded-lg bg-yellow-50">
              <p class="text-sm font-medium text-yellow-700">Total Alcohol</p>
              <p class="text-2xl font-bold text-yellow-800 mt-1">Rp {{ number_format($dashboardPreview['total_alcohol'] ?? 0, 0, ',', '.') }}</p>
            </div>

            <div class="p-4 border border-gray-200 rounded-lg bg-teal-50">
              <p class="text-sm font-medium text-teal-700">Total Beverage</p>
              <p class="text-2xl font-bold text-teal-800 mt-1">Rp {{ number_format($dashboardPreview['total_beverage'] ?? 0, 0, ',', '.') }}</p>
            </div>

            <div class="p-4 border border-gray-200 rounded-lg bg-fuchsia-50">
              <p class="text-sm font-medium text-fuchsia-700">Total Cigarette</p>
              <p class="text-2xl font-bold text-fuchsia-800 mt-1">Rp {{ number_format($dashboardPreview['total_cigarette'] ?? 0, 0, ',', '.') }}</p>
            </div>

            <div class="p-4 border border-gray-200 rounded-lg bg-red-50">
              <p class="text-sm font-medium text-red-700">Total Breakage</p>
              <p class="text-2xl font-bold text-red-800 mt-1">Rp {{ number_format($dashboardPreview['total_breakage'] ?? 0, 0, ',', '.') }}</p>
            </div>

            <div class="p-4 border border-gray-200 rounded-lg bg-blue-50">
              <p class="text-sm font-medium text-blue-700">Total Room</p>
              <p class="text-2xl font-bold text-blue-800 mt-1">Rp {{ number_format($dashboardPreview['total_room'] ?? 0, 0, ',', '.') }}</p>
            </div>

            <div class="p-4 border border-gray-200 rounded-lg bg-cyan-50">
              <p class="text-sm font-medium text-cyan-700">Total Staff Meal</p>
              <p class="text-2xl font-bold text-cyan-800 mt-1">Rp {{ number_format($dashboardPreview['total_staff_meal'] ?? 0, 0, ',', '.') }}</p>
            </div>

            <div class="p-4 border border-gray-200 rounded-lg bg-sky-50">
              <p class="text-sm font-medium text-sky-700">Total Compliment</p>
              <p class="text-2xl font-bold text-sky-800 mt-1">Rp {{ number_format($dashboardPreview['total_compliment_amount'] ?? 0, 0, ',', '.') }}</p>
              <p class="text-xs font-medium text-sky-600 mt-2">Qty {{ number_format($dashboardPreview['total_compliment_quantity'] ?? 0, 0, ',', '.') }}</p>
            </div>

            <div class="p-4 border border-gray-200 rounded-lg bg-indigo-50">
              <p class="text-sm font-medium text-indigo-700">Total FOC</p>
              <p class="text-2xl font-bold text-indigo-800 mt-1">Rp {{ number_format($dashboardPreview['total_foc_amount'] ?? 0, 0, ',', '.') }}</p>
              <p class="text-xs font-medium text-indigo-600 mt-2">Qty {{ number_format($dashboardPreview['total_foc_quantity'] ?? 0, 0, ',', '.') }}</p>
            </div>

            <div class="p-4 border border-gray-200 rounded-lg bg-purple-50">
              <p class="text-sm font-medium text-purple-700">Total LD</p>
              <p class="text-2xl font-bold text-purple-800 mt-1">Rp {{ number_format($dashboardPreview['total_ld'] ?? 0, 0, ',', '.') }}</p>
              <p class="text-xs font-medium text-purple-600 mt-2">Qty {{ number_format($dashboardPreview['total_ld_quantity'] ?? 0, 0, ',', '.') }}</p>
            </div>

            <div class="p-4 border border-gray-200 rounded-lg bg-gray-50">
              <p class="text-sm font-medium text-gray-500">Total Transaksi</p>
              <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($dashboardPreview['total_transactions'] ?? 0, 0, ',', '.') }}</p>
            </div>

            <div class="p-4 border border-gray-200 rounded-lg bg-emerald-50">
              <p class="text-sm font-medium text-emerald-700">Gross Sales (Included DP)</p>
              <p class="text-2xl font-bold text-emerald-800 mt-1">Rp {{ number_format($dashboardPreview['gross_sales'] ?? 0, 0, ',', '.') }}</p>
            </div>

            <div class="p-4 border border-gray-200 rounded-lg bg-slate-50">
              <p class="text-sm font-medium text-slate-700">Net Sales (Included DP)</p>
              <p class="text-2xl font-bold text-slate-800 mt-1">Rp {{ number_format($dashboardPreview['net_sales'] ?? 0, 0, ',', '.') }}</p>
            </div>

            <div class="p-4 border border-gray-200 rounded-lg bg-rose-50">
              <p class="text-sm font-medium text-rose-700">Total Penjualan Rokok (Qty)</p>
              <p class="text-2xl font-bold text-rose-800 mt-1">{{ number_format($dashboardPreview['total_penjualan_rokok'] ?? 0, 0, ',', '.') }}</p>
            </div>

            <div class="p-4 border border-gray-200 rounded-lg bg-amber-50">
              <p class="text-sm font-medium text-amber-700">Total Pajak</p>
              <p class="text-2xl font-bold text-amber-800 mt-1">Rp {{ number_format($dashboardPreview['total_tax'] ?? 0, 0, ',', '.') }}</p>
            </div>

            <div class="p-4 border border-gray-200 rounded-lg bg-orange-50">
              <p class="text-sm font-medium text-orange-700">Total Service Charge</p>
              <p class="text-2xl font-bold text-orange-800 mt-1">Rp {{ number_format($dashboardPreview['total_service_charge'] ?? 0, 0, ',', '.') }}</p>
            </div>

            <div class="p-4 border border-gray-200 rounded-lg bg-cyan-50">
              <p class="text-sm font-medium text-cyan-700">Total DP <span class="text-xs font-normal">(booking)</span></p>
              <p class="text-2xl font-bold text-cyan-800 mt-1">Rp {{ number_format($dashboardPreview['total_down_payment'] ?? 0, 0, ',', '.') }}</p>
            </div>

            <div class="p-4 border border-gray-200 rounded-lg bg-gray-50">
              <p class="text-sm font-medium text-gray-500">Total Tunai</p>
              <p class="text-2xl font-bold text-gray-900 mt-1">Rp {{ number_format($dashboardPreview['total_cash'] ?? 0, 0, ',', '.') }}</p>
            </div>

            <div class="p-4 border border-gray-200 rounded-lg bg-blue-50">
              <p class="text-sm font-medium text-blue-700">Total Transfer</p>
              <p class="text-2xl font-bold text-blue-800 mt-1">Rp {{ number_format($dashboardPreview['total_transfer'] ?? 0, 0, ',', '.') }}</p>
            </div>

            <div class="p-4 border border-gray-200 rounded-lg bg-indigo-50">
              <p class="text-sm font-medium text-indigo-700">Total Debit</p>
              <p class="text-2xl font-bold text-indigo-800 mt-1">Rp {{ number_format($dashboardPreview['total_debit'] ?? 0, 0, ',', '.') }}</p>
            </div>

            <div class="p-4 border border-gray-200 rounded-lg bg-violet-50">
              <p class="text-sm font-medium text-violet-700">Total Kredit</p>
              <p class="text-2xl font-bold text-violet-800 mt-1">Rp {{ number_format($dashboardPreview['total_kredit'] ?? 0, 0, ',', '.') }}</p>
            </div>

            <div class="p-4 border border-gray-200 rounded-lg bg-emerald-50">
              <p class="text-sm font-medium text-emerald-700">Total QRIS</p>
              <p class="text-2xl font-bold text-emerald-800 mt-1">Rp {{ number_format($dashboardPreview['total_qris'] ?? 0, 0, ',', '.') }}</p>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-500">Total Pajak</p>
            <p class="text-2xl font-bold text-amber-700 mt-1">Rp {{ number_format($totalTax, 0, ',', '.') }}</p>
          </div>

          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-500">Total Service Charge</p>
            <p class="text-2xl font-bold text-orange-700 mt-1">Rp {{ number_format($totalServiceCharge, 0, ',', '.') }}</p>
          </div>

          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-500">Total Pembayaran Tunai</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">Rp {{ number_format($paymentMethodTotals['cash'] ?? 0, 0, ',', '.') }}</p>
          </div>

          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-500">Total Pembayaran Transfer</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">Rp {{ number_format($paymentMethodTotals['transfer'] ?? 0, 0, ',', '.') }}</p>
          </div>

          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-500">Total Pembayaran Debit</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">Rp {{ number_format($paymentMethodTotals['debit'] ?? 0, 0, ',', '.') }}</p>
          </div>

          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-500">Total Pembayaran Kredit</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">Rp {{ number_format($paymentMethodTotals['kredit'] ?? 0, 0, ',', '.') }}</p>
          </div>

          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-500">Total Pembayaran QRIS</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">Rp {{ number_format($paymentMethodTotals['qris'] ?? 0, 0, ',', '.') }}</p>
          </div>

        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
          <div class="px-4 py-3 border-b border-gray-200">
            <h3 class="font-semibold text-gray-900">Kasir (Harga Ditampilkan)</h3>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tanggal & Jam</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">No. Transaksi</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Customer</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Metode Pembayaran</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">No. Referensi</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Detail Item</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Qty Item</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Total</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-100">
                @forelse ($cashierTransactions as $transaction)
                  <tr>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ $transaction['datetime'] }}</td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $transaction['order_number'] }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ $transaction['customer_name'] }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ $transaction['payment_method'] }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ $transaction['payment_reference_number'] ?: '-' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">
                      <details class="group">
                        <summary class="cursor-pointer text-slate-700 hover:text-slate-900 font-medium">Lihat Item</summary>
                        <div class="mt-2 space-y-2 rounded-md border border-gray-200 bg-gray-50 p-2">
                          @forelse ($transaction['items'] as $orderItem)
                            <div class="rounded border border-gray-200 bg-white p-2">
                              <p class="text-xs text-gray-800">
                                <span class="font-semibold">{{ $orderItem['quantity'] }}x</span>
                                {{ $orderItem['name'] }}
                              </p>
                              <p class="mt-1 text-[11px] text-gray-600">Harga: Rp {{ number_format($orderItem['price'], 0, ',', '.') }}</p>
                              <p class="text-[11px] text-gray-600">Subtotal: Rp {{ number_format($orderItem['subtotal'], 0, ',', '.') }}</p>
                              @if (($orderItem['tax_amount'] ?? 0) > 0)
                                <p class="text-[11px] text-amber-700">PB1: Rp {{ number_format($orderItem['tax_amount'], 0, ',', '.') }}</p>
                              @endif
                              @if (($orderItem['service_charge_amount'] ?? 0) > 0)
                                <p class="text-[11px] text-orange-700">Service: Rp {{ number_format($orderItem['service_charge_amount'], 0, ',', '.') }}</p>
                              @endif
                            </div>
                          @empty
                            <p class="text-xs text-gray-500">Tidak ada item.</p>
                          @endforelse

                          <div class="rounded border border-gray-200 bg-white p-2 space-y-1 text-[11px]">
                            <div class="flex items-center justify-between text-gray-700">
                              <span>Total Bill</span>
                              <span>Rp {{ number_format($transaction['total_bill'] ?? 0, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex items-center justify-between text-amber-700">
                              <span>PB1</span>
                              <span>Rp {{ number_format($transaction['tax_total'] ?? 0, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex items-center justify-between text-orange-700">
                              <span>Service Charge</span>
                              <span>Rp {{ number_format($transaction['service_charge_total'] ?? 0, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex items-center justify-between text-gray-700 font-medium border-t border-dashed border-gray-200 pt-1">
                              <span>Sub Total</span>
                              <span>Rp {{ number_format($transaction['sub_total'] ?? 0, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex items-center justify-between text-rose-700">
                              <span>Diskon</span>
                              <span>- Rp {{ number_format($transaction['discount_amount'] ?? 0, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex items-center justify-between text-indigo-700">
                              <span>DP</span>
                              <span>Rp {{ number_format($transaction['down_payment_amount'] ?? 0, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex items-center justify-between text-gray-900 font-semibold border-t border-dashed border-gray-200 pt-1">
                              <span>Sisa Bayar</span>
                              <span>Rp {{ number_format($transaction['total'] ?? 0, 0, ',', '.') }}</span>
                            </div>
                          </div>
                        </div>
                      </details>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700 text-right">{{ $transaction['items_count'] }}</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900 text-right">Rp {{ number_format($transaction['total'], 0, ',', '.') }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="8"
                        class="px-4 py-6 text-sm text-center text-gray-500">Tidak ada transaksi kasir pada tanggal ini.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>


        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200">
              <h3 class="font-semibold text-gray-900">Item Keluar Kitchen</h3>
            </div>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tanggal & Jam</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Order</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Item</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Qty</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                  @forelse ($kitchenItems as $item)
                    <tr>
                      <td class="px-4 py-3 text-sm text-gray-700">{{ $item['datetime'] }}</td>
                      <td class="px-4 py-3 text-sm text-gray-700">{{ $item['order_number'] }}</td>
                      <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $item['item_name'] }}</td>
                      <td class="px-4 py-3 text-sm text-gray-700 text-right">{{ $item['qty'] }}</td>
                    </tr>
                  @empty
                    <tr>
                      <td colspan="4"
                          class="px-4 py-6 text-sm text-center text-gray-500">Tidak ada item kitchen pada tanggal ini.</td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200">
              <h3 class="font-semibold text-gray-900">Item FOC/Compliment Keluar</h3>
            </div>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Item</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Qty</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                  @forelse ($focItems as $item)
                    <tr>
                      <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $item['name'] }}</td>
                      <td class="px-4 py-3 text-sm text-gray-700 text-right">{{ $item['quantity'] }}</td>
                    </tr>
                  @empty
                    <tr>
                      <td colspan="2"
                          class="px-4 py-6 text-sm text-center text-gray-500">Tidak ada item FOC/Compliment pada tanggal ini.</td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200">
              <h3 class="font-semibold text-gray-900">Item Keluar Bar</h3>
            </div>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tanggal & Jam</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Order</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Item</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Qty</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                  @forelse ($barItems as $item)
                    <tr>
                      <td class="px-4 py-3 text-sm text-gray-700">{{ $item['datetime'] }}</td>
                      <td class="px-4 py-3 text-sm text-gray-700">{{ $item['order_number'] }}</td>
                      <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $item['item_name'] }}</td>
                      <td class="px-4 py-3 text-sm text-gray-700 text-right">{{ $item['qty'] }}</td>
                    </tr>
                  @empty
                    <tr>
                      <td colspan="4"
                          class="px-4 py-6 text-sm text-center text-gray-500">Tidak ada item bar pada tanggal ini.</td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div x-show="activeTab === 'transactions-recap-today'"
           class="space-y-4">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h3 class="text-base font-semibold text-gray-900">Transactions Recap Hari Ini</h3>
              <p class="text-sm text-gray-500 mt-1">Klik row untuk melihat detail transaksi.</p>
            </div>
          </div>

          <div class="flex border-b border-gray-200 gap-2 mb-4">
            <button type="button"
                    @click="transactionRecapTab = 'billing'"
                    :class="transactionRecapTab === 'billing' ? '-mb-px border-b-2 border-slate-800 text-slate-900 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                    class="px-5 py-3 text-sm transition whitespace-nowrap">
              Dari Billing
            </button>
            <button type="button"
                    @click="transactionRecapTab = 'walkin'"
                    :class="transactionRecapTab === 'walkin' ? '-mb-px border-b-2 border-slate-800 text-slate-900 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                    class="px-5 py-3 text-sm transition whitespace-nowrap">
              Dari Walk-in
            </button>
          </div>

          <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-3">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">Filter Payment</label>
                <select x-model="transactionFilters[transactionRecapTab].payment"
                        class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-400">
                  <option value="all">Semua Payment</option>
                  <option value="cash">Tunai</option>
                  <option value="transfer">Transfer</option>
                  <option value="debit">Debit</option>
                  <option value="kredit">Kredit</option>
                  <option value="qris">QRIS</option>
                  <option value="split">Split Bill</option>
                </select>
              </div>
              <div class="flex items-end justify-start md:justify-end">
                <button type="button"
                        @click="resetTransactionFilters(transactionRecapTab)"
                        class="inline-flex items-center rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100">
                  Reset Filter
                </button>
              </div>
            </div>

            <div class="space-y-2">
              <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Filter Include Item</p>
              <div class="flex flex-wrap gap-2">
                <template x-for="category in filterCategories"
                          :key="category.key">
                  <button type="button"
                          @click="toggleTransactionCategory(transactionRecapTab, category.key)"
                          :class="isCategorySelected(transactionRecapTab, category.key) ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-100'"
                          class="rounded-full border px-3 py-1.5 text-xs font-medium transition"
                          x-text="category.label"></button>
                </template>
              </div>
            </div>
          </div>

          <div x-show="transactionRecapTab === 'billing'"
               class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tanggal & Jam</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">No. Transaksi</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Customer</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Metode Pembayaran</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Qty Item</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Total</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-100">
                <template x-for="transaction in filteredTransactions('billing')"
                          :key="transaction.transaction_number + '-' + transaction.datetime">
                  <tr @click="selectedTransaction = transaction; showTransactionModal = true"
                      class="cursor-pointer hover:bg-gray-50 transition">
                    <td class="px-4 py-3 text-sm text-gray-700"
                        x-text="transaction.datetime"></td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-900"
                        x-text="transaction.transaction_number"></td>
                    <td class="px-4 py-3 text-sm text-gray-700"
                        x-text="transaction.customer_name"></td>
                    <td class="px-4 py-3 text-sm text-gray-700"
                        x-text="transaction.payment_method"></td>
                    <td class="px-4 py-3 text-sm text-gray-700 text-right"
                        x-text="transaction.items_count"></td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900 text-right"
                        x-text="formatCurrency(transaction.total)"></td>
                  </tr>
                </template>
                <tr x-show="filteredTransactions('billing').length === 0">
                  <td colspan="6"
                      class="px-4 py-6 text-sm text-center text-gray-500">Belum ada transaksi billing dengan filter ini.</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div x-show="transactionRecapTab === 'walkin'"
               class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tanggal & Jam</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">No. Transaksi</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Customer</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Metode Pembayaran</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Qty Item</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Total</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-100">
                <template x-for="transaction in filteredTransactions('walkin')"
                          :key="transaction.transaction_number + '-' + transaction.datetime">
                  <tr @click="selectedTransaction = transaction; showTransactionModal = true"
                      class="cursor-pointer hover:bg-gray-50 transition">
                    <td class="px-4 py-3 text-sm text-gray-700"
                        x-text="transaction.datetime"></td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-900"
                        x-text="transaction.transaction_number"></td>
                    <td class="px-4 py-3 text-sm text-gray-700"
                        x-text="transaction.customer_name"></td>
                    <td class="px-4 py-3 text-sm text-gray-700"
                        x-text="transaction.payment_method"></td>
                    <td class="px-4 py-3 text-sm text-gray-700 text-right"
                        x-text="transaction.items_count"></td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900 text-right"
                        x-text="formatCurrency(transaction.total)"></td>
                  </tr>
                </template>
                <tr x-show="filteredTransactions('walkin').length === 0">
                  <td colspan="6"
                      class="px-4 py-6 text-sm text-center text-gray-500">Belum ada transaksi walk-in dengan filter ini.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div x-show="activeTab === 'history'"
           class="space-y-4">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h3 class="text-base font-semibold text-gray-900">History Closing</h3>
              <p class="text-sm text-gray-500 mt-1">List snapshot dashboard yang otomatis tersimpan setiap jam 12 malam.</p>
            </div>
          </div>

          <div class="space-y-3">
            @forelse ($recapHistories as $history)
              @php
                $historyPayload = [
                    'export_url' => route('admin.recap.history.export', $history),
                    'transactions_url' => route('admin.recap.history.transactions', $history),
                    'reprint_url' => route('admin.recap.history.reprint', $history),
                    'end_day' => $history->end_day?->format('d/m/Y') ?? '-',
                    'last_synced_at' => $history->last_synced_at?->format('d/m/Y H:i') ?? '-',
                    'total_transactions' => number_format($history->total_transactions, 0, ',', '.'),
                    'total_amount_raw' => (float) ($history->total_amount ?? 0),
                    'total_tax_raw' => (float) ($history->total_tax ?? 0),
                    'total_service_charge_raw' => (float) ($history->total_service_charge ?? 0),
                    'total_dp_raw' => (float) ($history->total_dp ?? 0),
                    'total_kitchen_items' => number_format($history->total_kitchen_items, 0, ',', '.'),
                    'total_bar_items' => number_format($history->total_bar_items, 0, ',', '.'),
                    'total_amount' => 'Rp ' . number_format($history->total_amount, 0, ',', '.'),
                    'total_food' => 'Rp ' . number_format($history->total_food ?? 0, 0, ',', '.'),
                    'total_alcohol' => 'Rp ' . number_format($history->total_alcohol ?? 0, 0, ',', '.'),
                    'total_beverage' => 'Rp ' . number_format($history->total_beverage ?? 0, 0, ',', '.'),
                    'total_cigarette' => 'Rp ' . number_format($history->total_cigarette ?? 0, 0, ',', '.'),
                    'total_breakage' => 'Rp ' . number_format($history->total_breakage ?? 0, 0, ',', '.'),
                    'total_room' => 'Rp ' . number_format($history->total_room ?? 0, 0, ',', '.'),
                    'total_ld' => 'Rp ' . number_format($history->total_ld ?? 0, 0, ',', '.'),
                    'total_compliment_amount' => 'Rp ' . number_format($history->total_compliment_amount ?? 0, 0, ',', '.'),
                    'total_foc_amount' => 'Rp ' . number_format($history->total_foc_amount ?? 0, 0, ',', '.'),
                    'gross_sales' => 'Rp ' . number_format(($history->total_amount ?? 0) + ($history->total_dp ?? 0), 0, ',', '.'),
                    'net_sales' => 'Rp ' . number_format(max(0, ($history->total_amount ?? 0) + ($history->total_dp ?? 0) - ($history->total_tax ?? 0) - ($history->total_service_charge ?? 0)), 0, ',', '.'),
                    'total_compliment_quantity' => number_format($history->total_compliment_quantity ?? 0, 0, ',', '.'),
                    'total_foc_quantity' => number_format($history->total_foc_quantity ?? 0, 0, ',', '.'),
                    'total_ld_quantity' => number_format($history->total_ld_quantity ?? 0, 0, ',', '.'),
                    'total_penjualan_rokok' => number_format($history->total_penjualan_rokok, 0, ',', '.'),
                    'total_tax' => 'Rp ' . number_format($history->total_tax, 0, ',', '.'),
                    'total_service_charge' => 'Rp ' . number_format($history->total_service_charge, 0, ',', '.'),
                    'total_dp' => 'Rp ' . number_format($history->total_dp ?? 0, 0, ',', '.'),
                    'total_cash' => 'Rp ' . number_format($history->total_cash, 0, ',', '.'),
                    'total_transfer' => 'Rp ' . number_format($history->total_transfer, 0, ',', '.'),
                    'total_debit' => 'Rp ' . number_format($history->total_debit, 0, ',', '.'),
                    'total_kredit' => 'Rp ' . number_format($history->total_kredit, 0, ',', '.'),
                    'total_qris' => 'Rp ' . number_format($history->total_qris, 0, ',', '.'),
                ];
              @endphp

              <button type="button"
                      @click="loadHistoryTransactions({{ \Illuminate\Support\Js::from($historyPayload) }})"
                      class="w-full text-left rounded-xl border border-gray-200 bg-white p-4 hover:border-slate-300 hover:bg-gray-50 transition">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                  <div>
                    <p class="text-sm text-gray-500">End Day</p>
                    <p class="text-lg font-semibold text-gray-900">{{ $history->end_day?->format('d/m/Y') ?? '-' }}</p>
                    <p class="text-xs text-gray-500 mt-1">Last sync: {{ $history->last_synced_at?->format('d/m/Y H:i') ?? '-' }}</p>
                  </div>
                  <div class="flex items-center justify-start lg:justify-end">
                    <span class="inline-flex items-center rounded-lg bg-slate-800 px-3 py-2 text-xs font-semibold text-white">Lihat Detail</span>
                  </div>
                </div>
              </button>
            @empty
              <div class="rounded-xl border border-dashed border-gray-300 px-4 py-10 text-center text-sm text-gray-500">
                Belum ada history closing otomatis.
              </div>
            @endforelse

            @if ($recapHistories->hasPages())
              <div class="border-t border-gray-200 px-4 py-4">
                {{ $recapHistories->links() }}
              </div>
            @endif
          </div>
        </div>
      </div>

      <div x-show="showHistoryModal"
           x-transition:enter="ease-out duration-300"
           x-transition:enter-start="opacity-0"
           x-transition:enter-end="opacity-100"
           x-transition:leave="ease-in duration-200"
           x-transition:leave-start="opacity-100"
           x-transition:leave-end="opacity-0"
           class="fixed inset-0 z-50 overflow-y-auto bg-black/60 p-4"
           @click.self="showHistoryModal = false">
        <div class="mx-auto flex min-h-full max-w-6xl items-start justify-center py-6">
          <div class="w-full rounded-2xl bg-white shadow-2xl overflow-hidden">
            <div class="flex items-start justify-between border-b border-gray-200 px-6 py-5">
              <div>
                <p class="text-sm text-gray-500">Detail History Closing</p>
                <h3 class="mt-1 text-2xl font-bold text-gray-900"
                    x-text="selectedHistory?.end_day ?? '-' "></h3>
                <p class="mt-1 text-sm text-gray-500">Snapshot recap tersimpan otomatis saat proses closing harian.</p>
              </div>

              <div class="flex items-center gap-2">
                <a :href="selectedHistory?.export_url ?? '#'"
                   class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700">
                  Export History (.xlsx)
                </a>

                <form method="POST"
                      :action="selectedHistory?.reprint_url ?? '#'">
                  @csrf
                  <button type="submit"
                          class="inline-flex items-center justify-center rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-900"
                          :disabled="!selectedHistory">
                    Reprint
                  </button>
                </form>

                <button type="button"
                        @click="showHistoryModal = false"
                        class="rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600">
                  <svg class="h-6 w-6"
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
            </div>

            <div class="space-y-6 px-6 py-6 bg-gray-50">
              <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                  <p class="text-sm font-medium text-gray-500">Transaksi Kasir</p>
                  <p class="text-2xl font-bold text-gray-900 mt-1"
                     x-text="selectedHistory?.total_transactions ?? '0'"></p>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                  <p class="text-sm font-medium text-gray-500">Item Keluar Kitchen</p>
                  <p class="text-2xl font-bold text-gray-900 mt-1"
                     x-text="selectedHistory?.total_kitchen_items ?? '0'"></p>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                  <p class="text-sm font-medium text-gray-500">Item Keluar Bar</p>
                  <p class="text-2xl font-bold text-gray-900 mt-1"
                     x-text="selectedHistory?.total_bar_items ?? '0'"></p>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                  <p class="text-sm font-medium text-gray-500">Total Penjualan Kasir</p>
                  {{-- <p class="text-2xl font-bold text-emerald-700 mt-1"
                     x-text="selectedHistory?.total_amount ?? 'Rp 0'"></p> --}}

                  <div class="space-y-1.5 mt-3 border-t pt-2">
                    <div class="flex justify-between items-center">
                      <span class="text-sm font-medium text-emerald-700">Gross Sales</span>
                      <span class="text-base font-semibold text-emerald-700"
                            x-text="'Rp ' + Number((Number(selectedHistory?.total_amount_raw || 0) + Number(selectedHistory?.total_dp_raw || 0))).toLocaleString('id-ID')"></span>
                    </div>
                    <div class="flex justify-between items-center">
                      <span class="text-sm font-medium text-slate-700">Net Sales</span>
                      <span class="text-base font-semibold text-slate-700"
                            x-text="'Rp ' + Number(Math.max(0, (Number(selectedHistory?.total_amount_raw || 0) + Number(selectedHistory?.total_dp_raw || 0) - Number(selectedHistory?.total_tax_raw || 0) - Number(selectedHistory?.total_service_charge_raw || 0)))).toLocaleString('id-ID')"></span>
                    </div>
                  </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                  <p class="text-sm font-medium text-gray-500">Total Food</p>
                  <p class="text-2xl font-bold text-lime-700 mt-1"
                     x-text="selectedHistory?.total_food ?? 'Rp 0'"></p>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                  <p class="text-sm font-medium text-gray-500">Total Alcohol</p>
                  <p class="text-2xl font-bold text-yellow-700 mt-1"
                     x-text="selectedHistory?.total_alcohol ?? 'Rp 0'"></p>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                  <p class="text-sm font-medium text-gray-500">Total Beverage</p>
                  <p class="text-2xl font-bold text-teal-700 mt-1"
                     x-text="selectedHistory?.total_beverage ?? 'Rp 0'"></p>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                  <p class="text-sm font-medium text-gray-500">Total Cigarette</p>
                  <p class="text-2xl font-bold text-fuchsia-700 mt-1"
                     x-text="selectedHistory?.total_cigarette ?? 'Rp 0'"></p>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                  <p class="text-sm font-medium text-gray-500">Total Breakage</p>
                  <p class="text-2xl font-bold text-red-700 mt-1"
                     x-text="selectedHistory?.total_breakage ?? 'Rp 0'"></p>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                  <p class="text-sm font-medium text-gray-500">Total Room</p>
                  <p class="text-2xl font-bold text-blue-700 mt-1"
                     x-text="selectedHistory?.total_room ?? 'Rp 0'"></p>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                  <p class="text-sm font-medium text-gray-500">Total LD</p>
                  <p class="text-2xl font-bold text-purple-700 mt-1"
                     x-text="selectedHistory?.total_ld ?? 'Rp 0'"></p>
                  <p class="text-xs font-medium text-purple-600 mt-2"
                     x-text="'Qty ' + (selectedHistory?.total_ld_quantity ?? '0')"></p>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                  <p class="text-sm font-medium text-gray-500">Total Compliment</p>
                  <p class="text-2xl font-bold text-sky-700 mt-1"
                     x-text="selectedHistory?.total_compliment_amount ?? 'Rp 0'"></p>
                  <p class="text-xs font-medium text-sky-600 mt-2"
                     x-text="'Qty ' + (selectedHistory?.total_compliment_quantity ?? '0')"></p>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                  <p class="text-sm font-medium text-gray-500">Total FOC</p>
                  <p class="text-2xl font-bold text-indigo-700 mt-1"
                     x-text="selectedHistory?.total_foc_amount ?? 'Rp 0'"></p>
                  <p class="text-xs font-medium text-indigo-600 mt-2"
                     x-text="'Qty ' + (selectedHistory?.total_foc_quantity ?? '0')"></p>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                  <p class="text-sm font-medium text-gray-500">Total Penjualan Rokok (Qty)</p>
                  <p class="text-2xl font-bold text-rose-700 mt-1"
                     x-text="selectedHistory?.total_penjualan_rokok ?? '0'"></p>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                  <p class="text-sm font-medium text-gray-500">Total DP <span class="text-xs font-normal">(booking)</span></p>
                  <p class="text-2xl font-bold text-cyan-700 mt-1"
                     x-text="selectedHistory?.total_dp ?? 'Rp 0'"></p>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                  <p class="text-sm font-medium text-gray-500">Total Tunai</p>
                  <p class="text-2xl font-bold text-gray-900 mt-1"
                     x-text="selectedHistory?.total_cash ?? 'Rp 0'"></p>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                  <p class="text-sm font-medium text-gray-500">Last Sync</p>
                  <p class="text-lg font-bold text-gray-900 mt-1"
                     x-text="selectedHistory?.last_synced_at ?? '-'"></p>
                </div>
              </div>

              <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                <div class="flex items-center justify-between mb-4">
                  <h3 class="text-base font-semibold text-gray-900">Preview Dashboard (Akumulasiii)</h3>
                  <span class="text-xs text-gray-500">Snapshot history closing</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                  <div class="p-4 border border-gray-200 rounded-lg bg-rose-50">
                    <p class="text-sm font-medium text-rose-700">Total Penjualan Rokok (Qty)</p>
                    <p class="text-2xl font-bold text-rose-800 mt-1"
                       x-text="selectedHistory?.total_penjualan_rokok ?? '0'"></p>
                  </div>

                  <div class="p-4 border border-gray-200 rounded-lg bg-sky-50">
                    <p class="text-sm font-medium text-sky-700">Total Compliment</p>
                    <p class="text-2xl font-bold text-sky-800 mt-1"
                       x-text="selectedHistory?.total_compliment_amount ?? 'Rp 0'"></p>
                    <p class="text-xs font-medium text-sky-600 mt-2"
                       x-text="'Qty ' + (selectedHistory?.total_compliment_quantity ?? '0')"></p>
                  </div>

                  <div class="p-4 border border-gray-200 rounded-lg bg-indigo-50">
                    <p class="text-sm font-medium text-indigo-700">Total FOC</p>
                    <p class="text-2xl font-bold text-indigo-800 mt-1"
                       x-text="selectedHistory?.total_foc_amount ?? 'Rp 0'"></p>
                    <p class="text-xs font-medium text-indigo-600 mt-2"
                       x-text="'Qty ' + (selectedHistory?.total_foc_quantity ?? '0')"></p>
                  </div>

                  <div class="p-4 border border-gray-200 rounded-lg bg-amber-50">
                    <p class="text-sm font-medium text-amber-700">Total Pajak</p>
                    <p class="text-2xl font-bold text-amber-800 mt-1"
                       x-text="selectedHistory?.total_tax ?? 'Rp 0'"></p>
                  </div>

                  <div class="p-4 border border-gray-200 rounded-lg bg-orange-50">
                    <p class="text-sm font-medium text-orange-700">Total Service Charge</p>
                    <p class="text-2xl font-bold text-orange-800 mt-1"
                       x-text="selectedHistory?.total_service_charge ?? 'Rp 0'"></p>
                  </div>

                  <div class="p-4 border border-gray-200 rounded-lg bg-gray-50">
                    <p class="text-sm font-medium text-gray-500">Total Tunai</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1"
                       x-text="selectedHistory?.total_cash ?? 'Rp 0'"></p>
                  </div>

                  <div class="p-4 border border-gray-200 rounded-lg bg-emerald-50">
                    <p class="text-sm font-medium text-emerald-700">Gross Sales (Included DP)</p>
                    <p class="text-2xl font-bold text-emerald-800 mt-1"
                       x-text="'Rp ' + Number((Number(selectedHistory?.total_amount_raw || 0) + Number(selectedHistory?.total_dp_raw || 0))).toLocaleString('id-ID')"></p>
                  </div>

                  <div class="p-4 border border-gray-200 rounded-lg bg-slate-50">
                    <p class="text-sm font-medium text-slate-700">Net Sales (Included DP)</p>
                    <p class="text-2xl font-bold text-slate-800 mt-1"
                       x-text="'Rp ' + Number(Math.max(0, (Number(selectedHistory?.total_amount_raw || 0) + Number(selectedHistory?.total_dp_raw || 0) - Number(selectedHistory?.total_tax_raw || 0) - Number(selectedHistory?.total_service_charge_raw || 0)))).toLocaleString('id-ID')"></p>
                  </div>

                  <div class="p-4 border border-gray-200 rounded-lg bg-blue-50">
                    <p class="text-sm font-medium text-blue-700">Total Transfer</p>
                    <p class="text-2xl font-bold text-blue-800 mt-1"
                       x-text="selectedHistory?.total_transfer ?? 'Rp 0'"></p>
                  </div>

                  <div class="p-4 border border-gray-200 rounded-lg bg-indigo-50">
                    <p class="text-sm font-medium text-indigo-700">Total Debit</p>
                    <p class="text-2xl font-bold text-indigo-800 mt-1"
                       x-text="selectedHistory?.total_debit ?? 'Rp 0'"></p>
                  </div>

                  <div class="p-4 border border-gray-200 rounded-lg bg-violet-50">
                    <p class="text-sm font-medium text-violet-700">Total Kredit</p>
                    <p class="text-2xl font-bold text-violet-800 mt-1"
                       x-text="selectedHistory?.total_kredit ?? 'Rp 0'"></p>
                  </div>

                  <div class="p-4 border border-gray-200 rounded-lg bg-emerald-50">
                    <p class="text-sm font-medium text-emerald-700">Total QRIS</p>
                    <p class="text-2xl font-bold text-emerald-800 mt-1"
                       x-text="selectedHistory?.total_qris ?? 'Rp 0'"></p>
                  </div>
                </div>
              </div>

              <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                <div class="flex items-center justify-between mb-4">
                  <div>
                    <h3 class="text-base font-semibold text-gray-900">Transactions Recap History</h3>
                    <p class="text-sm text-gray-500 mt-1">Formatnya disamakan dengan Transactions Recap Hari Ini.</p>
                  </div>
                </div>

                <div x-show="historyTransactionsLoading"
                     class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                  Memuat transaksi history...
                </div>

                <div class="flex border-b border-gray-200 gap-2 mb-4">
                  <button type="button"
                          @click="historyTransactionRecapTab = 'billing'"
                          :class="historyTransactionRecapTab === 'billing' ? '-mb-px border-b-2 border-slate-800 text-slate-900 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                          class="px-5 py-3 text-sm transition whitespace-nowrap">
                    Dari Billing
                  </button>
                  <button type="button"
                          @click="historyTransactionRecapTab = 'walkin'"
                          :class="historyTransactionRecapTab === 'walkin' ? '-mb-px border-b-2 border-slate-800 text-slate-900 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                          class="px-5 py-3 text-sm transition whitespace-nowrap">
                    Dari Walk-in
                  </button>
                </div>

                <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-3">
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">Filter Payment</label>
                      <select x-model="historyTransactionFilters[historyTransactionRecapTab].payment"
                              class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-400">
                        <option value="all">Semua Payment</option>
                        <option value="cash">Tunai</option>
                        <option value="transfer">Transfer</option>
                        <option value="debit">Debit</option>
                        <option value="kredit">Kredit</option>
                        <option value="qris">QRIS</option>
                        <option value="split">Split Bill</option>
                      </select>
                    </div>
                    <div class="flex items-end justify-start md:justify-end">
                      <button type="button"
                              @click="resetHistoryTransactionFilters(historyTransactionRecapTab)"
                              class="inline-flex items-center rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100">
                        Reset Filter
                      </button>
                    </div>
                  </div>

                  <div class="space-y-2">
                    <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Filter Include Item</p>
                    <div class="flex flex-wrap gap-2">
                      <template x-for="category in filterCategories"
                                :key="category.key">
                        <button type="button"
                                @click="toggleHistoryTransactionCategory(historyTransactionRecapTab, category.key)"
                                :class="isHistoryCategorySelected(historyTransactionRecapTab, category.key) ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-100'"
                                class="rounded-full border px-3 py-1.5 text-xs font-medium transition"
                                x-text="category.label"></button>
                      </template>
                    </div>
                  </div>
                </div>

                <div x-show="historyTransactionRecapTab === 'billing'"
                     class="overflow-x-auto border border-gray-200 rounded-lg">
                  <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                      <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tanggal &amp; Jam</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">No. Transaksi</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Customer</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Metode Pembayaran</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Qty Item</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Total</th>
                      </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                      <template x-for="transaction in filteredHistoryTransactions('billing')"
                                :key="transaction.transaction_number + '-' + transaction.datetime">
                        <tr @click="selectedTransaction = transaction; showTransactionModal = true"
                            class="cursor-pointer hover:bg-gray-50 transition">
                          <td class="px-4 py-3 text-sm text-gray-700"
                              x-text="transaction.datetime"></td>
                          <td class="px-4 py-3 text-sm font-medium text-gray-900"
                              x-text="transaction.transaction_number"></td>
                          <td class="px-4 py-3 text-sm text-gray-700"
                              x-text="transaction.customer_name"></td>
                          <td class="px-4 py-3 text-sm text-gray-700"
                              x-text="transaction.payment_method"></td>
                          <td class="px-4 py-3 text-sm text-gray-700 text-right"
                              x-text="transaction.items_count"></td>
                          <td class="px-4 py-3 text-sm font-semibold text-gray-900 text-right"
                              x-text="formatCurrency(transaction.total)"></td>
                        </tr>
                      </template>
                      <tr x-show="filteredHistoryTransactions('billing').length === 0">
                        <td colspan="6"
                            class="px-4 py-6 text-sm text-center text-gray-500">Belum ada transaksi billing dengan filter ini.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <div x-show="historyTransactionRecapTab === 'walkin'"
                     class="overflow-x-auto border border-gray-200 rounded-lg">
                  <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                      <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tanggal &amp; Jam</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">No. Transaksi</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Customer</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Metode Pembayaran</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Qty Item</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Total</th>
                      </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                      <template x-for="transaction in filteredHistoryTransactions('walkin')"
                                :key="transaction.transaction_number + '-' + transaction.datetime">
                        <tr @click="selectedTransaction = transaction; showTransactionModal = true"
                            class="cursor-pointer hover:bg-gray-50 transition">
                          <td class="px-4 py-3 text-sm text-gray-700"
                              x-text="transaction.datetime"></td>
                          <td class="px-4 py-3 text-sm font-medium text-gray-900"
                              x-text="transaction.transaction_number"></td>
                          <td class="px-4 py-3 text-sm text-gray-700"
                              x-text="transaction.customer_name"></td>
                          <td class="px-4 py-3 text-sm text-gray-700"
                              x-text="transaction.payment_method"></td>
                          <td class="px-4 py-3 text-sm text-gray-700 text-right"
                              x-text="transaction.items_count"></td>
                          <td class="px-4 py-3 text-sm font-semibold text-gray-900 text-right"
                              x-text="formatCurrency(transaction.total)"></td>
                        </tr>
                      </template>
                      <tr x-show="filteredHistoryTransactions('walkin').length === 0">
                        <td colspan="6"
                            class="px-4 py-6 text-sm text-center text-gray-500">Belum ada transaksi walk-in dengan filter ini.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div x-show="showTransactionModal"
           x-transition:enter="ease-out duration-300"
           x-transition:enter-start="opacity-0"
           x-transition:enter-end="opacity-100"
           x-transition:leave="ease-in duration-200"
           x-transition:leave-start="opacity-100"
           x-transition:leave-end="opacity-0"
           class="fixed inset-0 z-50 overflow-y-auto bg-black/60 p-4"
           @click.self="showTransactionModal = false">
        <div class="mx-auto flex min-h-full max-w-3xl items-start justify-center py-6">
          <div class="w-full rounded-2xl bg-white shadow-2xl overflow-hidden">
            <div class="flex items-start justify-between border-b border-gray-200 px-6 py-5">
              <div>
                <p class="text-sm text-gray-500">Detail Transaksi</p>
                <h3 class="mt-1 text-xl font-bold text-gray-900"
                    x-text="selectedTransaction?.transaction_number ?? '-' "></h3>
                <p class="mt-1 text-sm text-gray-500"
                   x-text="selectedTransaction?.datetime ?? '-' "></p>
              </div>

              <button type="button"
                      @click="showTransactionModal = false"
                      class="rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600">
                <svg class="h-6 w-6"
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

            <div class="space-y-4 px-6 py-6 bg-gray-50">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="rounded-lg border border-gray-200 bg-white p-3">
                  <p class="text-xs text-gray-500">Customer</p>
                  <p class="text-sm font-semibold text-gray-900"
                     x-text="selectedTransaction?.customer_name ?? '-' "></p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-3">
                  <p class="text-xs text-gray-500">Metode Pembayaran</p>
                  <p class="text-sm font-semibold text-gray-900"
                     x-text="selectedTransaction?.payment_method ?? '-' "></p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-3">
                  <p class="text-xs text-gray-500">FOC / Compliment</p>
                  <p class="text-sm font-semibold text-gray-900"
                     x-text="selectedTransaction?.foc_comp_payment_method ?? '-' "></p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-3 md:col-span-2">
                  <p class="text-xs text-gray-500">No. Referensi</p>
                  <p class="text-sm font-semibold text-gray-900"
                     x-text="selectedTransaction?.payment_reference_number || '-' "></p>
                </div>
              </div>

              <div x-show="Array.isArray(selectedTransaction?.split_payments) && selectedTransaction.split_payments.length > 0"
                   class="rounded-lg border border-gray-200 bg-white overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200">
                  <p class="text-sm font-semibold text-gray-900">Detail Split Payment</p>
                </div>
                <div class="divide-y divide-gray-100">
                  <template x-for="(payment, index) in (selectedTransaction?.split_payments ?? [])"
                            :key="payment.label + '-' + index">
                    <div class="px-4 py-3 grid grid-cols-1 gap-1 sm:grid-cols-4 sm:items-center sm:gap-3">
                      <div class="sm:col-span-1">
                        <p class="text-xs text-gray-500"
                           x-text="payment.label"></p>
                      </div>
                      <div class="sm:col-span-1">
                        <p class="text-sm font-semibold text-gray-900"
                           x-text="payment.method || '-' "></p>
                      </div>
                      <div class="sm:col-span-1">
                        <p class="text-xs text-gray-500">Referensi</p>
                        <p class="text-sm text-gray-700"
                           x-text="payment.reference_number || '-' "></p>
                      </div>
                      <div class="sm:col-span-1 sm:text-right">
                        <p class="text-xs text-gray-500">Nominal</p>
                        <p class="text-sm font-semibold text-gray-900"
                           x-text="'Rp ' + Number(payment.amount || 0).toLocaleString('id-ID')"></p>
                      </div>
                    </div>
                  </template>
                </div>
              </div>

              <div class="rounded-lg border border-gray-200 bg-white overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200">
                  <p class="text-sm font-semibold text-gray-900">Item</p>
                </div>
                <div class="max-h-64 overflow-auto divide-y divide-gray-100">
                  <template x-for="item in (selectedTransaction?.items ?? [])"
                            :key="item.name + '-' + item.quantity + '-' + item.price">
                    <div class="px-4 py-3 flex items-start justify-between gap-3">
                      <div>
                        <p class="text-sm font-medium text-gray-900"
                           x-text="item.name"></p>
                        <p class="text-xs text-gray-500"
                           x-text="item.quantity + ' x Rp ' + Number(item.price || 0).toLocaleString('id-ID')"></p>
                      </div>
                      <p class="text-sm font-semibold text-gray-900"
                         x-text="'Rp ' + Number(item.subtotal || 0).toLocaleString('id-ID')"></p>
                    </div>
                  </template>
                  <p x-show="(selectedTransaction?.items ?? []).length === 0"
                     class="px-4 py-4 text-sm text-gray-500">Tidak ada item.</p>
                </div>
              </div>

              <div class="rounded-lg border border-gray-200 bg-white p-4 space-y-2 text-sm">
                <div class="flex items-center justify-between text-gray-700">
                  <span>Total Bill</span>
                  <span x-text="'Rp ' + Number(selectedTransaction?.total_bill || 0).toLocaleString('id-ID')"></span>
                </div>
                <div class="flex items-center justify-between text-amber-700">
                  <span>PB1</span>
                  <span x-text="'Rp ' + Number(selectedTransaction?.tax_total || 0).toLocaleString('id-ID')"></span>
                </div>
                <div class="flex items-center justify-between text-orange-700">
                  <span>Service Charge</span>
                  <span x-text="'Rp ' + Number(selectedTransaction?.service_charge_total || 0).toLocaleString('id-ID')"></span>
                </div>
                <div class="flex items-center justify-between text-rose-700">
                  <span>Diskon</span>
                  <span x-text="'- Rp ' + Number(selectedTransaction?.discount_amount || 0).toLocaleString('id-ID')"></span>
                </div>
                <div class="flex items-center justify-between text-cyan-700">
                  <span>Subtotal</span>
                  <span x-text="'Rp ' + Number(Math.max(0, (Number(selectedTransaction?.total_bill || 0) + Number(selectedTransaction?.tax_total || 0) + Number(selectedTransaction?.service_charge_total || 0) - Number(selectedTransaction?.discount_amount || 0)))).toLocaleString('id-ID')"></span>
                </div>
                <div class="flex items-center justify-between text-cyan-700">
                  <span>DP</span>
                  <span x-text="'Rp ' + Number(selectedTransaction?.down_payment_amount || 0).toLocaleString('id-ID')"></span>
                </div>
                <div class="flex items-center justify-between text-gray-900 font-semibold border-t border-dashed border-gray-200 pt-2">
                  <span>Total Bayar</span>
                  <span x-text="'Rp ' + Number(selectedTransaction?.total || 0).toLocaleString('id-ID')"></span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <script>
    function recapPage({
      billingTransactions = [],
      walkInTransactions = []
    }) {
      return {
        activeTab: 'recap',
        transactionRecapTab: 'billing',
        historyTransactionRecapTab: 'billing',
        showHistoryModal: false,
        selectedHistory: null,
        historyTransactionsLoading: false,
        showTransactionModal: false,
        selectedTransaction: null,
        billingTransactions,
        walkInTransactions,
        transactionFilters: {
          billing: {
            payment: 'all',
            categories: [],
          },
          walkin: {
            payment: 'all',
            categories: [],
          },
        },
        historyTransactionFilters: {
          billing: {
            payment: 'all',
            categories: [],
          },
          walkin: {
            payment: 'all',
            categories: [],
          },
        },
        filterCategories: [{
            key: 'food',
            label: 'Food'
          },
          {
            key: 'alcohol',
            label: 'Alcohol'
          },
          {
            key: 'beverage',
            label: 'Beverage'
          },
          {
            key: 'cigarette',
            label: 'Cigarette'
          },
          {
            key: 'breakage',
            label: 'Breakage'
          },
          {
            key: 'room',
            label: 'Room'
          },
          {
            key: 'staff_meal',
            label: 'Staff Meal'
          },
          {
            key: 'compliment',
            label: 'Compliment'
          },
          {
            key: 'foc',
            label: 'FOC'
          },
          {
            key: 'ld',
            label: 'LD'
          },
          {
            key: 'dp',
            label: 'DP (Booking)'
          },
        ],

        resetTransactionFilters(tab) {
          this.transactionFilters[tab] = {
            payment: 'all',
            categories: [],
          };
        },

        resetHistoryTransactionFilters(tab) {
          this.historyTransactionFilters[tab] = {
            payment: 'all',
            categories: [],
          };
        },

        async loadHistoryTransactions(history) {
          this.selectedHistory = this.buildHistorySummary(history, [], []);
          this.historyTransactionRecapTab = 'billing';
          this.historyTransactionsLoading = true;
          this.showHistoryModal = true;

          try {
            const response = await fetch(history.transactions_url, {
              headers: {
                Accept: 'application/json',
              },
            });

            if (!response.ok) {
              throw new Error('Unable to load history transactions.');
            }

            const data = await response.json();
            const billingTransactions = data.billing_transactions ?? [];
            const walkInTransactions = data.walkin_transactions ?? [];

            this.selectedHistory = this.buildHistorySummary(history, billingTransactions, walkInTransactions);
          } catch (error) {
            this.selectedHistory = this.buildHistorySummary(history, [], []);
          } finally {
            this.historyTransactionsLoading = false;
          }
        },

        buildHistorySummary(history, billingTransactions = [], walkInTransactions = []) {
          const totalAmountRaw = Number(history?.total_amount_raw || 0);
          const totalTaxRaw = Number(history?.total_tax_raw || 0);
          const totalServiceChargeRaw = Number(history?.total_service_charge_raw || 0);
          const snapshotDpRaw = Number(history?.total_dp_raw || 0);

          const billingDpRaw = billingTransactions.reduce((sum, transaction) => {
            return sum + Number(transaction?.down_payment_amount || 0);
          }, 0);

          const totalDpRaw = Math.max(snapshotDpRaw, billingDpRaw);
          const grossSalesRaw = totalAmountRaw + totalDpRaw;
          const netSalesRaw = Math.max(0, grossSalesRaw - totalTaxRaw - totalServiceChargeRaw);

          return {
            ...history,
            total_amount_raw: totalAmountRaw,
            total_tax_raw: totalTaxRaw,
            total_service_charge_raw: totalServiceChargeRaw,
            total_dp_raw: totalDpRaw,
            total_dp: this.formatCurrency(totalDpRaw),
            gross_sales: this.formatCurrency(grossSalesRaw),
            net_sales: this.formatCurrency(netSalesRaw),
            billing_transactions: billingTransactions,
            walkin_transactions: walkInTransactions,
          };
        },

        toggleTransactionCategory(tab, categoryKey) {
          const categories = this.transactionFilters[tab].categories;
          const foundIndex = categories.indexOf(categoryKey);

          if (foundIndex >= 0) {
            categories.splice(foundIndex, 1);
            return;
          }

          categories.push(categoryKey);
        },

        isCategorySelected(tab, categoryKey) {
          return this.transactionFilters[tab].categories.includes(categoryKey);
        },

        toggleHistoryTransactionCategory(tab, categoryKey) {
          const categories = this.historyTransactionFilters[tab].categories;
          const foundIndex = categories.indexOf(categoryKey);

          if (foundIndex >= 0) {
            categories.splice(foundIndex, 1);
            return;
          }

          categories.push(categoryKey);
        },

        isHistoryCategorySelected(tab, categoryKey) {
          return this.historyTransactionFilters[tab].categories.includes(categoryKey);
        },

        filteredTransactions(tab) {
          const sourceTransactions = tab === 'billing' ? this.billingTransactions : this.walkInTransactions;
          const filter = this.transactionFilters[tab];

          return sourceTransactions.filter((transaction) => {
            const paymentPass = filter.payment === 'all' ||
              transaction.payment_method_key === filter.payment;

            if (!paymentPass) {
              return false;
            }

            if (!filter.categories.length) {
              return true;
            }

            return filter.categories.some((category) => {
              if (category === 'food') {
                return !!transaction.contains_food;
              }

              if (category === 'alcohol') {
                return !!transaction.contains_alcohol;
              }

              if (category === 'beverage') {
                return !!transaction.contains_beverage;
              }

              if (category === 'cigarette') {
                return !!transaction.contains_cigarette;
              }

              if (category === 'breakage') {
                return !!transaction.contains_breakage;
              }

              if (category === 'room') {
                return !!transaction.contains_room;
              }

              if (category === 'staff_meal') {
                return !!transaction.contains_staff_meal;
              }

              if (category === 'compliment') {
                return !!transaction.contains_compliment;
              }

              if (category === 'foc') {
                return !!transaction.contains_foc;
              }

              if (category === 'ld') {
                return !!transaction.contains_ld;
              }

              if (category === 'dp') {
                return !!transaction.has_down_payment;
              }

              return false;
            });
          });
        },

        filteredHistoryTransactions(tab) {
          const sourceTransactions = tab === 'billing' ?
            (this.selectedHistory?.billing_transactions ?? []) :
            (this.selectedHistory?.walkin_transactions ?? []);
          const filter = this.historyTransactionFilters[tab];

          return sourceTransactions.filter((transaction) => {
            const paymentPass = filter.payment === 'all' ||
              transaction.payment_method_key === filter.payment;

            if (!paymentPass) {
              return false;
            }

            if (!filter.categories.length) {
              return true;
            }

            return filter.categories.some((category) => {
              if (category === 'food') {
                return !!transaction.contains_food;
              }

              if (category === 'alcohol') {
                return !!transaction.contains_alcohol;
              }

              if (category === 'beverage') {
                return !!transaction.contains_beverage;
              }

              if (category === 'cigarette') {
                return !!transaction.contains_cigarette;
              }

              if (category === 'breakage') {
                return !!transaction.contains_breakage;
              }

              if (category === 'room') {
                return !!transaction.contains_room;
              }

              if (category === 'staff_meal') {
                return !!transaction.contains_staff_meal;
              }

              if (category === 'compliment') {
                return !!transaction.contains_compliment;
              }

              if (category === 'foc') {
                return !!transaction.contains_foc;
              }

              if (category === 'ld') {
                return !!transaction.contains_ld;
              }

              if (category === 'dp') {
                return !!transaction.has_down_payment;
              }

              return false;
            });
          });
        },

        formatCurrency(value) {
          return 'Rp ' + Number(value || 0).toLocaleString('id-ID');
        },
      };
    }
  </script>
</x-app-layout>
