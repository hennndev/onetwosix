<div id="deleteBankModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6">
    <div class="w-16 h-16 bg-red-100 text-red-600 rounded-full mx-auto mb-4 flex items-center justify-center text-2xl">!</div>
    <h3 class="text-xl font-bold text-gray-800 text-center mb-2">Hapus Rekening?</h3>
    <p class="text-gray-600 text-center mb-6">Rekening “<span id="deleteBankName" class="font-semibold"></span>” akan dihapus permanen.</p>
    <form id="deleteBankForm" method="POST">
      @csrf
      @method('DELETE')
      <div class="flex gap-3">
        <button type="button" data-close-delete-bank-modal class="flex-1 px-6 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold">Batal</button>
        <button type="submit" class="flex-1 px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold">Hapus</button>
      </div>
    </form>
  </div>
</div>
