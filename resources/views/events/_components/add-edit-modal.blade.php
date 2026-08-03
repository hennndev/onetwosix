<div id="eventModal"
     class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
    <div class="p-6 border-b border-gray-200">
      <h3 id="modalTitle"
          class="text-xl font-bold text-gray-900">Tambah Event</h3>
    </div>
    <form id="eventForm"
          method="POST"
          action="{{ route('admin.events.store') }}"
          class="p-6">
      @csrf
      <input type="hidden"
             name="_method"
             value="POST"
             id="formMethod">

      <div class="space-y-4">
        <!-- Area -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Area <span class="text-xs text-gray-500">(Opsional - Kosongkan untuk Semua Area)</span></label>
          <select name="area_id"
                  id="area_id"
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent">
            <option value="">Semua Area (Global)</option>
            @foreach (($areas ?? []) as $area)
              <option value="{{ $area->id }}">{{ $area->name }}</option>
            @endforeach
          </select>
        </div>

        <!-- Event Name -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Nama Event <span class="text-red-500">*</span></label>
          <input type="text"
                 name="name"
                 id="name"
                 required
                 placeholder="Contoh: Christmas Week Celebration"
                 class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent">
        </div>

        <!-- Description -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Deskripsi</label>
          <textarea name="description"
                    id="description"
                    rows="3"
                    placeholder="Deskripsi event..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent"></textarea>
        </div>

        <!-- Date Range -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Mulai <span class="text-red-500">*</span></label>
            <input type="date"
                   name="start_date"
                   id="start_date"
                   required
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Berakhir <span class="text-red-500">*</span></label>
            <input type="date"
                   name="end_date"
                   id="end_date"
                   required
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent">
          </div>
        </div>

        <!-- Time Range (Optional) -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Jam Mulai (Optional)</label>
            <input type="time"
                   name="start_time"
                   id="start_time"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Jam Berakhir (Optional)</label>
            <input type="time"
                   name="end_time"
                   id="end_time"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent">
          </div>
        </div>

        <!-- Price Adjustment Type -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Tipe Kenaikan Harga <span class="text-red-500">*</span></label>
          <select name="price_adjustment_type"
                  id="price_adjustment_type"
                  required
                  onchange="updatePriceLabel()"
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent">
            <option value="fixed">Fixed Amount (Rp)</option>
            <option value="percentage">Percentage (%)</option>
          </select>
        </div>

        <!-- Price Adjustment Value -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            <span id="priceLabel">Nilai Kenaikan (Rp)</span>
            <span class="text-red-500">*</span>
          </label>
          <input type="text"
                 name="price_adjustment_value"
                 id="price_adjustment_value"
                 inputmode="decimal"
                 required
                 placeholder="0"
                 class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent">
          <p id="priceHelp"
             class="text-xs text-gray-500 mt-1">Ditambahkan ke harga minimum charge</p>
        </div>

        <!-- Active Status -->
        <div class="flex items-center">
          <input type="checkbox"
                 name="is_active"
                 id="is_active"
                 class="w-4 h-4 text-slate-600 focus:ring-slate-500 border-gray-300 rounded">
          <label for="is_active"
                 class="ml-2 text-sm font-medium text-gray-700">Aktifkan event sekarang</label>
        </div>
      </div>

      <div class="flex justify-end gap-3 mt-6">
        <button type="button"
                onclick="closeModal()"
                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
          Batal
        </button>
        <button type="submit"
                class="px-4 py-2 bg-slate-800 text-white rounded-lg hover:bg-slate-900 transition">
          Simpan
        </button>
      </div>
    </form>
  </div>
</div>
