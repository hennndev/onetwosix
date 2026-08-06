{{-- Shared: dashboard stat cards. Rendered for both full page and realtime poll (X-Live). --}}
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
  <!-- Pendapatan Hari Ini -->
  <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-sm font-medium text-gray-600">Pendapatan Hari Ini</h3>
      <div class="bg-green-100 p-2 rounded-lg">
        <svg class="w-5 h-5 text-green-600"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
    </div>
    <div class="space-y-2 mb-3 border-t pt-3">
      <div class="flex justify-between items-center">
        <span class="text-sm font-medium text-emerald-700">Gross Sales</span>
        <span class="text-base font-semibold text-emerald-800">Rp {{ number_format($dashboardGrossSales, 0, ',', '.') }}</span>
      </div>
      <div class="flex justify-between items-center">
        <span class="text-sm font-medium text-slate-700">Net Sales</span>
        <span class="text-base font-semibold text-slate-800">Rp {{ number_format($dashboardNetSales, 0, ',', '.') }}</span>
      </div>
    </div>
  </div>

  <!-- Transaksi Hari Ini -->
  <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-sm font-medium text-gray-600">Transaksi Hari Ini</h3>
      <div class="bg-slate-700 p-2 rounded-lg">
        <svg class="w-5 h-5 text-white"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
      </div>
    </div>
    <div class="mb-1">
      <p class="text-2xl font-bold text-gray-800">{{ $transactionsToday }}</p>
    </div>
    <p class="text-sm text-gray-500">{{ $itemsSoldToday }} item terjual</p>
  </div>

  <!-- Booking Pending -->
  <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-sm font-medium text-gray-600">Booking Pending</h3>
      <div class="bg-orange-100 p-2 rounded-lg">
        <svg class="w-5 h-5 text-orange-600"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
    </div>
    <div class="mb-1">
      <p class="text-2xl font-bold text-gray-800">{{ $bookingPending }}</p>
    </div>
    <p class="text-sm text-gray-500">{{ $bookingConfirmed }} confirmed</p>
  </div>

  <!-- Meja Tersedia -->
  <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-sm font-medium text-gray-600">Meja Tersedia</h3>
      <div class="bg-blue-100 p-2 rounded-lg">
        <svg class="w-5 h-5 text-blue-600"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
    </div>
    <div class="mb-1">
      <p class="text-2xl font-bold text-gray-800">{{ $availableTables }}/{{ $totalTables }}</p>
    </div>
    <p class="text-sm text-gray-500">meja siap digunakan</p>
  </div>
</div>
