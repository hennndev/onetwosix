{{-- Shared: waiter performance summary stat cards. Rendered for both full page and realtime poll. --}}
<div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-6">
  <div class="bg-white border border-slate-200 rounded-xl p-5">
    <p class="text-sm text-slate-500">Total Waiter</p>
    <p class="text-2xl font-bold text-slate-800">{{ $summary['waitersTotal'] }}</p>
    <p class="text-xs text-slate-400 mt-1">{{ $summary['activeSessions'] }} sesi aktif sekarang</p>
  </div>
  <div class="bg-emerald-600 rounded-xl p-5 text-white">
    <p class="text-sm text-emerald-200">Revenue Sesi (Hari Ini)</p>
    <p class="text-2xl font-bold">Rp {{ number_format($summary['todayRevenue'], 0, ',', '.') }}</p>
  </div>
  <div class="bg-blue-600 rounded-xl p-5 text-white">
    <p class="text-sm text-blue-200">Sesi Hari Ini</p>
    <p class="text-2xl font-bold">{{ $summary['todaySessions'] }}</p>
  </div>
  <div class="bg-purple-600 rounded-xl p-5 text-white">
    <p class="text-sm text-purple-200">Customer Ditangani Hari Ini</p>
    <p class="text-2xl font-bold">{{ $summary['todayCustomers'] }}</p>
  </div>
</div>
