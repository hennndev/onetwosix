{{-- Tab Partial / Piutang --}}
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">

  {{-- Header & Stats --}}
  <div class="p-5 border-b border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-orange-50/40">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 bg-orange-100 rounded-xl flex items-center justify-center text-orange-600 font-bold">
        <svg class="w-5 h-5"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
      </div>
      <div>
        <h2 class="text-lg font-bold text-gray-900">Daftar Tagihan Parsial / Hutang</h2>
        <p class="text-xs text-gray-500 mt-0.5">Booking yang memiliki sisa hutang dan memerlukan pelunasan</p>
      </div>
    </div>
  </div>

  {{-- Filter Search --}}
  <div class="p-5 border-b border-gray-100 bg-gray-50/50">
    <form method="GET"
          action="{{ route('admin.bookings.index') }}"
          class="flex flex-wrap items-center gap-3">
      <input type="hidden"
             name="tab"
             value="partial">

      <div class="relative flex-1 min-w-[200px]">
        <input type="text"
               name="search"
               value="{{ request('search') }}"
               placeholder="Cari kode booking, nama customer, meja..."
               class="w-full pl-9 pr-4 py-2 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent">
        <svg class="w-4 h-4 text-gray-400 absolute left-3 top-3"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </div>

      <button type="submit"
              class="px-4 py-2 bg-orange-600 text-white rounded-xl text-sm font-semibold hover:bg-orange-500 transition">
        Filter
      </button>
      @if (request()->hasAny(['search', 'date_from', 'date_to']))
        <a href="{{ route('admin.bookings.index', ['tab' => 'partial']) }}"
           class="px-4 py-2 bg-gray-100 text-gray-600 rounded-xl text-sm font-medium hover:bg-gray-200 transition">
          Reset Filter
        </a>
      @endif
    </form>
  </div>

  {{-- Table --}}
  <div class="overflow-x-auto">
    <table class="w-full text-left border-collapse">
      <thead>
        <tr class="bg-gray-50/80 border-b border-gray-100 text-xs text-gray-500 uppercase tracking-wider">
          <th class="px-5 py-3.5 font-semibold">Customer</th>
          <th class="px-5 py-3.5 font-semibold">Meja & Area</th>
          <th class="px-5 py-3.5 font-semibold">Tgl / Waktu</th>
          <th class="px-5 py-3.5 font-semibold text-right">Total Bill</th>
          <th class="px-5 py-3.5 font-semibold text-right">Dibayar</th>
          <th class="px-5 py-3.5 font-semibold text-right">Sisa Hutang</th>
          <th class="px-5 py-3.5 font-semibold">Accurate Info</th>
          <th class="px-5 py-3.5 font-semibold text-right">Aksi Pelunasan</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        @forelse ($bookings as $booking)
          @php
            $billing = $booking->tableSession?->billing;
            $grandTotal = (float) ($billing?->grand_total ?? 0);
            $paidAmount = (float) ($billing?->paid_amount ?? 0);
            $remainingBalance = (float) ($billing?->remaining_balance ?? max(0, $grandTotal - $paidAmount));
            $invNo = $billing?->accurate_inv_number ?: $billing?->accurate_so_number;
            $receipts = $billing?->payments?->pluck('accurate_sales_receipt_number')->filter()->values();
          @endphp
          <tr class="hover:bg-gray-50 transition">
            <td class="px-5 py-4">
              <div class="font-semibold text-gray-900">{{ $booking->customer->name }}</div>
              <div class="text-xs text-gray-400 mt-0.5">{{ $booking->customer->email }}</div>
            </td>
            <td class="px-5 py-4">
              <div class="font-semibold text-gray-900">Meja {{ $booking->table?->table_number ?? '-' }}</div>
              <div class="text-xs text-gray-500">{{ $booking->table?->area?->name ?? '-' }}</div>
            </td>
            <td class="px-5 py-4 whitespace-nowrap text-xs text-gray-700">
              <div>{{ $booking->reservation_date ? $booking->reservation_date->format('d M Y') : '-' }}</div>
              <div class="text-gray-400 mt-0.5">{{ date('H:i', strtotime($booking->reservation_time)) }}</div>
            </td>
            <td class="px-5 py-4 text-right font-semibold text-gray-900 whitespace-nowrap">
              Rp {{ number_format($grandTotal, 0, ',', '.') }}
            </td>
            <td class="px-5 py-4 text-right font-semibold text-green-600 whitespace-nowrap">
              Rp {{ number_format($paidAmount, 0, ',', '.') }}
            </td>
            <td class="px-5 py-4 text-right font-bold text-red-600 whitespace-nowrap">
              Rp {{ number_format($remainingBalance, 0, ',', '.') }}
            </td>
            <td class="px-5 py-4 text-xs whitespace-nowrap">
              @if ($invNo)
                <div class="font-semibold text-gray-900"><span class="text-gray-400 font-normal">SI:</span> {{ $invNo }}</div>
              @else
                <div class="text-gray-400">SI: -</div>
              @endif
              @if ($receipts && $receipts->isNotEmpty())
                <div class="text-slate-700 font-medium mt-0.5"><span class="text-gray-400 font-normal">SR:</span> {{ $receipts->implode(', ') }}</div>
              @else
                <div class="text-gray-400 mt-0.5">SR: -</div>
              @endif
            </td>
            <td class="px-5 py-4 text-right whitespace-nowrap">
              <button type="button"
                      onclick="openSettlePaymentModal({{ $booking->id }}, '{{ addslashes($booking->customer->name) }}', '{{ $booking->table?->table_number ?? '-' }}', {{ $grandTotal }}, {{ $paidAmount }}, {{ $remainingBalance }})"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-orange-600 text-white hover:bg-orange-500 shadow-sm transition">
                <svg class="w-3.5 h-3.5"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                Pelunasan
              </button>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="8"
                class="px-5 py-12 text-center text-gray-400 text-sm">
              Tidak ada data booking dengan sisa hutang / bayar parsial saat ini.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
