<aside class="bg-slate-800 text-white flex flex-col shrink-0 overflow-hidden transition-all duration-300"
       :class="sidebarOpen ? 'w-64' : 'w-0'">
  <!-- Logo -->
  <div class="p-6 border-b border-slate-700">
    <div class="flex items-center space-x-3">
      <div class="bg-blue-600 rounded-lg p-2">
        <svg class="w-6 h-6 text-white"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
        </svg>
      </div>
      <div>
        <h1 class="text-lg font-bold">126 Club</h1>
        <p class="text-xs text-slate-400">Premium Management</p>
      </div>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="flex-1 overflow-y-auto py-4 px-3">
    <!-- OPERATIONS -->
    @canany(['admin.dashboard', 'admin.tables.*', 'admin.events.*'])
      <div class="mb-6">
        <h3 class="px-3 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Operations</h3>
        @can('admin.dashboard')
          <x-nav-link href="{{ route('admin.dashboard') }}"
                      :active="request()->routeIs('admin.dashboard')">
            <x-slot name="icon">
              <svg class="w-5 h-5"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
            </x-slot>
            Dashboard
          </x-nav-link>
        @endcan
        @can('admin.tables.*')
          <x-nav-link href="{{ route('admin.tables.index') }}"
                      :active="request()->routeIs('admin.tables.*')">
            <x-slot name="icon">
              <svg class="w-5 h-5"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
              </svg>
            </x-slot>
            Meja
          </x-nav-link>
        @endcan
        {{-- Active Tables dipindahkan ke tab Bookings --}}
        {{-- Table Scanner hidden from sidebar (accessible via waiter mobile app) --}}
        {{--
      <x-nav-link href="{{ route('admin.table-scanner.index') }}"
                  :active="request()->routeIs('admin.table-scanner.*')">
        <x-slot name="icon">
          <svg class="w-5 h-5"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
          </svg>
        </x-slot>
        Table Scanner
      </x-nav-link>
      --}}
        @can('admin.events.*')
          <x-nav-link href="{{ route('admin.events.index') }}"
                      :active="request()->routeIs('admin.events.*')">
            <x-slot name="icon">
              <svg class="w-5 h-5"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </x-slot>
            Acara
          </x-nav-link>
        @endcan
      </div>
    @endcanany

    @canany(['admin.pos.*', 'admin.bookings.*', 'admin.active-tables.readonly', 'admin.transaction-history.*', 'admin.recap.*'])
      <!-- TRANSACTION -->
      <div class="mb-6">
        <h3 class="px-3 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Transaction</h3>
        @can('admin.pos.*')
          <x-nav-link href="{{ route('admin.pos.index') }}"
                      :active="request()->routeIs('admin.pos.*')">
            <x-slot name="icon">
              <svg class="w-5 h-5"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
            </x-slot>
            Point of Sale
          </x-nav-link>
        @endcan
        @can('admin.bookings.*')
          <x-nav-link href="{{ route('admin.bookings.index') }}"
                      :active="request()->routeIs('admin.bookings.*')"
                      :badge="$pendingBookingsCount ?: null">
            <x-slot name="icon">
              <svg class="w-5 h-5"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </x-slot>
            Booking
          </x-nav-link>
        @endcan
        @can('admin.active-tables.readonly')
          <x-nav-link href="{{ route('admin.active-tables.readonly') }}"
                      :active="request()->routeIs('admin.active-tables.readonly')">
            <x-slot name="icon">
              <svg class="w-5 h-5"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </x-slot>
            Active Tables
          </x-nav-link>
        @endcan
        @can('admin.transaction-history.*')
          <x-nav-link href="{{ route('admin.transaction-history.index') }}"
                      :active="request()->routeIs('admin.transaction-history.*') && request('transaction_mode') !== 'walk_in'">
            <x-slot name="icon">
              <svg class="w-5 h-5"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </x-slot>
            Riwayat Transaksi
          </x-nav-link>

          <x-nav-link href="{{ route('admin.transaction-history.index', ['transaction_mode' => 'walk_in']) }}"
                      :active="request()->routeIs('admin.transaction-history.*') && request('transaction_mode') === 'walk_in'">
            <x-slot name="icon">
              <svg class="w-5 h-5"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
            </x-slot>
            Walk In
          </x-nav-link>
        @endcan

        @can('admin.recap.*')
          <x-nav-link href="{{ route('admin.recap.index') }}"
                      :active="request()->routeIs('admin.recap.*')">
            <x-slot name="icon">
              <svg class="w-5 h-5"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M9 17v-6m3 6V7m3 10v-3m5 7H4a2 2 0 01-2-2V5a2 2 0 012-2h16a2 2 0 012 2v14a2 2 0 01-2 2z" />
              </svg>
            </x-slot>
            Rekapan
          </x-nav-link>
        @endcan
      </div>
    @endcanany

    @canany(['admin.inventory.*', 'admin.menus.*', 'admin.stock-opname.*'])
      <!-- PRODUCTION -->
      <div class="mb-6">
        <h3 class="px-3 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Production</h3>
        @can('admin.inventory.*')
          <x-nav-link href="{{ route('admin.inventory.index') }}"
                      :active="request()->routeIs('admin.inventory.*')">
            <x-slot name="icon">
              <svg class="w-5 h-5"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
              </svg>
            </x-slot>
            Warehouse
          </x-nav-link>
        @endcan
        @can('admin.menus.*')
          <x-nav-link href="{{ route('admin.menus.index') }}"
                      :active="request()->routeIs('admin.menus.*')">
            <x-slot name="icon">
              <svg class="w-5 h-5"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
              </svg>
            </x-slot>
            Menu
          </x-nav-link>
        @endcan
        @can('admin.stock-opname.*')
          <x-nav-link href="{{ route('admin.stock-opname.index') }}"
                      :active="request()->routeIs('admin.stock-opname.*')">
            <x-slot name="icon">
              <svg class="w-5 h-5"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
              </svg>
            </x-slot>
            Stock Opname
          </x-nav-link>
        @endcan
      </div>
    @endcanany

    @canany(['admin.kitchen.*', 'admin.bar.*', 'admin.transaction-checker.*'])
      <!-- ORDER CHECKER -->
      <div class="mb-6">
        <h3 class="px-3 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Order Checker</h3>
        @can('admin.kitchen.*')
          <x-nav-link href="{{ route('admin.kitchen.index') }}"
                      :active="request()->routeIs('admin.kitchen.*')">
            <x-slot name="icon">
              <svg class="w-5 h-5"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
              </svg>
            </x-slot>
            Kitchen
          </x-nav-link>
        @endcan
        @can('admin.bar.*')
          <x-nav-link href="{{ route('admin.bar.index') }}"
                      :active="request()->routeIs('admin.bar.*')">
            <x-slot name="icon">
              <svg class="w-5 h-5"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
              </svg>
            </x-slot>
            Bar
          </x-nav-link>
        @endcan
        @can('admin.transaction-checker.*')
          <x-nav-link href="{{ route('admin.transaction-checker.index') }}"
                      :active="request()->routeIs('admin.transaction-checker.*')">
            <x-slot name="icon">
              <svg class="w-5 h-5"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
              </svg>
            </x-slot>
            Transaction Checker
          </x-nav-link>
        @endcan
      </div>
    @endcanany

    @canany(['admin.customers.*', 'admin.customer-keep.*', 'admin.rewards.*', 'admin.song-requests.*', 'admin.display-messages.*'])
      <!-- CUSTOMER MANAGEMENT -->
      <div class="mb-6">
        <h3 class="px-3 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Customer Management</h3>
        @can('admin.customers.*')
          <x-nav-link href="{{ route('admin.customers.index') }}"
                      :active="request()->routeIs('admin.customers.*')">
            <x-slot name="icon">
              <svg class="w-5 h-5"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
              </svg>
            </x-slot>
            Customer
          </x-nav-link>
        @endcan
        @can('admin.customer-keep.*')
          <x-nav-link href="{{ route('admin.customer-keep.index') }}"
                      :active="request()->routeIs('admin.customer-keep.*')">
            <x-slot name="icon">
              <svg class="w-5 h-5"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
              </svg>
            </x-slot>
            Customer Keep
          </x-nav-link>
        @endcan
        @can('admin.rewards.*')
          <x-nav-link href="{{ route('admin.rewards.index') }}"
                      :active="request()->routeIs('admin.rewards.*')">
            <x-slot name="icon">
              <svg class="w-5 h-5"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
              </svg>
            </x-slot>
            Reward
          </x-nav-link>
        @endcan
        @can('admin.song-requests.*')
          <x-nav-link href="{{ route('admin.song-requests.index') }}"
                      :active="request()->routeIs('admin.song-requests.*')"
                      :badge="$pendingSongRequestsCount ?: null">
            <x-slot name="icon">
              <svg class="w-5 h-5"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
              </svg>
            </x-slot>
            Song Request
          </x-nav-link>
        @endcan
        @can('admin.display-messages.*')
          <x-nav-link href="{{ route('admin.display-messages.index') }}"
                      :active="request()->routeIs('admin.display-messages.*')"
                      :badge="$pendingDisplayMessagesCount ?: null">
            <x-slot name="icon">
              <svg class="w-5 h-5"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
              </svg>
            </x-slot>
            Display Message
          </x-nav-link>
        @endcan
      </div>
    @endcanany

    @canany(['admin.waiter-performance.*', 'admin.settings.*'])
      <!-- SYSTEM -->
      <div class="mb-6">
        <h3 class="px-3 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">System</h3>
        @can('admin.waiter-performance.*')
          <x-nav-link href="{{ route('admin.waiter-performance.index') }}"
                      :active="request()->routeIs('admin.waiter-performance.*')">
            <x-slot name="icon">
              <svg class="w-5 h-5"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
            </x-slot>
            Waiter Performance
          </x-nav-link>
        @endcan
        @can('admin.settings.*')
          <x-nav-link href="{{ route('admin.settings.index') }}"
                      :active="request()->routeIs('admin.settings.*')">
            <x-slot name="icon">
              <svg class="w-5 h-5"
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
            </x-slot>
            Pengaturan
          </x-nav-link>
        @endcan
      </div>
    @endcanany
  </nav>

  <!-- Footer -->
  <div class="p-4 border-t border-slate-700">
    <div class="flex items-center justify-between px-3">
      <div class="flex items-center space-x-2">
        <div class="bg-slate-700 rounded p-1.5">
          <span class="text-xs font-bold">126</span>
        </div>
        <div class="text-xs">
          <p class="font-semibold">126 Club</p>
          <p class="text-slate-400">v1.0.0</p>
        </div>
      </div>
      <button class="p-1.5 hover:bg-slate-700 rounded">
        <svg class="w-4 h-4"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
      </button>
    </div>
  </div>
</aside>
