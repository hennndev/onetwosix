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

  <!-- Scripts -->
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased">
  <div class="min-h-screen flex">
    <!-- Left Side - Login Form -->
    <div class="w-full lg:w-1/2 flex items-center justify-center px-8 py-12 bg-gray-50">
      <div class="w-full max-w-md">
        {{ $slot }}
      </div>
    </div>

    <!-- Right Side - Branding -->
    <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-blue-500 to-blue-600 items-center justify-center p-12 relative overflow-hidden">
      <div class="text-center z-10">
        <!-- Logo -->
        <div class="mb-8">
          <h1 class="text-white font-black text-7xl tracking-wider mb-2">126</h1>
          <p class="text-white text-sm tracking-[0.3em] uppercase">One • Two • Six</p>
        </div>

        <!-- Illustration -->
        <div class="mb-8">
          <img src="{{ asset('images/126-login-image.png') }}"
               alt="DJ Illustration"
               class="size-100 mx-auto drop-shadow-2xl object-contain">
        </div>

        <!-- Tagline -->
        <div>
          <h2 class="text-white font-bold text-4xl mb-3">One Two Six</h2>
          <p class="text-blue-100 text-lg">New Dining and Nightlife Entertainment</p>
        </div>
      </div>

      <!-- Background Decorations -->
      <div class="absolute top-0 right-0 w-96 h-96 bg-blue-400/20 rounded-full blur-3xl"></div>
      <div class="absolute bottom-0 left-0 w-96 h-96 bg-purple-500/20 rounded-full blur-3xl"></div>
    </div>
  </div>
</body>

</html>
