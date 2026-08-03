<x-app-layout title="Kitchen Display">
  <div class="p-6"
       x-data="kitchenOrdersApp()"
       x-init="init()">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-orange-500 rounded-xl flex items-center justify-center">
          <svg class="w-5 h-5 text-white"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
          </svg>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Kitchen</h1>
          <p class="text-sm text-gray-500">Monitor dan kelola order makanan secara real-time</p>
        </div>
      </div>
      <button @click="fetchOrders()"
              :disabled="isLoading"
              class="flex items-center gap-2 bg-orange-500 hover:bg-orange-600 disabled:opacity-50 text-white px-4 py-2 rounded-xl font-medium transition text-sm">
        <svg class="w-4 h-4"
             :class="{ 'animate-spin': isLoading }"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        Refresh
      </button>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
      <div class="flex items-center gap-2">
        <button @click="activeTab = 'orders'"
                :class="activeTab === 'orders' ? 'bg-slate-800 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'"
                class="px-4 py-2 rounded-xl text-sm font-medium transition">
          Order
        </button>
        <button @click="activeTab = 'end-day'"
                :class="activeTab === 'end-day' ? 'bg-orange-500 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'"
                class="px-4 py-2 rounded-xl text-sm font-medium transition">
          End Day
        </button>
        <button @click="activeTab = 'history'"
                :class="activeTab === 'history' ? 'bg-orange-500 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'"
                class="px-4 py-2 rounded-xl text-sm font-medium transition">
          History
        </button>
      </div>

      <!-- Area Filter Pills -->
      @if (($areas ?? collect())->count() > 1)
        <div class="inline-flex items-center gap-1.5 p-1 bg-white rounded-xl border border-gray-200 shadow-sm">
          <a href="{{ route('admin.kitchen.index', array_merge(request()->query(), ['area_id' => 'all'])) }}"
             class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ empty($selectedAreaId) ? 'bg-orange-500 text-white shadow-xs' : 'text-gray-600 hover:bg-gray-100' }}">
            Semua Area
          </a>
          @foreach (($areas ?? []) as $area)
            <a href="{{ route('admin.kitchen.index', array_merge(request()->query(), ['area_id' => $area->id])) }}"
               class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ (int) ($selectedAreaId ?? 0) === (int) $area->id ? 'bg-orange-500 text-white shadow-xs' : 'text-gray-600 hover:bg-gray-100' }}">
              {{ $area->name }}
            </a>
          @endforeach
        </div>
      @endif
    </div>

    <!-- Stats -->
    <div x-show="activeTab === 'orders'"
         class="grid grid-cols-4 gap-4 mb-6">
      <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
        <p class="text-3xl font-bold text-gray-900"
           x-text="stats.total"></p>
        <p class="text-sm text-gray-500 mt-1">Total Order</p>
      </div>
      <div class="bg-red-50 border border-red-200 rounded-xl p-4 shadow-sm">
        <p class="text-3xl font-bold text-red-700"
           x-text="stats.baru"></p>
        <p class="text-sm text-red-600 mt-1">Baru</p>
      </div>
      <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 shadow-sm">
        <p class="text-3xl font-bold text-yellow-800"
           x-text="stats.proses"></p>
        <p class="text-sm text-yellow-700 mt-1">Sedang Dimasak</p>
      </div>
      <div class="bg-green-50 border border-green-200 rounded-xl p-4 shadow-sm">
        <p class="text-3xl font-bold text-green-700"
           x-text="stats.selesai"></p>
        <p class="text-sm text-green-600 mt-1">Siap Saji</p>
      </div>
    </div>

    <!-- Tabs -->
    <div x-show="activeTab === 'orders'"
         class="flex items-center gap-2 mb-6">
      <button @click="filterByStatus(null)"
              :class="currentStatus === null ? 'bg-slate-800 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'"
              class="px-4 py-2 rounded-xl text-sm font-medium transition">
        Semua (<span x-text="stats.total"></span>)
      </button>
      <button @click="filterByStatus('proses')"
              :class="currentStatus === 'proses' ? 'bg-orange-500 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'"
              class="px-4 py-2 rounded-xl text-sm font-medium transition">
        ⏳ Sedang Dimasak (<span x-text="stats.proses"></span>)
      </button>
      <button @click="filterByStatus('selesai')"
              :class="currentStatus === 'selesai' ? 'bg-green-500 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'"
              class="px-4 py-2 rounded-xl text-sm font-medium transition">
        ✅ Siap Saji (<span x-text="stats.selesai"></span>)
      </button>
    </div>

    <!-- Empty State -->
    <div x-show="activeTab === 'orders' && orders.length === 0 && !isLoading"
         class="bg-orange-50 border-2 border-dashed border-orange-200 rounded-2xl p-16 text-center">
      <div class="w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-orange-500"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
        </svg>
      </div>
      <h3 class="text-lg font-bold text-gray-900 mb-1">Tidak Ada Order</h3>
      <p class="text-gray-500 text-sm">Belum ada order makanan untuk diproses</p>
    </div>

    <!-- Order Cards Grid -->
    <div x-show="activeTab === 'orders' && orders.length > 0"
         class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <template x-for="order in orders"
                :key="order.id">
        <div class="bg-white rounded-2xl border-2 flex flex-col transition"
             :class="{
                 'border-red-300': order.status === 'baru',
                 'border-yellow-300': order.status === 'proses',
                 'border-green-300': order.status === 'selesai',
             }">

          <!-- Card Header -->
          <div class="px-4 pt-4 pb-3 border-b border-gray-100">
            <div class="flex items-start justify-between gap-2">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-bold text-gray-900 text-sm"
                      x-text="order.order_number"></span>
                <span class="text-xs font-bold px-2 py-0.5 rounded-full"
                      :class="{
                          'bg-red-100 text-red-700': order.status === 'baru',
                          'bg-yellow-100 text-yellow-700': order.status === 'proses',
                          'bg-green-100 text-green-700': order.status === 'selesai',
                      }"
                      x-text="order.status === 'baru' ? 'BARU' : order.status === 'proses' ? 'PROSES' : 'SIAP'">
                </span>
              </div>
              <span class="text-sm font-bold text-gray-700 shrink-0"
                    x-text="order.progress + '%'"></span>
            </div>
            <div class="flex items-center gap-3 mt-1.5 text-xs text-gray-400">
              <span x-text="order.created_at"></span>
              <span x-text="getCompletedCount(order) + '/' + order.items.length + ' food'"></span>
            </div>
          </div>

          <!-- Customer Info -->
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
              <span class="text-sm font-semibold text-gray-800"
                    x-text="order.customer?.name ?? 'Walk-in'"></span>
            </div>
            <div x-show="order.customer?.phone"
                 class="flex items-center gap-2 mb-1">
              <svg class="w-3.5 h-3.5 text-gray-400 shrink-0"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
              </svg>
              <span class="text-xs text-gray-500"
                    x-text="order.customer?.phone"></span>
            </div>
            <div x-show="order.table"
                 class="flex items-center gap-2 mt-1">
              <svg class="w-3.5 h-3.5 text-orange-500 shrink-0"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
              </svg>
              <span class="text-xs font-semibold text-orange-600"
                    x-text="(order.table?.area?.name ?? '') + ' ' + (order.table?.table_number ?? '')"></span>
            </div>
          </div>

          <!-- Items -->
          <div class="px-4 pt-3 pb-2 flex-1">
            <div class="flex items-center gap-2 mb-2">
              <svg class="w-3.5 h-3.5 text-orange-500"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
              </svg>
              <span class="text-xs font-bold text-orange-600 uppercase tracking-wide">Food Items (<span x-text="order.items.length"></span>)</span>
            </div>
            <div class="space-y-1.5">
              <template x-for="item in order.items"
                        :key="item.id">
                <div class="flex items-center gap-2.5 p-2.5 rounded-xl border transition"
                     :class="item.is_completed ? 'bg-green-50 border-green-100' : 'bg-gray-50 border-gray-100'">
                  <button @click="toggleItem(item.id, order.id)"
                          :disabled="processingItemId === item.id"
                          class="w-5 h-5 rounded flex items-center justify-center shrink-0 transition disabled:opacity-50"
                          :class="item.is_completed ? 'bg-green-500' : 'bg-gray-300 hover:bg-gray-400'">
                    <svg x-show="item.is_completed"
                         class="w-3 h-3 text-white"
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24">
                      <path stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="3"
                            d="M5 13l4 4L19 7" />
                    </svg>
                    <svg x-show="processingItemId === item.id"
                         class="w-3 h-3 text-gray-600 animate-spin"
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
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                  </button>
                  <div class="flex items-center gap-2 flex-1 min-w-0">
                    <span class="w-6 h-6 rounded-lg bg-orange-100 text-orange-700 text-xs font-bold flex items-center justify-center shrink-0"
                          x-text="item.quantity + 'x'"></span>
                    <span class="text-sm font-medium text-gray-800 truncate"
                          :class="{ 'line-through text-gray-400': item.is_completed }"
                          x-text="item.item_name ?? item.recipe_name ?? 'Unknown'"></span>
                  </div>
                </div>
              </template>
            </div>
          </div>

          <!-- Action Button -->
          <div class="px-4 pb-4 pt-2">
            <button @click="completeAll(order.id)"
                    :disabled="processingOrderId === order.id || order.status === 'selesai'"
                    :class="order.status === 'selesai' ? 'bg-green-100 text-green-600 cursor-default' : 'bg-orange-500 hover:bg-orange-600 text-white'"
                    class="w-full py-2.5 rounded-xl font-semibold text-sm transition disabled:opacity-60 flex items-center justify-center gap-2">
              <svg x-show="processingOrderId === order.id"
                   class="w-4 h-4 animate-spin"
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
                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <svg x-show="processingOrderId !== order.id"
                   class="w-4 h-4"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span x-text="processingOrderId === order.id ? 'Memproses...' : (order.status === 'selesai' ? 'Sudah Siap' : 'Tandai Semua Siap')"></span>
            </button>
          </div>
        </div>
      </template>
    </div>

    <div x-show="activeTab === 'end-day'"
         class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
      <h2 class="text-lg font-semibold text-gray-900">End Day Kitchen</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="rounded-xl border border-orange-200 bg-orange-50 p-4">
          <p class="text-sm text-orange-700">Total Item Kitchen (Berjalan)</p>
          <p class="text-3xl font-bold text-orange-800 mt-1">{{ number_format((int) ($kitchenEndDayPreview['total_items'] ?? 0), 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
          <p class="text-sm text-gray-600">Last Synced</p>
          <p class="text-lg font-semibold text-gray-900 mt-1">{{ $kitchenEndDayPreview['last_synced_at']?->format('d/m/Y H:i') ?? '-' }}</p>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <form action="{{ route('admin.kitchen.end-day.sync-snapshot') }}"
              method="POST">
          @csrf
          @if(!empty($selectedAreaId))
            <input type="hidden" name="area_id" value="{{ $selectedAreaId }}">
          @endif
          <button type="submit"
                  class="inline-flex items-center rounded-xl bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200 transition">
            Sync Snapshot
          </button>
        </form>
        <form action="{{ route('admin.kitchen.end-day') }}"
              x-ref="submitEndDayKitchenForm"
              method="POST">
          @csrf
          @if(!empty($selectedAreaId))
            <input type="hidden" name="area_id" value="{{ $selectedAreaId }}">
          @endif
          <button type="button"
                  @click="openSubmitEndDayKitchenModal()"
                  class="inline-flex items-center rounded-xl bg-orange-500 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600 transition">
            Submit End Day Kitchen {{ !empty($selectedAreaId) ? '('.($areas->firstWhere('id', $selectedAreaId)?->name ?? '').')' : '' }}
          </button>
        </form>
      </div>

      <div class="rounded-xl border border-orange-200 bg-orange-50/40 p-4">
        <h3 class="text-sm font-semibold text-orange-800">Preview Snapshot Kitchen</h3>
        <p class="text-xs text-orange-700 mt-1">Daftar item dan quantity yang akan masuk ke End Day saat ini.</p>

        <div class="mt-3 space-y-2">
          @forelse (($kitchenEndDayPreview['items'] ?? []) as $item)
            <div class="flex items-center justify-between rounded-lg border border-orange-100 bg-white px-3 py-2">
              <span class="text-sm text-gray-800">{{ $item['name'] }}</span>
              <span class="text-sm font-semibold text-orange-700">{{ number_format((int) $item['quantity'], 0, ',', '.') }}</span>
            </div>
          @empty
            <p class="text-sm text-gray-500">Belum ada item pada snapshot kitchen.</p>
          @endforelse
        </div>
      </div>
    </div>

    <div x-show="showSubmitEndDayKitchenModal"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         style="display: none;"
         class="fixed inset-0 z-[90] bg-black/40 flex items-center justify-center p-4"
         @click.self="closeSubmitEndDayKitchenModal()">
      <div class="w-full max-w-md bg-white rounded-2xl border border-gray-200 shadow-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
          <h3 class="text-lg font-semibold text-gray-900">Konfirmasi Submit End Day Kitchen</h3>
          <p class="text-sm text-gray-500 mt-1">Pastikan snapshot sudah sesuai. Data yang disubmit tidak bisa diubah.</p>
        </div>
        <div class="px-5 py-4 flex items-center justify-end gap-2">
          <button type="button"
                  @click="closeSubmitEndDayKitchenModal()"
                  class="inline-flex items-center rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition">
            Batal
          </button>
          <button type="button"
                  @click="confirmSubmitEndDayKitchen()"
                  class="inline-flex items-center rounded-xl bg-orange-500 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600 transition">
            Ya, Submit End Day
          </button>
        </div>
      </div>
    </div>

    <div x-show="activeTab === 'history'"
         class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900">History End Day Kitchen</h2>
        <p class="text-xs text-gray-500 mt-1">Klik baris history untuk melihat detail item dan quantity.</p>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">End Day</th>
              <th class="px-5 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Total Item</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Last Synced</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-100">
            @forelse ($kitchenRecapHistories as $history)
              <tr @click="openHistoryDetail({{ $history->id }})"
                  class="cursor-pointer hover:bg-orange-50 transition">
                <td class="px-5 py-3 text-sm text-gray-800">{{ $history->end_day?->format('d/m/Y') ?? '-' }}</td>
                <td class="px-5 py-3 text-sm text-gray-900 text-right font-semibold">{{ number_format((int) ($history->resolved_total_items ?? $history->total_items), 0, ',', '.') }}</td>
                <td class="px-5 py-3 text-sm text-gray-600">{{ $history->last_synced_at?->format('d/m/Y H:i') ?? '-' }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="3"
                    class="px-5 py-8 text-center text-sm text-gray-500">Belum ada history end day kitchen.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div x-show="showHistoryDetailModal"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         style="display: none;"
         class="fixed inset-0 z-[80] bg-black/40 flex items-center justify-center p-4"
         @click.self="closeHistoryDetail()">
      <div class="w-full max-w-lg max-h-[85vh] bg-white rounded-2xl border border-gray-200 shadow-xl overflow-hidden flex flex-col">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
          <div>
            <h3 class="text-lg font-semibold text-gray-900">Detail Item Kitchen</h3>
            <p class="text-xs text-gray-500"
               x-text="selectedHistoryDetail ? ('End Day ' + selectedHistoryDetail.end_day) : ''"></p>
          </div>
          <button type="button"
                  @click="closeHistoryDetail()"
                  class="text-gray-500 hover:text-gray-700 transition">
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
        <div class="p-5 overflow-y-auto">
          <div x-show="selectedHistoryDetail && selectedHistoryDetail.items.length > 0"
               class="space-y-2">
            <template x-for="item in (selectedHistoryDetail?.items || [])"
                      :key="`${item.name}-${item.quantity}`">
              <div class="flex items-center justify-between rounded-xl border border-gray-200 px-3 py-2.5">
                <p class="text-sm text-gray-800"
                   x-text="item.name"></p>
                <p class="text-sm font-semibold text-orange-600"
                   x-text="item.quantity"></p>
              </div>
            </template>
          </div>
          <p x-show="selectedHistoryDetail && selectedHistoryDetail.items.length === 0"
             class="text-sm text-gray-500 text-center py-6">
            Tidak ada detail item untuk end day ini.
          </p>

          <div class="mt-4 flex justify-end gap-2">
            <a :href="selectedHistoryDetail?.preview_url ?? '#'"
               target="_blank"
               rel="noopener noreferrer"
               class="inline-flex items-center rounded-xl border border-orange-200 bg-white px-4 py-2 text-sm font-semibold text-orange-600 hover:bg-orange-50 transition"
               :class="!selectedHistoryDetail ? 'pointer-events-none opacity-50' : ''">
              Preview Print
            </a>

            <button type="button"
                    @click="reprintHistoryDetail()"
                    :disabled="isReprintingHistory || !selectedHistoryDetail"
                    class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600 disabled:opacity-50 disabled:cursor-not-allowed transition">
              <svg x-show="isReprintingHistory"
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
              <span x-text="isReprintingHistory ? 'Reprint...' : 'Reprint'">Reprint</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Toast Notification -->
    <div x-show="showToast"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform translate-y-2"
         x-transition:enter-end="opacity-100 transform translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 transform translate-y-0"
         x-transition:leave-end="opacity-0 transform translate-y-2"
         @click="showToast = false"
         class="fixed bottom-4 right-4 z-[70] cursor-pointer">
      <div :class="toastType === 'success' ? 'bg-green-500' : 'bg-red-500'"
           class="px-6 py-3 rounded-lg shadow-lg text-white font-medium flex items-center gap-2">
        <svg x-show="toastType === 'success'"
             class="w-5 h-5"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M5 13l4 4L19 7" />
        </svg>
        <svg x-show="toastType === 'error'"
             class="w-5 h-5"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M6 18L18 6M6 6l12 12" />
        </svg>
        <span x-text="toastMessage"></span>
      </div>
    </div>
  </div>

  <script>
    function kitchenOrdersApp() {
      return {
        orders: {!! json_encode(
            $orders->map(function ($order) {
                    $sessionCustomer = $order->order?->tableSession?->customer;
                    $customerName = $order->customer?->user?->name ?? ($sessionCustomer?->name ?? 'Walk-in');
                    $customerPhone = $order->customer?->profile?->phone ?? ($sessionCustomer?->profile?->phone ?? null);
        
                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'status' => $order->status,
                        'progress' => $order->progress,
                        'created_at' => $order->created_at->format('d M Y H:i'),
                        'customer' => [
                            'id' => $order->customer?->id,
                            'name' => $customerName,
                            'phone' => $customerPhone,
                        ],
                        'table' => $order->table
                            ? [
                                'id' => $order->table->id,
                                'table_number' => $order->table->number ?? $order->table->table_number,
                                'area' => $order->table->area
                                    ? [
                                        'id' => $order->table->area->id,
                                        'name' => $order->table->area->name,
                                    ]
                                    : null,
                            ]
                            : null,
                        'items' => $order->items->map(function ($item) {
                                return [
                                    'id' => $item->id,
                                    'recipe_id' => $item->bom_recipe_id,
                                    'item_name' => $item->inventoryItem?->pos_name ?? ($item->inventoryItem?->name ?? 'Unknown'),
                                    'quantity' => $item->quantity,
                                    'is_completed' => $item->is_completed,
                                ];
                            })->values(),
                    ];
                })->values(),
        ) !!},
        stats: {!! json_encode(['total' => $totalOrders, 'baru' => $baruOrders, 'proses' => $prosesOrders, 'selesai' => $selesaiOrders]) !!},
        activeTab: 'orders',
        currentStatus: null,
        isLoading: false,
        processingItemId: null,
        processingOrderId: null,
        showToast: false,
        toastMessage: '',
        toastType: 'success',
        pollInterval: null,
        showSubmitEndDayKitchenModal: false,
        showHistoryDetailModal: false,
        selectedHistoryDetail: null,
        isReprintingHistory: false,
        historyDetails: {!! json_encode(
            $kitchenRecapHistories->map(function ($history) {
                    return [
                        'id' => $history->id,
                        'end_day' => $history->end_day?->format('d/m/Y') ?? '-',
                        'total_items' => (int) ($history->resolved_total_items ?? $history->total_items),
                        'last_synced_at' => $history->last_synced_at?->format('d/m/Y H:i') ?? '-',
                        'preview_url' => route('admin.kitchen.end-day.preview', $history),
                        'items' => $history->endayItems->map(function ($item) {
                                return [
                                    'name' => $item->inventoryItem?->pos_name ?? ($item->inventoryItem?->name ?? 'Unknown'),
                                    'quantity' => (int) $item->quantity,
                                ];
                            })->values(),
                    ];
                })->values(),
        ) !!},

        init() {
          // Start polling for updates every 30 seconds
          this.pollInterval = setInterval(() => {
            this.fetchOrders(true);
          }, 30000);
        },

        getCompletedCount(order) {
          return order.items.filter(item => item.is_completed).length;
        },

        async fetchOrders(silent = false) {
          if (this.isLoading && !silent) return;

          if (!silent) {
            this.isLoading = true;
          }

          try {
            const params = new URLSearchParams();
            if (this.currentStatus) {
              params.append('status', this.currentStatus);
            }

            const response = await fetch(`{{ route('admin.kitchen.fetch') }}?${params.toString()}`, {
              headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
              }
            });

            const data = await response.json();

            if (data.success) {
              this.orders = data.orders;
              this.stats = data.stats;
            }
          } catch (error) {
            console.error('Error fetching orders:', error);
          } finally {
            this.isLoading = false;
          }
        },

        filterByStatus(status) {
          this.currentStatus = status;
          this.fetchOrders();
        },

        openSubmitEndDayKitchenModal() {
          this.showSubmitEndDayKitchenModal = true;
        },

        closeSubmitEndDayKitchenModal() {
          this.showSubmitEndDayKitchenModal = false;
        },

        confirmSubmitEndDayKitchen() {
          this.closeSubmitEndDayKitchenModal();
          this.$refs.submitEndDayKitchenForm?.submit();
        },

        openHistoryDetail(historyId) {
          const selected = this.historyDetails.find(history => history.id === historyId);
          if (!selected) {
            return;
          }

          this.selectedHistoryDetail = selected;
          this.showHistoryDetailModal = true;
        },

        closeHistoryDetail() {
          this.showHistoryDetailModal = false;
          this.selectedHistoryDetail = null;
        },

        async reprintHistoryDetail() {
          if (!this.selectedHistoryDetail || this.isReprintingHistory) {
            return;
          }

          this.isReprintingHistory = true;

          try {
            const response = await fetch(`{{ route('admin.kitchen.end-day.reprint', '__HISTORY_ID__') }}`.replace('__HISTORY_ID__', this.selectedHistoryDetail.id), {
              method: 'POST',
              headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
              }
            });

            const data = await response.json();

            if (response.ok && data.success) {
              this.showToastMessage(data.message || 'Reprint berhasil diproses.', 'success');
            } else {
              this.showToastMessage(data.message || 'Reprint gagal diproses.', 'error');
            }
          } catch (error) {
            console.error('Error reprinting kitchen history:', error);
            this.showToastMessage('Reprint gagal diproses.', 'error');
          } finally {
            this.isReprintingHistory = false;
          }
        },

        async toggleItem(itemId, orderId) {
          if (this.processingItemId) return;
          this.processingItemId = itemId;

          try {
            const response = await fetch(`{{ route('admin.kitchen.toggle-item', '__ITEM_ID__') }}`.replace('__ITEM_ID__', itemId), {
              method: 'PATCH',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
              }
            });

            const data = await response.json();

            if (data.success) {
              // Update the specific order in the list
              const orderIndex = this.orders.findIndex(o => o.id === orderId);
              if (orderIndex !== -1) {
                this.orders[orderIndex] = data.order;
              }
              this.showToastMessage(data.message, 'success');
            } else {
              this.showToastMessage(data.message || 'Failed to update item', 'error');
            }
          } catch (error) {
            console.error('Error toggling item:', error);
            this.showToastMessage('Failed to update item status', 'error');
          } finally {
            this.processingItemId = null;
          }
        },

        async completeAll(orderId) {
          if (this.processingOrderId) return;
          this.processingOrderId = orderId;

          try {
            const response = await fetch(`{{ route('admin.kitchen.complete-all', '__ORDER_ID__') }}`.replace('__ORDER_ID__', orderId), {
              method: 'PATCH',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
              }
            });

            const data = await response.json();

            if (data.success) {
              // Remove order from list (default view excludes selesai)
              this.orders = this.orders.filter(o => o.id !== orderId);
              // Update stats
              if (this.stats) {
                this.stats.proses = Math.max(0, (this.stats.proses || 0) - 1);
                this.stats.selesai = (this.stats.selesai || 0) + 1;
              }
              this.showToastMessage(data.message, 'success');
            } else {
              this.showToastMessage(data.message || 'Failed to complete order', 'error');
            }
          } catch (error) {
            console.error('Error completing order:', error);
            this.showToastMessage('Failed to complete order', 'error');
          } finally {
            this.processingOrderId = null;
          }
        },

        showToastMessage(message, type = 'success') {
          this.toastMessage = message;
          this.toastType = type;
          this.showToast = true;
          setTimeout(() => {
            this.showToast = false;
          }, 3000);
        },

        destroy() {
          if (this.pollInterval) {
            clearInterval(this.pollInterval);
          }
        }
      };
    }
  </script>
</x-app-layout>
