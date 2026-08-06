<x-app-layout title="Pengaturan Tier Customer">
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
      <h1 class="text-2xl font-bold text-slate-800">Tier Settings</h1>
      <p class="text-sm text-slate-500 mt-1">Atur nama tier, diskon, dan minimum transaksi untuk naik tier</p>
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
        Tentang Sistem Tier:
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
          <span>Customer akan <strong>otomatis naik tier</strong> ketika <strong>TOTAL SPENT (akumulasi semua transaksi)</strong> mencapai minimum yang ditentukan</span>
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
          <span>Bukan per transaksi, tapi <strong>total keseluruhan belanja</strong> customer sejak bergabung</span>
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
          <span><strong>Diskon tier</strong> akan diterapkan secara otomatis pada transaksi berikutnya</span>
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
          <span>Tier 1 adalah tier awal untuk semua customer baru (minimum Rp 0 tidak bisa diubah)</span>
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
          <span>Nama tier dapat dikustomisasi sesuai kebutuhan club Anda</span>
        </li>
      </ul>
    </div>

    <!-- Tier Form -->
    <form action="{{ route('admin.settings.tier-settings.update') }}"
          method="POST"
          x-data="tierSettings()"
          x-init="init()"
          id="tierForm">
      @csrf
      @method('PUT')

      <div class="space-y-4 mb-6">
        @php
          $tierColors = [
              'slate' => ['bg' => 'bg-slate-100', 'icon' => 'text-slate-500', 'badge' => 'bg-slate-100 text-slate-700'],
              'blue' => ['bg' => 'bg-blue-100', 'icon' => 'text-blue-500', 'badge' => 'bg-blue-100 text-blue-700'],
              'amber' => ['bg' => 'bg-amber-100', 'icon' => 'text-amber-500', 'badge' => 'bg-amber-100 text-amber-700'],
          ];
          $tierLabels = [1 => 'Tier awal untuk customer baru', 2 => 'Tier menengah untuk customer setia', 3 => 'Tier tertinggi untuk VIP customer'];
        @endphp

        @foreach ($tiers as $index => $tier)
          @php $colors = $tierColors[$tier->color] ?? $tierColors['slate']; @endphp
          <div class="bg-white border border-slate-200 rounded-xl p-6">
            <!-- Tier Header -->
            <div class="flex items-center justify-between mb-5">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 {{ $colors['bg'] }} rounded-xl flex items-center justify-center">
                  <svg class="w-5 h-5 {{ $colors['icon'] }}"
                       fill="none"
                       stroke="currentColor"
                       viewBox="0 0 24 24">
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                </div>
                <div>
                  <h3 class="font-bold text-slate-800">Tier {{ $tier->level }}
                    <span class="font-normal text-slate-500">({{ ucfirst($tier->color) }})</span>
                  </h3>
                  <p class="text-xs text-slate-500">{{ $tierLabels[$tier->level] ?? '' }}</p>
                </div>
              </div>
              <span class="text-xs font-semibold px-2.5 py-1 rounded-full {{ $colors['badge'] }}"
                    x-text="tierNames[{{ $index }}] || '{{ $tier->name }}'">{{ $tier->name }}</span>
            </div>

            <input type="hidden"
                   name="tiers[{{ $index }}][id]"
                   value="{{ $tier->id }}">

            <!-- Fields -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
              <div>
                <label class="block text-sm font-medium text-slate-600 mb-1.5">Nama Tier</label>
                <input type="text"
                       name="tiers[{{ $index }}][name]"
                       x-model="tierNames[{{ $index }}]"
                       value="{{ $tier->name }}"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white @error('tiers.' . $index . '.name') border-red-400 @enderror"
                       required>
                @error('tiers.' . $index . '.name')
                  <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
              </div>

              <div>
                <label class="block text-sm font-medium text-slate-600 mb-1.5">Diskon (%)</label>
                <input type="number"
                       name="tiers[{{ $index }}][discount_percentage]"
                       x-model="discounts[{{ $index }}]"
                       value="{{ $tier->discount_percentage }}"
                       min="0"
                       max="100"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white @error('tiers.' . $index . '.discount_percentage') border-red-400 @enderror"
                       required>
                @error('tiers.' . $index . '.discount_percentage')
                  <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
              </div>

              <div>
                <label class="block text-sm font-medium text-slate-600 mb-1.5">Total Spent Minimum (Rp)</label>
                @if ($tier->is_first_tier)
                  <input type="number"
                         value="0"
                         disabled
                         class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm bg-slate-100 text-slate-400 cursor-not-allowed">
                  <input type="hidden"
                         name="tiers[{{ $index }}][minimum_spent]"
                         value="0">
                  <p class="text-xs text-slate-400 mt-1">Tier awal, tidak bisa diubah</p>
                @else
                  <input type="number"
                         name="tiers[{{ $index }}][minimum_spent]"
                         x-model="minimumSpents[{{ $index }}]"
                         value="{{ $tier->minimum_spent }}"
                         min="0"
                         step="1000000"
                         class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white @error('tiers.' . $index . '.minimum_spent') border-red-400 @enderror"
                         required>
                  @error('tiers.' . $index . '.minimum_spent')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                  @enderror
                @endif
              </div>
            </div>

            <!-- Preview -->
            <div class="bg-{{ $tier->color === 'amber' ? 'amber' : 'blue' }}-50 border border-{{ $tier->color === 'amber' ? 'amber' : 'blue' }}-100 rounded-lg px-4 py-2.5 text-sm">
              <span class="font-semibold text-{{ $tier->color === 'amber' ? 'amber' : 'blue' }}-700">Preview:</span>
              <span class="text-{{ $tier->color === 'amber' ? 'amber' : 'blue' }}-700">
                Customer dengan total spent ≥ Rp
                <span x-text="{{ $tier->is_first_tier ? '\'0\'' : 'Number(minimumSpents[' . $index . ']).toLocaleString(\'id-ID\')' }}">{{ number_format($tier->minimum_spent, 0, ',', '.') }}</span>
                mendapat diskon
                <span class="font-semibold"
                      x-text="discounts[{{ $index }}] + '%'">{{ $tier->discount_percentage }}%</span>
              </span>
            </div>
          </div>
        @endforeach
      </div>

      <!-- Action Buttons -->
      <div class="flex gap-3">
        <button type="submit"
                class="flex-1 flex items-center justify-center gap-2 bg-violet-600 hover:bg-violet-700 text-white font-semibold py-3 rounded-xl transition-colors">
          <svg class="w-4 h-4"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
          </svg>
          Simpan Perubahan
        </button>

        <form action="{{ route('admin.settings.tier-settings.reset') }}"
              method="POST">
          @csrf
          @method('DELETE')
          <button type="submit"
                  onclick="return confirm('Reset semua tier ke nilai default?')"
                  class="flex items-center gap-2 border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold px-5 py-3 rounded-xl transition-colors text-sm">
            <svg class="w-4 h-4"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Reset ke Default
          </button>
        </form>
      </div>
    </form>

    <!-- Ringkasan Tier -->
    <div class="mt-8 bg-white border border-slate-200 rounded-xl p-6">
      <h2 class="text-base font-bold text-slate-800 mb-4">Ringkasan Tier</h2>
      <div class="space-y-3">
        @php
          $badgeColors = [
              'slate' => 'bg-slate-200 text-slate-700',
              'blue' => 'bg-blue-600 text-white',
              'amber' => 'bg-amber-500 text-white',
          ];
        @endphp
        @foreach ($tiers as $tier)
          <div class="flex items-center justify-between py-2 border-b border-slate-100 last:border-0">
            <div class="flex items-center gap-3">
              <span class="text-xs font-bold px-2.5 py-1 rounded-full {{ $badgeColors[$tier->color] ?? 'bg-slate-200 text-slate-700' }}">
                {{ $tier->name }}
              </span>
              <span class="text-sm text-slate-600">• {{ $tier->formattedMinimumSpent }}</span>
            </div>
            <span class="text-sm font-semibold text-slate-700">{{ $tier->discount_percentage }}% diskon</span>
          </div>
        @endforeach
      </div>
    </div>

  </div>

  @push('scripts')
    <script>
      function tierSettings() {
        return {
          tierNames: @json($tiers->pluck('name')->values()),
          discounts: @json($tiers->pluck('discount_percentage')->values()),
          minimumSpents: @json($tiers->pluck('minimum_spent')->values()),
          init() {},
        }
      }
    </script>
  @endpush
</x-app-layout>
