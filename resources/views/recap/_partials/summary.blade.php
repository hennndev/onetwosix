{{-- Shared: recap summary cards. Rendered for both full page and realtime poll (X-Live). --}}
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
  <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
    <p class="text-sm font-medium text-gray-500">Transaksi Kasir</p>
    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $cashierCount }}</p>
  </div>

  <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
    <p class="text-sm font-medium text-gray-500">Total Penjualan Kasir</p>
    <div class="space-y-1.5 mt-3 border-t pt-2">
      <div class="flex justify-between items-center">
        <span class="text-sm font-medium text-emerald-700">Gross Sales</span>
        <span class="text-base font-semibold text-emerald-700">Rp {{ number_format($grossSales, 0, ',', '.') }}</span>
      </div>
      <div class="flex justify-between items-center">
        <span class="text-sm font-medium text-slate-700">Net Sales</span>
        <span class="text-base font-semibold text-slate-700">Rp {{ number_format($netSales, 0, ',', '.') }}</span>
      </div>
    </div>
  </div>

  <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
    <p class="text-sm font-medium text-gray-500">Item Keluar Kitchen</p>
    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $kitchenQtyTotal }}</p>
  </div>

  <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
    <p class="text-sm font-medium text-gray-500">Item Keluar Bar</p>
    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $barQtyTotal }}</p>
  </div>

  <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
    <p class="text-sm font-medium text-gray-500">Total Penjualan Rokok (Qty)</p>
    <p class="text-2xl font-bold text-rose-700 mt-1">{{ number_format($totalPenjualanRokok ?? 0, 0, ',', '.') }}</p>
  </div>
</div>
