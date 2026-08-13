<div id="areaModal"
     class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between p-6 border-b border-gray-200">
      <h3 class="text-xl font-bold text-gray-800"
          id="modalTitle">Tambah Area Baru</h3>
      <button onclick="closeModal()"
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

    <form id="areaForm"
          method="POST"
          action="{{ route('admin.areas.store') }}"
          enctype="multipart/form-data"
          class="p-6">
      @csrf
      <input type="hidden"
             name="_method"
             id="formMethod"
             value="POST">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="md:col-span-2">
          <label class="block text-sm font-semibold text-gray-700 mb-2">
            Nama Area <span class="text-red-500">*</span>
          </label>
          <input type="text"
                 name="name"
                 id="name"
                 required
                 placeholder="e.g., VIP Room, Balcony, Main Lounge"
                 class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">
            Kode <span class="text-red-500">*</span>
          </label>
          <input type="text"
                 name="code"
                 id="code"
                 required
                 placeholder="e.g., ROOM, BLCY, LNG"
                 class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent uppercase">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">
            Kapasitas (Pax)
          </label>
          <input type="number"
                 name="capacity"
                 id="capacity"
                 min="0"
                 placeholder="0"
                 class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent">
        </div>
        <div class="md:col-span-2">
          <label for="image" class="block text-sm font-semibold text-gray-700 mb-2">
            Gambar Denah
          </label>
          <div class="grid grid-cols-1 sm:grid-cols-[1fr_180px] gap-4 items-start">
            <div>
              <input type="file"
                     name="image"
                     id="image"
                     accept="image/jpeg,image/png,image/webp"
                     class="block w-full text-sm text-gray-600 border border-gray-300 rounded-lg file:mr-4 file:py-3 file:px-4 file:border-0 file:bg-teal-50 file:text-teal-700 file:font-semibold hover:file:bg-teal-100">
              <p class="mt-2 text-xs text-gray-500">JPG, PNG, atau WebP. Maksimal 2 MB. Gunakan gambar tampak atas.</p>
            </div>
            <div id="imagePreviewFrame"
                 class="hidden overflow-hidden rounded-lg border border-gray-200 bg-slate-50 aspect-[4/3]">
              <img id="imagePreview"
                   src=""
                   alt="Preview gambar denah"
                   class="w-full h-full object-cover">
            </div>
          </div>
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-semibold text-gray-700 mb-2">
            Deskripsi
          </label>
          <textarea name="description"
                    id="description"
                    rows="3"
                    placeholder="Deskripsi area..."
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent"></textarea>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">
            Urutan Tampilan
          </label>
          <input type="number"
                 name="sort_order"
                 id="sort_order"
                 value="0"
                 min="0"
                 class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent">
        </div>
        <div class="flex items-center">
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox"
                   name="is_active"
                   id="is_active"
                   value="1"
                   checked
                   class="sr-only peer">
            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-teal-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-teal-600"></div>
            <span class="ml-3 text-sm font-semibold text-gray-700">Active</span>
          </label>
        </div>
      </div>
      <div class="flex items-center justify-end space-x-3 mt-8 pt-6 border-t border-gray-200">
        <button type="button"
                onclick="closeModal()"
                class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold transition">
          Batal
        </button>
        <button type="submit"
                class="px-6 py-2.5 bg-teal-600 hover:bg-teal-700 text-white rounded-lg font-semibold transition">
          <span id="submitButtonText">Tambah</span>
        </button>
      </div>
    </form>
  </div>
</div>
