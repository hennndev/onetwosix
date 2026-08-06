<x-app-layout title="Kode Otorisasi Harian">
  <div class="p-4 sm:p-6">

    <!-- Back -->
    <a href="{{ route('admin.settings.index') }}"
       class="inline-flex items-center gap-1.5 text-sm text-slate-600 hover:text-slate-800 mb-6">
      <svg class="w-4 h-4"
           fill="none"
           stroke="currentColor"
           viewBox="0 0 24 24">
        <path stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M10 19l-7-7m0 0l7-7m-7 7h18" />
      </svg>
      Kembali ke Menu Pengaturan
    </a>

    @if (session('success'))
      <div class="mb-4 px-4 py-3 bg-green-100 border border-green-400 text-green-700 rounded-lg text-sm">
        {{ session('success') }}
      </div>
    @endif

    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-slate-800">Kode Autentikasi Harian</h1>
      <p class="text-sm text-slate-500 mt-1">Kelola kode akses untuk print struk dan checker</p>
    </div>

    <!-- Info Box -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-5 mb-6">
      <p class="text-sm font-semibold text-blue-700 mb-3 flex items-center gap-2">
        <svg class="w-4 h-4 flex-shrink-0"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        Cara Kerja Sistem Kode Harian:
      </p>
      <ul class="space-y-1.5 text-sm text-blue-700">
        <li class="flex items-start gap-2">
          <svg class="w-4 h-4 mt-0.5 text-blue-500 flex-shrink-0"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M5 13l4 4L19 7" />
          </svg>
          <span><strong>Kode di-generate otomatis</strong> berdasarkan tanggal (algoritma deterministik)</span>
        </li>
        <li class="flex items-start gap-2">
          <svg class="w-4 h-4 mt-0.5 text-blue-500 flex-shrink-0"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M5 13l4 4L19 7" />
          </svg>
          <span><strong>Tanggal sama = kode sama</strong> – Regenerate di hari yang sama akan menghasilkan kode yang sama</span>
        </li>
        <li class="flex items-start gap-2">
          <svg class="w-4 h-4 mt-0.5 text-blue-500 flex-shrink-0"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M5 13l4 4L19 7" />
          </svg>
          <span><strong>Tanggal berbeda = kode berbeda</strong> – Setiap hari punya kode unik</span>
        </li>
        <li class="flex items-start gap-2">
          <svg class="w-4 h-4 mt-0.5 text-blue-500 flex-shrink-0"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M5 13l4 4L19 7" />
          </svg>
          <span><strong>Tidak perlu khawatir lupa kode</strong> – Cukup klik regenerate untuk mendapatkan kode hari ini kembali</span>
        </li>
        <li class="flex items-start gap-2">
          <svg class="w-4 h-4 mt-0.5 text-blue-500 flex-shrink-0"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M5 13l4 4L19 7" />
          </svg>
          <span>Kode berganti otomatis setiap tengah malam (00:00 WIB)</span>
        </li>
      </ul>
    </div>

    <!-- Kode Hari Ini -->
    <div class="bg-white border border-slate-200 rounded-xl p-6 mb-4">
      <h2 class="text-lg font-bold text-slate-800">Kode Hari Ini</h2>
      <p class="text-sm text-slate-500 mb-5">{{ $today }}</p>

      <!-- Code Display -->
      <div class="bg-gradient-to-br from-slate-50 to-slate-100 border border-slate-200 rounded-xl py-8 text-center mb-5">
        @if ($isOverridden)
          <span class="inline-block mb-2 text-xs font-semibold bg-amber-100 text-amber-700 px-2.5 py-0.5 rounded-full">Override Manual Aktif</span>
        @endif
        <p class="text-sm text-slate-500 mb-1">Kode Autentikasi</p>
        <p class="text-7xl font-bold tracking-widest text-slate-800 font-mono">{{ $activeCode }}</p>
        <p class="text-xs text-slate-400 mt-3">Generated: {{ $generatedAt }}</p>
      </div>

      <!-- Action Buttons -->
      <div class="flex gap-3">
        <button onclick="copyCode('{{ $activeCode }}')"
                class="flex-1 flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-xl transition-colors">
          <svg class="w-4 h-4"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
          </svg>
          <span id="copyBtnText">Salin Kode</span>
        </button>

        <form action="{{ route('admin.settings.daily-auth-code.regenerate') }}"
              method="POST"
              class="flex-1">
          @csrf
          <button type="submit"
                  class="w-full flex items-center justify-center gap-2 bg-white hover:bg-slate-50 border border-slate-300 text-slate-700 font-semibold py-3 rounded-xl transition-colors">
            <svg class="w-4 h-4"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Regenerate
          </button>
        </form>
      </div>

      <!-- Tips -->
      <div class="mt-4 flex items-start gap-2 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-sm text-amber-700">
        <span class="text-base leading-tight">💡</span>
        <p><strong>Tips:</strong> Kode ini digunakan untuk verifikasi saat mencetak struk atau checker. Pastikan kode dibagikan hanya kepada staff yang berwenang.</p>
      </div>

      <!-- Technical Notes (collapsible) -->
      <div class="mt-4 border border-slate-200 rounded-lg overflow-hidden"
           x-data="{ open: false }">
        <button @click="open = !open"
                class="w-full flex items-center gap-2 px-4 py-3 text-sm text-slate-600 hover:bg-slate-50 text-left">
          <span>📋</span>
          <span class="font-medium">Catatan Teknis (Untuk Developer)</span>
          <svg class="w-4 h-4 ml-auto transition-transform"
               :class="open ? 'rotate-180' : ''"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <div x-show="open"
             x-cloak
             class="px-4 pb-4 pt-1 border-t border-slate-200 bg-slate-50 text-sm text-slate-600 space-y-1">
          <p><strong>Algoritma:</strong> <code class="text-xs bg-slate-200 px-1 py-0.5 rounded">crc32(date + APP_KEY) % 10000</code></p>
          <p><strong>Format:</strong> 4 digit angka (0000–9999)</p>
          <p><strong>Reset:</strong> Otomatis setiap tengah malam</p>
          <p><strong>Penyimpanan Override:</strong> Laravel Cache (expires end of day)</p>
        </div>
      </div>
    </div>

    <!-- Override Manual -->
    <div class="bg-white border border-slate-200 rounded-xl p-6"
         x-data="{ showOverride: {{ $isOverridden ? 'true' : 'false' }} }">
      <div class="flex items-center justify-between mb-1">
        <div>
          <h2 class="text-lg font-bold text-slate-800">Override Manual (Emergency)</h2>
          <p class="text-sm text-slate-500">Atur kode custom jika diperlukan</p>
        </div>
        <button @click="showOverride = !showOverride"
                class="text-sm font-medium text-slate-600 border border-slate-300 hover:bg-slate-50 px-3 py-1.5 rounded-lg transition-colors">
          <span x-text="showOverride ? 'Tutup' : 'Buka'"></span>
        </button>
      </div>

      <div x-show="showOverride"
           x-cloak
           class="mt-4 space-y-4">

        <!-- Warning -->
        <div class="flex items-start gap-2 bg-amber-50 border border-amber-300 rounded-lg px-4 py-3 text-sm text-amber-700">
          <svg class="w-4 h-4 mt-0.5 flex-shrink-0"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <p><strong>Peringatan:</strong> Gunakan fitur ini hanya dalam keadaan darurat. Kode manual akan menimpa kode otomatis untuk hari ini.</p>
        </div>

        <!-- Current Active Code -->
        @if ($isOverridden)
          <div class="bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm">
            <p class="text-slate-500 mb-0.5">Kode Aktif Saat Ini:</p>
            <p class="text-2xl font-bold font-mono text-slate-800 tracking-widest">{{ $activeCode }}</p>
          </div>

          <form action="{{ route('admin.settings.daily-auth-code.clear-override') }}"
                method="POST">
            @csrf
            @method('DELETE')
            <button type="submit"
                    class="w-full bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-2.5 rounded-xl text-sm transition-colors">
              Hapus Override – Gunakan Kode Otomatis
            </button>
          </form>

          <div class="border-t border-slate-200 pt-4">
            <p class="text-sm font-medium text-slate-600 mb-3">Ganti Kode Manual:</p>
          </div>
        @else
          <div class="bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm">
            <p class="text-slate-500 mb-0.5">Kode Aktif Saat Ini:</p>
            <p class="text-2xl font-bold font-mono text-slate-800 tracking-widest">{{ $activeCode }}</p>
          </div>
        @endif

        <!-- Override Form -->
        <form action="{{ route('admin.settings.daily-auth-code.override') }}"
              method="POST"
              class="space-y-3">
          @csrf
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5"
                   for="override_code">Kode Baru (4 digit)</label>
            <input id="override_code"
                   type="text"
                   name="code"
                   maxlength="4"
                   inputmode="numeric"
                   pattern="[0-9]{4}"
                   placeholder="1234"
                   class="w-full text-center text-2xl font-bold font-mono tracking-widest border border-slate-300 rounded-xl py-4 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('code') border-red-400 @enderror"
                   value="{{ old('code') }}">
            @error('code')
              <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
          </div>
          <button type="submit"
                  class="w-full flex items-center justify-center gap-2 bg-amber-500 hover:bg-amber-600 text-white font-semibold py-3 rounded-xl transition-colors">
            <svg class="w-4 h-4"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
            </svg>
            Simpan Kode Manual
          </button>
        </form>
      </div>
    </div>

  </div>

  @push('scripts')
    <script>
      function copyCode(code) {
        navigator.clipboard.writeText(code).then(() => {
          const btn = document.getElementById('copyBtnText');
          btn.textContent = 'Tersalin!';
          setTimeout(() => {
            btn.textContent = 'Salin Kode';
          }, 2000);
        });
      }
    </script>
  @endpush
</x-app-layout>
