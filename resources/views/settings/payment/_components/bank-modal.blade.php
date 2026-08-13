<div id="bankModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between p-6 border-b border-gray-200">
      <h3 class="text-xl font-bold text-gray-800" id="bankModalTitle">Tambah Rekening Bank</h3>
      <button type="button" data-close-bank-modal class="text-gray-400 hover:text-gray-600" aria-label="Tutup modal">✕</button>
    </div>
    <form id="bankForm" method="POST" action="{{ route('admin.settings.payment.bank-accounts.store') }}" class="p-6">
      @csrf
      <input type="hidden" name="_method" id="bankFormMethod" value="POST">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="md:col-span-2">
          <label for="bank_name" class="block text-sm font-semibold text-gray-700 mb-2">Nama Bank <span class="text-red-500">*</span></label>
          <input id="bank_name" type="text" name="bank_name" required placeholder="BCA, BNI, Mandiri, BRI"
                 class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
        </div>
        <div>
          <label for="account_number" class="block text-sm font-semibold text-gray-700 mb-2">Nomor Rekening <span class="text-red-500">*</span></label>
          <input id="account_number" type="text" name="account_number" required
                 class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
        </div>
        <div>
          <label for="account_holder" class="block text-sm font-semibold text-gray-700 mb-2">Atas Nama <span class="text-red-500">*</span></label>
          <input id="account_holder" type="text" name="account_holder" required
                 class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
        </div>
        <div class="flex items-center">
          <label class="inline-flex items-center gap-3 cursor-pointer">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" id="bank_is_active" value="1" checked class="w-5 h-5 rounded text-emerald-600 focus:ring-emerald-500">
            <span class="text-sm font-semibold text-gray-700">Active</span>
          </label>
        </div>
      </div>
      <div class="flex justify-end gap-3 mt-8 pt-6 border-t border-gray-200">
        <button type="button" data-close-bank-modal class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold">Batal</button>
        <button type="submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-semibold"><span id="bankSubmitText">Tambah</span></button>
      </div>
    </form>
  </div>
</div>
