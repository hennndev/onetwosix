<x-app-layout title="Pengaturan Tier Customer">
  <div class="p-4 sm:p-6">

    <!-- Back -->
    <a href="{{ route('admin.settings.index') }}"
       class="inline-flex items-center gap-1.5 text-sm text-slate-600 hover:text-slate-800 mb-6">
      <svg class="w-4 h-4"
           fill="none"
           stroke="currentColor"
           viewBox="0 0 24 24">
        <path stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M10 19l-7-7m0 0l7-7m-7 7h18" />
      </svg>
      Kembali ke Menu Pengaturan
    </a>

    @if (session('success'))
      <div class="mb-4 px-4 py-3 bg-green-100 border border-green-400 text-green-700 rounded-lg text-sm">
        {{ session('success') }}
      </div>
    @endif

    @if ($errors->any())
      <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
        <ul class="list-disc list-inside space-y-0.5">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <!-- Header -->
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Tier Settings</h1>
        <p class="text-sm text-slate-500 mt-1">Kelola tier customer: nama, diskon, minimum spent, warna, dan ketentuan</p>
      </div>
      <button onclick="openModal('add')"
              class="flex items-center justify-center gap-2 bg-violet-600 hover:bg-violet-700 text-white font-semibold px-5 py-2.5 rounded-xl text-sm transition-colors">
        <svg class="w-4 h-4"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
        </svg>
        Tambah Tier
      </button>
    </div>

    <!-- Info Box -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-5 mb-6">
      <p class="text-sm font-semibold text-blue-700 mb-3 flex items-center gap-2">
        <svg class="w-4 h-4 flex-shrink-0"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        Tentang Sistem Tier:
      </p>
      <ul class="space-y-1.5 text-sm text-blue-700">
        <li class="flex items-start gap-2">
          <svg class="w-4 h-4 mt-0.5 text-blue-500 flex-shrink-0"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M5 13l4 4L19 7" />
          </svg>
          <span>Customer <strong>otomatis naik tier</strong> ketika <strong>total spent (akumulasi semua transaksi)</strong> mencapai minimum yang ditentukan</span>
        </li>
        <li class="flex items-start gap-2">
          <svg class="w-4 h-4 mt-0.5 text-blue-500 flex-shrink-0"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M5 13l4 4L19 7" />
          </svg>
          <span>Bukan per transaksi — <strong>total keseluruhan belanja</strong> customer sejak bergabung</span>
        </li>
        <li class="flex items-start gap-2">
          <svg class="w-4 h-4 mt-0.5 text-blue-500 flex-shrink-0"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M5 13l4 4L19 7" />
          </svg>
          <span><strong>Diskon tier</strong> diterapkan otomatis pada transaksi berikutnya</span>
        </li>
        <li class="flex items-start gap-2">
          <svg class="w-4 h-4 mt-0.5 text-blue-500 flex-shrink-0"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M5 13l4 4L19 7" />
          </svg>
          <span>Tier paling bawah adalah <strong>tier awal</strong> (minimum spent selalu Rp 0). Menghapus tier awal akan menjadikan tier terendah berikutnya sebagai tier awal</span>
        </li>
      </ul>
    </div>

    <!-- Daftar Tier -->
    <div class="space-y-4">
      @forelse ($tiers as $tier)
        <div class="bg-white border border-slate-200 rounded-xl p-6">
          <div class="flex items-start justify-between gap-4">
            <div class="flex items-center gap-3">
              <span class="text-xs font-bold px-2.5 py-1 rounded-full {{ $tier->colorClasses('badge') }}">
                {{ $tier->name }}
              </span>
              @if ($tier->is_first_tier)
                <span class="text-xs text-slate-400 italic">Tier awal</span>
              @endif
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
              <button onclick="editTier({{ json_encode([
                  'id' => $tier->id,
                  'name' => $tier->name,
                  'discount_percentage' => $tier->discount_percentage,
                  'minimum_spent' => $tier->minimum_spent,
                  'color' => $tier->color,
                  'description' => $tier->description,
                  'is_first_tier' => $tier->is_first_tier,
              ]) }})"
                      class="p-1.5 text-slate-500 hover:text-violet-600 hover:bg-violet-50 rounded-lg transition"
                      title="Edit tier">
                <svg class="w-4 h-4"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
              </button>
              <button onclick="deleteTier({{ $tier->id }}, '{{ addslashes($tier->name) }}')"
                      class="p-1.5 text-slate-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition"
                      title="Hapus tier">
                <svg class="w-4 h-4"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </div>
          </div>

          <div class="mt-3 flex flex-wrap items-center gap-x-6 gap-y-1 text-sm text-slate-600">
            <span><span class="font-semibold text-slate-800">{{ $tier->discount_percentage }}%</span> diskon</span>
            <span>Min. spent <span class="font-semibold text-slate-800">{{ $tier->formattedMinimumSpent }}</span></span>
          </div>

          @if ($tier->description)
            <p class="mt-3 text-sm text-slate-500 border-t border-slate-100 pt-3">{{ $tier->description }}</p>
          @endif
        </div>
      @empty
        <div class="bg-white border border-slate-200 rounded-xl p-12 text-center">
          <p class="text-slate-500 font-medium">Belum ada tier</p>
          <p class="text-slate-400 text-sm mt-1">Klik "Tambah Tier" untuk membuat tier pertama (otomatis menjadi tier awal)</p>
        </div>
      @endforelse
    </div>

    <!-- Modals -->
    @include('settings._components.tier-form-modal')
    @include('settings._components.tier-delete-modal')

  </div>

  @push('scripts')
    <script>
      const storeUrl = '{{ route('admin.settings.tier-settings.store') }}';

      function openModal(mode) {
        document.getElementById('tierModal').classList.remove('hidden');
        if (mode === 'add') {
          document.getElementById('modalTitle').textContent = 'Tambah Tier Baru';
          document.getElementById('submitButtonText').textContent = 'Tambah Tier';
          document.getElementById('tierForm').action = storeUrl;
          document.getElementById('formMethod').value = 'POST';
          document.getElementById('tierForm').reset();
          document.getElementById('firstTierHint').classList.add('hidden');
          document.getElementById('tierMinSpentInput').disabled = false;
          // Pilih warna pertama sebagai default
          document.querySelector('input[name="color"]').checked = true;
        }
      }

      function editTier(tier) {
        document.getElementById('tierModal').classList.remove('hidden');
        document.getElementById('modalTitle').textContent = 'Edit Tier';
        document.getElementById('submitButtonText').textContent = 'Simpan Perubahan';
        document.getElementById('tierForm').action = `{{ url('admin/settings/tier-settings') }}/${tier.id}`;
        document.getElementById('formMethod').value = 'PUT';

        document.getElementById('tierNameInput').value = tier.name;
        document.getElementById('tierDiscountInput').value = tier.discount_percentage;
        document.getElementById('tierDescInput').value = tier.description || '';

        // Tier awal: min spent dipaksa 0 dan di-disable
        const minInput = document.getElementById('tierMinSpentInput');
        if (tier.is_first_tier) {
          minInput.value = 0;
          minInput.disabled = true;
          document.getElementById('firstTierHint').classList.remove('hidden');
        } else {
          minInput.value = tier.minimum_spent;
          minInput.disabled = false;
          document.getElementById('firstTierHint').classList.add('hidden');
        }

        // Pilih radio warna
        const radio = document.querySelector(`input[name="color"][value="${tier.color}"]`);
        if (radio) radio.checked = true;
      }

      function closeModal() {
        document.getElementById('tierModal').classList.add('hidden');
        document.getElementById('tierForm').reset();
      }

      function deleteTier(id, name) {
        document.getElementById('deleteModal').classList.remove('hidden');
        document.getElementById('deleteTierName').textContent = name;
        document.getElementById('deleteForm').action = `{{ url('admin/settings/tier-settings') }}/${id}`;
      }

      function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
      }

      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          closeModal();
          closeDeleteModal();
        }
      });

      document.getElementById('tierModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
      });

      document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) closeDeleteModal();
      });
    </script>
  @endpush
</x-app-layout>
