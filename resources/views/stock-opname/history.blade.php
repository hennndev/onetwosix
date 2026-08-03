<x-app-layout title="Riwayat Stock Opname">
  <div class="p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Riwayat Stock Opname</h1>
        <p class="text-sm text-gray-500">Daftar semua stock opname yang pernah dilakukan</p>
      </div>
      <a href="{{ route('admin.stock-opname.index') }}"
         class="px-4 py-2 bg-teal-500 text-white rounded-lg hover:bg-teal-600 transition flex items-center gap-2">
        <svg class="w-4 h-4"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 4v16m8-8H4" />
        </svg>
        Stock Opname Baru
      </a>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      @if ($opnames->isEmpty())
        <div class="p-8 text-center text-gray-400">
          <svg class="w-12 h-12 mx-auto mb-3 text-gray-300"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
          </svg>
          <p>Belum ada riwayat stock opname.</p>
        </div>
      @else
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 text-xs uppercase tracking-wider">
              <tr>
                <th class="px-4 py-3 text-left">Tanggal</th>
                <th class="px-4 py-3 text-left">Petugas</th>
                <th class="px-4 py-3 text-center">Produk</th>
                <th class="px-4 py-3 text-center">Status</th>
                <th class="px-4 py-3 text-left">Diselesaikan Oleh</th>
                <th class="px-4 py-3 text-left">Catatan</th>
                <th class="px-4 py-3 text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($opnames as $opname)
                <tr class="border-t border-gray-100 hover:bg-gray-50">
                  <td class="px-4 py-3 font-medium text-gray-900">
                    {{ $opname->opname_date->translatedFormat('d M Y') }}
                  </td>
                  <td class="px-4 py-3 text-gray-700">{{ $opname->officer_name }}</td>
                  <td class="px-4 py-3 text-center text-gray-600">{{ $opname->items_count }}</td>
                  <td class="px-4 py-3 text-center">
                    @if ($opname->status === 'completed')
                      <span class="px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-xs font-medium">Selesai</span>
                    @else
                      <span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 rounded-full text-xs font-medium">Draft</span>
                    @endif
                  </td>
                  <td class="px-4 py-3 text-gray-600">
                    {{ $opname->adjustedBy?->name ?? '—' }}
                    @if ($opname->adjusted_at)
                      <br><span class="text-xs text-gray-400">{{ $opname->adjusted_at->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }}</span>
                    @endif
                  </td>
                  <td class="px-4 py-3 text-gray-500 text-xs max-w-xs truncate">{{ $opname->notes ?? '—' }}</td>
                  <td class="px-4 py-3 text-center">
                    <a href="{{ route('admin.stock-opname.show', $opname) }}"
                       class="inline-flex items-center gap-1 px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-xs font-medium">
                      <svg class="w-3.5 h-3.5"
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
                      Detail
                    </a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        @if ($opnames->hasPages())
          <div class="px-4 py-3 border-t border-gray-100">
            {{ $opnames->links() }}
          </div>
        @endif
      @endif
    </div>
  </div>
</x-app-layout>
