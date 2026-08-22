@php
  $tierColors = \App\Models\Tier::COLORS;
@endphp

<div id="tierModal"
     class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-2xl max-w-xl w-full max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between p-6 border-b border-gray-200">
      <h3 class="text-xl font-bold text-gray-800"
          id="modalTitle">Tambah Tier Baru</h3>
      <button onclick="closeModal()"
              type="button"
              class="text-gray-400 hover:text-gray-600">
        <svg class="w-6 h-6"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <form id="tierForm"
          method="POST"
          action="{{ route('admin.settings.tier-settings.store') }}"
          class="p-6">
      @csrf
      <input type="hidden"
             name="_method"
             id="formMethod"
             value="POST">

      <div class="space-y-4">
        <!-- Nama Tier -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">
            Nama Tier <span class="text-red-500">*</span>
          </label>
          <input type="text"
                 name="name"
                 id="tierNameInput"
                 required
                 placeholder="e.g. Registered, VIP, Diamond"
                 class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent">
        </div>

        <!-- Diskon & Min Spent -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">
              Diskon (%) <span class="text-red-500">*</span>
            </label>
            <input type="number"
                   name="discount_percentage"
                   id="tierDiscountInput"
                   required
                   min="0"
                   max="100"
                   value="0"
                   class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent">
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">
              Min. Total Spent (Rp) <span class="text-red-500">*</span>
            </label>
            <input type="number"
                   name="minimum_spent"
                   id="tierMinSpentInput"
                   required
                   min="0"
                   step="100000"
                   value="0"
                   class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent">
            <p id="firstTierHint"
               class="hidden text-xs text-slate-400 mt-1">Tier awal: minimum spent selalu Rp 0</p>
          </div>
        </div>

        <!-- Warna Badge -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">
            Warna Badge <span class="text-red-500">*</span>
          </label>
          <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
            @foreach ($tierColors as $colorKey => $colorConfig)
              <label class="cursor-pointer">
                <input type="radio"
                       name="color"
                       value="{{ $colorKey }}"
                       class="peer sr-only">
                <div class="flex items-center justify-center py-2 px-3 rounded-lg text-xs font-semibold {{ $colorConfig['badge'] }} border-2 border-transparent peer-checked:border-violet-600 peer-checked:ring-2 peer-checked:ring-violet-200 transition">
                  {{ ucfirst($colorKey) }}
                </div>
              </label>
            @endforeach
          </div>
        </div>

        <!-- Ketentuan / Deskripsi -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">
            Ketentuan & Benefit
          </label>
          <textarea name="description"
                    id="tierDescInput"
                    rows="3"
                    maxlength="500"
                    placeholder="Tulis ketentuan, benefit, atau catatan untuk tier ini..."
                    class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent"></textarea>
        </div>
      </div>

      <div class="flex items-center justify-end space-x-3 mt-6 pt-5 border-t border-gray-200">
        <button type="button"
                onclick="closeModal()"
                class="px-5 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm font-semibold transition">
          Batal
        </button>
        <button type="submit"
                class="px-5 py-2.5 bg-violet-600 hover:bg-violet-700 text-white rounded-lg text-sm font-semibold transition">
          <span id="submitButtonText">Tambah Tier</span>
        </button>
      </div>
    </form>
  </div>
</div>
