<x-app-layout title="Request Lagu">
  <div class="p-6">
    @if (session('success'))
      <div class="mb-4 px-4 py-3 bg-green-100 border border-green-400 text-green-700 rounded-lg">
        {{ session('success') }}
      </div>
    @endif

    @if ($errors->any())
      <div class="mb-4 px-4 py-3 bg-red-100 border border-red-400 text-red-700 rounded-lg">
        <ul class="list-disc list-inside">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-3">
        <div class="w-12 h-12 bg-slate-800 rounded-xl flex items-center justify-center">
          <svg class="w-6 h-6 text-white"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
          </svg>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Song Request</h1>
          <p class="text-sm text-gray-500">Kelola permintaan lagu dari customer untuk DJ</p>
        </div>
      </div>
      <button onclick="openModal('add')"
              class="px-4 py-2 bg-pink-600 text-white rounded-lg hover:bg-pink-700 transition flex items-center gap-2">
        <svg class="w-5 h-5"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 4v16m8-8H4" />
        </svg>
        Request Baru
      </button>
    </div>

    <!-- Live Active Song Banner -->
    @php
      $activePlayedSong = $songRequests->firstWhere('status', 'played');
    @endphp
    <div class="mb-6 bg-slate-950 rounded-2xl p-5 border border-slate-800 shadow-2xl relative overflow-hidden text-white">
      <!-- Glow Background -->
      <div class="absolute -top-24 -left-24 w-64 h-64 bg-pink-600/20 rounded-full blur-3xl pointer-events-none"></div>
      <div class="absolute -bottom-24 -right-24 w-64 h-64 bg-purple-500/15 rounded-full blur-3xl pointer-events-none"></div>

      <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4 pb-3 border-b border-slate-800/80 mb-4">
        <div class="flex items-center gap-3">
          @if ($activePlayedSong)
            <div class="flex items-center gap-2 px-3 py-1 bg-green-500/20 border border-green-500/40 rounded-full">
              <span class="relative flex h-2.5 w-2.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-green-500"></span>
              </span>
              <span class="text-xs font-bold uppercase tracking-wider text-green-400">NOW PLAYING ON DJ BOOTH</span>
            </div>
            <span class="text-xs text-slate-400 font-mono">ID: SONG-{{ str_pad($activePlayedSong->id, 4, '0', STR_PAD_LEFT) }}</span>
          @else
            <div class="flex items-center gap-2 px-3 py-1 bg-slate-800 border border-slate-700 rounded-full">
              <span class="h-2.5 w-2.5 rounded-full bg-slate-500"></span>
              <span class="text-xs font-bold uppercase tracking-wider text-slate-400">DJ BOOTH IDLE</span>
            </div>
            <span class="text-xs text-slate-400">Tidak ada lagu yang sedang aktif diputar</span>
          @endif
        </div>

        @if ($activePlayedSong)
          <div class="flex items-center gap-3">
            <div class="text-xs text-right">
              <span class="text-slate-400">Request Dari:</span>
              <span class="font-semibold text-white ml-1">{{ $activePlayedSong->customerUser->user->name }}</span>
              @if ($activePlayedSong->tip)
                <span class="ml-2 px-2 py-0.5 bg-yellow-400/20 border border-yellow-400/40 text-yellow-300 font-bold rounded">💰 Tip: Rp {{ number_format($activePlayedSong->tip, 0, ',', '.') }}</span>
              @endif
            </div>
            <button onclick="updateStatus({{ $activePlayedSong->id }}, 'completed')"
                    class="px-3 py-1 text-xs bg-blue-500/20 border border-blue-500/40 text-blue-300 rounded-lg hover:bg-blue-500/30 transition flex items-center gap-1 font-semibold">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
              </svg>
              Selesaikan Lagu
            </button>
          </div>
        @endif
      </div>

      <!-- Active Song Display Card -->
      <div class="relative w-full bg-slate-900/95 border border-slate-800 rounded-xl p-4 overflow-hidden shadow-inner">
        @if ($activePlayedSong)
          <div class="flex items-center gap-4">
            @if ($activePlayedSong->cover_image)
              <img src="{{ $activePlayedSong->cover_image }}" alt="Cover" class="w-16 h-16 rounded-xl object-cover border border-slate-700 shadow-lg flex-shrink-0">
            @else
              <div class="w-16 h-16 bg-pink-600 rounded-xl flex items-center justify-center text-white flex-shrink-0 shadow-lg">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>
                </svg>
              </div>
            @endif
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <span class="px-2 py-0.5 bg-pink-500/20 text-pink-300 text-[10px] font-bold uppercase rounded tracking-wider border border-pink-500/30">CURRENT TRACK</span>
              </div>
              <h2 class="text-xl font-extrabold text-white truncate mt-1">{{ $activePlayedSong->song_title }}</h2>
              <p class="text-sm font-medium text-pink-300 truncate">🎤 {{ $activePlayedSong->artist }}</p>
            </div>
            @if ($activePlayedSong->preview_url)
              <button type="button"
                      onclick="toggleAudioPreview(this, '{{ $activePlayedSong->preview_url }}')"
                      class="px-3.5 py-2 bg-pink-600 hover:bg-pink-700 text-white rounded-xl transition flex items-center gap-2 font-semibold text-xs shadow-lg flex-shrink-0">
                <svg class="w-4 h-4 play-icon" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                <svg class="w-4 h-4 pause-icon hidden animate-pulse" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                <span class="btn-text">Dengar Preview</span>
              </button>
            @endif
          </div>
        @else
          <div class="text-center font-mono text-xs sm:text-sm tracking-wider text-slate-500 py-2 uppercase">
            --- DJ BOOTH SIAP - HANYA 1 LAGU BERSTATUS "PLAYED" DAPAT DIPUTAR ---
          </div>
        @endif
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-3 gap-4 mb-6">
      <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-yellow-500 rounded-lg flex items-center justify-center">
            <svg class="w-5 h-5 text-white"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <p class="text-sm text-yellow-700 font-medium">Pending</p>
            <p class="text-2xl font-bold text-yellow-900">{{ $pendingRequests }}</p>
          </div>
        </div>
      </div>

      <div class="bg-green-50 border border-green-200 rounded-xl p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center">
            <svg class="w-5 h-5 text-white"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <p class="text-sm text-green-700 font-medium">Played</p>
            <p class="text-2xl font-bold text-green-900">{{ $playedRequests }}</p>
          </div>
        </div>
      </div>

      <div class="bg-pink-50 border border-pink-200 rounded-xl p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-pink-500 rounded-lg flex items-center justify-center">
            <svg class="w-5 h-5 text-white"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
            </svg>
          </div>
          <div>
            <p class="text-sm text-pink-700 font-medium">Total Requests</p>
            <p class="text-2xl font-bold text-pink-900">{{ $totalRequests }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
      <div class="p-4 flex flex-wrap gap-4">
        <div class="flex-1 min-w-[300px]">
          <div class="relative">
            <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input type="text"
                   id="searchInput"
                   placeholder="Cari song request (nama, lagu, artist, ID)..."
                   class="pl-10 pr-4 py-2 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent">
          </div>
        </div>
        <select id="statusFilter"
                class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent">
          <option value="">Semua Status</option>
          <option value="pending">Pending</option>
          <option value="played">Played (Sedang Diputar)</option>
          <option value="completed">Completed (Selesai Diputar)</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>
    </div>

    <!-- Table Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Song Details</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200"
                 id="songTableBody">
            @foreach ($songRequests as $request)
              <tr class="hover:bg-gray-50 transition song-row"
                  data-status="{{ $request->status }}">
                <td class="px-6 py-4 whitespace-nowrap">
                  @if ($request->status === 'pending')
                    <span class="px-3 py-1 text-xs font-medium rounded bg-yellow-100 text-yellow-700 flex items-center gap-1 w-fit">
                      <svg class="w-3 h-3"
                           fill="currentColor"
                           viewBox="0 0 20 20">
                        <circle cx="10"
                                cy="10"
                                r="3" />
                      </svg>
                      Pending
                    </span>
                  @elseif($request->status === 'played')
                    <span class="px-3 py-1 text-xs font-medium rounded bg-green-100 text-green-700 flex items-center gap-1 w-fit">
                      <svg class="w-3 h-3"
                           fill="currentColor"
                           viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd" />
                      </svg>
                      Played
                    </span>
                  @elseif($request->status === 'completed')
                    <span class="px-3 py-1 text-xs font-medium rounded bg-blue-100 text-blue-700 flex items-center gap-1 w-fit">
                      <svg class="w-3 h-3"
                           fill="currentColor"
                           viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd" />
                      </svg>
                      Completed
                    </span>
                  @else
                    <span class="px-3 py-1 text-xs font-medium rounded bg-red-100 text-red-700 flex items-center gap-1 w-fit">
                      <svg class="w-3 h-3"
                           fill="currentColor"
                           viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                              clip-rule="evenodd" />
                      </svg>
                      Rejected
                    </span>
                  @endif
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-medium text-gray-900">SONG-{{ str_pad($request->id, 4, '0', STR_PAD_LEFT) }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-pink-500 rounded-full flex items-center justify-center text-white font-semibold">
                      {{ strtoupper(substr($request->customerUser->user->name, 0, 1)) }}
                    </div>
                    <div>
                      <div class="text-sm font-medium text-gray-900">{{ $request->customerUser->user->name }}</div>
                      <div class="text-xs text-gray-500">{{ $request->customerUser->user->profile->phone ?? '-' }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <div class="max-w-md">
                    <div class="flex items-start gap-3">
                      @if ($request->cover_image)
                        <img src="{{ $request->cover_image }}" alt="Cover" class="w-12 h-12 rounded-lg object-cover flex-shrink-0 border border-gray-200 shadow-sm">
                      @else
                        <div class="w-12 h-12 bg-pink-500 rounded-lg flex items-center justify-center flex-shrink-0 text-white shadow-sm">
                          <svg class="w-6 h-6"
                               fill="none"
                               stroke="currentColor"
                               viewBox="0 0 24 24">
                            <path stroke-linecap="round"
                                  stroke-linejoin="round"
                                  stroke-width="2"
                                  d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                          </svg>
                        </div>
                      @endif
                      <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-gray-900 truncate">{{ $request->song_title }}</p>
                        <p class="text-xs text-gray-500 mt-0.5 font-medium">🎤 {{ $request->artist }}</p>
                        @if ($request->preview_url)
                          <button type="button"
                                  onclick="toggleAudioPreview(this, '{{ $request->preview_url }}')"
                                  class="mt-1.5 px-2.5 py-1 text-xs bg-slate-100 hover:bg-pink-50 text-slate-700 hover:text-pink-600 rounded-lg border border-slate-200 hover:border-pink-300 transition flex items-center gap-1.5 font-medium">
                            <svg class="w-3.5 h-3.5 play-icon text-pink-600" fill="currentColor" viewBox="0 0 24 24">
                              <path d="M8 5v14l11-7z"/>
                            </svg>
                            <svg class="w-3.5 h-3.5 pause-icon hidden text-pink-600 animate-pulse" fill="currentColor" viewBox="0 0 24 24">
                              <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>
                            </svg>
                            <span class="btn-text">Dengar Preview 30s</span>
                          </button>
                        @endif
                        @if ($request->tip)
                          <span class="inline-block mt-1.5 px-2 py-0.5 text-xs font-semibold rounded bg-yellow-400 text-gray-900">
                            💰 Tips: Rp {{ number_format($request->tip, 0, ',', '.') }}
                          </span>
                        @endif
                      </div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">
                    {{ $request->created_at->format('d M Y') }}
                  </div>
                  <div class="text-xs text-gray-500">{{ $request->created_at->format('H:i') }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex flex-wrap gap-2">
                    @if ($request->status === 'pending')
                      <button onclick="updateStatus({{ $request->id }}, 'played')"
                              class="px-3 py-1 text-xs bg-green-500 text-white rounded hover:bg-green-600 transition flex items-center gap-1 font-semibold">
                        <svg class="w-3 h-3"
                             fill="none"
                             stroke="currentColor"
                             viewBox="0 0 24 24">
                          <path stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Played
                      </button>
                      <button onclick="updateStatus({{ $request->id }}, 'rejected')"
                              class="px-3 py-1 text-xs bg-red-500 text-white rounded hover:bg-red-600 transition flex items-center gap-1 font-semibold">
                        <svg class="w-3 h-3"
                             fill="none"
                             stroke="currentColor"
                             viewBox="0 0 24 24">
                          <path stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Tolak
                      </button>
                    @endif
                    @if ($request->status === 'played')
                      <button onclick="updateStatus({{ $request->id }}, 'completed')"
                              class="px-3 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700 transition flex items-center gap-1 font-semibold">
                        <svg class="w-3 h-3"
                             fill="none"
                             stroke="currentColor"
                             viewBox="0 0 24 24">
                          <path stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M5 13l4 4L19 7" />
                        </svg>
                        Selesai
                      </button>
                    @endif
                    <button onclick="editSongRequest({{ $request->id }})"
                            class="px-3 py-1 text-xs border border-gray-300 text-gray-700 rounded hover:bg-gray-50 transition">
                      Edit
                    </button>
                    <button onclick="deleteSongRequest({{ $request->id }})"
                            class="px-3 py-1 text-xs text-red-600 rounded hover:bg-red-50 transition">
                      Hapus
                    </button>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Add/Edit Modal -->
  @include('song-requests._components.add-edit-modal')

  <!-- Delete Modal -->
  @include('song-requests._components.delete-modal-confirmation')

  <!-- Status Confirm Modal -->
  @include('song-requests._components.status-confirmation-modal')

  <!-- Active Song Warning Modal -->
  <div id="activeSongWarningModal"
       class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full overflow-hidden transition-all border border-amber-200">
      <div class="p-6 text-center">
        <div class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4 bg-amber-100 border border-amber-200 text-amber-600">
          <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
          </svg>
        </div>
        <h3 class="text-lg font-bold text-gray-900 mb-2">Lagu Aktif Sedang Diputar</h3>
        <p id="activeSongWarningText" class="text-sm text-gray-600 mb-6">
          Saat ini terdapat lagu yang sedang aktif diputar di DJ Booth. Silakan selesaikan lagu aktif tersebut terlebih dahulu sebelum memutar lagu baru.
        </p>
        <button type="button"
                onclick="closeActiveSongWarningModal()"
                class="w-full px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-xl transition">
          Saya Mengerti
        </button>
      </div>
    </div>
  </div>

  @push('scripts')
    <script>
      const songRequests = @json($songRequests);

      function formatSongTipRupiah(input) {
        let value = input.value.replace(/\D/g, '');
        if (value === '') {
          input.value = '';
          document.getElementById('tip').value = '';
          return;
        }

        const numericValue = parseInt(value, 10);
        input.value = new Intl.NumberFormat('id-ID').format(numericValue);
        document.getElementById('tip').value = numericValue;
      }

      function openModal(mode, requestId = null) {
        const modal = document.getElementById('songModal');
        const form = document.getElementById('songForm');
        const modalTitle = document.getElementById('modalTitle');
        const formMethod = document.getElementById('formMethod');

        if (mode === 'add') {
          modalTitle.textContent = 'Request Baru';
          form.action = '{{ route('admin.song-requests.store') }}';
          formMethod.value = 'POST';
          form.reset();
          document.getElementById('tip').value = '';
          document.getElementById('tip_display').value = '';
          clearSelectedSongApi();
          document.getElementById('status').value = 'pending';
        } else if (mode === 'edit' && requestId) {
          const request = songRequests.find(r => r.id === requestId);
          if (request) {
            modalTitle.textContent = 'Edit Song Request';
            form.action = `/admin/song-requests/${requestId}`;
            formMethod.value = 'PUT';

            document.getElementById('customer_user_id').value = request.customer_user_id;
            document.getElementById('song_title').value = request.song_title;
            document.getElementById('artist').value = request.artist;
            document.getElementById('cover_image').value = request.cover_image || '';
            document.getElementById('preview_url').value = request.preview_url || '';

            if (request.tip) {
              const tipInt = parseInt(request.tip, 10);
              document.getElementById('tip').value = tipInt;
              document.getElementById('tip_display').value = new Intl.NumberFormat('id-ID').format(tipInt);
            } else {
              document.getElementById('tip').value = '';
              document.getElementById('tip_display').value = '';
            }

            document.getElementById('status').value = request.status;

            if (request.cover_image) {
              showSelectedPreviewBadge(request.cover_image, request.song_title, request.artist, request.preview_url);
            } else {
              clearSelectedSongApi();
            }
          }
        }

        modal.classList.remove('hidden');
      }

      function closeModal() {
        document.getElementById('songModal').classList.add('hidden');
        if (apiSearchResults) apiSearchResults.classList.add('hidden');
        stopCurrentAudio();
      }

      function editSongRequest(requestId) {
        openModal('edit', requestId);
      }

      function deleteSongRequest(requestId) {
        const form = document.getElementById('deleteForm');
        form.action = `/admin/song-requests/${requestId}`;
        document.getElementById('deleteModal').classList.remove('hidden');
      }

      function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
      }

      const activePlayedId = @json($activePlayedSong ? $activePlayedSong->id : null);
      let pendingStatusUpdate = { requestId: null, status: null };

      function showActiveSongWarningModal(activeId) {
        const activeSong = songRequests.find(s => s.id == activeId);
        const songName = activeSong ? ` (${activeSong.song_title} - ${activeSong.artist})` : '';
        const formattedId = 'SONG-' + String(activeId).padStart(4, '0');
        document.getElementById('activeSongWarningText').textContent =
          `Saat ini lagu ${formattedId}${songName} sedang aktif diputar di DJ Booth. Silakan selesaikan lagu aktif tersebut terlebih dahulu sebelum memutar lagu baru.`;
        document.getElementById('activeSongWarningModal').classList.remove('hidden');
      }

      function closeActiveSongWarningModal() {
        document.getElementById('activeSongWarningModal').classList.add('hidden');
      }

      function updateStatus(requestId, status) {
        if (status === 'played' && activePlayedId && activePlayedId != requestId) {
          showActiveSongWarningModal(activePlayedId);
          return;
        }

        pendingStatusUpdate = { requestId, status };

        const modal = document.getElementById('statusConfirmModal');
        const iconBg = document.getElementById('statusConfirmIconBg');
        const icon = document.getElementById('statusConfirmIcon');
        const title = document.getElementById('statusConfirmTitle');
        const msg = document.getElementById('statusConfirmMessage');
        const confirmBtn = document.getElementById('confirmStatusBtn');

        if (status === 'played') {
          iconBg.className = 'w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4 bg-emerald-100';
          icon.className = 'w-7 h-7 text-emerald-600';
          icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>`;
          title.textContent = 'Putar Lagu';
          msg.textContent = 'Apakah Anda yakin ingin memutar lagu ini di DJ Booth?';
          confirmBtn.className = 'flex-1 px-4 py-2.5 bg-emerald-600 text-white rounded-xl hover:bg-emerald-700 font-semibold transition flex items-center justify-center gap-2';
        } else if (status === 'completed') {
          iconBg.className = 'w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4 bg-blue-100';
          icon.className = 'w-7 h-7 text-blue-600';
          icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>`;
          title.textContent = 'Selesaikan Lagu';
          msg.textContent = 'Apakah Anda yakin penayangan lagu ini telah selesai?';
          confirmBtn.className = 'flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-xl hover:bg-blue-700 font-semibold transition flex items-center justify-center gap-2';
        } else if (status === 'rejected') {
          iconBg.className = 'w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4 bg-red-100';
          icon.className = 'w-7 h-7 text-red-600';
          icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>`;
          title.textContent = 'Tolak Request Lagu';
          msg.textContent = 'Apakah Anda yakin ingin menolak song request ini?';
          confirmBtn.className = 'flex-1 px-4 py-2.5 bg-red-600 text-white rounded-xl hover:bg-red-700 font-semibold transition flex items-center justify-center gap-2';
        } else {
          iconBg.className = 'w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4 bg-slate-100';
          icon.className = 'w-7 h-7 text-slate-600';
          icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>`;
          title.textContent = 'Konfirmasi Status';
          msg.textContent = 'Apakah Anda yakin ingin mengubah status song request ini?';
          confirmBtn.className = 'flex-1 px-4 py-2.5 bg-slate-800 text-white rounded-xl hover:bg-slate-900 font-semibold transition flex items-center justify-center gap-2';
        }

        modal.classList.remove('hidden');
      }

      function closeStatusConfirmModal() {
        document.getElementById('statusConfirmModal').classList.add('hidden');
        pendingStatusUpdate = { requestId: null, status: null };
      }

      function submitStatusChange() {
        if (!pendingStatusUpdate.requestId || !pendingStatusUpdate.status) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/admin/song-requests/${pendingStatusUpdate.requestId}/status`;

        const csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = '_token';
        csrfToken.value = '{{ csrf_token() }}';

        const methodField = document.createElement('input');
        methodField.type = 'hidden';
        methodField.name = '_method';
        methodField.value = 'PATCH';

        const statusField = document.createElement('input');
        statusField.type = 'hidden';
        statusField.name = 'status';
        statusField.value = pendingStatusUpdate.status;

        form.appendChild(csrfToken);
        form.appendChild(methodField);
        form.appendChild(statusField);

        document.body.appendChild(form);
        form.submit();
      }

      // Add/Edit Form submission validation
      document.getElementById('songForm').addEventListener('submit', function(e) {
        const statusVal = document.getElementById('status').value;
        const currentFormMethod = document.getElementById('formMethod').value;
        const formAction = this.action;
        let editingId = null;

        if (currentFormMethod === 'PUT') {
          const parts = formAction.split('/');
          editingId = parseInt(parts[parts.length - 1], 10);
        }

        if (statusVal === 'played' && activePlayedId && activePlayedId != editingId) {
          e.preventDefault();
          closeModal();
          showActiveSongWarningModal(activePlayedId);
        }
      });

      // iTunes Live Search API
      let apiDebounceTimeout = null;
      const apiSearchInput = document.getElementById('apiSearchInput');
      const apiSearchSpinner = document.getElementById('apiSearchSpinner');
      const apiSearchResults = document.getElementById('apiSearchResults');
      let modalSelectedPreviewUrl = null;

      if (apiSearchInput) {
        apiSearchInput.addEventListener('input', function() {
          clearTimeout(apiDebounceTimeout);
          const query = this.value.trim();

          if (query.length < 2) {
            apiSearchResults.classList.add('hidden');
            apiSearchResults.innerHTML = '';
            return;
          }

          apiSearchSpinner.classList.remove('hidden');

          apiDebounceTimeout = setTimeout(() => {
            fetch(`/admin/song-requests/search-api?q=${encodeURIComponent(query)}`)
              .then(res => res.json())
              .then(results => {
                apiSearchSpinner.classList.add('hidden');
                if (results.length === 0) {
                  apiSearchResults.innerHTML = `<div class="p-3 text-xs text-gray-500 text-center">Lagu tidak ditemukan</div>`;
                  apiSearchResults.classList.remove('hidden');
                  return;
                }

                apiSearchResults.innerHTML = results.map((item, idx) => `
                  <div onclick="selectApiSong(${idx})" class="p-2.5 hover:bg-pink-50 cursor-pointer flex items-center justify-between gap-3 transition border-b border-gray-100 last:border-0">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                      <img src="${item.cover_image || 'https://ui-avatars.com/api/?name=Song'}" class="w-10 h-10 rounded-lg object-cover bg-slate-100 flex-shrink-0" onerror="this.src='https://ui-avatars.com/api/?name=Song'">
                      <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold text-gray-900 truncate">${escapeHtml(item.song_title)}</p>
                        <p class="text-[11px] text-gray-500 truncate">🎤 ${escapeHtml(item.artist)} ${item.album ? '• ' + escapeHtml(item.album) : ''}</p>
                      </div>
                    </div>
                    ${item.preview_url ? `
                      <button type="button"
                              onclick="event.stopPropagation(); toggleDropdownAudio(this, '${escapeHtml(item.preview_url)}')"
                              title="Dengar preview"
                              class="p-2 bg-pink-100 hover:bg-pink-200 text-pink-600 rounded-full flex-shrink-0 transition">
                        <svg class="w-3.5 h-3.5 play-icon" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                        <svg class="w-3.5 h-3.5 pause-icon hidden animate-pulse" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                      </button>
                    ` : ''}
                  </div>
                `).join('');

                window.lastApiSearchResults = results;
                apiSearchResults.classList.remove('hidden');
              })
              .catch(() => {
                apiSearchSpinner.classList.add('hidden');
              });
          }, 350);
        });
      }

      function selectApiSong(idx) {
        if (!window.lastApiSearchResults || !window.lastApiSearchResults[idx]) return;
        const song = window.lastApiSearchResults[idx];

        document.getElementById('song_title').value = song.song_title;
        document.getElementById('artist').value = song.artist;
        document.getElementById('cover_image').value = song.cover_image || '';
        document.getElementById('preview_url').value = song.preview_url || '';

        showSelectedPreviewBadge(song.cover_image, song.song_title, song.artist, song.preview_url);
        apiSearchResults.classList.add('hidden');
        if (apiSearchInput) apiSearchInput.value = '';
      }

      function showSelectedPreviewBadge(cover, title, artist, previewUrl = null) {
        const badge = document.getElementById('selectedSongPreview');
        if (cover) {
          document.getElementById('selectedCoverImg').src = cover;
        }
        document.getElementById('selectedSongTitleText').textContent = title;
        document.getElementById('selectedArtistText').textContent = artist;

        const btnContainer = document.getElementById('selectedAudioPreviewBtnContainer');
        if (previewUrl) {
          modalSelectedPreviewUrl = previewUrl;
          btnContainer.classList.remove('hidden');
        } else {
          modalSelectedPreviewUrl = null;
          btnContainer.classList.add('hidden');
        }

        badge.classList.remove('hidden');
        badge.classList.add('flex');
      }

      function playModalSelectedAudio() {
        if (!modalSelectedPreviewUrl) return;
        const btn = document.getElementById('selectedPlayPreviewBtn');
        toggleAudioPreview(btn, modalSelectedPreviewUrl);
      }

      function toggleDropdownAudio(btn, url) {
        toggleAudioPreview(btn, url);
      }

      function clearSelectedSongApi() {
        document.getElementById('cover_image').value = '';
        document.getElementById('preview_url').value = '';
        modalSelectedPreviewUrl = null;
        stopCurrentAudio();
        const badge = document.getElementById('selectedSongPreview');
        if (badge) {
          badge.classList.add('hidden');
          badge.classList.remove('flex');
        }
      }

      function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, function(m) {
          return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        });
      }

      // Audio preview toggler
      let currentAudio = null;
      let currentAudioBtn = null;

      function stopCurrentAudio() {
        if (currentAudio) {
          currentAudio.pause();
          if (currentAudioBtn) setAudioBtnState(currentAudioBtn, false);
          currentAudio = null;
          currentAudioBtn = null;
        }
      }

      function toggleAudioPreview(btn, url) {
        if (currentAudio && currentAudioBtn === btn) {
          if (currentAudio.paused) {
            currentAudio.play();
            setAudioBtnState(btn, true);
          } else {
            currentAudio.pause();
            setAudioBtnState(btn, false);
          }
          return;
        }

        stopCurrentAudio();

        currentAudio = new Audio(url);
        currentAudioBtn = btn;

        setAudioBtnState(btn, true);
        currentAudio.play().catch(() => setAudioBtnState(btn, false));

        currentAudio.onended = function() {
          setAudioBtnState(btn, false);
        };
      }

      function setAudioBtnState(btn, isPlaying) {
        const playIcon = btn.querySelector('.play-icon');
        const pauseIcon = btn.querySelector('.pause-icon');
        const text = btn.querySelector('.btn-text');

        if (isPlaying) {
          if (playIcon) playIcon.classList.add('hidden');
          if (pauseIcon) pauseIcon.classList.remove('hidden');
          if (text) text.textContent = 'Memutar...';
          btn.classList.add('bg-pink-100', 'border-pink-400');
        } else {
          if (playIcon) playIcon.classList.remove('hidden');
          if (pauseIcon) pauseIcon.classList.add('hidden');
          if (text) text.textContent = text.dataset.originalText || (btn.id === 'selectedPlayPreviewBtn' ? 'Putar Audio 30s' : 'Dengar Preview 30s');
          btn.classList.remove('bg-pink-100', 'border-pink-400');
        }
      }

      // Search functionality
      document.getElementById('searchInput').addEventListener('input', function(e) {
        filterSongs();
      });

      document.getElementById('statusFilter').addEventListener('change', function(e) {
        filterSongs();
      });

      function filterSongs() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const statusFilter = document.getElementById('statusFilter').value;
        const rows = document.querySelectorAll('.song-row');

        rows.forEach(row => {
          const text = row.textContent.toLowerCase();
          const matchesSearch = text.includes(searchTerm);
          const matchesStatus = !statusFilter || row.dataset.status == statusFilter;

          row.style.display = matchesSearch && matchesStatus ? '' : 'none';
        });
      }

      // Close modals on Escape key
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          closeModal();
          closeDeleteModal();
          closeStatusConfirmModal();
          closeActiveSongWarningModal();
        }
      });

      // Close modals on outside click
      document.getElementById('songModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
      });

      document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) closeDeleteModal();
      });

      document.getElementById('statusConfirmModal').addEventListener('click', function(e) {
        if (e.target === this) closeStatusConfirmModal();
      });

      document.getElementById('activeSongWarningModal').addEventListener('click', function(e) {
        if (e.target === this) closeActiveSongWarningModal();
      });
    </script>
  @endpush
</x-app-layout>
