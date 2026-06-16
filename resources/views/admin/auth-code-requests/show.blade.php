<x-app-layout>
  <div class="p-6 max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
        <h2 class="text-lg font-bold text-slate-800">Review Permintaan Auth Code</h2>
        <span class="px-3 py-1 text-xs font-semibold rounded-full 
          @if($request->status === 'pending') bg-yellow-100 text-yellow-700
          @elseif($request->status === 'approved') bg-green-100 text-green-700
          @else bg-red-100 text-red-700 @endif">
          {{ ucfirst($request->status) }}
        </span>
      </div>

      <div class="p-6">
        <div class="grid grid-cols-2 gap-4 mb-6 text-sm">
          <div>
            <p class="text-slate-500 mb-1">Diminta Oleh</p>
            <p class="font-semibold text-slate-800">{{ $request->user->name ?? 'System' }}</p>
          </div>
          <div>
            <p class="text-slate-500 mb-1">Waktu Request</p>
            <p class="font-semibold text-slate-800">{{ $request->created_at->format('d M Y H:i:s') }}</p>
          </div>
          <div>
            <p class="text-slate-500 mb-1">Source / Layar</p>
            <p class="font-semibold text-slate-800">{{ $request->source }}</p>
          </div>
        </div>

        @if(session('success'))
          <div class="mb-6 p-4 bg-green-50 text-green-700 rounded-lg border border-green-200">
            {{ session('success') }}
          </div>
        @endif

        @if($request->status === 'pending')
          <form action="{{ route('admin.auth-code-requests.update', $request) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-6">
              <label class="block text-sm font-semibold text-slate-700 mb-2">Catatan untuk Kasir / Waiter (Opsional)</label>
              <textarea name="manager_note" rows="3" 
                class="w-full border border-slate-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-slate-400 outline-none"
                placeholder="Misal: Tolong pastikan bill meja 5 sudah dibayar cash."></textarea>
            </div>

            <div class="flex items-center gap-3">
              <button type="submit" name="status" value="approved" class="flex-1 bg-slate-900 text-white font-semibold py-3 px-4 rounded-lg hover:bg-slate-800 transition-colors">
                Kirim Auth Code & Approve
              </button>
              <button type="submit" name="status" value="rejected" class="flex-1 bg-red-50 text-red-600 font-semibold py-3 px-4 rounded-lg hover:bg-red-100 transition-colors">
                Tolak (Reject)
              </button>
            </div>
          </form>
        @else
          <div class="bg-slate-50 p-4 rounded-lg border border-slate-200 mt-6">
            <p class="text-sm text-slate-500 mb-1">Catatan Manager:</p>
            <p class="font-medium text-slate-800">{{ $request->manager_note ?: '-' }}</p>
          </div>
        @endif
      </div>
    </div>
  </div>
</x-app-layout>
