
<div id="leaderboardContent"
     class="hidden">
  <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <!-- Tabs Navigation -->
    <div class="mb-6 flex items-center gap-2 border-b border-gray-200">
      <button id="leaderboardTabLifetime"
              onclick="showLeaderboardTab('lifetime')"
              class="px-4 py-3 font-medium text-gray-700 border-b-2 border-transparent hover:text-gray-900 -mb-px transition {{ request('leaderboard_type') !== 'today' ? 'border-slate-800 text-slate-800' : '' }}">
        Lifetime Top Spenders
      </button>
      <button id="leaderboardTabToday"
              onclick="showLeaderboardTab('today')"
              class="px-4 py-3 font-medium text-gray-700 border-b-2 border-transparent hover:text-gray-900 -mb-px transition {{ request('leaderboard_type') === 'today' ? 'border-slate-800 text-slate-800' : '' }}">
        Today Top Spenders
      </button>
    </div>

    <!-- Lifetime Leaderboard -->
    <div id="leaderboardLifetime"
         class="{{ request('leaderboard_type') === 'today' ? 'hidden' : '' }}">
      <div class="mb-4 flex items-center justify-between gap-3">
        <h3 class="text-lg font-bold text-gray-900">Top {{ $leaderboardLimit }} Spenders (Lifetime)</h3>
        <form method="GET"
              action="{{ route('admin.customers.index') }}"
              class="flex items-center gap-2">
          <input type="hidden"
                 name="tab"
                 value="leaderboard">
          <input type="hidden"
                 name="leaderboard_type"
                 value="lifetime">
          <label for="leaderboard_limit"
                 class="text-sm text-gray-600">Tampilkan</label>
          <select id="leaderboard_limit"
                  name="leaderboard_limit"
                  onchange="this.form.submit()"
                  class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-slate-500 focus:border-transparent">
            @foreach ($leaderboardLimitOptions as $limitOption)
              <option value="{{ $limitOption }}"
                      @selected($leaderboardLimit === $limitOption)>
                Top {{ $limitOption }}
              </option>
            @endforeach
          </select>
        </form>
      </div>
      <div class="space-y-4">
        @forelse ($leaderboard as $index => $customer)
          <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
            <div class="flex-shrink-0">
              <div class="w-10 h-10 rounded-full bg-gradient-to-br from-yellow-400 to-orange-500 flex items-center justify-center text-white font-bold">
                #{{ $index + 1 }}
              </div>
            </div>
            <div class="flex-1">
              <div class="font-medium text-gray-900">{{ $customer->user->name }}</div>
              <div class="text-xs text-gray-500">{{ $customer->customer_code }}</div>
            </div>
            <div class="text-right">
              <div class="font-bold text-green-600">Rp {{ number_format((float) ($customer->transaction_lifetime_spending ?? 0), 0, ',', '.') }}</div>
            </div>
            @php $lifetimeTier = $tiers->first(fn($t) => $t->minimum_spent <= (float) ($customer->transaction_lifetime_spending ?? 0)); @endphp
            @if ($lifetimeTier)
              <span class="px-3 py-1 text-xs font-medium rounded-full {{ $lifetimeTier->colorClasses('badge') }}">
                {{ $lifetimeTier->name }}
              </span>
            @endif
          </div>
        @empty
          <div class="text-center py-8 text-gray-500">
            Tidak ada data top spenders lifetime
          </div>
        @endforelse
      </div>
    </div>

    <!-- Today's Leaderboard -->
    <div id="leaderboardTodayContent"
         class="{{ request('leaderboard_type') === 'today' ? '' : 'hidden' }}">
      <div class="mb-4 flex items-center justify-between gap-3">
        <h3 class="text-lg font-bold text-gray-900">Top {{ $leaderboardLimit }} Spenders</h3>
        <form method="GET"
              action="{{ route('admin.customers.index') }}"
              class="flex items-center gap-2">
          <input type="hidden"
                 name="tab"
                 value="leaderboard">
          <input type="hidden"
                 name="leaderboard_type"
                 value="today">
          <label for="leaderboard_limit_today"
                 class="text-sm text-gray-600">Tampilkan</label>
          <select id="leaderboard_limit_today"
                  name="leaderboard_limit"
                  onchange="this.form.submit()"
                  class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-slate-500 focus:border-transparent">
            @foreach ($leaderboardLimitOptions as $limitOption)
              <option value="{{ $limitOption }}"
                      @selected($leaderboardLimit === $limitOption)>
                Top {{ $limitOption }}
              </option>
            @endforeach
          </select>
        </form>
      </div>
      <div class="space-y-4">
        @forelse ($leaderboardToday as $index => $customer)
          <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
            <div class="flex-shrink-0">
              <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-400 to-cyan-500 flex items-center justify-center text-white font-bold">
                #{{ $index + 1 }}
              </div>
            </div>
            <div class="flex-1">
              <div class="font-medium text-gray-900">{{ $customer->user->name }}</div>
              <div class="text-xs text-gray-500">{{ $customer->customer_code }}</div>
            </div>
            <div class="text-right">
              <div class="font-bold text-blue-600">Rp {{ number_format((float) ($customer->transaction_daily_spending ?? 0), 0, ',', '.') }}</div>
            </div>
            @php $dailyTier = $tiers->first(fn($t) => $t->minimum_spent <= (float) ($customer->transaction_daily_spending ?? 0)); @endphp
            @if ($dailyTier)
              <span class="px-3 py-1 text-xs font-medium rounded-full {{ $dailyTier->colorClasses('badge') }}">
                {{ $dailyTier->name }}
              </span>
            @endif
          </div>
        @empty
          <div class="text-center py-8 text-gray-500">
            Tidak ada data top spenders hari ini
          </div>
        @endforelse
      </div>
    </div>
  </div>
</div>

@push('scripts')
  <script>
    function showLeaderboardTab(tab) {
      const lifetimeBtn = document.getElementById('leaderboardTabLifetime');
      const todayBtn = document.getElementById('leaderboardTabToday');
      const lifetimeContent = document.getElementById('leaderboardLifetime');
      const todayContent = document.getElementById('leaderboardTodayContent');

      if (tab === 'lifetime') {
        lifetimeBtn.classList.add('border-slate-800', 'text-slate-800');
        lifetimeBtn.classList.remove('border-transparent', 'text-gray-700');
        todayBtn.classList.remove('border-slate-800', 'text-slate-800');
        todayBtn.classList.add('border-transparent', 'text-gray-700');
        lifetimeContent.classList.remove('hidden');
        todayContent.classList.add('hidden');
      } else {
        todayBtn.classList.add('border-slate-800', 'text-slate-800');
        todayBtn.classList.remove('border-transparent', 'text-gray-700');
        lifetimeBtn.classList.remove('border-slate-800', 'text-slate-800');
        lifetimeBtn.classList.add('border-transparent', 'text-gray-700');
        todayContent.classList.remove('hidden');
        lifetimeContent.classList.add('hidden');
      }
    }
  </script>
@endpush
