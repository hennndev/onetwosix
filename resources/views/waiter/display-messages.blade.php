<x-waiter-mobile-layout>
  <div class="px-5 pt-5 pb-5">
    <div class="mb-5">
      <h1 class="text-xl font-bold">Request Tamu</h1>
      <p class="text-slate-700 text-xs mt-0.5">Kirim dan pantau display message dari menu ini</p>
    </div>

    @if (session('success'))
      <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
        {{ session('success') }}
      </div>
    @endif

    @if ($errors->any())
      <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <ul class="list-disc space-y-1 pl-5">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="bg-white rounded-2xl p-4 mb-5 border border-slate-100 shadow-sm">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <p class="text-[11px] uppercase tracking-wide text-slate-500">Tamu Aktif</p>
          @if ($selectedSession)
            <p class="mt-1 text-sm font-semibold text-slate-900">
              Meja {{ $selectedSession->table?->table_number ?? '-' }} · {{ $selectedSession->customer?->name ?? 'Tamu' }}
            </p>
            <p class="mt-0.5 text-xs text-slate-500">
              {{ $selectedSession->table?->area?->name ?? '—' }}
            </p>
          @else
            <p class="mt-1 text-sm font-semibold text-slate-900">Belum ada tamu aktif dipilih</p>
            <p class="mt-0.5 text-xs text-slate-500">Pilih booking aktif untuk mengirim request ke tamu yang benar.</p>
          @endif
        </div>
        <div class="flex-shrink-0 rounded-xl bg-teal-50 px-3 py-2 text-right">
          <p class="text-[11px] uppercase tracking-wide text-teal-700">Active</p>
          <p class="text-sm font-bold text-teal-900">{{ $activeSessions->count() }}</p>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-3 gap-3 mb-5">
      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
        <p class="text-[11px] uppercase tracking-wide text-slate-500">Total</p>
        <p class="mt-1 text-2xl font-bold text-slate-900">{{ $totalMessages }}</p>
      </div>
      <div class="rounded-2xl border border-amber-100 bg-amber-50 p-4">
        <p class="text-[11px] uppercase tracking-wide text-amber-700">Pending</p>
        <p class="mt-1 text-2xl font-bold text-amber-900">{{ $pendingMessages }}</p>
      </div>
      <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4">
        <p class="text-[11px] uppercase tracking-wide text-emerald-700">Displayed</p>
        <p class="mt-1 text-2xl font-bold text-emerald-900">{{ $displayedMessages }}</p>
      </div>
    </div>

    <div class="bg-white rounded-2xl p-5 mb-5 border border-slate-100 shadow-sm">
      <div class="flex items-start gap-3 mb-4">
        <div class="w-10 h-10 rounded-xl bg-fuchsia-100 flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5 text-fuchsia-600"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
          </svg>
        </div>
        <div class="min-w-0">
          <h2 class="font-bold text-base text-slate-900">Buat Display Message</h2>
          <p class="text-xs text-slate-600 mt-0.5">Tambah request tamu langsung dari halaman ini.</p>
        </div>
      </div>

      <form method="POST"
            action="{{ route('waiter.display-messages.store') }}"
            class="space-y-4">
        @csrf

        <div>
          <label for="session_id"
                 class="block text-sm font-medium text-slate-700 mb-2">Pilih Tamu / Meja</label>
          <select id="session_id"
                  name="session_id"
                  class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-fuchsia-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-fuchsia-100"
                  @disabled($activeSessions->isEmpty())>
            @forelse ($activeSessions as $session)
              <option value="{{ $session->id }}"
                      @selected((int) old('session_id', $selectedSessionId ?? $selectedSession?->id) === (int) $session->id)>
                Meja {{ $session->table?->table_number ?? '-' }} - {{ $session->customer?->name ?? 'Tamu' }}
              </option>
            @empty
              <option value="">Tidak ada tamu aktif</option>
            @endforelse
          </select>
          <p class="mt-1 text-xs text-slate-500">Request akan dikirim atas nama tamu yang dipilih.</p>
        </div>

        <div>
          <label for="message"
                 class="block text-sm font-medium text-slate-700 mb-2">Pesan</label>
          <textarea id="message"
                    name="message"
                    rows="4"
                    maxlength="500"
                    placeholder="Contoh: Happy birthday untuk meja 12, semoga malamnya seru!"
                    class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-fuchsia-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-fuchsia-100">{{ old('message') }}</textarea>
          <p class="mt-1 text-xs text-slate-500">Maksimal 500 karakter.</p>
        </div>

        <div>
          <label for="tip"
                 class="block text-sm font-medium text-slate-700 mb-2">Tips opsional</label>
          <input id="tip"
                 name="tip"
                 type="number"
                 min="0"
                 step="1"
                 inputmode="numeric"
                 value="{{ old('tip') }}"
                 placeholder="0"
                 class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-fuchsia-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-fuchsia-100">
          <p class="mt-1 text-xs text-slate-500">Kosongkan kalau tidak ada tips untuk request ini.</p>
        </div>

        <button type="submit"
                class="w-full rounded-2xl bg-fuchsia-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition active:scale-[0.99] hover:bg-fuchsia-700 disabled:cursor-not-allowed disabled:bg-fuchsia-300"
                @disabled($activeSessions->isEmpty())>
          Kirim Message
        </button>
      </form>
    </div>

    <div class="mb-3 flex items-center justify-between">
      <h2 class="font-semibold text-base text-slate-900">Daftar Request</h2>
      <span class="text-xs text-slate-500">{{ $messages->count() }} item</span>
    </div>

    @if ($messages->isEmpty())
      <div class="bg-white rounded-2xl p-6 text-center border border-slate-100 shadow-sm">
        <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-3">
          <svg class="w-6 h-6 text-slate-700"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
          </svg>
        </div>
        <p class="text-slate-700 text-sm">Belum ada request tamu.</p>
      </div>
    @else
      <div class="space-y-3">
        @foreach ($messages as $message)
          <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100">
            <div class="flex items-start justify-between gap-3 mb-3">
              <div class="min-w-0">
                <div class="flex items-center gap-2 mb-1">
                  <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold
                    @if ($message->status === 'pending')
                      bg-amber-100 text-amber-700
                    @elseif ($message->status === 'displayed')
                      bg-emerald-100 text-emerald-700
                    @elseif ($message->status === 'rejected')
                      bg-red-100 text-red-700
                    @else
                      bg-slate-100 text-slate-700
                    @endif">
                    {{ ucfirst($message->status) }}
                  </span>
                  <span class="text-xs text-slate-500">#{{ str_pad((string) $message->id, 4, '0', STR_PAD_LEFT) }}</span>
                </div>
                <p class="text-sm font-semibold text-slate-900">Request tamu</p>
                <p class="text-xs text-slate-500 mt-0.5">
                  {{ $message->created_at?->timezone('Asia/Jakarta')->format('d M Y, H:i') }}
                </p>
              </div>
              @if ($message->tip !== null)
                <div class="flex-shrink-0 rounded-xl bg-amber-50 px-3 py-2 text-right">
                  <p class="text-[11px] uppercase tracking-wide text-amber-700">Tips</p>
                  <p class="text-sm font-bold text-amber-900">Rp {{ number_format($message->tip, 0, ',', '.') }}</p>
                </div>
              @endif
            </div>

            <p class="text-sm text-slate-700 leading-6">{{ $message->message }}</p>
          </div>
        @endforeach
      </div>
    @endif
  </div>
</x-waiter-mobile-layout>
