<x-app-layout>
  <div class="p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-3">
        <div class="w-12 h-12 bg-slate-800 rounded-xl flex items-center justify-center">
          <svg class="w-6 h-6 text-white"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Active Tables</h1>
          <p class="text-sm text-gray-500">Monitor meja yang sedang aktif</p>
        </div>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
      <div class="bg-white rounded-xl p-6 border border-gray-200">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
            <svg class="w-6 h-6 text-green-600"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <p class="text-sm text-gray-500">Total Active Tables</p>
            <p class="text-2xl font-bold text-slate-800">{{ $totalActiveSessions }}</p>
          </div>
        </div>
      </div>
    </div>

    @if (! empty($topSpenders))
      <div class="mb-6">
        <div class="mb-3 flex items-center justify-between">
          <div>
            <h2 class="text-lg font-bold text-gray-900">Top 3 Spender</h2>
            <p class="text-sm text-gray-500">Diurutkan berdasarkan subtotal realtime dari active tables.</p>
          </div>
          <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">
            Realtime
          </span>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
          @foreach ($topSpenders as $spender)
            @php
              $rankClasses = match ($spender['rank'] ?? 0) {
                  1 => 'border-amber-200 bg-amber-50/80',
                  2 => 'border-slate-200 bg-slate-50/80',
                  3 => 'border-orange-200 bg-orange-50/80',
                  default => 'border-gray-200 bg-white',
              };
              $badgeClasses = match ($spender['rank'] ?? 0) {
                  1 => 'bg-amber-500 text-white',
                  2 => 'bg-slate-500 text-white',
                  3 => 'bg-orange-500 text-white',
                  default => 'bg-gray-500 text-white',
              };
            @endphp
            <div class="rounded-2xl border p-5 shadow-sm {{ $rankClasses }}">
              <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                  <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl {{ $badgeClasses }} font-bold">
                    #{{ $spender['rank'] ?? '-' }}
                  </div>
                  <div class="min-w-0">
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-gray-500">Top Spender</p>
                    <p class="truncate text-base font-bold text-gray-900">{{ $spender['customer_name'] }}</p>
                  </div>
                </div>
                <div class="text-right">
                  <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500">Subtotal</p>
                  <p class="text-lg font-extrabold text-slate-900">Rp {{ number_format((float) ($spender['orders_subtotal'] ?? 0), 0, ',', '.') }}</p>
                </div>
              </div>
              <div class="mt-4 flex items-center justify-between text-sm text-gray-600">
                <span>Table {{ $spender['table_number'] ?? '-' }}</span>
                @if (filled($spender['area_name'] ?? null))
                  <span>{{ $spender['area_name'] }}</span>
                @endif
              </div>
            </div>
          @endforeach
        </div>
      </div>
    @endif

    <!-- Filters -->
    <div class="bg-white rounded-xl border border-gray-200 p-4 mb-6">
      <form method="GET"
            action="{{ route('admin.active-tables.index') }}"
            class="flex flex-wrap gap-4">
        <!-- Search -->
        <div class="flex-1 min-w-[200px]">
          <label class="block text-sm font-medium text-gray-700 mb-2">Cari</label>
          <input type="text"
                 name="search"
                 value="{{ request('search') }}"
                 placeholder="Session code, nomor meja, atau nama customer..."
                 class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>

        <!-- Area Filter -->
        <div class="w-48">
          <label class="block text-sm font-medium text-gray-700 mb-2">Area</label>
          <select name="area_id"
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <option value="">Semua Area</option>
            @foreach ($areas as $area)
              <option value="{{ $area->id }}"
                      {{ request('area_id') == $area->id ? 'selected' : '' }}>
                {{ $area->name }}
              </option>
            @endforeach
          </select>
        </div>

        <!-- Buttons -->
        <div class="flex items-end gap-2">
          <button type="submit"
                  class="px-4 py-2 bg-slate-800 text-white rounded-lg hover:bg-slate-900 transition">
            Filter
          </button>
          <a href="{{ route('admin.active-tables.index') }}"
             class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
            Reset
          </a>
        </div>
      </form>
    </div>

    <!-- Active Tables List -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Session</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Meja</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Area</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pax</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check-in</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bill Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grand Total</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            @forelse ($sessions as $session)
              <tr class="hover:bg-gray-50 transition">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                      <svg class="w-5 h-5 text-green-600"
                           fill="none"
                           stroke="currentColor"
                           viewBox="0 0 24 24">
                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                    </div>
                    <div class="ml-3">
                      <p class="text-sm font-medium text-gray-900">{{ $session->session_code }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="text-sm font-medium text-gray-900">{{ $session->table->table_number }}</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    {{ $session->table->area->name }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div>
                    <p class="text-sm font-medium text-gray-900">{{ $session->customer->name }}</p>
                    @if ($session->customer->profile)
                      <p class="text-xs text-gray-500">{{ $session->customer->profile->phone }}</p>
                    @endif
                  </div>
                </td>

                {{-- PAX --}}
                <td class="px-6 py-4 whitespace-nowrap"
                    x-data="paxEditor({{ $session->id }}, {{ $session->pax ?? 'null' }}, '{{ route('admin.active-tables.updatePax', $session) }}')">
                  <div x-show="!editing"
                       class="flex items-center gap-1.5">
                    <span class="text-sm font-semibold text-gray-900"
                          x-text="pax !== null ? pax + ' org' : '—'"></span>
                    <button @click="editing = true"
                            class="text-gray-400 hover:text-blue-600 transition"
                            title="Edit pax">
                      <svg class="w-3.5 h-3.5"
                           fill="none"
                           stroke="currentColor"
                           viewBox="0 0 24 24">
                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </button>
                  </div>
                  <div x-show="editing"
                       x-cloak
                       class="flex items-center gap-1">
                    <input type="number"
                           x-model="draft"
                           min="1"
                           class="w-16 px-2 py-1 text-sm border border-blue-400 rounded focus:outline-none focus:ring-1 focus:ring-blue-400"
                           @keydown.enter="save()"
                           @keydown.escape="editing = false">
                    <button @click="save()"
                            class="text-green-600 hover:text-green-800 transition">
                      <svg class="w-4 h-4"
                           fill="none"
                           stroke="currentColor"
                           viewBox="0 0 24 24">
                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M5 13l4 4L19 7" />
                      </svg>
                    </button>
                    <button @click="editing = false"
                            class="text-gray-400 hover:text-gray-600 transition">
                      <svg class="w-4 h-4"
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
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <p class="text-sm text-gray-900">{{ $session->checked_in_at->format('d M Y') }}</p>
                  <p class="text-xs text-gray-500">{{ $session->checked_in_at->format('H:i') }}</p>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="text-sm text-gray-900">
                    {{ $session->checked_in_at->diffForHumans(null, true) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  @if ($session->billing)
                    @php
                      $statusColors = [
                          'draft' => 'bg-gray-100 text-gray-800',
                          'finalized' => 'bg-yellow-100 text-yellow-800',
                          'paid' => 'bg-green-100 text-green-800',
                          'partially_paid' => 'bg-orange-100 text-orange-800',
                      ];
                      $statusLabels = [
                          'draft' => 'Draft',
                          'finalized' => 'Finalized',
                          'paid' => 'Paid',
                          'partially_paid' => 'Partially Paid',
                      ];
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$session->billing->billing_status] ?? 'bg-gray-100 text-gray-800' }}">
                      {{ $statusLabels[$session->billing->billing_status] ?? $session->billing->billing_status }}
                    </span>
                  @else
                    <span class="text-xs text-gray-400">-</span>
                  @endif
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  @if ($session->billing)
                    <p class="text-sm font-semibold text-gray-900">Rp {{ number_format($session->billing->grand_total, 0, ',', '.') }}</p>
                    <p class="text-xs text-gray-500">Paid: Rp {{ number_format($session->billing->paid_amount, 0, ',', '.') }}</p>
                  @else
                    <span class="text-xs text-gray-400">-</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="9"
                    class="px-6 py-12 text-center">
                  <div class="flex flex-col items-center justify-center text-gray-400">
                    <svg class="w-16 h-16 mb-4"
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24">
                      <path stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                    <p class="text-lg font-medium">Tidak ada meja yang aktif</p>
                    <p class="text-sm">Semua meja sedang tidak digunakan</p>
                  </div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  @push('scripts')
    <script>
      function paxEditor(sessionId, initialPax, updateUrl) {
        return {
          editing: false,
          pax: initialPax,
          draft: initialPax ?? '',
          async save() {
            const val = parseInt(this.draft);
            if (!val || val < 1) return;
            const res = await fetch(updateUrl, {
              method: 'PATCH',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
              },
              body: JSON.stringify({
                pax: val
              }),
            });
            const data = await res.json();
            if (data.success) {
              this.pax = data.pax;
              this.editing = false;
            }
          },
        };
      }
    </script>
  @endpush
</x-app-layout>
