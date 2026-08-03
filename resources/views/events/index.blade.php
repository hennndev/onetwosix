<x-app-layout title="Manajemen Event">
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
                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Event Management</h1>
          <p class="text-sm text-gray-500">Kelola event dan atur kenaikan harga table saat event berlangsung</p>
        </div>
      </div>
      <button onclick="openModal('add')"
              class="px-4 py-2 bg-slate-800 text-white rounded-lg hover:bg-slate-900 transition flex items-center gap-2">
        <svg class="w-5 h-5"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 4v16m8-8H4" />
        </svg>
        Tambah Event
      </button>
    </div>

    <!-- Area Filter Pills -->
    @if (($areas ?? collect())->count() > 1)
      <div class="flex items-center gap-2 mb-6">
        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Area:</span>
        <div class="inline-flex items-center gap-1.5 p-1 bg-white rounded-xl border border-gray-200 shadow-sm">
          <a href="{{ route('admin.events.index', array_merge(request()->query(), ['area_id' => 'all'])) }}"
             class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ empty($selectedAreaId) ? 'bg-slate-800 text-white shadow-xs' : 'text-gray-600 hover:bg-gray-100' }}">
            Semua Area
          </a>
          @foreach ($areas as $area)
            <a href="{{ route('admin.events.index', array_merge(request()->query(), ['area_id' => $area->id])) }}"
               class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ (int) ($selectedAreaId ?? 0) === (int) $area->id ? 'bg-slate-800 text-white shadow-xs' : 'text-gray-600 hover:bg-gray-100' }}">
              {{ $area->name }}
            </a>
          @endforeach
        </div>
      </div>
    @endif

    <!-- Stats Cards -->
    <div class="grid grid-cols-4 gap-4 mb-6">
      <div class="bg-purple-50 border border-purple-200 rounded-xl p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-purple-500 rounded-lg flex items-center justify-center">
            <svg class="w-5 h-5 text-white"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </div>
          <div>
            <p class="text-sm text-purple-700 font-medium">Today's Events</p>
            <p class="text-2xl font-bold text-purple-900">{{ $todayEvents }}</p>
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
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </div>
          <div>
            <p class="text-sm text-blue-700 font-medium">Upcoming Events</p>
            <p class="text-2xl font-bold text-blue-900">{{ $upcomingEvents }}</p>
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
            <p class="text-sm text-green-700 font-medium">Active Events</p>
            <p class="text-2xl font-bold text-green-900">{{ $activeEvents }}</p>
          </div>
        </div>
      </div>

      <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-gray-500 rounded-lg flex items-center justify-center">
            <svg class="w-5 h-5 text-white"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
            </svg>
          </div>
          <div>
            <p class="text-sm text-gray-700 font-medium">Total Events</p>
            <p class="text-2xl font-bold text-gray-900">{{ $totalEvents }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Events Section -->
    <div class="mb-6">
      <h2 class="text-lg font-bold text-gray-900 mb-4">📅 Semua Event ({{ $events->count() }})</h2>

      <div class="grid grid-cols-3 gap-4">
        @forelse($events as $event)
          <div class="bg-white border border-gray-200 rounded-xl p-5 hover:shadow-lg transition">
            <!-- Header Badges -->
            <div class="flex items-center gap-2 mb-3">
              @if ($event->isMultiDay())
                <span class="px-2 py-1 text-xs font-medium rounded bg-blue-100 text-blue-700 flex items-center gap-1">
                  <svg class="w-3 h-3"
                       fill="none"
                       stroke="currentColor"
                       viewBox="0 0 24 24">
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  Multi-Day
                </span>
              @endif

              @if ($event->is_active)
                <span class="px-2 py-1 text-xs font-medium rounded bg-green-100 text-green-700 flex items-center gap-1">
                  <svg class="w-3 h-3"
                       fill="currentColor"
                       viewBox="0 0 20 20">
                    <path fill-rule="evenodd"
                          d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                          clip-rule="evenodd" />
                  </svg>
                  Active
                </span>
              @else
                <span class="px-2 py-1 text-xs font-medium rounded bg-gray-100 text-gray-600 flex items-center gap-1">
                  <svg class="w-3 h-3"
                       fill="currentColor"
                       viewBox="0 0 20 20">
                    <circle cx="10"
                            cy="10"
                            r="3" />
                  </svg>
                  Inactive
                </span>
              @endif

              <span class="px-2 py-1 text-xs font-medium rounded bg-purple-100 text-purple-700 flex items-center gap-1">
                <svg class="w-3 h-3"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                </svg>
                {{ $event->area ? $event->area->name : 'Semua Area' }}
              </span>
            </div>

            <!-- Event Name -->
            <h3 class="text-lg font-bold text-gray-900 mb-3">{{ $event->name }}</h3>

            <!-- Dates -->
            <div class="space-y-1 mb-3">
              <div class="flex items-center gap-2 text-sm text-gray-600">
                <svg class="w-4 h-4"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span>Mulai: {{ $event->start_date->format('d F Y') }}</span>
              </div>
              <div class="flex items-center gap-2 text-sm text-gray-600">
                <svg class="w-4 h-4"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span>Berakhir: {{ $event->end_date->format('d F Y') }}</span>
              </div>
            </div>

            <!-- Description -->
            @if ($event->description)
              <p class="text-sm text-gray-600 mb-4 line-clamp-2">{{ $event->description }}</p>
            @endif

            <!-- Price Adjustment -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
              <div class="flex items-start gap-2">
                <svg class="w-4 h-4 text-yellow-600 mt-0.5"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
                <div class="flex-1">
                  <p class="text-xs text-yellow-700 font-medium mb-1">Kenaikan Harga Table</p>
                  <p class="text-lg font-bold text-yellow-900">{{ $event->getPriceAdjustmentFormatted() }}</p>
                  <p class="text-xs text-yellow-600 mt-1">{{ $event->getPriceAdjustmentDescription() }}</p>
                </div>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex gap-2">
              @if ($event->is_active)
                <button onclick="toggleStatus({{ $event->id }})"
                        class="flex-1 px-3 py-2 text-sm border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition flex items-center justify-center gap-2">
                  <svg class="w-4 h-4"
                       fill="none"
                       stroke="currentColor"
                       viewBox="0 0 24 24">
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                  </svg>
                  Nonaktifkan
                </button>
              @else
                <button onclick="toggleStatus({{ $event->id }})"
                        class="flex-1 px-3 py-2 text-sm bg-green-500 text-white rounded-lg hover:bg-green-600 transition flex items-center justify-center gap-2">
                  <svg class="w-4 h-4"
                       fill="none"
                       stroke="currentColor"
                       viewBox="0 0 24 24">
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  Aktifkan
                </button>
              @endif
              <button onclick="editEvent({{ $event->id }})"
                      class="px-4 py-2 text-sm border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
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
              <button onclick="deleteEvent({{ $event->id }})"
                      class="px-4 py-2 text-sm text-red-600 rounded-lg hover:bg-red-50 transition">
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
        @empty
          <div class="col-span-3 text-center py-12">
            <svg class="w-16 h-16 mx-auto text-gray-400 mb-4"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <p class="text-gray-500">Belum ada event</p>
          </div>
        @endforelse
      </div>
    </div>
  </div>

  <!-- Add/Edit Modal -->
  @include('events._components.add-edit-modal')

  <!-- Delete Modal -->
  @include('events._components.delete-confirmation-modal')

  @push('scripts')
    <script>
      const events = @json($events);

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

      function getPriceAdjustmentInput() {
        return document.getElementById('price_adjustment_value');
      }

      function getPriceAdjustmentType() {
        return document.getElementById('price_adjustment_type').value;
      }

      function updatePriceInputDisplay() {
        const priceInput = getPriceAdjustmentInput();
        const type = getPriceAdjustmentType();

        if (type === 'percentage') {
          priceInput.value = normalizePercentageValue(priceInput.value);
          return;
        }

        const normalizedFixedValue = normalizeFixedValue(priceInput.value);
        priceInput.value = formatRupiahValue(normalizedFixedValue);
      }

      function normalizePriceInputForSubmit() {
        const priceInput = getPriceAdjustmentInput();
        const type = getPriceAdjustmentType();

        if (type === 'percentage') {
          priceInput.value = normalizePercentageValue(priceInput.value);
          return;
        }

        priceInput.value = normalizeFixedValue(priceInput.value);
      }

      function setPriceAdjustmentInputValue(value) {
        const priceInput = getPriceAdjustmentInput();
        const type = getPriceAdjustmentType();

        if (type === 'percentage') {
          priceInput.value = normalizePercentageValue(value);
          return;
        }

        priceInput.value = formatRupiahValue(value);
      }

      function updatePriceLabel() {
        const type = document.getElementById('price_adjustment_type').value;
        const label = document.getElementById('priceLabel');
        const help = document.getElementById('priceHelp');
        const priceInput = getPriceAdjustmentInput();

        if (type === 'percentage') {
          label.textContent = 'Nilai Kenaikan (%)';
          help.textContent = 'Dari harga minimum charge normal';
          priceInput.placeholder = 'Contoh: 10';
        } else {
          label.textContent = 'Nilai Kenaikan (Rp)';
          help.textContent = 'Ditambahkan ke harga minimum charge';
          priceInput.placeholder = 'Contoh: Rp 100.000';
        }

        updatePriceInputDisplay();
      }

      function openModal(mode, eventId = null) {
        const modal = document.getElementById('eventModal');
        const form = document.getElementById('eventForm');
        const modalTitle = document.getElementById('modalTitle');
        const formMethod = document.getElementById('formMethod');

        if (mode === 'add') {
          modalTitle.textContent = 'Tambah Event';
          form.action = '{{ route('admin.events.store') }}';
          formMethod.value = 'POST';
          form.reset();
          document.getElementById('area_id').value = '';
          updatePriceLabel();
        } else if (mode === 'edit' && eventId) {
          const event = events.find(e => e.id === eventId);
          if (event) {
            modalTitle.textContent = 'Edit Event';
            form.action = `/admin/events/${eventId}`;
            formMethod.value = 'PUT';

            document.getElementById('area_id').value = event.area_id || '';
            document.getElementById('name').value = event.name;
            document.getElementById('description').value = event.description || '';
            document.getElementById('start_date').value = event.start_date;
            document.getElementById('end_date').value = event.end_date;
            document.getElementById('start_time').value = event.start_time || '';
            document.getElementById('end_time').value = event.end_time || '';
            document.getElementById('price_adjustment_type').value = event.price_adjustment_type;
            setPriceAdjustmentInputValue(event.price_adjustment_value);
            document.getElementById('is_active').checked = event.is_active;
            updatePriceLabel();
          }
        }

        modal.classList.remove('hidden');
      }

      function closeModal() {
        document.getElementById('eventModal').classList.add('hidden');
      }

      function editEvent(eventId) {
        openModal('edit', eventId);
      }

      function deleteEvent(eventId) {
        const form = document.getElementById('deleteForm');
        form.action = `/admin/events/${eventId}`;
        document.getElementById('deleteModal').classList.remove('hidden');
      }

      function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
      }

      function toggleStatus(eventId) {
        if (confirm('Apakah Anda yakin ingin mengubah status event ini?')) {
          const form = document.createElement('form');
          form.method = 'POST';
          form.action = `/admin/events/${eventId}/toggle-status`;

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
      document.getElementById('eventModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
      });

      document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) closeDeleteModal();
      });

      document.getElementById('price_adjustment_value').addEventListener('input', function() {
        updatePriceInputDisplay();
      });

      document.getElementById('eventForm').addEventListener('submit', function() {
        normalizePriceInputForSubmit();
      });
    </script>
  @endpush
</x-app-layout>
