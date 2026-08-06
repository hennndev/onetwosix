{{-- Shared: transaction-history order rows. Rendered for both full page and realtime poll (X-Live). Expects to sit inside a <tbody>. --}}
  @forelse ($orders as $order)
    @php
      $displayId = $order->order_number;
      $isBooking = $order->tableSession?->reservation !== null;
      $tableName = $order->tableSession?->table?->table_number;
      $customerName = $order->tableSession?->customer?->name ?? $order->customer?->user?->name;
      $orderBilling = $order->billing ?? $order->tableSession?->billing;
    @endphp
    <tr x-on:click="openOrderDetailById({{ $order->id }})"
        class="hover:bg-gray-50 transition-colors cursor-pointer">
      <td class="px-5 py-3.5 whitespace-nowrap">
        @if ($order->ordered_at)
          <div class="font-medium text-gray-500 text-xs">{{ $order->ordered_at->format('d M') }}</div>
          <div class="text-xs text-gray-400">{{ $order->ordered_at->format('H:i') }}</div>
        @else
          <span class="text-gray-400">—</span>
        @endif
      </td>

      <td class="px-5 py-3.5">
        <span class="font-mono font-semibold text-gray-800 text-sm">{{ $displayId }}</span>
      </td>

      <td class="px-5 py-3.5">
        @if ($customerName)
          <span class="font-medium text-gray-800">{{ $customerName }}</span>
        @else
          <span class="text-gray-400 text-xs">Walk-in</span>
        @endif
      </td>

      <td class="px-5 py-3.5">
        <div class="flex flex-col gap-0.5">
          @if ($isBooking)
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700 w-fit">
              Booking
            </span>
          @else
            <span class="text-xs text-gray-500">Walk-in</span>
          @endif
          @if ($orderBilling && ($orderBilling->is_debt || $orderBilling->billing_status === 'partial_paid'))
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-red-100 text-red-700 border border-red-200 w-fit">
              HUTANG (Sisa: Rp {{ number_format($orderBilling->remaining_balance, 0, ',', '.') }})
            </span>
          @endif
          @if ($tableName)
            <span class="text-xs text-gray-400">{{ $isBooking ? ($order->tableSession->table->area->name ?? 'VIP') . ' ' . $tableName : 'Table ' . $tableName }}</span>
          @endif
        </div>
      </td>

      <td class="px-5 py-3.5">
        <span class="font-medium text-gray-700">{{ $order->items->count() }}</span>
      </td>

      <td class="px-5 py-3.5 text-right whitespace-nowrap">
        <span class="font-semibold text-gray-800">Rp {{ number_format($order->total, 0, ',', '.') }}</span>
      </td>

      <td x-on:click.stop
          class="px-5 py-3.5 text-center">
        <button x-on:click.stop="openPrintModalById({{ $order->id }})"
                class="inline-flex items-center justify-center w-8 h-8 rounded-lg hover:bg-gray-100 transition text-gray-400 hover:text-gray-700">
          <svg class="w-5 h-5"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
          </svg>
        </button>
      </td>
    </tr>
  @empty
    <tr>
      <td colspan="7" class="px-5 py-16 text-center text-gray-400">
        <p class="text-sm font-medium">Tidak ada transaksi ditemukan</p>
      </td>
    </tr>
  @endforelse
