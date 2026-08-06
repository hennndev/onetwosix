<div class="flex-1 flex flex-col overflow-hidden">
  <!-- Search & Filter Bar -->
  <div class="flex flex-wrap items-center gap-3 px-4 sm:px-6 pt-4 sm:pt-6 pb-4 flex-shrink-0">
    <div class="flex-1 relative">
      <form method="GET"
            action="{{ route('admin.pos.index') }}">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <input type="text"
               name="search"
               value="{{ request('search') }}"
               placeholder="Cari produk..."
               class="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
      </form>
    </div>
    <!-- Grid Size Picker -->
    <div class="flex items-center gap-1 bg-white border border-gray-200 rounded-xl px-2 py-1.5">
      <span class="text-xs text-gray-400 font-medium mr-1">Grid</span>
      @foreach ([2, 3, 4, 5, 6] as $cols)
        <button type="button"
                @click="gridCols = {{ $cols }}; localStorage.setItem('posGridCols', {{ $cols }})"
                :class="gridCols === {{ $cols }} ? 'bg-slate-800 text-white' : 'text-gray-500 hover:bg-gray-100'"
                class="w-7 h-7 rounded-lg text-xs font-semibold transition-colors">
          {{ $cols }}
        </button>
      @endforeach
    </div>
  </div>

  <!-- Products Grid -->
  <div class="lg:overflow-y-auto flex-1 px-4 sm:px-6 pb-6">
    <div class="grid gap-4"
         :style="`grid-template-columns: repeat(${gridCols}, minmax(0, 1fr))`">
      @forelse($products as $product)
        @php
          $category = strtolower($product['category'] ?? '');
          $prepLoc = $posSettings->get($product['category'] ?? '')?->preparation_location ?? 'bar';
          $isKitchen = $prepLoc === 'kitchen';
          $isItemGroup = (bool) ($product['is_item_group'] ?? false);
          $gradientClass = $isKitchen ? 'from-orange-500 to-red-600' : 'from-blue-400 to-cyan-500';
          $dotColor = $isKitchen ? 'bg-orange-400' : 'bg-blue-300';
          $isAvailable = (bool) ($product['is_available'] ?? true);
          $outOfStock = isset($product['type']) && $product['type'] === 'item' && !$isItemGroup && ($product['stock'] ?? 0) <= 0;
          $disabled = $outOfStock || !$isAvailable;
        @endphp
        <div class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-shadow flex flex-col {{ $disabled ? 'opacity-60' : '' }}">
          <div class="px-3 pt-3 pb-1">
            <span class="font-bold text-gray-900 text-sm">Rp {{ number_format($product['price'], 0, ',', '.') }}</span>
          </div>
          <div class="relative bg-gradient-to-br {{ $gradientClass }} mx-3 rounded-xl overflow-hidden">
            <div class="absolute top-2 right-2 z-10 w-2.5 h-2.5 rounded-full {{ $dotColor }} opacity-80"></div>
            @if ($disabled)
              <div class="absolute inset-0 z-20 flex items-center justify-center bg-black/50 backdrop-blur-[1px] rounded-xl">
                <span class="px-2.5 py-1 bg-red-600 text-white text-xs font-bold rounded-lg shadow-sm tracking-wide uppercase">Sold Out</span>
              </div>
            @endif
            @if ($isItemGroup)
              <div class="absolute bottom-2 left-2 z-10">
                <span class="px-2 py-0.5 bg-emerald-600 text-white text-xs font-bold rounded shadow-sm">Item Group</span>
              </div>
            @elseif (!$isKitchen && isset($product['stock']))
              <div class="absolute bottom-2 left-2 z-10">
                @if (($product['stock'] ?? 0) > 10)
                  <span class="px-2 py-0.5 bg-green-500 text-white text-xs font-bold rounded">Stock: {{ $product['stock'] }}</span>
                @elseif(($product['stock'] ?? 0) > 0)
                  <span class="px-2 py-0.5 bg-red-500 text-white text-xs font-bold rounded">Stock: {{ $product['stock'] }}</span>
                @else
                  <span class="px-2 py-0.5 bg-gray-500 text-white text-xs font-bold rounded">Stock: 0</span>
                @endif
              </div>
            @endif
            <div class="h-28 flex items-center justify-center">
              @if ($isKitchen)
                {{-- Food: plate with fork & knife --}}
                <svg class="w-16 h-16 text-white/80"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <circle cx="12"
                          cy="12"
                          r="9"
                          stroke-width="1.5" />
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M9 7v4a2 2 0 004 0V7M11 11v6M15 7v10" />
                </svg>
              @else
                {{-- Beverage: cocktail glass --}}
                <svg class="w-16 h-16 text-white/80"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M7 3l2 6h6l2-6H7z" />
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M5 9h14" />
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M9 21h6M12 9v12" />
                </svg>
              @endif
            </div>
          </div>
          <div class="px-3 pt-2 pb-3 flex-1 flex flex-col justify-between">
            <div>
              <h3 class="font-semibold text-gray-900 text-sm truncate">{{ $product['name'] }}</h3>
              <p class="text-xs text-gray-400 mt-0.5 capitalize">{{ ucfirst($product['category']) }}</p>
            </div>
            <div class="flex items-center justify-between mt-2">
              <span class="text-sm font-bold text-gray-900">Rp {{ number_format($product['price'], 0, ',', '.') }}</span>
              <button type="button"
                      @click="addToCart('{{ $product['id'] }}')"
                      :disabled="isProcessing || {{ $disabled ? 'true' : 'false' }}"
                      class="w-8 h-8 bg-gray-900 hover:bg-gray-700 text-white rounded-lg flex items-center justify-center transition disabled:opacity-40 disabled:cursor-not-allowed flex-shrink-0">
                <svg x-show="!isProcessing"
                     class="w-4 h-4"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2.5"
                        d="M12 4v16m8-8H4" />
                </svg>
                <svg x-show="isProcessing"
                     class="w-4 h-4 animate-spin"
                     fill="none"
                     viewBox="0 0 24 24">
                  <circle class="opacity-25"
                          cx="12"
                          cy="12"
                          r="10"
                          stroke="currentColor"
                          stroke-width="4"></circle>
                  <path class="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
              </button>
            </div>
          </div>
        </div>
      @empty
        <div class="col-span-4 text-center py-16">
          <svg class="mx-auto h-12 w-12 text-gray-300"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
          </svg>
          <h3 class="mt-3 text-sm font-semibold text-gray-900">Tidak ada produk</h3>
          <p class="mt-1 text-sm text-gray-400">Produk belum tersedia.</p>
        </div>
      @endforelse
    </div>
  </div>
</div>
