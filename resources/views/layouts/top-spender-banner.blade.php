<div class="border-b border-amber-200 bg-gradient-to-r from-amber-50 via-orange-50 to-white">
  <div class="px-4 py-3 sm:px-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-start gap-3">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-amber-500 text-white shadow-sm">
          <svg class="h-5 w-5"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M13 10V3L4 14h7v7l9-11h-7z" />
          </svg>
        </div>
        <div class="min-w-0">
          <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-amber-700">Realtime Top Spender</p>
          @if ($realtimeTopSpender)
            <p class="mt-1 text-base font-bold text-slate-900">{{ $realtimeTopSpender['customer_name'] }}</p>
            <p class="text-sm text-slate-600">
              Active booking table {{ $realtimeTopSpender['table_number'] ?? '-' }}
              @if (filled($realtimeTopSpender['area_name'] ?? null))
                · {{ $realtimeTopSpender['area_name'] }}
              @endif
            </p>
          @else
            <p class="mt-1 text-base font-bold text-slate-900">Belum ada booking aktif</p>
            <p class="text-sm text-slate-600">Banner ini akan otomatis menampilkan top spender dari total orders active tables.</p>
          @endif
        </div>
      </div>
      <div class="rounded-2xl bg-white/90 px-4 py-3 text-left shadow-sm ring-1 ring-amber-100 sm:min-w-[190px] sm:text-right">
        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Subtotal Orders</p>
        <p class="mt-1 text-lg font-extrabold text-amber-700">
          Rp {{ number_format((float) ($realtimeTopSpender['orders_subtotal'] ?? 0), 0, ',', '.') }}
        </p>
      </div>
    </div>
  </div>
</div>
