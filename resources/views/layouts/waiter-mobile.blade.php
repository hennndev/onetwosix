<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
  <meta charset="utf-8">
  <meta name="viewport"
        content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <meta name="csrf-token"
        content="{{ csrf_token() }}">
  <meta name="theme-color"
        content="#f8fafc">

  <title>{{ config('app.name', '126 Club') }}</title>

  <link rel="preconnect"
        href="https://fonts.bunny.net">
  <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap"
        rel="stylesheet" />

  @vite(['resources/css/app.css', 'resources/js/app.js'])

  @stack('styles')

  <style>
    body {
      background-color: #f1f5f9;
      color: #0f172a;
      -webkit-tap-highlight-color: transparent;
      overscroll-behavior: none;
    }

    .nav-icon-active {
      background-color: rgba(20, 184, 166, 0.15);
      border-radius: 10px;
      padding: 6px;
    }
  </style>
</head>

<body class="font-sans antialiased">

  {{-- Fixed Top Header --}}
  <header class="fixed top-0 left-0 right-0 bg-white border-b border-slate-200 z-50 h-12 flex items-center justify-between px-4">
    <div class="flex items-center gap-2">
      <div class="w-6 h-6 rounded bg-teal-500 flex items-center justify-center">
        <span class="text-white font-bold text-xs leading-none">W</span>
      </div>
      <span class="text-sm font-semibold text-slate-800 truncate max-w-[160px]">{{ auth()->user()?->name }}</span>
    </div>
    <div class="flex items-center gap-1">
      <a href="{{ route('waiter.display-messages.index') }}"
         class="p-1.5 rounded-lg {{ request()->routeIs('waiter.display-messages.*') ? 'text-teal-600 bg-teal-50' : 'text-slate-500 hover:bg-slate-100' }} transition"
         aria-label="Display Message">
        <svg class="w-5 h-5"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
        </svg>
      </a>
      <a href="{{ route('waiter.settings') }}"
         class="p-1.5 rounded-lg {{ request()->routeIs('waiter.settings') ? 'text-teal-600 bg-teal-50' : 'text-slate-500 hover:bg-slate-100' }} transition"
         aria-label="Settings">
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
      </a>
    </div>
  </header>

  <div class="pt-12">
    @include('layouts.top-spender-banner')
  </div>

  {{-- Main scrollable content --}}
  <main class="min-h-screen pb-20">
    {{ $slot }}
  </main>

  {{-- Fixed Bottom Navigation --}}
  @php
    $pendingNotifCount = auth()->user()?->unreadNotifications()->where('type', \App\Notifications\WaiterAssignedNotification::class)->count();
  @endphp

  <nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-slate-200 z-50 shadow-lg">
    <div class="flex items-center justify-around px-1 py-1.5">

      {{-- POS --}}
      <a href="{{ route('waiter.pos') }}"
         class="flex flex-col items-center gap-0.5 px-2 py-1 min-w-0 flex-1 rounded-xl transition {{ request()->routeIs('waiter.pos') ? 'text-teal-600' : 'text-slate-500' }}">
        <div class="p-1.5 rounded-xl {{ request()->routeIs('waiter.pos') ? 'bg-teal-50' : '' }}">
          <svg class="w-5 h-5"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
          </svg>
        </div>
        <span class="text-[10px] font-medium leading-none">POS</span>
      </a>

      {{-- Active Table --}}
      <a href="{{ route('waiter.active-tables') }}"
         class="flex flex-col items-center gap-0.5 px-2 py-1 min-w-0 flex-1 rounded-xl transition {{ request()->routeIs('waiter.active-tables') ? 'text-teal-600' : 'text-slate-500' }}">
        <div class="p-1.5 rounded-xl {{ request()->routeIs('waiter.active-tables') ? 'bg-teal-50' : '' }}">
          <svg class="w-5 h-5"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
        </div>
        <span class="text-[10px] font-medium leading-none">Meja</span>
      </a>

      {{-- Transaction Checker --}}
      <a href="{{ route('waiter.transaction-checker') }}"
         class="flex flex-col items-center gap-0.5 px-2 py-1 min-w-0 flex-1 rounded-xl transition {{ request()->routeIs('waiter.transaction-checker') ? 'text-teal-600' : 'text-slate-500' }}">
        <div class="p-1.5 rounded-xl {{ request()->routeIs('waiter.transaction-checker') ? 'bg-teal-50' : '' }}">
          <svg class="w-5 h-5"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
          </svg>
        </div>
        <span class="text-[10px] font-medium leading-none">Transaksi</span>
      </a>

      {{-- Scanner --}}
      <a href="{{ route('waiter.scanner') }}"
         class="flex flex-col items-center gap-0.5 px-2 py-1 min-w-0 flex-1 rounded-xl transition {{ request()->routeIs('waiter.scanner') ? 'text-teal-600' : 'text-slate-500' }}">
        <div class="p-1.5 rounded-xl {{ request()->routeIs('waiter.scanner') ? 'bg-teal-50' : '' }}">
          <svg class="w-5 h-5"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
          </svg>
        </div>
        <span class="text-[10px] font-medium leading-none">Scan</span>
      </a>

      {{-- Notifications --}}
      <a href="{{ route('waiter.notifications') }}"
         class="flex flex-col items-center gap-0.5 px-2 py-1 min-w-0 flex-1 rounded-xl transition {{ request()->routeIs('waiter.notifications') ? 'text-teal-600' : 'text-slate-500' }}">
        <div class="relative p-1.5 rounded-xl {{ request()->routeIs('waiter.notifications') ? 'bg-teal-50' : '' }}">
          <svg class="w-5 h-5"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
          </svg>
          @if ($pendingNotifCount > 0)
            <span class="absolute top-0.5 right-0.5 bg-red-500 text-white rounded-full font-bold leading-none flex items-center justify-center"
                  style="min-width: 16px; height: 16px; font-size: 9px; padding: 0 3px;">
              {{ $pendingNotifCount > 9 ? '9+' : $pendingNotifCount }}
            </span>
          @endif
        </div>
        <span class="text-[10px] font-medium leading-none">Notif</span>
      </a>

    </div>
  </nav>

  @stack('scripts')

</body>

</html>
