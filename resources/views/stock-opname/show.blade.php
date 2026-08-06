<x-app-layout title="Detail Stock Opname">
  <div class="p-4 sm:p-6">
    <!-- Header -->
    <div class="flex items-start justify-between mb-6">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <a href="{{ route('admin.stock-opname.history') }}"
             class="text-gray-400 hover:text-gray-600 transition">
            <svg class="w-5 h-5"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M15 19l-7-7 7-7" />
            </svg>
          </a>
          <h1 class="text-2xl font-bold text-gray-900">Detail Stock Opname</h1>
          @if ($stockOpname->status === 'completed')
            <span class="px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-xs font-medium">Selesai</span>
          @else
            <span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 rounded-full text-xs font-medium">Draft</span>
          @endif
        </div>
        <p class="text-sm text-gray-500">
          {{ $stockOpname->opname_date->translatedFormat('d F Y') }}
          &bull; Petugas: {{ $stockOpname->officer_name }}
        </p>
      </div>
    </div>

    <!-- Info Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <p class="text-xs text-gray-500 mb-1">Total Produk</p>
        <p class="text-2xl font-bold text-gray-800">{{ $stockOpname->items->count() }}</p>
      </div>
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <p class="text-xs text-gray-500 mb-1">Sudah Dihitung</p>
        <p class="text-2xl font-bold text-teal-600">{{ $stockOpname->counted_items_count }}</p>
      </div>
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <p class="text-xs text-gray-500 mb-1">Ada Selisih</p>
        <p class="text-2xl font-bold text-orange-500">{{ $stockOpname->discrepant_items_count }}</p>
      </div>
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <p class="text-xs text-gray-500 mb-1">Total Selisih</p>
        <p class="text-2xl font-bold text-red-600">{{ $stockOpname->total_discrepancy }}</p>
      </div>
    </div>

    <!-- Meta Info -->
    @if ($stockOpname->notes || $stockOpname->adjustedBy)
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6 text-sm text-gray-600 flex flex-wrap gap-6">
        @if ($stockOpname->notes)
          <div>
            <span class="font-medium text-gray-700">Catatan:</span> {{ $stockOpname->notes }}
          </div>
        @endif
        @if ($stockOpname->adjustedBy)
          <div>
            <span class="font-medium text-gray-700">Diselesaikan oleh:</span>
            {{ $stockOpname->adjustedBy->name }}
            @if ($stockOpname->adjusted_at)
              pada {{ $stockOpname->adjusted_at->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }}
            @endif
          </div>
        @endif
      </div>
    @endif

    <!-- Items Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-gray-600 text-xs uppercase tracking-wider">
            <tr>
              <th class="px-4 py-3 text-left w-10">No</th>
              <th class="px-4 py-3 text-left">Produk</th>
              <th class="px-4 py-3 text-left">Kategori</th>
              <th class="px-4 py-3 text-center">Satuan</th>
              <th class="px-4 py-3 text-center">Stock Sistem</th>
              <th class="px-4 py-3 text-center">Stock Fisik</th>
              <th class="px-4 py-3 text-center">Selisih</th>
              <th class="px-4 py-3 text-left">Catatan</th>
            </tr>
          </thead>
          <tbody>
            @php $no = 1; @endphp
            @foreach ($stockOpname->items as $item)
              @php
                $diff = $item->difference;
              @endphp
              <tr class="border-t border-gray-100 hover:bg-gray-50">
                <td class="px-4 py-3 text-gray-500">{{ $no++ }}</td>
                <td class="px-4 py-3 font-medium text-gray-900">{{ $item->inventoryItem->name }}</td>
                <td class="px-4 py-3">
                  @php
                    $cat = $item->inventoryItem->category_type;
                    $catClass = match ($cat) {
                        'spices' => 'bg-orange-100 text-orange-700',
                        'spirits' => 'bg-purple-100 text-purple-700',
                        'beverage' => 'bg-blue-100 text-blue-700',
                        'dairy' => 'bg-green-100 text-green-700',
                        'condiments' => 'bg-yellow-100 text-yellow-700',
                        default => 'bg-gray-100 text-gray-700',
                    };
                  @endphp
                  <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $catClass }}">
                    {{ ucfirst($cat) }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center text-gray-600">{{ $item->inventoryItem->unit }}</td>
                <td class="px-4 py-3 text-center font-mono text-gray-700">{{ $item->system_stock }}</td>
                <td class="px-4 py-3 text-center font-mono text-gray-700">
                  {{ $item->physical_stock ?? '—' }}
                </td>
                <td class="px-4 py-3 text-center font-mono font-semibold">
                  @if ($item->physical_stock !== null)
                    <span class="{{ $diff > 0 ? 'text-green-600' : ($diff < 0 ? 'text-red-600' : 'text-gray-500') }}">
                      {{ $diff > 0 ? '+' : '' }}{{ $diff }}
                    </span>
                  @else
                    <span class="text-gray-300">—</span>
                  @endif
                </td>
                <td class="px-4 py-3 text-gray-500 text-xs">{{ $item->notes ?? '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</x-app-layout>
