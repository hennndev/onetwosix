<div id="messageModal"
     class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
    <div class="p-6 border-b border-gray-200">
      <h3 id="modalTitle"
          class="text-xl font-bold text-gray-900">Message Baru</h3>
    </div>
    <form id="messageForm"
          method="POST"
          action="{{ route('admin.display-messages.store') }}"
          class="p-6">
      @csrf
      <input type="hidden"
             name="_method"
             value="POST"
             id="formMethod">

      <div class="space-y-4">
        <!-- Customer -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Customer <span class="text-red-500">*</span></label>
          <select name="customer_id"
                  id="customer_id"
                  required
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent">
            <option value="">Pilih Customer</option>
            @foreach ($customers as $customer)
              <option value="{{ $customer->id }}">{{ $customer->name }} - {{ $customer->email }}</option>
            @endforeach
          </select>
        </div>

        <!-- Message -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Message <span class="text-red-500">*</span></label>
          <textarea name="message"
                    id="message"
                    required
                    rows="4"
                    maxlength="500"
                    placeholder="Tulis pesan untuk ditampilkan di layar LED..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent"
                    oninput="updateCharCount()"></textarea>
          <div class="flex justify-between mt-1">
            <span class="text-xs text-gray-500">Maksimal 500 karakter</span>
            <span id="charCount"
                  class="text-xs text-gray-500">0/500</span>
          </div>
        </div>

        <!-- Tip -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Tip (Optional)</label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 font-medium">Rp</span>
            <input type="text"
                   id="tip_display"
                   placeholder="0"
                   oninput="formatTipRupiah(this)"
                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent font-medium">
            <input type="hidden"
                   name="tip"
                   id="tip">
          </div>
        </div>

        <!-- Status -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Status <span class="text-red-500">*</span></label>
          <select name="status"
                  id="status"
                  required
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent">
            <option value="pending">Pending</option>
            <option value="displayed">Displayed</option>
            <option value="completed">Completed (Selesai Tampil)</option>
            <option value="rejected">Rejected</option>
            <option value="cancelled">Cancelled</option>
          </select>
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
