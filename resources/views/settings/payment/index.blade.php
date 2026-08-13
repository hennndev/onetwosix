<x-app-layout title="Pengaturan Pembayaran">
  <div class="p-4 sm:p-6">
    @if (session('success'))
      <div class="mb-6 px-4 py-3 bg-green-100 border border-green-400 text-green-700 rounded-lg" role="alert">
        {{ session('success') }}
      </div>
    @endif

    @if ($errors->any())
      <div class="mb-6 px-4 py-3 bg-red-100 border border-red-400 text-red-700 rounded-lg" role="alert">
        <strong class="font-bold">Error!</strong>
        <ul class="mt-2 list-disc list-inside">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <a href="{{ route('admin.settings.index') }}"
       class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700 mb-6">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
      </svg>
      Kembali ke Pengaturan
    </a>

    <div class="bg-gradient-to-br from-emerald-600 to-emerald-700 rounded-2xl p-6 sm:p-8 mb-6">
      <div class="flex items-center gap-3">
        <div class="bg-white/20 p-3 rounded-lg">
          <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
          </svg>
        </div>
        <div>
          <h1 class="text-2xl sm:text-3xl font-bold text-white">Pengaturan Pembayaran</h1>
          <p class="text-emerald-100">Kelola rekening bank, WhatsApp konfirmasi, dan QRIS aplikasi mobile.</p>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-6">
      <div class="p-6 border-b border-gray-100 flex items-center gap-3">
        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
          <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 24 24">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347M12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893C23.943 5.346 18.608 0 12.05 0z" />
          </svg>
        </div>
        <div>
          <h2 class="text-lg font-bold text-gray-800">Nomor WhatsApp Konfirmasi</h2>
          <p class="text-sm text-gray-500">Hanya satu nomor aktif yang dikirim ke pengguna mobile.</p>
        </div>
      </div>

      <form action="{{ route('admin.settings.payment.whatsapp.save') }}" method="POST" class="p-6">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label for="phone_number" class="block text-sm font-semibold text-gray-700 mb-2">Nomor WhatsApp <span class="text-red-500">*</span></label>
            <input id="phone_number" type="text" name="phone_number"
                   value="{{ old('phone_number', $whatsapp?->phone_number) }}" required placeholder="6281234567890"
                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
            <p class="text-xs text-gray-400 mt-1">Format 62xxx, tanpa tanda + dan tanpa spasi.</p>
          </div>
          <div>
            <label for="whatsapp_description" class="block text-sm font-semibold text-gray-700 mb-2">Deskripsi</label>
            <input id="whatsapp_description" type="text" name="description"
                   value="{{ old('description', $whatsapp?->description) }}" placeholder="Nomor konfirmasi utama"
                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
          </div>
        </div>
        <div class="flex justify-end mt-6">
          <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2.5 rounded-lg font-semibold transition">Simpan WhatsApp</button>
        </div>
      </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-6">
      <div class="p-6 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
            </svg>
          </div>
          <div>
            <h2 class="text-lg font-bold text-gray-800">Rekening Bank</h2>
            <p class="text-sm text-gray-500">Kelola daftar rekening pembayaran pengguna.</p>
          </div>
        </div>
        <button type="button" data-open-bank-modal
                class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-lg font-semibold flex items-center justify-center gap-2 transition">
          <span class="text-lg leading-none">+</span> Tambah Rekening
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-slate-50 border-b border-gray-200">
            <tr>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Bank</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Nomor Rekening</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Atas Nama</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
              <th class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            @forelse ($bankAccounts as $account)
              @php
                $accountPayload = [
                    'bank_name' => $account->bank_name,
                    'account_number' => $account->account_number,
                    'account_holder' => $account->account_holder,
                    'is_active' => $account->is_active,
                ];
              @endphp
              <tr class="hover:bg-gray-50 transition">
                <td class="px-6 py-4 font-semibold text-gray-800">{{ $account->bank_name }}</td>
                <td class="px-6 py-4"><span class="bg-slate-100 text-slate-700 px-3 py-1 rounded-md font-mono text-sm font-semibold">{{ $account->account_number }}</span></td>
                <td class="px-6 py-4 text-sm text-gray-700">{{ $account->account_holder }}</td>
                <td class="px-6 py-4">
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ $account->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                    {{ $account->is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  <div class="flex justify-end gap-2">
                    <button type="button" data-edit-bank data-account='@json($accountPayload)'
                            data-update-url="{{ route('admin.settings.payment.bank-accounts.update', $account) }}"
                            class="px-3 py-1.5 text-sm border border-blue-200 text-blue-700 rounded-lg hover:bg-blue-50">Edit</button>
                    <button type="button" data-delete-bank data-bank-label="{{ $account->bank_name }} - {{ $account->account_number }}"
                            data-delete-url="{{ route('admin.settings.payment.bank-accounts.destroy', $account) }}"
                            class="px-3 py-1.5 text-sm border border-red-200 text-red-700 rounded-lg hover:bg-red-50">Hapus</button>
                  </div>
                </td>
              </tr>
            @empty
              <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500">Belum ada rekening bank.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="p-6 border-b border-gray-100 flex items-center gap-3">
        <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
          <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 0h2v2h-2v-2zm4 0h2v6h-6v-2" />
          </svg>
        </div>
        <div><h2 class="text-lg font-bold text-gray-800">QRIS</h2><p class="text-sm text-gray-500">Kelola kode QR untuk pembayaran QRIS.</p></div>
      </div>

      <form action="{{ route('admin.settings.payment.qris.save') }}" method="POST" enctype="multipart/form-data" class="p-6">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label for="qris_name" class="block text-sm font-semibold text-gray-700 mb-2">Nama Merchant QRIS <span class="text-red-500">*</span></label>
            <input id="qris_name" type="text" name="name" value="{{ old('name', $qris?->name) }}" required placeholder="126 Club"
                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
          </div>
          <div>
            <label for="qris_image" class="block text-sm font-semibold text-gray-700 mb-2">Gambar QR Code</label>
            <input id="qris_image" type="file" name="qris_image" accept="image/png,image/jpeg"
                   class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm">
            <p class="text-xs text-gray-400 mt-1">PNG, JPG, atau JPEG. Maksimal 2 MB.</p>
          </div>
        </div>
        @if ($qris?->image_path)
          <div class="mt-5">
            <p class="text-sm font-semibold text-gray-700 mb-2">QR Code Saat Ini</p>
            <img src="{{ Storage::disk('public')->url($qris->image_path) }}" alt="QRIS {{ $qris->name }}"
                 class="w-40 h-40 object-contain border border-gray-200 rounded-lg p-2">
          </div>
        @endif
        <div class="flex justify-end mt-6">
          <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-2.5 rounded-lg font-semibold transition">Simpan QRIS</button>
        </div>
      </form>
    </div>

    @include('settings.payment._components.bank-modal')
    @include('settings.payment._components.delete-bank-modal')
  </div>
</x-app-layout>
