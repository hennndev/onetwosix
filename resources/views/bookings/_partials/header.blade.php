{{-- Page Header --}}
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
  <div class="flex items-center gap-3">
    <div class="w-12 h-12 bg-slate-800 rounded-xl flex items-center justify-center">
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
      <h1 class="text-2xl font-bold text-gray-900">Manajemen Booking</h1>
      <p class="text-sm text-gray-500">Kelola reservasi nightclub</p>
    </div>
  </div>
  <div class="flex flex-wrap gap-2">
    <button type="button"
            onclick="openQrScanner('booking-search-customer')"
            class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3v2H5v3H3V5zm13-2h3a2 2 0 012 2v3h-2V5h-3V3zM3 16h2v3h3v2H5a2 2 0 01-2-2v-3zm16 0h2v3a2 2 0 01-2 2h-3v-2h3v-3zM7 7h3v3H7V7zm7 0h3v3h-3V7z" />
      </svg>
      Cari via QR
    </button>
    <button @click="openModal(null)"
            class="flex items-center gap-2 bg-slate-800 hover:bg-slate-900 text-white px-4 py-2.5 rounded-lg font-medium transition text-sm">
      <svg class="w-4 h-4"
           fill="none"
           stroke="currentColor"
           viewBox="0 0 24 24">
        <path stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 4v16m8-8H4" />
      </svg>
      Booking Baru
    </button>
  </div>
</div>
