<div class="p-6">
  @if (session('success'))
    <div class="mb-4 px-4 py-3 bg-green-100 border border-green-400 text-green-700 rounded-lg">
      {{ session('success') }}
    </div>
  @endif

  @if ($errors->any())
    <div class="mb-4 px-4 py-3 bg-red-100 border border-red-400 text-red-700 rounded-lg">
      <ul class="list-disc list-inside">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Inventory Management</h1>
      <p class="text-sm text-gray-500">Kelola daftar produk dan stok gudang</p>
    </div>
    <button data-sync-btn
            onclick="syncFromAccurate()"
            class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors flex items-center justify-center gap-2 whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed">
      <span data-sync-icon
            class="flex items-center">
        <svg class="w-5 h-5"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
      </span>
      <span data-sync-text>Sync dari Accurate</span>
    </button>
    {{-- <button onclick="openModal('add')"
              class="px-4 py-2 bg-slate-800 text-white rounded-lg hover:bg-slate-900 transition flex items-center gap-2">
        <svg class="w-5 h-5"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 4v16m8-8H4" />
        </svg>
        Tambah Produk
      </button> --}}
  </div>

  <!-- Stats Cards -->
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white border border-gray-200 rounded-xl p-4">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm text-gray-500 font-medium">Total Produk</p>
          <p class="text-2xl font-bold text-gray-900">{{ $totalItems }}</p>
        </div>
        <div class="w-12 h-12 bg-slate-100 rounded-lg flex items-center justify-center">
          <svg class="w-6 h-6 text-slate-600"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
          </svg>
        </div>
      </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl p-4">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm text-gray-500 font-medium">Total Nilai Stok</p>
          <p class="text-2xl font-bold text-gray-900">Rp {{ number_format($totalStockValue, 0, ',', '.') }}</p>
        </div>
        <div class="w-12 h-12 bg-slate-100 rounded-lg flex items-center justify-center">
          <svg class="w-6 h-6 text-slate-600"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
          </svg>
        </div>
      </div>
    </div>


  </div>

  <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
    <form method="GET"
          action="{{ route('admin.inventory.index') }}"
          id="filterForm"
          class="p-4 space-y-3">
      <div class="relative">
        <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <input type="text"
               id="searchInput"
               name="search"
               value="{{ request('search') }}"
               placeholder="Cari produk berdasarkan nama, kode, atau kategori..."
               class="pl-10 pr-4 py-2 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent">
      </div>

      <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
        <select id="filterCategory"
                name="category"
                onchange="this.form.submit()"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500 focus:border-transparent">
          <option value="">Semua Kategori</option>
          @foreach ($categoryTypes as $type)
            <option value="{{ $type }}"
                    @selected(request('category') === $type)>{{ ucfirst($type) }}</option>
          @endforeach
        </select>

        <select id="filterStatus"
                name="status"
                onchange="this.form.submit()"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500 focus:border-transparent">
          <option value="">Semua Status</option>
          <option value="1"
                  @selected(request('status') === '1')>Active</option>
          <option value="0"
                  @selected(request('status') === '0')>Inactive</option>
        </select>

        <select id="filterItemType"
                name="item_group"
                onchange="this.form.submit()"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500 focus:border-transparent">
          <option value="">Semua Tipe</option>
          <option value="1"
                  @selected(request('item_group') === '1')>Item Group</option>
          <option value="0"
                  @selected(request('item_group') === '0')>Single Item</option>
        </select>

        <select name="per_page"
                onchange="this.form.submit()"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500 focus:border-transparent">
          @foreach ([10, 20, 50, 100] as $opt)
            <option value="{{ $opt }}"
                    @selected((int) request('per_page', 20) === $opt)>{{ $opt }} / halaman</option>
          @endforeach
        </select>

        <button type="button"
                id="lowStockBtn"
                onclick="toggleStockFilter()"
                class="px-3 py-2 text-sm font-medium rounded-lg border border-yellow-300 transition {{ request('stock_filter') === 'low' ? 'bg-yellow-500 text-white border-yellow-500 hover:bg-yellow-600' : 'text-yellow-700 bg-yellow-50 hover:bg-yellow-100' }}">
          Stok Menipis
        </button>
        <input type="hidden"
               name="stock_filter"
               id="stockFilterInput"
               value="{{ request('stock_filter') }}">
      </div>
    </form>
  </div>

  <!-- Actions Bar -->
  <div class="flex items-center justify-end mb-4">
    <button onclick="openThresholdModal()"
            class="px-4 py-2 text-yellow-600 border border-yellow-300 rounded-lg hover:bg-yellow-50 transition flex items-center gap-2">
      <svg class="w-4 h-4"
           fill="none"
           stroke="currentColor"
           viewBox="0 0 24 24">
        <path stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
      </svg>
      Edit Threshold Sekaligus
    </button>
  </div>

  <!-- Table -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead class="bg-gray-50 border-b border-gray-200">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Produk</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipe Item</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stok</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stok vs Threshold</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200"
               id="itemTableBody">
          @foreach ($items as $item)
            <tr class="hover:bg-gray-50 transition item-row"
                data-item-id="{{ $item->id }}"
                data-item="{{ json_encode($item->only(['id', 'name', 'code', 'category_type', 'price', 'stock_quantity', 'threshold', 'unit', 'is_active'])) }}">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">{{ $item->name }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-xs font-mono text-gray-600">{{ $item->code }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 py-1 text-xs font-medium rounded 
                                    @if (strtolower($item->category_type) === 'spices') bg-orange-100 text-orange-700
                                    @elseif(strtolower($item->category_type) === 'spirits') bg-purple-100 text-purple-700
                                    @elseif(strtolower($item->category_type) === 'beverage') bg-blue-100 text-blue-700
                                    @elseif(strtolower($item->category_type) === 'dairy') bg-green-100 text-green-700
                                    @elseif(strtolower($item->category_type) === 'condiments') bg-yellow-100 text-yellow-700
                                    @else bg-gray-100 text-gray-700 @endif">
                  {{ ucfirst($item->category_type) }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                @php
                  $itemType = strtoupper((string) ($item->item_type ?: ($item->is_item_group ? 'GROUP' : 'INVENTORY')));
                @endphp
                @if ($itemType === 'GROUP')
                  <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold rounded-md bg-purple-100 text-purple-800 border border-purple-200">
                    <svg class="w-3.5 h-3.5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    GROUP
                  </span>
                @elseif ($itemType === 'NON_INVENTORY')
                  <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold rounded-md bg-amber-100 text-amber-800 border border-amber-200">
                    NON-INVENTORY
                  </span>
                @elseif ($itemType === 'SERVICE')
                  <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold rounded-md bg-rose-100 text-rose-800 border border-rose-200">
                    SERVICE
                  </span>
                @else
                  <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold rounded-md bg-cyan-100 text-cyan-800 border border-cyan-200">
                    INVENTORY
                  </span>
                @endif
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">Rp {{ number_format($item->price, 0, ',', '.') }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm">
                  <span class="@if ($item->isLowStock()) text-red-600 font-bold @else text-green-600 font-medium @endif">
                    {{ $item->stock_quantity }} {{ $item->unit }}
                  </span>
                </div>
              </td>
              <td class="px-6 py-4">
                @php
                  $pct = $item->threshold > 0 ? min(100, round(($item->stock_quantity / $item->threshold) * 100)) : 100;
                  $barColor = $pct <= 25 ? 'bg-red-500' : ($pct <= 75 ? 'bg-yellow-400' : 'bg-green-500');
                  $textColor = $pct <= 25 ? 'text-red-700' : ($pct <= 75 ? 'text-yellow-700' : 'text-green-700');
                @endphp
                <div class="min-w-[110px]">
                  <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-semibold {{ $textColor }}">{{ $pct }}%</span>
                    <span class="text-xs text-gray-400">min {{ $item->threshold }} {{ $item->unit }}</span>
                  </div>
                  <div class="w-full bg-gray-100 rounded-full h-1.5">
                    <div class="h-1.5 rounded-full {{ $barColor }}"
                         style="width: {{ $pct }}%"></div>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                @if ($item->is_active)
                  <span class="px-2 py-1 text-xs font-medium rounded bg-green-100 text-green-700">Active</span>
                @else
                  <span class="px-2 py-1 text-xs font-medium rounded bg-gray-100 text-gray-700">Inactive</span>
                @endif
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex gap-2">
                  <button onclick="fetchDetail({{ $item->id }}, '{{ addslashes($item->name) }}')"
                          class="p-1 text-gray-600 hover:text-indigo-600 transition"
                          title="Detail Accurate">
                    <svg class="w-5 h-5"
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24">
                      <path stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </button>
                  <form method="POST"
                        action="{{ route('admin.inventory.toggle-active', $item) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-2.5 py-1.5 rounded-lg border transition {{ $item->is_active ? 'border-green-200 bg-green-50 text-green-700 hover:bg-green-100' : 'border-gray-200 bg-gray-50 text-gray-700 hover:bg-gray-100' }}"
                            title="Toggle Active/Inactive">
                      <span class="inline-flex h-5 w-9 items-center rounded-full transition {{ $item->is_active ? 'bg-green-500 justify-end' : 'bg-gray-300 justify-start' }} px-0.5">
                        <span class="h-4 w-4 rounded-full bg-white"></span>
                      </span>
                      <span class="text-xs font-semibold">{{ $item->is_active ? 'Active' : 'Inactive' }}</span>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-6 py-4 border-t border-gray-200">
      <p class="text-sm text-gray-500">
        Menampilkan
        <span class="font-semibold text-gray-800">{{ $items->firstItem() ?? 0 }}</span>
        –
        <span class="font-semibold text-gray-800">{{ $items->lastItem() ?? 0 }}</span>
        dari
        <span class="font-semibold text-gray-800">{{ number_format($items->total(), 0, ',', '.') }}</span>
        produk
      </p>

      <div class="light-pagination">
        {{ $items->onEachSide(1)->links() }}
      </div>
    </div>
  </div>
</div>
