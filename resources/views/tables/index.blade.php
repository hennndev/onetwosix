<x-app-layout title="Manajemen Master Meja">
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
                  d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
          </svg>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Manajemen Meja</h1>
          <p class="text-sm text-gray-500">Kelola meja dan minimum charge per lokasi</p>
        </div>
      </div>
      <div class="flex flex-col sm:flex-row gap-2">
        <a href="{{ route('admin.tables.layout') }}"
           class="px-4 py-2 bg-white border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition flex items-center gap-2 justify-center font-medium">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
          </svg>
          Atur Denah
        </a>
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
          Tambah Meja
        </button>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-white rounded-xl p-6 border border-gray-200">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 bg-slate-800 rounded-xl flex items-center justify-center">
            <svg class="w-6 h-6 text-white"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
            </svg>
          </div>
          <div>
            <p class="text-sm text-gray-500">Total Meja</p>
            <p class="text-2xl font-bold text-slate-800">{{ $totalTables }}</p>
          </div>
        </div>
      </div>
      <div class="bg-white rounded-xl p-6 border border-gray-200">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
            <svg class="w-6 h-6 text-green-500"
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
            <p class="text-sm text-gray-500">Tersedia</p>
            <p class="text-2xl font-bold text-green-500">{{ $availableTables }}</p>
          </div>
        </div>
      </div>
      <div class="bg-white rounded-xl p-6 border border-gray-200">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
            <svg class="w-6 h-6 text-blue-500"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
          </div>
          <div>
            <p class="text-sm text-gray-500">Kapasitas</p>
            <p class="text-2xl font-bold text-blue-500">{{ $totalCapacity }}</p>
          </div>
        </div>
      </div>
      {{-- <div class="bg-white rounded-xl p-6 border border-gray-200">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center">
            <svg class="w-6 h-6 text-orange-500"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <p class="text-sm text-gray-500">Min. Charge</p>
            <p class="text-2xl font-bold text-orange-500">Rp {{ number_format($avgMinCharge ?? 0, 0, ',', '.') }}jt</p>
          </div>
        </div>
      </div> --}}
    </div>

    <!-- Area Filter Tabs -->
    @if (($areaStats ?? collect())->count() > 1)
      <div class="mb-6">
        <div class="flex gap-2 overflow-x-auto pb-2">
          <button onclick="filterByArea('all')"
                  class="area-filter-btn px-4 py-2 bg-slate-800 text-white rounded-lg whitespace-nowrap active">
            Semua Area
          </button>
          @foreach ($areaStats as $areaStat)
            <button onclick="filterByArea({{ $areaStat->id }})"
                    class="area-filter-btn px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg transition whitespace-nowrap">
              {{ $areaStat->name }} ({{ $areaStat->tables_count }})
            </button>
          @endforeach
        </div>
      </div>
    @endif

    <!-- Table Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
      <!-- Search -->
      <div class="p-4 border-b border-gray-200">
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
                 placeholder="Cari nomor meja atau lokasi..."
                 class="pl-10 pr-4 py-2 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-slate-500 focus:border-transparent">
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-slate-800 text-white">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Nama Meja</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Lokasi</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Kapasitas</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Min. Charge</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Action</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200"
                 id="tableBody">
            @foreach ($tables as $table)
              <tr class="hover:bg-gray-50 transition table-row"
                  data-area-id="{{ $table->area_id }}">
                <td class="px-6 py-4 whitespace-nowrap">
                  @if ($table->status === 'available')
                    <span class="px-3 py-1 text-xs font-medium rounded-full bg-green-100 text-green-700 flex items-center gap-1 w-fit">
                      <svg class="w-3 h-3"
                           fill="currentColor"
                           viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd" />
                      </svg>
                      Tersedia
                    </span>
                  @elseif($table->status === 'reserved')
                    @if (isset($reservations[$table->id]))
                      <button onclick="showReservationInfo({{ $table->id }})"
                              class="px-3 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-700 hover:bg-yellow-200 transition cursor-pointer flex items-center gap-1">
                        <svg class="w-3 h-3"
                             fill="currentColor"
                             viewBox="0 0 20 20">
                          <path fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                clip-rule="evenodd" />
                        </svg>
                        Reserved
                      </button>
                    @else
                      <span class="px-3 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-700">Reserved</span>
                    @endif
                  @elseif($table->status === 'occupied')
                    <span class="px-3 py-1 text-xs font-medium rounded-full bg-red-100 text-red-700 flex items-center gap-1 w-fit">
                      <svg class="w-3 h-3"
                           fill="currentColor"
                           viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                              clip-rule="evenodd" />
                      </svg>
                      Booked
                    </span>
                  @else
                    <span class="px-3 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-700">Maintenance</span>
                  @endif
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-slate-800 rounded-lg flex items-center justify-center">
                      <svg class="w-5 h-5 text-white"
                           fill="none"
                           stroke="currentColor"
                           viewBox="0 0 24 24">
                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                      </svg>
                    </div>
                    <div>
                      <div class="text-sm font-medium text-gray-900">{{ $table->table_number }}</div>
                      <div class="text-xs text-gray-500">TBL-{{ $table->id }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-700">
                    {{ $table->area->name ?? '-' }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center gap-1 text-sm text-gray-900">
                    <svg class="w-4 h-4 text-gray-400"
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24">
                      <path stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    {{ $table->capacity }}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-semibold text-gray-900">
                    Rp {{ number_format($table->minimum_charge ?? 0, 0, ',', '.') }}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center gap-2">
                    <button onclick="showQRCode({{ $table->id }})"
                            class="px-3 py-1 text-sm border border-slate-800 text-slate-800 rounded hover:bg-slate-50 transition flex items-center gap-1">
                      <svg class="w-4 h-4"
                           fill="none"
                           stroke="currentColor"
                           viewBox="0 0 24 24">
                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                      </svg>
                      QR Code
                    </button>
                    <button onclick="editTable({{ $table->id }})"
                            class="px-3 py-1 text-sm border border-gray-300 text-gray-700 rounded hover:bg-gray-50 transition flex items-center gap-1">
                      <svg class="w-4 h-4"
                           fill="none"
                           stroke="currentColor"
                           viewBox="0 0 24 24">
                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                      Edit
                    </button>
                    @if ($table->is_active)
                      <button onclick="toggleActive({{ $table->id }}, false)"
                              class="px-3 py-1 text-sm text-gray-700 rounded hover:bg-gray-100 transition">
                        Nonaktifkan
                      </button>
                    @else
                      <button onclick="toggleActive({{ $table->id }}, true)"
                              class="px-3 py-1 text-sm text-green-700 rounded hover:bg-green-50 transition">
                        Aktifkan
                      </button>
                    @endif
                    <button onclick="deleteTable({{ $table->id }})"
                            class="text-red-600 hover:text-red-900">
                      <svg class="w-5 h-5"
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
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Add/Edit Modal -->
  @include('tables._components.add-edit-modal')

  <!-- QR Code Modal -->
  @include('tables._components.qr-code-modal')

  <!-- Reservation Info Modal -->
  @include('tables._components.reservation-info-modal')

  <!-- Delete Modal -->
  @include('tables._components.delete-modal-confirmation')

  @push('scripts')
    <!-- QR Code Library -->
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

    <script>
      const tables = @json($tables);
      const reservations = @json($reservations);
      let currentAreaFilter = 'all';

      function openModal(mode, tableId = null) {
        const modal = document.getElementById('tableModal');
        const form = document.getElementById('tableForm');
        const modalTitle = document.getElementById('modalTitle');
        const formMethod = document.getElementById('formMethod');

        if (mode === 'add') {
          modalTitle.textContent = 'Tambah Meja';
          form.action = '{{ route('admin.tables.store') }}';
          formMethod.value = 'POST';
          form.reset();
          document.getElementById('is_active').checked = true;
          document.getElementById('status').value = 'available';
        } else if (mode === 'edit' && tableId) {
          const table = tables.find(t => t.id === tableId);
          if (table) {
            modalTitle.textContent = 'Edit Meja';
            form.action = `/admin/tables/${tableId}`;
            formMethod.value = 'PUT';

            document.getElementById('area_id').value = table.area_id;
            document.getElementById('table_number').value = table.table_number;
            document.getElementById('capacity').value = table.capacity;
            document.getElementById('minimum_charge').value = table.minimum_charge;
            setMinChargeDisplay(table.minimum_charge);
            document.getElementById('status').value = table.status;
            document.getElementById('notes').value = table.notes || '';
            document.getElementById('is_active').checked = table.is_active;
          }
        }

        modal.classList.remove('hidden');
      }

      function closeModal() {
        document.getElementById('tableModal').classList.add('hidden');
      }

      function formatMinCharge(input) {
        // Strip non-digits
        const raw = input.value.replace(/\D/g, '');
        const numeric = raw === '' ? '' : parseInt(raw, 10);
        input.value = numeric === '' ? '' : new Intl.NumberFormat('id-ID').format(numeric);
        document.getElementById('minimum_charge').value = numeric;
      }

      function setMinChargeDisplay(value) {
        const numeric = parseFloat(value) || 0;
        document.getElementById('minimum_charge_display').value =
          numeric > 0 ? new Intl.NumberFormat('id-ID').format(numeric) : '';
        document.getElementById('minimum_charge').value = numeric > 0 ? numeric : '';
      }

      function editTable(tableId) {
        openModal('edit', tableId);
      }

      function deleteTable(tableId) {
        const form = document.getElementById('deleteForm');
        form.action = `/admin/tables/${tableId}`;
        document.getElementById('deleteModal').classList.remove('hidden');
      }

      function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
      }

      function toggleActive(tableId, isActive) {
        const table = tables.find(t => t.id === tableId);
        if (table) {
          table.is_active = isActive;
          editTable(tableId);
        }
      }

      function filterByArea(areaId) {
        currentAreaFilter = areaId;
        const rows = document.querySelectorAll('.table-row');
        const buttons = document.querySelectorAll('.area-filter-btn');

        // Update button styles
        buttons.forEach(btn => {
          btn.classList.remove('bg-slate-800', 'text-white', 'active');
          btn.classList.add('bg-white', 'border', 'border-gray-300', 'text-gray-700');
        });
        event.target.classList.remove('bg-white', 'border', 'border-gray-300', 'text-gray-700');
        event.target.classList.add('bg-slate-800', 'text-white', 'active');

        // Filter rows
        rows.forEach(row => {
          if (areaId === 'all') {
            row.style.display = '';
          } else {
            row.style.display = row.dataset.areaId == areaId ? '' : 'none';
          }
        });
      }

      // Search functionality
      document.getElementById('searchInput').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('.table-row');

        rows.forEach(row => {
          const text = row.textContent.toLowerCase();
          const matchesSearch = text.includes(searchTerm);
          const matchesArea = currentAreaFilter === 'all' || row.dataset.areaId == currentAreaFilter;
          row.style.display = matchesSearch && matchesArea ? '' : 'none';
        });
      });

      // Close modal on Escape key
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          closeModal();
          closeDeleteModal();
          closeQRModal();
          closeReservationModal();
        }
      });

      // Close modal on outside click
      document.getElementById('tableModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
      });

      document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) closeDeleteModal();
      });

      document.getElementById('qrModal').addEventListener('click', function(e) {
        if (e.target === this) closeQRModal();
      });

      document.getElementById('reservationModal').addEventListener('click', function(e) {
        if (e.target === this) closeReservationModal();
      });

      // QR Code Functions
      let currentQRTable = null;

      function showQRCode(tableId) {
        const table = tables.find(t => t.id === tableId);
        if (!table) return;

        currentQRTable = table;

        // Set table info
        document.getElementById('qrTableName').textContent = table.table_number;
        document.getElementById('qrAreaName').textContent = table.area.name;
        document.getElementById('qrTableInfo').textContent = `Table ID: TBL-${table.id}`;
        document.getElementById('qrCapacity').textContent = `${table.capacity} orang`;
        document.getElementById('qrMinCharge').textContent = `Rp ${new Intl.NumberFormat('id-ID').format(table.minimum_charge)}jt`;
        document.getElementById('qrCodeText').textContent = table.qr_code;

        // Clear previous QR code
        const container = document.getElementById('qrcodeContainer');
        container.innerHTML = '';

        // Generate QR code
        const qrCodeData = JSON.stringify({
          table_id: table.id,
          table_number: table.table_number,
          area: table.area.name,
          qr_code: table.qr_code,
          club: '126 Club'
        });

        new QRCode(container, {
          text: qrCodeData,
          width: 180,
          height: 180,
          colorDark: "#1e293b",
          colorLight: "#ffffff",
          correctLevel: QRCode.CorrectLevel.H
        });

        // Show modal
        document.getElementById('qrModal').classList.remove('hidden');
      }

      function closeQRModal() {
        document.getElementById('qrModal').classList.add('hidden');
        currentQRTable = null;
      }

      // Reservation Info Functions
      function showReservationInfo(tableId) {
        const table = tables.find(t => t.id === tableId);
        const reservation = reservations[tableId];

        if (!table || !reservation) return;

        // Table info
        document.getElementById('resTableNumber').textContent = table.table_number;
        document.getElementById('resAreaName').textContent = table.area.name;
        document.getElementById('resCapacity').textContent = table.capacity + ' orang';

        // Booking info
        document.getElementById('resBookingCode').textContent = 'BKG-' + reservation.booking_code;

        // Status badge
        const statusBadge = document.getElementById('resStatusBadge');
        if (reservation.status === 'confirmed') {
          statusBadge.innerHTML = '<span class="px-3 py-1 text-xs font-medium rounded bg-blue-100 text-blue-700">Confirmed</span>';
        } else if (reservation.status === 'checked_in') {
          statusBadge.innerHTML = '<span class="px-3 py-1 text-xs font-medium rounded bg-gray-100 text-gray-700">Checked-in</span>';
        }

        // Date and time
        const reservationDateRaw = String(reservation.reservation_date || '').trim();
        const resDate = /^\d{4}-\d{2}-\d{2}$/.test(reservationDateRaw) ?
          new Date(`${reservationDateRaw}T00:00:00`) :
          new Date(reservationDateRaw);
        document.getElementById('resDate').textContent = Number.isNaN(resDate.getTime()) ?
          reservationDateRaw :
          resDate.toLocaleDateString('id-ID', {
            timeZone: 'Asia/Jakarta',
            day: '2-digit',
            month: 'long',
            year: 'numeric'
          });
        document.getElementById('resTime').textContent = reservation.reservation_time.substring(0, 5);

        // Customer info
        document.getElementById('resCustomerName').textContent = reservation.customer.name;
        document.getElementById('resCustomerEmail').textContent = reservation.customer.email;
        document.getElementById('resCustomerPhone').textContent = reservation.customer.profile?.phone || '-';

        const memberLevel = reservation.customer.customer_user?.membership_level || 'Regular Customer';
        document.getElementById('resCustomerLevel').textContent = memberLevel;

        // Note
        const noteSection = document.getElementById('resNoteSection');
        if (reservation.note) {
          document.getElementById('resNote').textContent = reservation.note;
          noteSection.classList.remove('hidden');
        } else {
          noteSection.classList.add('hidden');
        }

        // Minimum charge
        document.getElementById('resMinCharge').textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(table.minimum_charge) + 'jt';

        // Edit button
        document.getElementById('resEditButton').href = '/admin/bookings?search=BKG-' + reservation.booking_code;

        // Show modal
        document.getElementById('reservationModal').classList.remove('hidden');
      }

      function closeReservationModal() {
        document.getElementById('reservationModal').classList.add('hidden');
      }
    </script>
    <script src="{{ asset('js/tables-print.js') }}?v={{ filemtime(public_path('js/tables-print.js')) }}"></script>
  @endpush
</x-app-layout>
