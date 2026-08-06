<x-app-layout title="Monitoring Meja Aktif">
  <div class="p-4 sm:p-6">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-800">
          <svg class="h-6 w-6 text-white"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Active Tables</h1>
          <p class="text-sm text-gray-500">Readonly monitor meja aktif tanpa aksi edit, hapus, atau close bill.</p>
        </div>
      </div>
      <div class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">
        Readonly
      </div>
    </div>

    <!-- Area Tabs (multi-area header) -->
    @if (($areas ?? collect())->count() > 1 && (! session('active_area_id') || session('active_area_id') === 'all'))
      <div class="mb-6">
        <div class="flex gap-2 overflow-x-auto pb-2">
          <a href="{{ route('admin.active-tables.readonly') }}"
             class="px-4 py-2 rounded-lg whitespace-nowrap transition {{ is_null($activeAreaId) ? 'bg-slate-800 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
            Semua Area
          </a>
          @foreach ($areas as $area)
            <a href="{{ route('admin.active-tables.readonly', ['area_id' => $area->id]) }}"
               class="px-4 py-2 rounded-lg whitespace-nowrap transition {{ $activeAreaId === $area->id ? 'bg-slate-800 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
              {{ $area->name }}
            </a>
          @endforeach
        </div>
      </div>
    @endif

    <!-- Realtime stats (polled) -->
    <div id="realtimeStats"
         x-data="realtimePoll({ url: '{{ route('admin.active-tables.readonly') }}', target: 'realtimeStats', interval: 15000 })">
      @include('active-tables._partials.stats')
    </div>

    <div class="mb-6 rounded-xl border border-gray-200 bg-white p-4">
      <form method="GET"
            action="{{ route('admin.active-tables.readonly') }}"
            class="flex flex-wrap gap-4">
        <div class="min-w-[200px] flex-1">
          <label class="mb-2 block text-sm font-medium text-gray-700">Cari</label>
          <input type="text"
                 name="search"
                 value="{{ request('search') }}"
                 placeholder="Session code, nomor meja, atau nama customer..."
                 class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-transparent focus:ring-2 focus:ring-blue-500">
        </div>

        @if (! is_null($activeAreaId))
          <input type="hidden"
                 name="area_id"
                 value="{{ $activeAreaId }}">
        @endif

        <div class="flex items-end gap-2">
          <button type="submit"
                  class="rounded-lg bg-slate-800 px-4 py-2 text-white transition hover:bg-slate-900">
            Filter
          </button>
          <a href="{{ route('admin.active-tables.readonly') }}"
             class="rounded-lg bg-gray-200 px-4 py-2 text-gray-700 transition hover:bg-gray-300">
            Reset
          </a>
        </div>
      </form>
    </div>

    <!-- Realtime sessions table (polled) -->
    @php
      $tableQuery = array_merge(['live' => 'table'], request()->only(['search', 'area_id']));
    @endphp
    <div id="realtimeTable"
         x-data="realtimePoll({ url: '{{ route('admin.active-tables.readonly', $tableQuery) }}', target: 'realtimeTable', interval: 30000 })">
      @include('active-tables._partials.table')
    </div>
  </div>
</x-app-layout>
