<div id="deleteModal"
     class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-2xl max-w-md w-full">
    <div class="p-6">
      <div class="flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mx-auto mb-4">
        <svg class="w-8 h-8 text-red-600"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
      </div>
      <h3 class="text-xl font-bold text-gray-800 text-center mb-2">Hapus Tier?</h3>
      <p class="text-gray-600 text-center text-sm mb-6">
        Tier "<span id="deleteTierName"
              class="font-semibold"></span>" akan dihapus secara permanen.
        Customer di tier ini akan turun ke tier di bawahnya sesuai total spent.
        Tindakan ini tidak dapat dibatalkan.
      </p>

      <form id="deleteForm"
            method="POST">
        @csrf
        @method('DELETE')
        <div class="flex items-center space-x-3">
          <button type="button"
                  onclick="closeDeleteModal()"
                  class="flex-1 px-6 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm font-semibold transition">
            Batal
          </button>
          <button type="submit"
                  class="flex-1 px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-semibold transition">
            Hapus
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
