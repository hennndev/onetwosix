{{-- Shared: active tables stats + top spenders. Rendered server-side for both the full page and the realtime poll. --}}
<div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2">
  <div class="rounded-xl border border-gray-200 bg-white p-6">
    <div class="flex items-center gap-4">
      <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-green-100">
        <svg class="h-6 w-6 text-green-600"
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
  <div class="rounded-xl border border-gray-200 bg-white p-6">
    <div class="flex items-center gap-4">
      <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-100">
        <svg class="h-6 w-6 text-blue-600"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <div>
        <p class="text-sm text-gray-500">Total Revenue</p>
        <p class="text-2xl font-bold text-slate-800">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</p>
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
