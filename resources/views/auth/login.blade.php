<x-guest-layout title="Login">
  <div class="mb-8">
    <h1 class="text-gray-800 font-black text-6xl tracking-wider mb-2">126</h1>
    <p class="text-gray-500 text-xs tracking-[0.3em] uppercase">One • Two • Six</p>
  </div>
  <div class="mb-8">
    <h2 class="text-3xl font-bold text-gray-800 mb-2">Login</h2>
    <p class="text-gray-500 text-sm">Masuk ke akun administrator</p>
  </div>
  <x-auth-session-status class="mb-4"
                         :status="session('status')" />

  <form method="POST"
        action="{{ route('login') }}"
        class="space-y-5">
    @csrf

    <!-- Email Address -->
    <div>
      <x-text-input id="email"
                    class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                    type="email"
                    name="email"
                    :value="old('email')"
                    placeholder="superadmin@onetwosix.com"
                    required
                    autofocus
                    autocomplete="username" />
      <x-input-error :messages="$errors->get('email')"
                     class="mt-2" />
    </div>
    <div>
      <x-text-input id="password"
                    class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                    type="password"
                    name="password"
                    placeholder="••••••••"
                    required
                    autocomplete="current-password" />
      <x-input-error :messages="$errors->get('password')"
                     class="mt-2" />
    </div>
    <div class="pt-2">
      <button type="submit"
              class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
        Masuk
      </button>
    </div>
  </form>
</x-guest-layout>
