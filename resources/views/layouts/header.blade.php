<header class="bg-white border-b border-gray-200 px-6 py-4">
  <div class="flex items-center justify-between">
    <div class="flex items-center space-x-3">
      <!-- Sidebar Toggle -->
      <button @click="sidebarOpen = !sidebarOpen; localStorage.setItem('sidebarOpen', sidebarOpen)"
              class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors"
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
      <div class="bg-slate-800 rounded-lg p-2">
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
      <h1 class="text-xl font-bold text-gray-800">{{ $title ?? 'Dashboard' }}</h1>
    </div>

    <div class="flex items-center space-x-4">
      <!-- Area Switcher -->
      @auth
        @if(Auth::user()->hasMultiAreaAccess())
          @php
            $allAreas = \App\Models\Area::where('is_active', true)->orderBy('sort_order')->get();
            $currentAreaId = session('active_area_id');
          @endphp
          <form method="POST" action="{{ route('admin.switch-area') }}" class="flex items-center space-x-1.5 bg-slate-100 hover:bg-slate-200/80 rounded-xl px-3 py-1.5 border border-slate-200 transition-colors">
            @csrf
            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider hidden md:inline">Area:</span>
            <select name="area_id" onchange="this.form.submit()" class="bg-transparent text-xs font-bold text-slate-800 border-none focus:ring-0 py-0.5 pl-1 pr-6 cursor-pointer">
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
      <div class="relative">
        <button class="flex items-center space-x-3 hover:bg-gray-100 rounded-lg px-3 py-2">
          <div class="text-right">
            <p class="text-sm font-semibold text-gray-700">{{ Auth::user()->name }}</p>
            <p class="text-xs text-gray-500">Administrator</p>
          </div>
          <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
            <span class="text-white font-semibold">{{ substr(Auth::user()->name, 0, 1) }}</span>
          </div>
        </button>
      </div>

      <!-- Logout -->
      <button type="button"
              onclick="document.getElementById('logoutModal').classList.remove('hidden')"
              class="p-2 text-gray-500 hover:bg-red-50 hover:text-red-500 rounded-lg transition-colors"
              title="Logout">
        <svg class="w-5 h-5"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
        </svg>
      </button>
    </div>
  </div>
</header>

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
