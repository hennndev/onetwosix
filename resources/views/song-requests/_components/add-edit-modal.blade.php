<div id="songModal"
     class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
    <div class="p-6 border-b border-gray-200">
      <h3 id="modalTitle"
          class="text-xl font-bold text-gray-900">Request Baru</h3>
    </div>
    <form id="songForm"
          method="POST"
          action="{{ route('admin.song-requests.store') }}"
          class="p-6">
      @csrf
      <input type="hidden"
             name="_method"
             value="POST"
             id="formMethod">

      <div class="space-y-4">
        <!-- Live API Song Search -->
        <div class="bg-gradient-to-r from-pink-50 via-purple-50 to-indigo-50 p-4 rounded-xl border border-pink-200">
          <label class="block text-xs font-bold uppercase tracking-wider text-pink-700 mb-1 flex items-center gap-1.5">
            <svg class="w-4 h-4 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            Cari Lagu via Apple Music / iTunes API
          </label>
          <div class="relative">
            <input type="text"
                   id="apiSearchInput"
                   placeholder="Ketik judul lagu / nama artis..."
                   autocomplete="off"
                   class="w-full pl-9 pr-10 py-2 text-sm bg-white border border-pink-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent">
            <svg class="w-4 h-4 text-pink-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>
            </svg>
            <div id="apiSearchSpinner" class="hidden absolute right-3 top-1/2 -translate-y-1/2">
              <svg class="animate-spin h-4 w-4 text-pink-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
            </div>
            <!-- Search Results Dropdown -->
            <div id="apiSearchResults" class="hidden absolute z-30 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-2xl max-h-60 overflow-y-auto divide-y divide-gray-100">
            </div>
          </div>
          <p class="text-[11px] text-gray-500 mt-1">Pilih lagu dari hasil pencarian untuk mengisi otomatis Judul, Artis, Cover & Audio Preview.</p>
        </div>

        <input type="hidden" name="cover_image" id="cover_image">
        <input type="hidden" name="preview_url" id="preview_url">

        <!-- Selected Song Preview Badge -->
        <div id="selectedSongPreview" class="hidden items-center gap-3 p-3 bg-slate-900 text-white rounded-xl">
          <img id="selectedCoverImg" src="" alt="Album Cover" class="w-12 h-12 rounded-lg object-cover bg-slate-800 flex-shrink-0">
          <div class="flex-1 min-w-0">
            <p id="selectedSongTitleText" class="text-sm font-bold text-white truncate"></p>
            <p id="selectedArtistText" class="text-xs text-slate-300 truncate"></p>
            <div id="selectedAudioPreviewBtnContainer" class="hidden mt-1.5">
              <button type="button"
                      id="selectedPlayPreviewBtn"
                      onclick="playModalSelectedAudio()"
                      class="px-2.5 py-1 text-[11px] bg-pink-600 hover:bg-pink-700 text-white font-semibold rounded-md transition flex items-center gap-1">
                <svg class="w-3 h-3 play-icon" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M8 5v14l11-7z"/>
                </svg>
                <svg class="w-3 h-3 pause-icon hidden animate-pulse" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>
                </svg>
                <span class="btn-text">Putar Audio 30s</span>
              </button>
            </div>
          </div>
          <button type="button" onclick="clearSelectedSongApi()" class="p-1.5 text-slate-400 hover:text-red-400 transition bg-slate-800 hover:bg-slate-700 rounded-lg">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <!-- Customer -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Customer <span class="text-red-500">*</span></label>
          <select name="customer_user_id"
                  id="customer_user_id"
                  required
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent">
            <option value="">Pilih Customer</option>
            @foreach ($customerUsers as $customerUser)
              <option value="{{ $customerUser->id }}">{{ $customerUser->user->name }} - {{ $customerUser->user->email }}</option>
            @endforeach
          </select>
        </div>

        <!-- Song Title -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Judul Lagu <span class="text-red-500">*</span></label>
          <input type="text"
                 name="song_title"
                 id="song_title"
                 required
                 placeholder="Contoh: Shape of You"
                 class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent">
        </div>

        <!-- Artist -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Artist <span class="text-red-500">*</span></label>
          <input type="text"
                 name="artist"
                 id="artist"
                 required
                 placeholder="Contoh: Ed Sheeran"
                 class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent">
        </div>

        <!-- Tip -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Tip (Optional)</label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 font-medium">Rp</span>
            <input type="text"
                   id="tip_display"
                   oninput="formatSongTipRupiah(this)"
                   placeholder="0"
                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent">
            <input type="hidden" name="tip" id="tip">
          </div>
        </div>

        <!-- Status -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Status <span class="text-red-500">*</span></label>
          <select name="status"
                  id="status"
                  required
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent">
            <option value="pending">Pending</option>
            <option value="played">Played (Sedang Diputar)</option>
            <option value="completed">Completed (Selesai Diputar)</option>
            <option value="rejected">Rejected</option>
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
                class="px-4 py-2 bg-pink-600 text-white rounded-lg hover:bg-pink-700 transition">
          Simpan
        </button>
      </div>
    </form>
  </div>
</div>
