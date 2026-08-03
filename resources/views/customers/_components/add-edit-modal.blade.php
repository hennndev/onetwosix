<div id="customerModal"
     class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
    <div class="p-6 border-b border-gray-200">
      <h3 id="modalTitle"
          class="text-xl font-bold text-gray-900">Tambah Customer</h3>
    </div>
    <form id="customerForm"
          method="POST"
          action="{{ route('admin.customers.store') }}"
          class="p-6">
      @csrf
      <input type="hidden"
             name="_method"
             value="POST"
             id="formMethod">

      <div class="grid grid-cols-2 gap-4">
        <!-- Nama -->
        <div class="col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-2">Nama Lengkap <span class="text-red-500">*</span></label>
          <input type="text"
                 name="name"
                 id="name"
                 required
                 class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent"
                 placeholder="Contoh: Alexander Chen">
        </div>

        <!-- Email -->
        <div class="col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-2">Email <span class="text-red-500">*</span></label>
          <input type="email"
                 name="email"
                 id="email"
                 required
                 class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent"
                 placeholder="Contoh: alexander@email.com">
        </div>

        <!-- Password -->
        <div class="col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-2">Password <span class="text-red-500"
                  id="passwordRequired">*</span></label>
          <input type="password"
                 name="password"
                 id="password"
                 class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent"
                 placeholder="Minimal 8 karakter">
          <p class="text-xs text-gray-500 mt-1"
             id="passwordHint">Kosongkan jika tidak ingin mengubah password</p>
        </div>

        <!-- Phone -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Nomor Telepon</label>
          <input type="text"
                 name="phone"
                 id="phone"
                 class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent"
                 placeholder="08123456789">
        </div>

        <!-- Birth Date -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Lahir</label>
          <input type="date"
                 name="birth_date"
                 id="birth_date"
                 class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent">
        </div>

        <!-- Address -->
        <div class="col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-2">Alamat</label>
          <textarea name="address"
                    id="address"
                    rows="2"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent"
                    placeholder="Alamat lengkap"></textarea>
        </div>

        <!-- Customer Data (Only for Edit) -->
        <div id="customerDataFields"
             class="col-span-2 hidden">
          <div class="grid grid-cols-2 gap-4 p-4 bg-gray-50 rounded-lg">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Total Kunjungan</label>
              <input type="number"
                     name="total_visits"
                     id="total_visits"
                     min="0"
                     class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Total Spending (Rp)</label>
              <input type="number"
                     name="lifetime_spending"
                     id="lifetime_spending"
                     min="0"
                     step="1000"
                     class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent">
            </div>
          </div>
        </div>
      </div>

      <div class="flex justify-end gap-3 mt-6">
        <button type="button"
                id="cancelCustomerBtn"
                onclick="closeModal()"
                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
          Batal
        </button>
        <button type="submit"
                id="submitCustomerBtn"
                class="px-4 py-2 bg-slate-800 text-white rounded-lg hover:bg-slate-900 transition flex items-center justify-center gap-2 font-medium">
          <svg id="customerSubmitSpinner" class="hidden w-4 h-4 text-white animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span id="customerSubmitText">Simpan</span>
        </button>
      </div>
    </form>
  </div>
</div>
