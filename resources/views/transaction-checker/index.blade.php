<x-app-layout title="Transaction Checker">
  <div class="p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-slate-800 rounded-xl flex items-center justify-center">
          <svg class="w-5 h-5 text-white"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
          </svg>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Transaction Checker</h1>
          <p class="text-sm text-gray-500">Pantau dan check setiap item dalam orderan secara real-time</p>
        </div>
      </div>
      <a href="{{ request()->fullUrl() }}"
         class="flex items-center gap-2 bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 rounded-xl font-medium transition text-sm">
        <svg class="w-4 h-4"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        Refresh
      </a>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-4 gap-4 mb-6">
      <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
        <p class="text-3xl font-bold text-gray-900">{{ $totalOrders }}</p>
        <p class="text-sm text-gray-500 mt-1">Total Order</p>
      </div>
      <div class="bg-red-50 border border-red-200 rounded-xl p-4 shadow-sm">
        <p class="text-3xl font-bold text-red-700">{{ $baruOrders }}</p>
        <p class="text-sm text-red-600 mt-1">Baru</p>
      </div>
      <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 shadow-sm">
        <p class="text-3xl font-bold text-yellow-800">{{ $baruOrders + $prosesOrders }}</p>
        <p class="text-sm text-yellow-700 mt-1">Dalam Proses</p>
      </div>
      <div class="bg-green-50 border border-green-200 rounded-xl p-4 shadow-sm">
        <p class="text-3xl font-bold text-green-700">{{ $selesaiOrders }}</p>
        <p class="text-sm text-green-600 mt-1">Selesai</p>
      </div>
    </div>

    <!-- Area Filter Pills + Tabs -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
      <!-- Tabs -->
      <div class="flex items-center gap-2">
        <a href="{{ route('admin.transaction-checker.index', array_merge(request()->query(), ['tab' => 'all'])) }}"
           class="px-4 py-2 rounded-xl text-sm font-medium transition {{ $tab === 'all' ? 'bg-slate-800 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }}">
          Semua ({{ $totalOrders }})
        </a>
        <a href="{{ route('admin.transaction-checker.index', array_merge(request()->query(), ['tab' => 'proses'])) }}"
           class="px-4 py-2 rounded-xl text-sm font-medium transition {{ $tab === 'proses' ? 'bg-orange-500 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }}">
          Dalam Proses ({{ $baruOrders + $prosesOrders }})
        </a>
        <a href="{{ route('admin.transaction-checker.index', array_merge(request()->query(), ['tab' => 'selesai'])) }}"
           class="px-4 py-2 rounded-xl text-sm font-medium transition {{ $tab === 'selesai' ? 'bg-green-500 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }}">
          Selesai ({{ $selesaiOrders }})
        </a>
      </div>

      <!-- Area Filter Pills -->
      @if (($areas ?? collect())->count() > 1)
        <div class="inline-flex items-center gap-1.5 p-1 bg-white rounded-xl border border-gray-200 shadow-sm">
          <a href="{{ route('admin.transaction-checker.index', array_merge(request()->query(), ['area_id' => 'all'])) }}"
             class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ empty($selectedAreaId) ? 'bg-slate-800 text-white shadow-xs' : 'text-gray-600 hover:bg-gray-100' }}">
            Semua Area
          </a>
          @foreach ($areas as $area)
            <a href="{{ route('admin.transaction-checker.index', array_merge(request()->query(), ['area_id' => $area->id])) }}"
               class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ (int) ($selectedAreaId ?? 0) === (int) $area->id ? 'bg-slate-800 text-white shadow-xs' : 'text-gray-600 hover:bg-gray-100' }}">
              {{ $area->name }}
            </a>
          @endforeach
        </div>
      @endif
    </div>

    @if ($orders->isEmpty())
      <div class="bg-slate-50 border-2 border-dashed border-slate-200 rounded-2xl p-16 text-center">
        <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8 text-slate-500"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2" />
          </svg>
        </div>
        <h3 class="text-lg font-bold text-gray-900 mb-1">Tidak Ada Order</h3>
        <p class="text-gray-500 text-sm">Belum ada order untuk dicek</p>
      </div>
    @else
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($orders as $order)
          @php
            $totalItems = $order->items->where('status', '!=', 'cancelled')->count();
            $servedItems = $order->items->where('status', 'served')->count();
            $displayId = $order->ordered_at && $order->ordered_at->isToday() ? '#TRX-TODAY-' . $order->id : '#TRX-' . $order->id;
            $activeItems = $order->items->where('status', '!=', 'cancelled');
          @endphp

          <div x-show="!hidden"
               x-data="{
                   loading: false,
                   hidden: false,
                   orderStatus: '{{ $order->status }}',
                   servedCount: {{ $servedItems }},
                   totalCount: {{ $totalItems }},
                   items: @js($activeItems->map(fn($i) => ['id' => $i->id, 'item_name' => $i->item_name, 'quantity' => $i->quantity, 'price' => $i->price, 'status' => $i->status, 'preparation_location' => $i->preparation_location])->values()->toArray()),
                   get progressPct() {
                       return this.totalCount > 0 ? Math.round((this.servedCount / this.totalCount) * 100) : 0;
                   },
                   statusLabel(status) {
                       return { pending: 'Baru', preparing: 'Dalam Proses', ready: 'Siap Saji', completed: 'Selesai', cancelled: 'Dibatalkan' } [status] || status;
                   },
                   statusClass(status) {
                       return { pending: 'bg-red-100 text-red-700', preparing: 'bg-yellow-100 text-yellow-700', ready: 'bg-blue-100 text-blue-700', completed: 'bg-green-100 text-green-700', cancelled: 'bg-gray-100 text-gray-500' } [status] || 'bg-gray-100 text-gray-500';
                   },
                   borderClass(status) {
                       return { pending: 'border-red-300', preparing: 'border-yellow-300', ready: 'border-blue-300', completed: 'border-green-300' } [status] || 'border-gray-200';
                   },
                   async checkItem(itemId) {
                       if (this.loading) return;
                       this.loading = true;
                       try {
                           const url = '{{ route('admin.transaction-checker.check-item', ':id') }}'.replace(':id', itemId);
                           const res = await fetch(url, {
                               method: 'PATCH',
                               headers: {
                                   'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                   'Accept': 'application/json'
                               }
                           });
                           const data = await res.json();
                           if (data.success) {
                               this.items = this.items.map(item => item.id === itemId ? { ...item, status: 'served' } : item);
                               this.servedCount = data.served_count;
                               this.totalCount = data.total_count;
                               this.orderStatus = data.order_status;
                           }
                       } finally {
                           this.loading = false;
                       }
                   },
                   async checkAll() {
                       if (this.loading || this.orderStatus === 'completed') return;
                       this.loading = true;
                       try {
                           const url = '{{ route('admin.transaction-checker.check-all', ':id') }}'.replace(':id', '{{ $order->id }}');
                           const res = await fetch(url, {
                               method: 'PATCH',
                               headers: {
                                   'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                   'Accept': 'application/json'
                               }
                           });
                           const data = await res.json();
                           if (data.success) {
                               this.items = this.items.map(item => ({ ...item, status: 'served' }));
                               this.servedCount = this.totalCount;
                               this.orderStatus = data.order_status;
                               this.hidden = true;
                           }
                       } finally {
                           this.loading = false;
                       }
                   }
               }"
               class="bg-white rounded-2xl border-2 flex flex-col transition"
               :class="borderClass(orderStatus)">
            <div class="px-4 pt-4 pb-3 border-b border-gray-100">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-bold text-gray-900 text-sm">{{ $displayId }}</span>
                    <span class="text-xs font-bold px-2 py-0.5 rounded-full"
                          :class="statusClass(orderStatus)"
                          x-text="statusLabel(orderStatus)">
                    </span>
                  </div>
                  <div class="mt-1.5 text-xs text-gray-400">
                    @if ($order->ordered_at)
                      {{ $order->ordered_at->format('d M Y H:i') }}
                    @else
                      —
                    @endif
                  </div>
                </div>
                <div class="text-right shrink-0">
                  <div class="text-sm font-bold text-gray-800"
                       x-text="progressPct + '%' "></div>
                  <div class="text-xs text-gray-400">Rp {{ number_format($order->total, 0, ',', '.') }}</div>
                </div>
              </div>

              <div class="mt-3 flex items-center gap-2">
                <div class="flex-1 bg-gray-200 rounded-full h-1.5 overflow-hidden">
                  <div class="bg-orange-500 h-1.5 rounded-full transition-all duration-300"
                       :style="`width: ${progressPct}%`"></div>
                </div>
                <span class="text-xs text-gray-500 whitespace-nowrap"
                      x-text="`${servedCount}/${totalCount} item`"></span>
              </div>
            </div>

            <div class="px-4 py-3 border-b border-gray-50">
              <div class="flex items-center gap-2 mb-1">
                <svg class="w-3.5 h-3.5 text-gray-400 shrink-0"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span class="text-sm font-semibold text-gray-800">
                  {{ $order->tableSession?->customer?->name ?? 'Guest' }}
                </span>
              </div>
              @if ($order->tableSession?->customer?->profile?->phone)
                <div class="flex items-center gap-2 mb-1">
                  <svg class="w-3.5 h-3.5 text-gray-400 shrink-0"
                       fill="none"
                       stroke="currentColor"
                       viewBox="0 0 24 24">
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                  </svg>
                  <span class="text-xs text-gray-500">{{ $order->tableSession->customer->profile->phone }}</span>
                </div>
              @endif
              @if ($order->tableSession?->table)
                <div class="flex items-center gap-2 mt-1">
                  <svg class="w-3.5 h-3.5 text-slate-600 shrink-0"
                       fill="none"
                       stroke="currentColor"
                       viewBox="0 0 24 24">
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                  </svg>
                  <span class="text-xs font-semibold text-slate-700">Meja {{ $order->tableSession->table->table_number }}</span>
                </div>
              @endif
            </div>

            <div class="px-4 pt-3 pb-2 flex-1">
              <div class="flex items-center gap-2 mb-2">
                <svg class="w-3.5 h-3.5 text-slate-700"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2" />
                </svg>
                <span class="text-xs font-bold text-slate-700 uppercase tracking-wide">Order Items (<span x-text="items.length"></span>)</span>
              </div>

              <div class="space-y-1.5">
                <template x-for="item in items"
                          :key="item.id">
                  <div class="flex items-start gap-2.5 p-2.5 rounded-xl border transition"
                       :class="item.status === 'served' ? 'bg-green-50 border-green-100' : 'bg-gray-50 border-gray-100'">
                    <button @click="checkItem(item.id)"
                            :disabled="item.status === 'served' || loading"
                            class="w-5 h-5 mt-0.5 rounded flex items-center justify-center shrink-0 transition disabled:opacity-50"
                            :class="item.status === 'served' ? 'bg-green-500' : 'bg-slate-800'">
                      <svg x-show="item.status === 'served'"
                           class="w-3 h-3 text-white"
                           fill="none"
                           stroke="currentColor"
                           viewBox="0 0 24 24">
                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2.5"
                              d="M5 13l4 4L19 7" />
                      </svg>
                    </button>

                    <div class="flex-1 min-w-0">
                      <div class="flex items-start justify-between gap-2">
                        <div>
                          <div class="text-sm font-semibold text-gray-800"
                               :class="{ 'line-through text-gray-400': item.status === 'served' }"
                               x-text="item.item_name"></div>
                          <div class="text-xs text-gray-500 mt-0.5"
                               x-text="`Rp ${Number(item.price).toLocaleString('id-ID')}`"></div>
                        </div>
                        <span class="shrink-0 text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full font-semibold"
                              x-text="`x${item.quantity}`"></span>
                      </div>

                      <div class="flex items-center justify-between gap-2 mt-2">
                        <span class="text-[11px] font-medium uppercase tracking-wide text-slate-500"
                              x-text="item.preparation_location ? item.preparation_location : 'service'"></span>

                        <button x-show="item.status !== 'served'"
                                @click="checkItem(item.id)"
                                :disabled="loading"
                                class="flex items-center gap-1 text-slate-700 hover:text-slate-900 disabled:opacity-50 transition text-xs font-medium whitespace-nowrap">
                          <span x-text="loading ? 'Updating...' : 'Check'"></span>
                        </button>
                      </div>
                    </div>
                  </div>
                </template>
              </div>
            </div>

            <div class="px-4 py-3 border-t border-gray-100">
              <button @click="checkAll()"
                      :disabled="loading || orderStatus === 'completed'"
                      :class="orderStatus === 'completed' ? 'bg-gray-100 text-gray-400 cursor-not-allowed opacity-60' : 'bg-slate-800 hover:bg-slate-700 text-white'"
                      class="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition">
                <svg class="w-4 h-4"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span x-text="loading ? 'Processing...' : 'Check All'"></span>
              </button>
            </div>
          </div>
        @endforeach
      </div>
    @endif
  </div>
</x-app-layout>
