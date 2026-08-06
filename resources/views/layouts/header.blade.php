<header class="bg-white border-b border-gray-200 px-4 sm:px-6 py-3 sm:py-4">
  <div class="flex items-center justify-between gap-2 sm:gap-4">
    <div class="flex items-center space-x-2 sm:space-x-3 min-w-0 flex-1">
      <!-- Sidebar Toggle -->
      <button @click="sidebarOpen = !sidebarOpen; localStorage.setItem('sidebarOpen', sidebarOpen)"
              class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors shrink-0"
              :title="sidebarOpen ? 'Tutup sidebar' : 'Buka sidebar'">
        <svg class="w-5 h-5"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>
      <div class="bg-slate-800 rounded-lg p-2 shrink-0">
        <svg class="w-5 h-5 text-white"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
        </svg>
      </div>
      <h1 class="text-lg sm:text-xl font-bold text-gray-800 truncate">{{ $title ?? 'Dashboard' }}</h1>
    </div>

    <div class="flex items-center space-x-2 sm:space-x-4 shrink-0">
      <!-- Area Switcher -->
      @auth
        @if(Auth::user()->hasMultiAreaAccess())
          @php
            $allAreas = \App\Models\Area::where('is_active', true)->orderBy('sort_order')->get();
            $currentAreaId = session('active_area_id');
          @endphp
          <form method="POST" action="{{ route('admin.switch-area') }}" class="flex items-center space-x-1.5 bg-slate-100 hover:bg-slate-200/80 rounded-xl px-3 py-1.5 border border-slate-200 transition-colors">
            @csrf
            <svg class="w-4 h-4 text-slate-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider hidden md:inline">Area:</span>
            <select name="area_id" onchange="this.form.submit()" class="bg-transparent text-xs font-bold text-slate-800 border-none focus:ring-0 py-0.5 pl-1 pr-6 cursor-pointer max-w-24 sm:max-w-none truncate">
              @foreach($allAreas as $areaItem)
                <option value="{{ $areaItem->id }}" {{ (string)$currentAreaId === (string)$areaItem->id ? 'selected' : '' }}>
                  {{ $areaItem->name }} ({{ rtrim($areaItem->so_prefix, '-') }})
                </option>
              @endforeach
              <option value="all" {{ $currentAreaId === 'all' ? 'selected' : '' }}>Semua Area (Combined)</option>
            </select>
          </form>
        @else
          @php
            $assignedArea = Auth::user()->resolveActiveArea();
          @endphp
          @if($assignedArea)
            <div class="flex items-center space-x-1.5 bg-slate-100 rounded-xl px-3 py-1.5 border border-slate-200">
              <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
              <span class="text-xs font-bold text-slate-700 uppercase tracking-wide">{{ $assignedArea->name }}</span>
            </div>
          @endif
        @endif
      @endauth

      <!-- User Menu -->
      <div class="relative"
           x-data="{ userMenuOpen: false }"
           @keydown.escape.window="userMenuOpen = false">
        <button @click="userMenuOpen = !userMenuOpen"
                :aria-expanded="userMenuOpen"
                class="flex items-center space-x-3 hover:bg-gray-100 rounded-lg px-2 sm:px-3 py-2">
          <div class="text-right hidden sm:block">
            <p class="text-sm font-semibold text-gray-700 max-w-40 truncate">{{ Auth::user()->name }}</p>
            <p class="text-xs text-gray-500">Administrator</p>
          </div>
          <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
            <span class="text-white font-semibold">{{ substr(Auth::user()->name, 0, 1) }}</span>
          </div>
        </button>

        <!-- Dropdown -->
        <div x-show="userMenuOpen"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-1"
             @click.outside="userMenuOpen = false"
             class="absolute right-0 mt-2 w-64 rounded-xl bg-white shadow-lg border border-gray-100 py-2 z-50">
          <div class="px-4 py-2 border-b border-gray-100 sm:hidden">
            <p class="text-sm font-semibold text-gray-800 truncate">{{ Auth::user()->name }}</p>
            <p class="text-xs text-gray-500">Administrator</p>
          </div>
          <button type="button"
                  @click="userMenuOpen = false; document.getElementById('profileModal').classList.remove('hidden')"
                  class="w-full flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 font-medium transition">
            <svg class="w-4 h-4"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            Profil
          </button>
          <button type="button"
                  @click="userMenuOpen = false; document.getElementById('logoutModal').classList.remove('hidden')"
                  class="w-full flex items-center gap-2 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 font-medium transition">
            <svg class="w-4 h-4"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            Keluar
          </button>
        </div>
      </div>
    </div>
  </div>
</header>

<!-- Profile Info Modal -->
<div id="profileModal"
     class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
     onclick="if(event.target===this) this.classList.add('hidden')">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6">
    <div class="flex items-start justify-between mb-5">
      <h3 class="text-lg font-bold text-gray-900">Profil Saya</h3>
      <button type="button"
              onclick="document.getElementById('profileModal').classList.add('hidden')"
              class="text-gray-400 hover:text-gray-600 transition">
        <svg class="w-5 h-5"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <div class="flex flex-col items-center mb-6">
      <div class="w-20 h-20 bg-blue-600 rounded-2xl flex items-center justify-center mb-3">
        <span class="text-3xl font-bold text-white">{{ substr(Auth::user()->name, 0, 1) }}</span>
      </div>
      <p class="text-base font-bold text-gray-900 text-center">{{ Auth::user()->name }}</p>
      <p class="text-sm text-gray-500 text-center">{{ Auth::user()->email }}</p>
    </div>

    <div class="space-y-3">
      @php
        $profile = Auth::user()->profile;
      @endphp
      <div class="flex items-center justify-between py-2.5 px-4 bg-gray-50 rounded-lg">
        <span class="text-sm text-gray-500">Telepon</span>
        <span class="text-sm font-semibold text-gray-800">{{ $profile?->phone ?: '—' }}</span>
      </div>
      <div class="flex items-center justify-between py-2.5 px-4 bg-gray-50 rounded-lg">
        <span class="text-sm text-gray-500">Alamat</span>
        <span class="text-sm font-semibold text-gray-800 text-right max-w-[60%] truncate">{{ $profile?->address ?: '—' }}</span>
      </div>
      <div class="flex items-center justify-between py-2.5 px-4 bg-gray-50 rounded-lg">
        <span class="text-sm text-gray-500">Tanggal Lahir</span>
        <span class="text-sm font-semibold text-gray-800">{{ $profile?->birth_date?->format('d M Y') ?: '—' }}</span>
      </div>
      <div class="flex items-center justify-between py-2.5 px-4 bg-gray-50 rounded-lg">
        <span class="text-sm text-gray-500">Role</span>
        <span class="text-sm font-semibold text-gray-800 capitalize">{{ Auth::user()->roles->pluck('name')->join(', ') ?: 'Administrator' }}</span>
      </div>
      <div class="flex items-center justify-between py-2.5 px-4 bg-gray-50 rounded-lg">
        <span class="text-sm text-gray-500">Bergabung</span>
        <span class="text-sm font-semibold text-gray-800">{{ Auth::user()->created_at?->format('d M Y') }}</span>
      </div>
    </div>

    <div class="mt-6">
      <a href="{{ route('profile.edit') }}"
         class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-800 text-white rounded-xl hover:bg-slate-900 font-medium transition text-sm">
        Edit Profil
      </a>
    </div>
  </div>
</div>

<!-- Logout Confirmation Modal -->
<div id="logoutModal"
     class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
     onclick="if(event.target===this) this.classList.add('hidden')">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6">
    <div class="flex items-center justify-center w-14 h-14 mx-auto bg-red-100 rounded-full mb-4">
      <svg class="w-7 h-7 text-red-600"
           fill="none"
           stroke="currentColor"
           viewBox="0 0 24 24">
        <path stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
      </svg>
    </div>
    <h3 class="text-lg font-bold text-gray-900 text-center mb-1">Keluar dari sistem?</h3>
    <p class="text-sm text-gray-500 text-center mb-6">Sesi kamu akan diakhiri dan kamu perlu login kembali.</p>
    <div class="flex gap-3">
      <button type="button"
              onclick="document.getElementById('logoutModal').classList.add('hidden')"
              class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 font-medium transition">
        Batal
      </button>
      <form method="POST"
            action="{{ route('logout') }}"
            class="flex-1">
        @csrf
        <button type="submit"
                class="w-full px-4 py-2.5 bg-red-600 text-white rounded-xl hover:bg-red-700 font-semibold transition">
          Keluar
        </button>
      </form>
    </div>
  </div>
</div>
