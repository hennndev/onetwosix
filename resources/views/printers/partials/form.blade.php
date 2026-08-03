<!-- Printer Modal -->
<div id="printerModal"
     class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
  <div class="relative top-10 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-xl bg-white">
    <div class="flex items-center justify-between mb-6">
      <h3 id="modalTitle"
          class="text-xl font-bold text-gray-800">Tambah Printer Baru</h3>
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

    <form id="printerForm"
          method="POST"
          action=""
          enctype="multipart/form-data">
      @csrf
      <input type="hidden"
             name="_method"
             id="formMethod"
             value="POST">
      <input type="hidden"
             id="logo_path"
             name="logo_path"
             value="">

      <div class="grid grid-cols-2 gap-4">
        <!-- Name -->
        <div class="col-span-2 sm:col-span-1">
          <label for="name"
                 class="block text-sm font-medium text-gray-700 mb-1">Nama Printer *</label>
          <input type="text"
                 id="name"
                 name="name"
                 required
                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                 placeholder="e.g., Kasir Utama">
        </div>

        <!-- Location -->
        <div class="col-span-2 sm:col-span-1">
          <label for="location"
                 class="block text-sm font-medium text-gray-700 mb-1">Lokasi</label>
          <select id="location"
                  name="location"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option value="">-- Pilih Lokasi --</option>
            @foreach ($printerLocations as $value => $label)
              @if (is_array($label))
                <optgroup label="{{ $value }}">
                  @foreach ($label as $subVal => $subLabel)
                    <option value="{{ $subVal }}"
                            {{ old('location') == $subVal ? 'selected' : '' }}>{{ $subLabel }}</option>
                  @endforeach
                </optgroup>
              @else
                <option value="{{ $value }}"
                        {{ old('location') == $value ? 'selected' : '' }}>{{ $label }}</option>
              @endif
            @endforeach
          </select>
        </div>

        <!-- Printer Type -->
        <div class="col-span-2 sm:col-span-1">
          <label for="printer_type"
                 class="block text-sm font-medium text-gray-700 mb-1">Tipe Printer</label>
          <select id="printer_type"
                  name="printer_type"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option value="">-- Tanpa Tipe --</option>
            <option value="kitchen">Kitchen</option>
            <option value="bar">Bar</option>
            <option value="cashier">Kasir</option>
            <option value="checker">Checker</option>
          </select>
          <p class="mt-1 text-xs text-gray-400">Menentukan tipe print job yang dikirim ke printer ini.</p>
        </div>

        <!-- Area Assignment -->
        <div class="col-span-2 sm:col-span-1">
          <label for="area_id"
                 class="block text-sm font-medium text-gray-700 mb-1">Area Spesifik (Opsional)</label>
          <select id="area_id"
                  name="area_id"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option value="">-- Semua Area / Tidak Dibatasi --</option>
            @foreach ($areas ?? [] as $area)
              <option value="{{ $area->id }}">{{ $area->name }} ({{ $area->code }})</option>
            @endforeach
          </select>
          <p class="mt-1 text-xs text-gray-400">Otomatis diprioritaskan untuk kasir/waiter di area ini.</p>
        </div>

        <!-- Connection Type -->
        <div class="col-span-2">
          <label for="connection_type"
                 class="block text-sm font-medium text-gray-700 mb-1">Tipe Koneksi *</label>
          <select id="connection_type"
                  name="connection_type"
                  required
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option value="network">Network (TCP/IP)</option>
            <option value="file">File/USB</option>
            <option value="windows">Windows Shared</option>
            <option value="log">Log / Simulasi (Testing)</option>
          </select>
        </div>

        <!-- Network Fields -->
        <div id="networkFields"
             class="col-span-2 grid grid-cols-3 gap-4">
          <div>
            <label for="ip"
                   class="block text-sm font-medium text-gray-700 mb-1">IP Address *</label>
            <input type="text"
                   id="ip"
                   name="ip"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                   placeholder="192.168.1.100">
          </div>
          <div>
            <label for="port"
                   class="block text-sm font-medium text-gray-700 mb-1">Port</label>
            <input type="number"
                   id="port"
                   name="port"
                   value="9100"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          </div>
          <div>
            <label for="timeout"
                   class="block text-sm font-medium text-gray-700 mb-1">Timeout (s)</label>
            <input type="number"
                   id="timeout"
                   name="timeout"
                   value="30"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          </div>
        </div>

        <!-- Path Fields (for file/windows) -->
        <div id="pathFields"
             class="col-span-2 hidden">
          <label for="path"
                 class="block text-sm font-medium text-gray-700 mb-1">Path *</label>
          <input type="text"
                 id="path"
                 name="path"
                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                 placeholder="e.g., /dev/usb/lp0 or \\server\printer">
        </div>

        <!-- Receipt Settings -->
        <div class="col-span-2 border-t border-gray-200 pt-4 mt-2">
          <h4 class="text-sm font-semibold text-gray-700 mb-3">Pengaturan Struk</h4>
        </div>

        <!-- Logo -->
        <div class="col-span-2">
          <label for="logo"
                 class="block text-sm font-medium text-gray-700 mb-1">Logo</label>
          <div class="flex items-start space-x-4">
            <div class="flex-shrink-0">
              <div id="logoPreview"
                   class="w-24 h-16 bg-gray-100 rounded-lg flex items-center justify-center overflow-hidden border border-gray-200">
                <svg class="w-8 h-8 text-gray-300"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </div>
            </div>
            <div class="flex-1">
              <input type="file"
                     id="logo"
                     name="logo"
                     accept="image/*"
                     class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
              <p class="mt-1 text-xs text-gray-500">PNG, JPG, GIF. Max 2MB. Recommended: 200x100px.</p>
            </div>
          </div>
        </div>

        <div>
          <label for="header"
                 class="block text-sm font-medium text-gray-700 mb-1">Header</label>
          <input type="text"
                 id="header"
                 name="header"
                 value="126 Club"
                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
        </div>

        <div>
          <label for="footer"
                 class="block text-sm font-medium text-gray-700 mb-1">Footer</label>
          <input type="text"
                 id="footer"
                 name="footer"
                 value="Thank you for your visit!"
                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
        </div>

        <div>
          <label for="width"
                 class="block text-sm font-medium text-gray-700 mb-1">Lebar (karakter)</label>
          <input type="number"
                 id="width"
                 name="width"
                 value="42"
                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
        </div>

        <div class="flex items-center space-x-6">
          <label class="flex items-center">
            <input type="checkbox"
                   id="show_qr_code"
                   name="show_qr_code"
                   value="1"
                   checked
                   class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
            <span class="ml-2 text-sm text-gray-700">Tampilkan QR Code</span>
          </label>
        </div>

        <div class="flex items-center space-x-6">
          <label class="flex items-center">
            <input type="checkbox"
                   id="is_default"
                   name="is_default"
                   value="1"
                   class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
            <span class="ml-2 text-sm text-gray-700">Jadikan Default</span>
          </label>
          <label class="flex items-center">
            <input type="checkbox"
                   id="is_active"
                   name="is_active"
                   value="1"
                   checked
                   class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
            <span class="ml-2 text-sm text-gray-700">Aktif</span>
          </label>
        </div>
      </div>

      <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
        <button type="button"
                onclick="closeModal()"
                class="px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
          Batal
        </button>
        <button type="submit"
                id="submitButtonText"
                class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold transition">
          Tambah
        </button>
      </div>
    </form>
  </div>
</div>
