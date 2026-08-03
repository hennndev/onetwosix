<x-app-layout title="Pengaturan Sistem">
  <div class="p-6">
    <!-- Header -->
    <div class="flex items-center gap-3 mb-8">
      <div class="w-12 h-12 bg-slate-700 rounded-xl flex items-center justify-center">
        <svg class="w-6 h-6 text-white"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
      </div>
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Pengaturan</h1>
        <p class="text-sm text-slate-500">Kelola konfigurasi dan pengaturan 126 Club</p>
      </div>
    </div>

    <!-- Section Title -->
    <h2 class="text-xl font-bold text-slate-800 mb-1">Pengaturan Sistem</h2>
    <p class="text-sm text-slate-500 mb-6">Kelola konfigurasi dan pengaturan 126 Club</p>

    <!-- Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

      <!-- Kode Autentikasi Harian -->
      <a href="{{ route('admin.settings.daily-auth-code.index') }}"
         class="flex items-start gap-4 p-5 bg-white border border-slate-200 rounded-xl hover:border-slate-300 hover:shadow-sm transition-all">
        <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center flex-shrink-0">
          <svg class="w-6 h-6 text-orange-500"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
          </svg>
        </div>
        <div class="flex-1 min-w-0">
          <h3 class="font-semibold text-slate-800 mb-1">Kode Autentikasi Harian</h3>
          <p class="text-sm text-slate-500 mb-3">Kelola kode akses untuk print struk dan checker</p>
          <span class="text-sm font-medium text-violet-600 hover:text-violet-700">Lihat Detail →</span>
        </div>
      </a>

      <!-- Tier Settings -->
      <a href="{{ route('admin.settings.tier-settings.index') }}"
         class="flex items-start gap-4 p-5 bg-white border border-slate-200 rounded-xl hover:border-slate-300 hover:shadow-sm transition-all">
        <div class="w-12 h-12 bg-violet-100 rounded-xl flex items-center justify-center flex-shrink-0">
          <svg class="w-6 h-6 text-violet-500"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
          </svg>
        </div>
        <div class="flex-1 min-w-0">
          <h3 class="font-semibold text-slate-800 mb-1">Tier Settings</h3>
          <p class="text-sm text-slate-500 mb-3">Atur nama tier, diskon, dan minimum transaksi</p>
          <span class="text-sm font-medium text-violet-600 hover:text-violet-700">Lihat Detail →</span>
        </div>
      </a>

      <!-- User Management -->
      <a href="{{ route('admin.users.index') }}"
         class="flex items-start gap-4 p-5 bg-white border border-slate-200 rounded-xl hover:border-slate-300 hover:shadow-sm transition-all">
        <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0">
          <svg class="w-6 h-6 text-blue-500"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
          </svg>
        </div>
        <div class="flex-1 min-w-0">
          <h3 class="font-semibold text-slate-800 mb-1">User Management</h3>
          <p class="text-sm text-slate-500 mb-3">Kelola akun pengguna untuk mengakses aplikasi</p>
          <span class="text-sm font-medium text-violet-600 hover:text-violet-700">Lihat Detail →</span>
        </div>
      </a>

      <!-- Role Management -->
      <a href="{{ route('admin.roles.index') }}"
         class="flex items-start gap-4 p-5 bg-white border border-slate-200 rounded-xl hover:border-slate-300 hover:shadow-sm transition-all">
        <div class="w-12 h-12 bg-violet-100 rounded-xl flex items-center justify-center flex-shrink-0">
          <svg class="w-6 h-6 text-violet-500"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
          </svg>
        </div>
        <div class="flex-1 min-w-0">
          <h3 class="font-semibold text-slate-800 mb-1">Role Management</h3>
          <p class="text-sm text-slate-500 mb-3">Atur akses menu untuk setiap role pengguna</p>
          <span class="text-sm font-medium text-violet-600 hover:text-violet-700">Lihat Detail →</span>
        </div>
      </a>

      <!-- Area Management -->
      <a href="{{ route('admin.areas.index') }}"
         class="flex items-start gap-4 p-5 bg-white border border-slate-200 rounded-xl hover:border-slate-300 hover:shadow-sm transition-all">
        <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center flex-shrink-0">
          <svg class="w-6 h-6 text-green-500"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
        </div>
        <div class="flex-1 min-w-0">
          <h3 class="font-semibold text-slate-800 mb-1">Area Management</h3>
          <p class="text-sm text-slate-500 mb-3">Kelola area/section (Room, Balcony, Lounge) dan table</p>
          <span class="text-sm font-medium text-violet-600 hover:text-violet-700">Lihat Detail →</span>
        </div>
      </a>

      <!-- POS Category Settings -->
      <a href="{{ route('admin.settings.pos-categories.index') }}"
         class="flex items-start gap-4 p-5 bg-white border border-slate-200 rounded-xl hover:border-slate-300 hover:shadow-sm transition-all">
        <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center flex-shrink-0">
          <svg class="w-6 h-6 text-green-600"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
          </svg>
        </div>
        <div class="flex-1 min-w-0">
          <h3 class="font-semibold text-slate-800 mb-1">Kategori POS</h3>
          <p class="text-sm text-slate-500 mb-3">Atur kategori mana yang tampil di POS</p>
          <span class="text-sm font-medium text-violet-600 hover:text-violet-700">Lihat Detail →</span>
        </div>
      </a>

      <!-- General Settings -->
      <a href="{{ route('admin.settings.general.index') }}"
         class="flex items-start gap-4 p-5 bg-white border border-slate-200 rounded-xl hover:border-slate-300 hover:shadow-sm transition-all">
        <div class="w-12 h-12 bg-slate-100 rounded-xl flex items-center justify-center flex-shrink-0">
          <svg class="w-6 h-6 text-slate-600"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19.5A2.5 2.5 0 016.5 17H20" />
          </svg>
        </div>
        <div class="flex-1 min-w-0">
          <h3 class="font-semibold text-slate-800 mb-1">Pengaturan Umum</h3>
          <p class="text-sm text-slate-500 mb-3">Konfigurasi PB1 dan service charge</p>
          <span class="text-sm font-medium text-violet-600 hover:text-violet-700">Lihat Detail →</span>
        </div>
      </a>

      <!-- Printer Management -->
      <a href="{{ route('admin.printers.index') }}"
         class="flex items-start gap-4 p-5 bg-white border border-slate-200 rounded-xl hover:border-slate-300 hover:shadow-sm transition-all">
        <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center flex-shrink-0">
          <svg class="w-6 h-6 text-orange-500"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
          </svg>
        </div>
        <div class="flex-1 min-w-0">
          <h3 class="font-semibold text-slate-800 mb-1">Printer Management</h3>
          <p class="text-sm text-slate-500 mb-3">Konfigurasi printer untuk checker, struk, dan kitchen</p>
          <span class="text-sm font-medium text-violet-600 hover:text-violet-700">Lihat Detail →</span>
        </div>
      </a>

    </div>
  </div>
</x-app-layout>
