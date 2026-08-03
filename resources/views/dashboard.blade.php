<x-app-layout title="Dashboard">
  <div class="p-6">
    @if (session('success'))
      <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
        {{ session('success') }}
      </div>
    @endif

    <!-- Hero Section -->
    <div class="bg-gradient-to-br from-slate-700 to-slate-800 rounded-2xl p-8 mb-6">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h1 class="text-3xl font-bold text-white mb-2">Dashboard 126 Club</h1>
          <p class="text-slate-300">Ringkasan sistem manajemen POS dan booking</p>
        </div>
        <form method="POST"
              action="{{ route('admin.dashboard.sync') }}"
              class="sm:pt-1">
          @csrf
          <button type="submit"
                  class="inline-flex items-center gap-2 rounded-lg border border-white/30 bg-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/20">
            <svg class="h-4 w-4"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Sync Dashboard Hari Ini
          </button>
        </form>
      </div>
    </div>

    <!-- Area Selector Pills -->
    @if (($areas ?? collect())->count() > 1)
      <div class="bg-white rounded-xl p-4 mb-6 shadow-sm border border-gray-100 flex items-center gap-3">
        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Area:</span>
        <a href="{{ route('admin.dashboard', array_merge(request()->except('area_id'), ['area_id' => 'all'])) }}"
           class="px-4 py-1.5 text-xs rounded-full font-medium transition {{ empty($selectedAreaId) ? 'bg-slate-800 text-white font-semibold shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
          Semua Area
        </a>
        @foreach ($areas as $a)
          <a href="{{ route('admin.dashboard', array_merge(request()->except('area_id'), ['area_id' => $a->id])) }}"
             class="px-4 py-1.5 text-xs rounded-full font-medium transition {{ $selectedAreaId === $a->id ? 'bg-slate-800 text-white font-semibold shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
            {{ $a->name }}
          </a>
        @endforeach
      </div>
    @endif

    <!-- Stats Cards Row -->
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
        {{-- <div class="mb-4">
          <p class="text-2xl font-bold text-gray-800">Rp {{ number_format($revenueToday, 0, ',', '.') }}</p>
        </div> --}}

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

    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 mb-6">
      <div class="flex items-center space-x-2 mb-4">
        <svg class="w-5 h-5 text-gray-600"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <h2 class="text-lg font-semibold text-gray-800">Ringkasan Transaksi Dashboard</h2>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <div class="p-4 bg-lime-50 border border-lime-200 rounded-lg">
          <p class="text-sm font-medium text-lime-700">Total Food</p>
          <p class="text-2xl font-bold text-lime-800 mt-1">Rp {{ number_format($dashboardTotalFood, 0, ',', '.') }}</p>
        </div>

        <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
          <p class="text-sm font-medium text-yellow-700">Total Alcohol</p>
          <p class="text-2xl font-bold text-yellow-800 mt-1">Rp {{ number_format($dashboardTotalAlcohol, 0, ',', '.') }}</p>
        </div>

        <div class="p-4 bg-teal-50 border border-teal-200 rounded-lg">
          <p class="text-sm font-medium text-teal-700">Total Beverage</p>
          <p class="text-2xl font-bold text-teal-800 mt-1">Rp {{ number_format($dashboardTotalBeverage, 0, ',', '.') }}</p>
        </div>

        <div class="p-4 bg-fuchsia-50 border border-fuchsia-200 rounded-lg">
          <p class="text-sm font-medium text-fuchsia-700">Total Cigarette</p>
          <p class="text-2xl font-bold text-fuchsia-800 mt-1">Rp {{ number_format($dashboardTotalCigarette, 0, ',', '.') }}</p>
        </div>

        <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
          <p class="text-sm font-medium text-red-700">Total Breakage</p>
          <p class="text-2xl font-bold text-red-800 mt-1">Rp {{ number_format($dashboardTotalBreakage, 0, ',', '.') }}</p>
        </div>

        <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
          <p class="text-sm font-medium text-blue-700">Total Room</p>
          <p class="text-2xl font-bold text-blue-800 mt-1">Rp {{ number_format($dashboardTotalRoom, 0, ',', '.') }}</p>
        </div>

        <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-lg">
          <p class="text-sm font-medium text-emerald-700">Total Staff Meal</p>
          <p class="text-2xl font-bold text-emerald-800 mt-1">Rp {{ number_format($dashboardTotalStaffMeal, 0, ',', '.') }}</p>
        </div>

        <div class="p-4 bg-sky-50 border border-sky-200 rounded-lg">
          <p class="text-sm font-medium text-sky-700">Total Compliment (Qty)</p>
          <p class="text-2xl font-bold text-sky-800 mt-1">{{ number_format($dashboardTotalComplimentQuantity, 0, ',', '.') }}</p>
        </div>

        <div class="p-4 bg-indigo-50 border border-indigo-200 rounded-lg">
          <p class="text-sm font-medium text-indigo-700">Total FOC (Qty)</p>
          <p class="text-2xl font-bold text-indigo-800 mt-1">{{ number_format($dashboardTotalFocQuantity, 0, ',', '.') }}</p>
        </div>

        <div class="p-4 bg-purple-50 border border-purple-200 rounded-lg">
          <p class="text-sm font-medium text-purple-700">Total LD</p>
          <p class="text-2xl font-bold text-purple-800 mt-1">Rp {{ number_format($dashboardTotalLd, 0, ',', '.') }}</p>
          <p class="text-xs font-medium text-purple-600 mt-2">Qty {{ number_format($dashboardTotalLdQuantity ?? 0, 0, ',', '.') }}</p>
        </div>

        <div class="p-4 bg-rose-50 border border-rose-200 rounded-lg">
          <p class="text-sm font-medium text-rose-700">Total Penjualan Rokok (Qty)</p>
          <p class="text-2xl font-bold text-rose-800 mt-1">{{ number_format($dashboardTotalPenjualanRokok, 0, ',', '.') }}</p>
        </div>

        <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg">
          <p class="text-sm font-medium text-amber-700">Total Pajak</p>
          <p class="text-2xl font-bold text-amber-800 mt-1">Rp {{ number_format($dashboardTotalTax, 0, ',', '.') }}</p>
        </div>

        <div class="p-4 bg-orange-50 border border-orange-200 rounded-lg">
          <p class="text-sm font-medium text-orange-700">Total Service Charge</p>
          <p class="text-2xl font-bold text-orange-800 mt-1">Rp {{ number_format($dashboardTotalServiceCharge, 0, ',', '.') }}</p>
        </div>

        <div class="p-4 bg-cyan-50 border border-cyan-200 rounded-lg">
          <p class="text-sm font-medium text-cyan-700">Total DP <span class="text-xs font-normal">(booking)</span></p>
          <p class="text-2xl font-bold text-cyan-800 mt-1">Rp {{ number_format($dashboardTotalDp, 0, ',', '.') }}</p>
        </div>

        <div class="p-4 bg-gray-50 border border-gray-200 rounded-lg">
          <p class="text-sm font-medium text-gray-700">Total Pembayaran Tunai</p>
          <p class="text-2xl font-bold text-gray-900 mt-1">Rp {{ number_format($dashboardTotalCash, 0, ',', '.') }}</p>
        </div>

        <div class="p-4 bg-sky-50 border border-sky-200 rounded-lg">
          <p class="text-sm font-medium text-sky-700">Total Pembayaran Transfer</p>
          <p class="text-2xl font-bold text-sky-800 mt-1">Rp {{ number_format($dashboardTotalTransfer, 0, ',', '.') }}</p>
        </div>

        <div class="p-4 bg-indigo-50 border border-indigo-200 rounded-lg">
          <p class="text-sm font-medium text-indigo-700">Total Pembayaran Debit</p>
          <p class="text-2xl font-bold text-indigo-800 mt-1">Rp {{ number_format($dashboardTotalDebit, 0, ',', '.') }}</p>
        </div>

        <div class="p-4 bg-violet-50 border border-violet-200 rounded-lg">
          <p class="text-sm font-medium text-violet-700">Total Pembayaran Kredit</p>
          <p class="text-2xl font-bold text-violet-800 mt-1">Rp {{ number_format($dashboardTotalKredit, 0, ',', '.') }}</p>
        </div>

        <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-lg">
          <p class="text-sm font-medium text-emerald-700">Total Pembayaran QRIS</p>
          <p class="text-2xl font-bold text-emerald-800 mt-1">Rp {{ number_format($dashboardTotalQris, 0, ',', '.') }}</p>
        </div>

        <div class="p-4 bg-rose-50 border border-rose-200 rounded-lg">
          <p class="text-sm font-medium text-rose-700">Total Item Keluar Kitchen</p>
          <p class="text-2xl font-bold text-rose-800 mt-1">{{ number_format($dashboardTotalKitchenItems, 0, ',', '.') }}</p>
        </div>

        <div class="p-4 bg-cyan-50 border border-cyan-200 rounded-lg">
          <p class="text-sm font-medium text-cyan-700">Total Item Keluar Bar</p>
          <p class="text-2xl font-bold text-cyan-800 mt-1">{{ number_format($dashboardTotalBarItems, 0, ',', '.') }}</p>
        </div>
      </div>
    </div>

    <!-- Bottom Sections -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Status Booking -->
      <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center space-x-2 mb-6">
          <svg class="w-5 h-5 text-gray-600"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
          <h2 class="text-lg font-semibold text-gray-800">Status Booking</h2>
        </div>
        <p class="text-sm text-gray-500 mb-6">Ringkasan booking berdasarkan status</p>

        <div class="space-y-3">
          <!-- Pending -->
          <div class="flex items-center justify-between p-4 bg-orange-50 border border-orange-200 rounded-lg">
            <div class="flex items-center space-x-3">
              <div class="bg-orange-500 p-2 rounded-lg">
                <svg class="w-5 h-5 text-white"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div>
                <p class="font-semibold text-gray-800">Pending</p>
                <p class="text-sm text-gray-600">Menunggu konfirmasi</p>
              </div>
            </div>
            <div class="text-3xl font-bold text-orange-600">{{ $bookingPending }}</div>
          </div>

          <!-- Confirmed -->
          <div class="flex items-center justify-between p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <div class="flex items-center space-x-3">
              <div class="bg-blue-500 p-2 rounded-lg">
                <svg class="w-5 h-5 text-white"
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
                <p class="font-semibold text-gray-800">Confirmed</p>
                <p class="text-sm text-gray-600">Sudah dikonfirmasi</p>
              </div>
            </div>
            <div class="text-3xl font-bold text-blue-600">{{ $bookingConfirmed }}</div>
          </div>

          <!-- Completed -->
          <div class="flex items-center justify-between p-4 bg-green-50 border border-green-200 rounded-lg">
            <div class="flex items-center space-x-3">
              <div class="bg-green-500 p-2 rounded-lg">
                <svg class="w-5 h-5 text-white"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M5 13l4 4L19 7" />
                </svg>
              </div>
              <div>
                <p class="font-semibold text-gray-800">Completed</p>
                <p class="text-sm text-gray-600">Sudah selesai</p>
              </div>
            </div>
            <div class="text-3xl font-bold text-green-600">{{ $bookingCompleted }}</div>
          </div>
        </div>
      </div>

      <!-- Status Produk -->
      <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center space-x-2 mb-6">
          <svg class="w-5 h-5 text-gray-600"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
          </svg>
          <h2 class="text-lg font-semibold text-gray-800">Status Produk</h2>
        </div>
        <p class="text-sm text-gray-500 mb-6">Informasi stok dan inventori</p>

        <div class="space-y-3">
          <!-- Total Produk -->
          <div class="flex items-center justify-between p-4 bg-slate-50 border border-slate-200 rounded-lg">
            <div class="flex items-center space-x-3">
              <div class="bg-slate-700 p-2 rounded-lg">
                <svg class="w-5 h-5 text-white"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
              </div>
              <div>
                <p class="font-semibold text-gray-800">Total Produk</p>
                <p class="text-sm text-gray-600">Semua produk</p>
              </div>
            </div>
            <div class="text-3xl font-bold text-slate-700">{{ $totalProducts }}</div>
          </div>

          <!-- Stok Rendah -->
          <div class="flex items-center justify-between p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
            <div class="flex items-center space-x-3">
              <div class="bg-yellow-500 p-2 rounded-lg">
                <svg class="w-5 h-5 text-white"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
              </div>
              <div>
                <p class="font-semibold text-gray-800">Stok Rendah</p>
                <p class="text-sm text-gray-600">&lt; 10 item</p>
              </div>
            </div>
            <div class="text-3xl font-bold text-yellow-600">{{ $lowStockCount }}</div>
          </div>

          <!-- Habis -->
          <div class="flex items-center justify-between p-4 bg-red-50 border border-red-200 rounded-lg">
            <div class="flex items-center space-x-3">
              <div class="bg-red-500 p-2 rounded-lg">
                <svg class="w-5 h-5 text-white"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
              </div>
              <div>
                <p class="font-semibold text-gray-800">Habis</p>
                <p class="text-sm text-gray-600">Stok = 0</p>
              </div>
            </div>
            <div class="text-3xl font-bold text-red-600">{{ $outOfStockCount }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</x-app-layout>
