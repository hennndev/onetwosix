<div id="statusConfirmModal"
     class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-xl max-w-md w-full overflow-hidden transition-all">
    <div class="p-6">
      <div id="statusConfirmIconBg"
           class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4 bg-emerald-100">
        <svg id="statusConfirmIcon"
             class="w-7 h-7 text-emerald-600"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2.5"
                d="M5 13l4 4L19 7" />
        </svg>
      </div>
      <h3 id="statusConfirmTitle"
          class="text-lg font-bold text-gray-900 text-center mb-2">Konfirmasi Status</h3>
      <p id="statusConfirmMessage"
         class="text-sm text-gray-600 text-center mb-6">Apakah Anda yakin ingin mengubah status lagu ini?</p>
      <div class="flex items-center gap-3">
        <button type="button"
                onclick="closeStatusConfirmModal()"
                class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 font-semibold transition">
          Batal
        </button>
        <button type="button"
                id="confirmStatusBtn"
                onclick="submitStatusChange()"
                class="flex-1 px-4 py-2.5 bg-slate-800 text-white rounded-xl hover:bg-slate-900 font-semibold transition flex items-center justify-center gap-2">
          <span>Ya, Lanjutkan</span>
        </button>
      </div>
    </div>
  </div>
</div>
