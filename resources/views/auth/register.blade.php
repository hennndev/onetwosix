<x-guest-layout title="Register">
  <!-- Logo -->
  <div class="mb-8">
    <h1 class="text-gray-800 font-black text-6xl tracking-wider mb-2">126</h1>
    <p class="text-gray-500 text-xs tracking-[0.3em] uppercase">One • Two • Six</p>
  </div>

  <!-- Header -->
  <div class="mb-8">
    <h2 class="text-3xl font-bold text-gray-800 mb-2">Daftar</h2>
    <p class="text-gray-500 text-sm">Buat akun administrator baru</p>
  </div>

  <form method="POST"
        action="{{ route('register') }}"
        class="space-y-5">
    @csrf

    <!-- Name -->
    <div>
      <x-text-input id="name"
                    class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                    type="text"
                    name="name"
                    :value="old('name')"
                    placeholder="Nama Lengkap"
                    required
                    autofocus
                    autocomplete="name" />
      <x-input-error :messages="$errors->get('name')"
                     class="mt-2" />
    </div>

    <!-- Email Address -->
    <div>
      <x-text-input id="email"
                    class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                    type="email"
                    name="email"
                    :value="old('email')"
                    placeholder="Email"
                    required
                    autocomplete="username" />
      <x-input-error :messages="$errors->get('email')"
                     class="mt-2" />
    </div>

    <!-- Password -->
    <div>
      <x-text-input id="password"
                    class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                    type="password"
                    name="password"
                    placeholder="Password"
                    required
                    autocomplete="new-password" />
      <x-input-error :messages="$errors->get('password')"
                     class="mt-2" />
    </div>

    <!-- Confirm Password -->
    <div>
      <x-text-input id="password_confirmation"
                    class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                    type="password"
                    name="password_confirmation"
                    placeholder="Konfirmasi Password"
                    required
                    autocomplete="new-password" />
      <x-input-error :messages="$errors->get('password_confirmation')"
                     class="mt-2" />
    </div>

    <!-- Submit Button -->
    <div class="pt-2">
      <button type="submit"
              class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
        Daftar
      </button>
    </div>

    <!-- Login Link -->
    <div class="text-center pt-4">
      <span class="text-gray-600 text-sm">Sudah punya akun?</span>
      <a href="{{ route('login') }}"
         class="text-blue-600 hover:text-blue-800 font-medium text-sm ml-1 transition">
        Masuk Sekarang
      </a>
    </div>
  </form>
</x-guest-layout>
