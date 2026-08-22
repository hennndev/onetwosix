<div id="thresholdModal"
     class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[85vh] flex flex-col">
    <div class="p-6 border-b border-gray-200 flex items-center justify-between">
      <div>
        <h3 class="text-lg font-bold text-gray-900">Edit Threshold Sekaligus</h3>
        <p class="text-sm text-gray-500">Atur batas minimum stok untuk setiap produk</p>
      </div>
      <button onclick="closeThresholdModal()"
              class="p-2 hover:bg-gray-100 rounded-lg transition">
        <svg class="w-5 h-5 text-gray-500"
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
    <div class="p-4 border-b border-gray-100">
      <input type="text"
             id="thresholdSearch"
             placeholder="Cari produk..."
             oninput="filterThresholdList()"
             class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-slate-500 focus:border-transparent">
    </div>
    <form id="thresholdForm"
          method="POST"
          action="{{ route('admin.inventory.updateThreshold') }}"
          class="flex flex-col flex-1 min-h-0">
      @csrf
      <div class="overflow-y-auto flex-1 divide-y divide-gray-100"
           id="thresholdList">
        @foreach ($allItems as $index => $item)
          <div class="flex items-center gap-4 px-6 py-3 hover:bg-gray-50 threshold-item"
               data-name="{{ strtolower($item->name) }}">
            <input type="hidden"
                   name="items[{{ $index }}][id]"
                   value="{{ $item->id }}">
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-900 truncate">{{ $item->name }}</p>
              <p class="text-xs text-gray-500">{{ $item->stock_quantity }} {{ $item->unit }} tersedia</p>
            </div>
            <div class="shrink-0 flex items-center gap-2">
              <span class="text-xs text-gray-500">Min stok:</span>
              <input type="number"
                     name="items[{{ $index }}][threshold]"
                     value="{{ $item->threshold }}"
                     min="0"
                     class="w-24 px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent text-center">
              <span class="text-xs text-gray-500 w-8">{{ $item->unit }}</span>
            </div>
          </div>
        @endforeach
      </div>
      <div class="p-4 border-t border-gray-200 flex gap-3">
        <button type="button"
                onclick="closeThresholdModal()"
                class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium">
          Batal
        </button>
        <button type="submit"
                class="flex-1 px-4 py-2 bg-slate-800 text-white rounded-xl hover:bg-slate-900 transition font-semibold">
          Simpan Semua
        </button>
      </div>
    </form>
  </div>
</div>
