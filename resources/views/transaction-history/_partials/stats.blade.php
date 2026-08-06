{{-- Shared: transaction-history stat cards. Rendered for both full page and realtime poll (X-Live). --}}
<div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-4">
  <!-- Total Transaksi -->
  <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm flex items-center gap-4">
    <div class="w-12 h-12 bg-slate-800 rounded-xl flex items-center justify-center shrink-0">
      <svg class="w-6 h-6 text-white"
           fill="none"
           stroke="currentColor"
           viewBox="0 0 24 24">
        <path stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
      </svg>
    </div>
    <div>
      <p class="text-xs text-gray-500 mb-0.5">Total Transaksi</p>
      <p class="text-2xl font-bold text-gray-900">{{ $totalOrders }}</p>
    </div>
  </div>

  <!-- Hari Ini -->
  <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm flex items-center gap-4"
       style="background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);">
    <div class="w-12 h-12 bg-green-500 rounded-xl flex items-center justify-center shrink-0">
      <svg class="w-6 h-6 text-white"
           fill="none"
           stroke="currentColor"
           viewBox="0 0 24 24">
        <path stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
      </svg>
    </div>
    <div>
      <p class="text-xs text-green-700 mb-0.5">Hari Ini</p>
      <p class="text-2xl font-bold text-gray-900">{{ $todayOrders }}</p>
    </div>
  </div>

  <!-- Pendapatan Hari Ini -->
  <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm flex items-center gap-4">
    <div class="w-12 h-12 bg-slate-700 rounded-xl flex items-center justify-center shrink-0">
      <svg class="w-6 h-6 text-white"
           fill="none"
           stroke="currentColor"
           viewBox="0 0 24 24">
        <path stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
      </svg>
    </div>
    <div>
      <p class="text-xs text-gray-500 mb-0.5">Pendapatan Hari Ini</p>
      <p class="text-xl font-bold text-gray-900">
        Rp {{ number_format($todayRevenue / 1000000, 1, '.', '') }}jt
      </p>
    </div>
  </div>

  <!-- Total DP Booking Hari Ini -->
  <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm flex items-center gap-4"
       style="background: linear-gradient(135deg, #cffafe 0%, #a5f3fc 100%);">
    <div class="w-12 h-12 bg-cyan-600 rounded-xl flex items-center justify-center shrink-0">
      <svg class="w-6 h-6 text-white"
           fill="none"
           stroke="currentColor"
           viewBox="0 0 24 24">
        <path stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
      </svg>
    </div>
    <div>
      <p class="text-xs text-cyan-700 mb-0.5">Total DP Hari Ini <span class="font-normal">(booking)</span></p>
      <p class="text-2xl font-bold text-gray-900">Rp {{ number_format($todayBookingDownPayment, 0, ',', '.') }}</p>
    </div>
  </div>
</div>
