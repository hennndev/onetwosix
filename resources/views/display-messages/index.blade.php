<x-app-layout title="Display Messages">
  <div class="p-4 sm:p-6">
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
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
      <div class="flex items-center gap-3">
        <div class="w-12 h-12 bg-slate-800 rounded-xl flex items-center justify-center">
          <svg class="w-6 h-6 text-white"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
          </svg>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Display Message</h1>
          <p class="text-sm text-gray-500">Kelola pesan untuk ditampilkan di layar LED nightclub</p>
        </div>
      </div>
      <button onclick="openModal('add')"
              class="px-4 py-2 bg-slate-800 text-white rounded-lg hover:bg-slate-900 transition flex items-center gap-2 justify-center">
        <svg class="w-5 h-5"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 4v16m8-8H4" />
        </svg>
        Message Baru
      </button>
    </div>

    <style>
      @keyframes marqueeSeamless {
        0% {
          transform: translateX(0%);
        }
        100% {
          transform: translateX(-50%);
        }
      }
      .marquee-wrapper {
        overflow: hidden;
        width: 100%;
        white-space: nowrap;
      }
      .marquee-content {
        display: inline-flex;
        width: max-content;
        animation: marqueeSeamless 16s linear infinite;
      }
      .marquee-content:hover {
        animation-play-state: paused;
      }
    </style>

    <!-- Live LED Display Marquee Banner -->
    @php
      $activeDisplayedMessage = $messages->firstWhere('status', 'displayed');
    @endphp
    <div class="mb-6 bg-slate-950 rounded-2xl p-5 border border-slate-800 shadow-2xl relative overflow-hidden text-white">
      <!-- Glow Background -->
      <div class="absolute -top-24 -left-24 w-64 h-64 bg-indigo-600/20 rounded-full blur-3xl pointer-events-none"></div>
      <div class="absolute -bottom-24 -right-24 w-64 h-64 bg-amber-500/15 rounded-full blur-3xl pointer-events-none"></div>

      <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4 pb-3 border-b border-slate-800/80 mb-4">
        <div class="flex items-center gap-3">
          @if ($activeDisplayedMessage)
            <div class="flex items-center gap-2 px-3 py-1 bg-red-500/20 border border-red-500/40 rounded-full">
              <span class="relative flex h-2.5 w-2.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
              </span>
              <span class="text-xs font-bold uppercase tracking-wider text-red-400">LIVE ON LED DISPLAY</span>
            </div>
            <span class="text-xs text-slate-400 font-mono">ID: MSG-{{ str_pad($activeDisplayedMessage->id, 4, '0', STR_PAD_LEFT) }}</span>
          @else
            <div class="flex items-center gap-2 px-3 py-1 bg-slate-800 border border-slate-700 rounded-full">
              <span class="h-2.5 w-2.5 rounded-full bg-slate-500"></span>
              <span class="text-xs font-bold uppercase tracking-wider text-slate-400">LED DISPLAY IDLE</span>
            </div>
            <span class="text-xs text-slate-400">Tidak ada pesan yang sedang ditampilkan</span>
          @endif
        </div>

        @if ($activeDisplayedMessage)
          <div class="flex items-center gap-3">
            <div class="text-xs text-right">
              <span class="text-slate-400">Pengirim:</span>
              <span class="font-semibold text-white ml-1">{{ $activeDisplayedMessage->customer->name }}</span>
              @if ($activeDisplayedMessage->tip)
                <span class="ml-2 px-2 py-0.5 bg-amber-400/20 border border-amber-400/40 text-amber-300 font-bold rounded">💰 Tip: Rp {{ number_format($activeDisplayedMessage->tip, 0, ',', '.') }}</span>
              @endif
            </div>
            <button onclick="updateStatus({{ $activeDisplayedMessage->id }}, 'completed')"
                    class="px-3 py-1 text-xs bg-blue-500/20 border border-blue-500/40 text-blue-300 rounded-lg hover:bg-blue-500/30 transition flex items-center gap-1 font-semibold">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
              </svg>
              Selesaikan Display
            </button>
          </div>
        @endif
      </div>

      <!-- Marquee LED Screen -->
      <div class="relative w-full bg-slate-900/95 border border-slate-800 rounded-xl py-3 px-4 overflow-hidden shadow-inner">
        @if ($activeDisplayedMessage)
          <div class="marquee-wrapper">
            <div class="marquee-content font-mono text-xl sm:text-2xl tracking-widest text-amber-400 font-extrabold uppercase drop-shadow-[0_0_12px_rgba(251,191,36,0.6)]">
              <span class="inline-flex items-center gap-8 pr-16">
                <span>{{ $activeDisplayedMessage->message }}</span>
              </span>
              <span class="inline-flex items-center gap-8 pr-16" aria-hidden="true">
                <span>{{ $activeDisplayedMessage->message }}</span>
              </span>
              <span class="inline-flex items-center gap-8 pr-16" aria-hidden="true">
                <span>{{ $activeDisplayedMessage->message }}</span>
              </span>
              <span class="inline-flex items-center gap-8 pr-16" aria-hidden="true">
                <span>{{ $activeDisplayedMessage->message }}</span>
              </span>
              <span class="inline-flex items-center gap-8 pr-16" aria-hidden="true">
                <span>{{ $activeDisplayedMessage->message }}</span>
              </span>
              <span class="inline-flex items-center gap-8 pr-16" aria-hidden="true">
                <span>{{ $activeDisplayedMessage->message }}</span>
              </span>
              <span class="inline-flex items-center gap-8 pr-16" aria-hidden="true">
                <span>{{ $activeDisplayedMessage->message }}</span>
              </span>
              <span class="inline-flex items-center gap-8 pr-16" aria-hidden="true">
                <span>{{ $activeDisplayedMessage->message }}</span>
              </span>
            </div>
          </div>
        @else
          <div class="text-center font-mono text-xs sm:text-sm tracking-wider text-slate-500 py-1 uppercase">
            --- LED DISPLAY SIAP - HANYA 1 PESAN BERSTATUS "DISPLAYED" DAPAT DITAMPILKAN ---
          </div>
        @endif
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
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
            <p class="text-2xl font-bold text-yellow-900">{{ $pendingMessages }}</p>
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
            <p class="text-sm text-green-700 font-medium">Displayed</p>
            <p class="text-2xl font-bold text-green-900">{{ $displayedMessages }}</p>
          </div>
        </div>
      </div>

      <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center">
            <svg class="w-5 h-5 text-white"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
            </svg>
          </div>
          <div>
            <p class="text-sm text-blue-700 font-medium">Total Messages</p>
            <p class="text-2xl font-bold text-blue-900">{{ $totalMessages }}</p>
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
                   placeholder="Cari message (nama, pesan, ID)..."
                   class="pl-10 pr-4 py-2 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent">
          </div>
        </div>
        <select id="statusFilter"
                class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent">
          <option value="">Semua Status</option>
          <option value="pending">Pending</option>
          <option value="displayed">Displayed</option>
          <option value="completed">Completed (Selesai Tampil)</option>
          <option value="rejected">Rejected</option>
          <option value="cancelled">Cancelled</option>
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
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Message</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200"
                 id="messageTableBody">
            @foreach ($messages as $message)
              <tr class="hover:bg-gray-50 transition message-row"
                  data-status="{{ $message->status }}">
                <td class="px-6 py-4 whitespace-nowrap">
                  @if ($message->status === 'pending')
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
                  @elseif($message->status === 'displayed')
                    <span class="px-3 py-1 text-xs font-medium rounded bg-green-100 text-green-700 flex items-center gap-1 w-fit">
                      <svg class="w-3 h-3"
                           fill="currentColor"
                           viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd" />
                      </svg>
                      Displayed
                    </span>
                  @elseif($message->status === 'completed')
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
                  @elseif($message->status === 'rejected')
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
                  @else
                    <span class="px-3 py-1 text-xs font-medium rounded bg-gray-100 text-gray-700 flex items-center gap-1 w-fit">
                      <svg class="w-3 h-3"
                           fill="currentColor"
                           viewBox="0 0 20 20">
                        <circle cx="10"
                                cy="10"
                                r="3" />
                      </svg>
                      Cancelled
                    </span>
                  @endif
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-medium text-gray-900">MSG-{{ str_pad($message->id, 4, '0', STR_PAD_LEFT) }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-semibold">
                      {{ strtoupper(substr($message->customer->name, 0, 1)) }}
                    </div>
                    <div>
                      <div class="text-sm font-medium text-gray-900">{{ $message->customer->name }}</div>
                      <div class="text-xs text-gray-500">{{ $message->customer->profile->phone ?? '-' }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <div class="max-w-md">
                    <div class="flex items-start gap-2">
                      <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-white"
                             fill="none"
                             stroke="currentColor"
                             viewBox="0 0 24 24">
                          <path stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                        </svg>
                      </div>
                      <div class="flex-1">
                        <p class="text-sm text-gray-900">{{ $message->message }}</p>
                        <p class="text-xs text-gray-500 mt-1">{{ strlen($message->message) }} karakter</p>
                        @if ($message->tip)
                          <span class="inline-block mt-2 px-2 py-1 text-xs font-semibold rounded bg-yellow-400 text-gray-900">
                            💰 Tips: Rp {{ number_format($message->tip, 0, ',', '.') }}
                          </span>
                        @endif
                      </div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">
                    {{ $message->created_at->format('d M Y') }}
                  </div>
                  <div class="text-xs text-gray-500">{{ $message->created_at->format('H:i') }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex flex-wrap gap-2">
                    @if ($message->status === 'pending')
                      <button onclick="updateStatus({{ $message->id }}, 'displayed')"
                              class="px-3 py-1 text-xs bg-green-500 text-white rounded hover:bg-green-600 transition flex items-center gap-1">
                        <svg class="w-3 h-3"
                             fill="none"
                             stroke="currentColor"
                             viewBox="0 0 24 24">
                          <path stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Tampil
                      </button>
                      <button onclick="updateStatus({{ $message->id }}, 'rejected')"
                              class="px-3 py-1 text-xs bg-red-500 text-white rounded hover:bg-red-600 transition flex items-center gap-1">
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
                    @if ($message->status === 'displayed')
                      <button onclick="updateStatus({{ $message->id }}, 'completed')"
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
                    <button onclick="editMessage({{ $message->id }})"
                            class="px-3 py-1 text-xs border border-gray-300 text-gray-700 rounded hover:bg-gray-50 transition">
                      Edit
                    </button>
                    <button onclick="deleteMessage({{ $message->id }})"
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
  @include('display-messages._components.add-edit-modal')

  <!-- Delete Modal -->
  @include('display-messages._components.delete-confirmation-modal')

  <!-- Status Confirm Modal -->
  @include('display-messages._components.status-confirmation-modal')

  <!-- Active Display Warning Modal -->
  <div id="displayedWarningModal"
       class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full overflow-hidden transition-all border border-amber-200">
      <div class="p-6 text-center">
        <div class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4 bg-amber-100 border border-amber-200 text-amber-600">
          <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
          </svg>
        </div>
        <h3 class="text-lg font-bold text-gray-900 mb-2">Pesan Aktif Sedang Berjalan</h3>
        <p id="displayedWarningText" class="text-sm text-gray-600 mb-6">
          Saat ini terdapat pesan yang sedang aktif ditampilkan di layar LED. Silakan matikan atau selesaikan pesan aktif tersebut terlebih dahulu sebelum menampilkan pesan baru.
        </p>
        <button type="button"
                onclick="closeDisplayedWarningModal()"
                class="w-full px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-xl transition">
          Saya Mengerti
        </button>
      </div>
    </div>
  </div>

  @push('scripts')
    <script>
      const messages = @json($messages);
      const activeDisplayedId = @json($activeDisplayedMessage ? $activeDisplayedMessage->id : null);
      let pendingStatusUpdate = { messageId: null, status: null };

      function showActiveWarningModal(activeId) {
        const formattedId = 'MSG-' + String(activeId).padStart(4, '0');
        document.getElementById('displayedWarningText').textContent =
          `Saat ini pesan ${formattedId} sedang aktif ditampilkan di layar LED. Silakan matikan atau selesaikan pesan aktif tersebut terlebih dahulu sebelum menampilkan pesan baru.`;
        document.getElementById('displayedWarningModal').classList.remove('hidden');
      }

      function closeDisplayedWarningModal() {
        document.getElementById('displayedWarningModal').classList.add('hidden');
      }

      function formatTipRupiah(input) {
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

      function openModal(mode, messageId = null) {
        const modal = document.getElementById('messageModal');
        const form = document.getElementById('messageForm');
        const modalTitle = document.getElementById('modalTitle');
        const formMethod = document.getElementById('formMethod');

        if (mode === 'add') {
          modalTitle.textContent = 'Message Baru';
          form.action = '{{ route('admin.display-messages.store') }}';
          formMethod.value = 'POST';
          form.reset();
          document.getElementById('tip').value = '';
          document.getElementById('tip_display').value = '';
          document.getElementById('status').value = 'pending';
          updateCharCount();
        } else if (mode === 'edit' && messageId) {
          const message = messages.find(m => m.id === messageId);
          if (message) {
            modalTitle.textContent = 'Edit Message';
            form.action = `/admin/display-messages/${messageId}`;
            formMethod.value = 'PUT';

            document.getElementById('customer_id').value = message.customer_id;
            document.getElementById('message').value = message.message;
            if (message.tip) {
              document.getElementById('tip').value = message.tip;
              document.getElementById('tip_display').value = new Intl.NumberFormat('id-ID').format(message.tip);
            } else {
              document.getElementById('tip').value = '';
              document.getElementById('tip_display').value = '';
            }
            document.getElementById('status').value = message.status;
            updateCharCount();
          }
        }

        modal.classList.remove('hidden');
      }

      function closeModal() {
        document.getElementById('messageModal').classList.add('hidden');
      }

      function editMessage(messageId) {
        openModal('edit', messageId);
      }

      function deleteMessage(messageId) {
        const form = document.getElementById('deleteForm');
        form.action = `/admin/display-messages/${messageId}`;
        document.getElementById('deleteModal').classList.remove('hidden');
      }

      function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
      }

      function updateStatus(messageId, status) {
        if (status === 'displayed' && activeDisplayedId && activeDisplayedId != messageId) {
          showActiveWarningModal(activeDisplayedId);
          return;
        }

        pendingStatusUpdate = { messageId, status };

        const modal = document.getElementById('statusConfirmModal');
        const iconBg = document.getElementById('statusConfirmIconBg');
        const icon = document.getElementById('statusConfirmIcon');
        const title = document.getElementById('statusConfirmTitle');
        const msg = document.getElementById('statusConfirmMessage');
        const confirmBtn = document.getElementById('confirmStatusBtn');

        if (status === 'displayed') {
          iconBg.className = 'w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4 bg-emerald-100';
          icon.className = 'w-7 h-7 text-emerald-600';
          icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>`;
          title.textContent = 'Tampilkan Pesan';
          msg.textContent = 'Apakah Anda yakin ingin menyetujui dan menampilkan pesan ini ke layar LED?';
          confirmBtn.className = 'flex-1 px-4 py-2.5 bg-emerald-600 text-white rounded-xl hover:bg-emerald-700 font-semibold transition flex items-center justify-center gap-2';
        } else if (status === 'completed') {
          iconBg.className = 'w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4 bg-blue-100';
          icon.className = 'w-7 h-7 text-blue-600';
          icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>`;
          title.textContent = 'Selesaikan Penayangan Pesan';
          msg.textContent = 'Apakah Anda yakin penayangan pesan ini telah selesai dan ingin mematikan display?';
          confirmBtn.className = 'flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-xl hover:bg-blue-700 font-semibold transition flex items-center justify-center gap-2';
        } else if (status === 'rejected') {
          iconBg.className = 'w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4 bg-red-100';
          icon.className = 'w-7 h-7 text-red-600';
          icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>`;
          title.textContent = 'Tolak Pesan';
          msg.textContent = 'Apakah Anda yakin ingin menolak pesan ini?';
          confirmBtn.className = 'flex-1 px-4 py-2.5 bg-red-600 text-white rounded-xl hover:bg-red-700 font-semibold transition flex items-center justify-center gap-2';
        } else {
          iconBg.className = 'w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4 bg-slate-100';
          icon.className = 'w-7 h-7 text-slate-600';
          icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>`;
          title.textContent = 'Konfirmasi Status';
          msg.textContent = 'Apakah Anda yakin ingin mengubah status pesan ini?';
          confirmBtn.className = 'flex-1 px-4 py-2.5 bg-slate-800 text-white rounded-xl hover:bg-slate-900 font-semibold transition flex items-center justify-center gap-2';
        }

        modal.classList.remove('hidden');
      }

      function closeStatusConfirmModal() {
        document.getElementById('statusConfirmModal').classList.add('hidden');
        pendingStatusUpdate = { messageId: null, status: null };
      }

      function submitStatusChange() {
        if (!pendingStatusUpdate.messageId || !pendingStatusUpdate.status) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/admin/display-messages/${pendingStatusUpdate.messageId}/status`;

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

      function updateCharCount() {
        const textarea = document.getElementById('message');
        const charCount = document.getElementById('charCount');
        const currentLength = textarea.value.length;
        charCount.textContent = `${currentLength}/500`;

        if (currentLength > 450) {
          charCount.classList.add('text-red-500');
          charCount.classList.remove('text-gray-500');
        } else {
          charCount.classList.remove('text-red-500');
          charCount.classList.add('text-gray-500');
        }
      }

      // Add/Edit Form submit validation
      document.getElementById('messageForm').addEventListener('submit', function(e) {
        const statusVal = document.getElementById('status').value;
        const currentFormMethod = document.getElementById('formMethod').value;
        const formAction = this.action;
        let editingId = null;

        if (currentFormMethod === 'PUT') {
          const parts = formAction.split('/');
          editingId = parseInt(parts[parts.length - 1], 10);
        }

        if (statusVal === 'displayed' && activeDisplayedId && activeDisplayedId != editingId) {
          e.preventDefault();
          closeModal();
          showActiveWarningModal(activeDisplayedId);
        }
      });

      // Search functionality
      document.getElementById('searchInput').addEventListener('input', function(e) {
        filterMessages();
      });

      document.getElementById('statusFilter').addEventListener('change', function(e) {
        filterMessages();
      });

      function filterMessages() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const statusFilter = document.getElementById('statusFilter').value;
        const rows = document.querySelectorAll('.message-row');

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
          closeDisplayedWarningModal();
        }
      });

      // Close modals on outside click
      document.getElementById('messageModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
      });

      document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) closeDeleteModal();
      });

      document.getElementById('statusConfirmModal').addEventListener('click', function(e) {
        if (e.target === this) closeStatusConfirmModal();
      });

      document.getElementById('displayedWarningModal').addEventListener('click', function(e) {
        if (e.target === this) closeDisplayedWarningModal();
      });
    </script>
  @endpush
</x-app-layout>
