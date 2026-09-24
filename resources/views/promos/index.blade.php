<x-app-layout>
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
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Promo Management</h1>
                    <p class="text-sm text-gray-500">Kelola promo dan penawaran spesial untuk customer</p>
                </div>
            </div>
            <button onclick="openModal('add')"
                class="px-4 py-2 bg-slate-800 text-white rounded-lg hover:bg-slate-900 transition flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Promo
            </button>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-4 gap-4 mb-6">
            <div class="bg-purple-50 border border-purple-200 rounded-xl p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-purple-500 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-purple-700 font-medium">Promo Hari Ini</p>
                        <p class="text-2xl font-bold text-purple-900">{{ $todayPromos }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-blue-700 font-medium">Promo Mendatang</p>
                        <p class="text-2xl font-bold text-blue-900">{{ $upcomingPromos }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-green-50 border border-green-200 rounded-xl p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-green-700 font-medium">Promo Aktif</p>
                        <p class="text-2xl font-bold text-green-900">{{ $activePromos }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gray-500 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-700 font-medium">Total Promo</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $totalPromos }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Promos Section -->
        <div class="mb-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">🏷️ Semua Promo ({{ $promos->count() }})</h2>

            <div class="grid grid-cols-3 gap-4">
                @forelse($promos as $promo)
                <div class="bg-white border border-gray-200 rounded-xl p-5 hover:shadow-lg transition">
                    <!-- Header Badges -->
                    <div class="flex items-center gap-2 mb-3">
                        @if ($promo->isMultiDay())
                        <span
                            class="px-2 py-1 text-xs font-medium rounded bg-blue-100 text-blue-700 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Multi-Day
                        </span>
                        @endif

                        @if ($promo->is_active)
                        <span
                            class="px-2 py-1 text-xs font-medium rounded bg-green-100 text-green-700 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                            Active
                        </span>
                        @else
                        <span
                            class="px-2 py-1 text-xs font-medium rounded bg-gray-100 text-gray-600 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                <circle cx="10" cy="10" r="3" />
                            </svg>
                            Inactive
                        </span>
                        @endif
                    </div>

                    <!-- Promo Image -->
                    @if ($promo->image)
                    <div class="mb-3 rounded-lg overflow-hidden">
                        <img src="{{ asset('storage/' . $promo->image) }}" alt="{{ $promo->name }}"
                            class="w-full h-32 object-cover">
                    </div>
                    @endif

                    <!-- Promo Name -->
                    <h3 class="text-lg font-bold text-gray-900 mb-3">{{ $promo->name }}</h3>

                    <!-- Dates -->
                    <div class="space-y-1 mb-3">
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span>Mulai: {{ $promo->start_date->format('d F Y') }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span>Berakhir: {{ $promo->end_date->format('d F Y') }}</span>
                        </div>
                    </div>

                    <!-- Description -->
                    @if ($promo->description)
                    <p class="text-sm text-gray-600 mb-4 line-clamp-2">{{ $promo->description }}</p>
                    @endif

                    <!-- Discount Info -->
                    <div class="bg-orange-50 border border-orange-200 rounded-lg p-3 mb-4">
                        <div class="flex items-start gap-2">
                            <svg class="w-4 h-4 text-orange-600 mt-0.5" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                            <div class="flex-1">
                                <p class="text-xs text-orange-700 font-medium mb-1">Diskon</p>
                                <p class="text-lg font-bold text-orange-900">{{ $promo->getDiscountFormatted() }}</p>
                                <p class="text-xs text-orange-600 mt-1">{{ $promo->getDiscountDescription() }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-2">
                        @if ($promo->is_active)
                        <button onclick="toggleStatus({{ $promo->id }})"
                            class="flex-1 px-3 py-2 text-sm border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                            </svg>
                            Nonaktifkan
                        </button>
                        @else
                        <button onclick="toggleStatus({{ $promo->id }})"
                            class="flex-1 px-3 py-2 text-sm bg-green-500 text-white rounded-lg hover:bg-green-600 transition flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Aktifkan
                        </button>
                        @endif
                        <button onclick="editPromo({{ $promo->id }})"
                            class="px-4 py-2 text-sm border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </button>
                        <button onclick="deletePromo({{ $promo->id }})"
                            class="px-4 py-2 text-sm text-red-600 rounded-lg hover:bg-red-50 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
                @empty
                <div class="col-span-3 text-center py-12">
                    <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                    <p class="text-gray-500">Belum ada promo</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    @include('promos._components.add-edit-modal')

    <!-- Delete Modal -->
    @include('promos._components.delete-confirmation-modal')

    @push('scripts')
    <script>
        const promos = @json($promos);

      function formatRupiahValue(value) {
        const numericValue = Number(String(value || '').replace(/[^\d]/g, ''));

        if (!Number.isFinite(numericValue) || numericValue <= 0) {
          return '';
        }

        return `Rp ${new Intl.NumberFormat('id-ID').format(numericValue)}`;
      }

      function normalizePercentageValue(value) {
        const normalized = String(value || '').replace(',', '.').replace(/[^\d.]/g, '');
        const parts = normalized.split('.');

        if (parts.length <= 1) {
          return parts[0] || '';
        }

        return `${parts.shift()}.${parts.join('')}`;
      }

      function normalizeFixedValue(value) {
        return String(value || '').replace(/[^\d]/g, '');
      }

      function getDiscountInput() {
        return document.getElementById('discount_value');
      }

      function getDiscountType() {
        return document.getElementById('discount_type').value;
      }

      function updateDiscountInputDisplay() {
        const discountInput = getDiscountInput();
        const type = getDiscountType();

        if (type === 'percentage') {
          discountInput.value = normalizePercentageValue(discountInput.value);
          return;
        }

        const normalizedFixedValue = normalizeFixedValue(discountInput.value);
        discountInput.value = formatRupiahValue(normalizedFixedValue);
      }

      function normalizeDiscountInputForSubmit() {
        const discountInput = getDiscountInput();
        const type = getDiscountType();

        if (type === 'percentage') {
          discountInput.value = normalizePercentageValue(discountInput.value);
          return;
        }

        discountInput.value = normalizeFixedValue(discountInput.value);
      }

      function setDiscountInputValue(value) {
        const discountInput = getDiscountInput();
        const type = getDiscountType();

        if (type === 'percentage') {
          discountInput.value = normalizePercentageValue(value);
          return;
        }

        discountInput.value = formatRupiahValue(value);
      }

      function updateDiscountLabel() {
        const type = document.getElementById('discount_type').value;
        const label = document.getElementById('discountLabel');
        const help = document.getElementById('discountHelp');
        const discountInput = getDiscountInput();

        if (type === 'percentage') {
          label.textContent = 'Nilai Diskon (%)';
          help.textContent = 'Potongan persentase dari harga normal';
          discountInput.placeholder = 'Contoh: 10';
        } else {
          label.textContent = 'Nilai Diskon (Rp)';
          help.textContent = 'Potongan harga tetap';
          discountInput.placeholder = 'Contoh: Rp 100.000';
        }

        updateDiscountInputDisplay();
      }

      function openModal(mode, promoId = null) {
        const modal = document.getElementById('promoModal');
        const form = document.getElementById('promoForm');
        const modalTitle = document.getElementById('modalTitle');
        const formMethod = document.getElementById('formMethod');

        if (mode === 'add') {
          modalTitle.textContent = 'Tambah Promo';
          form.action = '{{ route('admin.promos.store') }}';
          formMethod.value = 'POST';
          form.reset();
          updateDiscountLabel();
        } else if (mode === 'edit' && promoId) {
          const promo = promos.find(p => p.id === promoId);
          if (promo) {
            modalTitle.textContent = 'Edit Promo';
            form.action = `/admin/promos/${promoId}`;
            formMethod.value = 'PUT';

            document.getElementById('name').value = promo.name;
            document.getElementById('description').value = promo.description || '';
            document.getElementById('start_date').value = promo.start_date;
            document.getElementById('end_date').value = promo.end_date;
            document.getElementById('start_time').value = promo.start_time || '';
            document.getElementById('end_time').value = promo.end_time || '';
            document.getElementById('discount_type').value = promo.discount_type;
            setDiscountInputValue(promo.discount_value);
            document.getElementById('terms_conditions').value = promo.terms_conditions || '';
            document.getElementById('is_active').checked = promo.is_active;
            updateDiscountLabel();
          }
        }

        modal.classList.remove('hidden');
      }

      function closeModal() {
        document.getElementById('promoModal').classList.add('hidden');
      }

      function editPromo(promoId) {
        openModal('edit', promoId);
      }

      function deletePromo(promoId) {
        const form = document.getElementById('deleteForm');
        form.action = `/admin/promos/${promoId}`;
        document.getElementById('deleteModal').classList.remove('hidden');
      }

      function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
      }

      function toggleStatus(promoId) {
        if (confirm('Apakah Anda yakin ingin mengubah status promo ini?')) {
          const form = document.createElement('form');
          form.method = 'POST';
          form.action = `/admin/promos/${promoId}/toggle-status`;

          const csrfToken = document.createElement('input');
          csrfToken.type = 'hidden';
          csrfToken.name = '_token';
          csrfToken.value = '{{ csrf_token() }}';

          const methodField = document.createElement('input');
          methodField.type = 'hidden';
          methodField.name = '_method';
          methodField.value = 'PATCH';

          form.appendChild(csrfToken);
          form.appendChild(methodField);

          document.body.appendChild(form);
          form.submit();
        }
      }

      // Close modals on Escape key
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          closeModal();
          closeDeleteModal();
        }
      });

      // Close modals on outside click
      document.getElementById('promoModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
      });

      document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) closeDeleteModal();
      });

      document.getElementById('discount_value').addEventListener('input', function() {
        updateDiscountInputDisplay();
      });

      document.getElementById('promoForm').addEventListener('submit', function() {
        normalizeDiscountInputForSubmit();
      });
    </script>
    @endpush
</x-app-layout>
