<div id="promoModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200">
            <h3 id="modalTitle" class="text-xl font-bold text-gray-900">Tambah Promo</h3>
        </div>
        <form id="promoForm" method="POST" action="{{ route('admin.promos.store') }}" enctype="multipart/form-data"
            class="p-6">
            @csrf
            <input type="hidden" name="_method" value="POST" id="formMethod">

            <div class="space-y-4">
                <!-- Promo Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nama Promo <span
                            class="text-red-500">*</span></label>
                    <input type="text" name="name" id="name" required placeholder="Contoh: Diskon Weekend 20%"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent">
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Deskripsi</label>
                    <textarea name="description" id="description" rows="3" placeholder="Deskripsi promo..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent"></textarea>
                </div>

                <!-- Image -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Gambar Promo</label>
                    <input type="file" name="image" id="image" accept="image/*"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent">
                    <p class="text-xs text-gray-500 mt-1">Format: JPG, PNG, GIF. Maksimal 2MB</p>
                </div>

                <!-- Date Range -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Mulai <span
                                class="text-red-500">*</span></label>
                        <input type="date" name="start_date" id="start_date" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Berakhir <span
                                class="text-red-500">*</span></label>
                        <input type="date" name="end_date" id="end_date" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent">
                    </div>
                </div>

                <!-- Time Range (Optional) -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Jam Mulai (Optional)</label>
                        <input type="time" name="start_time" id="start_time"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Jam Berakhir (Optional)</label>
                        <input type="time" name="end_time" id="end_time"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent">
                    </div>
                </div>

                <!-- Discount Type -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tipe Diskon <span
                            class="text-red-500">*</span></label>
                    <select name="discount_type" id="discount_type" required onchange="updateDiscountLabel()"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent">
                        <option value="percentage">Percentage (%)</option>
                        <option value="fixed">Fixed Amount (Rp)</option>
                    </select>
                </div>

                <!-- Discount Value -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <span id="discountLabel">Nilai Diskon (%)</span>
                        <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="discount_value" id="discount_value" inputmode="decimal" required
                        placeholder="0"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent">
                    <p id="discountHelp" class="text-xs text-gray-500 mt-1">Potongan persentase dari harga normal</p>
                </div>

                <!-- Terms & Conditions -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Syarat & Ketentuan</label>
                    <textarea name="terms_conditions" id="terms_conditions" rows="3"
                        placeholder="Syarat dan ketentuan berlaku..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent"></textarea>
                </div>

                <!-- Active Status -->
                <div class="flex items-center">
                    <input type="checkbox" name="is_active" id="is_active"
                        class="w-4 h-4 text-slate-600 focus:ring-slate-500 border-gray-300 rounded">
                    <label for="is_active" class="ml-2 text-sm font-medium text-gray-700">Aktifkan promo
                        sekarang</label>
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="closeModal()"
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
