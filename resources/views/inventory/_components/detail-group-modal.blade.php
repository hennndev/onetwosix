<div id="detailGroupModal"
     class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
  <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[80vh] flex flex-col">
    <div class="flex justify-between items-center p-6 border-b border-gray-200">
      <div>
        <h2 class="text-lg font-semibold text-gray-900">Detail Group</h2>
        <p id="detailGroupItemName"
           class="text-sm text-gray-500 mt-0.5"></p>
      </div>
      <button onclick="closeDetailModal()"
              class="text-gray-400 hover:text-gray-600 transition">
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
    <div id="detailGroupContent"
         class="p-6 overflow-y-auto flex-1">
      <div id="detailGroupLoading"
           class="flex justify-center py-8">
        <svg class="animate-spin h-8 w-8 text-indigo-500"
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
                d="M4 12a8 8 0 018-8v8z"></path>
        </svg>
      </div>
      <div id="detailGroupEmpty"
           class="hidden text-center py-8 text-gray-500">Tidak ada data detailGroup.</div>
      <table id="detailGroupTable"
             class="hidden w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
          <tr>
            <th class="px-4 py-2 text-left">#</th>
            <th class="px-4 py-2 text-left">Nama</th>
            <th class="px-4 py-2 text-right">Qty</th>
            <th class="px-4 py-2 text-left">Satuan</th>
            <th class="px-4 py-2 text-right">Stok Saat Ini</th>
          </tr>
        </thead>
        <tbody id="detailGroupBody"
               class="divide-y divide-gray-100"></tbody>
      </table>
    </div>
  </div>
</div>
