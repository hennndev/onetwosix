<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
  <meta charset="utf-8">
  <meta name="viewport"
        content="width=device-width, initial-scale=1">
  <meta name="csrf-token"
        content="{{ csrf_token() }}">

  <title>{{ isset($title) && filled($title) ? $title . ' — ' . config('app.name', '126 Club') : config('app.name', '126 Club') }}</title>

  <!-- Fonts -->
  <link rel="preconnect"
        href="https://fonts.bunny.net">
  <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap"
        rel="stylesheet" />

  <!-- Alpine x-cloak — must be inline before any content renders -->
  <style>
    [x-cloak] {
      display: none !important
    }
  </style>

  <!-- Scripts -->
  @vite(['resources/css/app.css', 'resources/js/app.js'])

  @stack('styles')
</head>

<body class="font-sans antialiased bg-gray-50">
  <div class="flex h-screen overflow-hidden"
       x-data="{ sidebarOpen: window.innerWidth >= 1024 && JSON.parse(localStorage.getItem('sidebarOpen') ?? 'true') }"
       x-cloak
       @keydown.escape.window="sidebarOpen = false; localStorage.setItem('sidebarOpen', false)">
    <!-- Sidebar: off-canvas overlay di mobile, inline di desktop -->
    <aside :class="[
      'fixed z-40 inset-y-0 left-0 overflow-hidden transition-all duration-300 lg:relative lg:inset-auto',
      sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
      sidebarOpen ? 'w-64' : 'w-0 lg:w-64'
    ]">
      @include('layouts.sidebar')
    </aside>

    <!-- Overlay (mobile only, saat sidebar terbuka) -->
    <div x-show="sidebarOpen"
         @click="sidebarOpen = false; localStorage.setItem('sidebarOpen', false)"
         x-transition.opacity
         class="fixed inset-0 z-30 bg-black/50 lg:hidden"></div>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <!-- Top Header -->
      @include('layouts.header')
      @include('layouts.top-spender-banner')

      <!-- Page Content -->
      <main class="flex-1 overflow-y-auto">
        {{ $slot }}
      </main>
    </div>
  </div>

  @stack('scripts')
</body>

</html>
