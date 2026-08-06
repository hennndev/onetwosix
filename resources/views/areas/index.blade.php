<x-app-layout title="Manajemen Area">
  <div class="p-4 sm:p-6">
    <!-- Success/Error Messages -->
    @if (session('success'))
      <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative"
           role="alert">
        <span class="block sm:inline">{{ session('success') }}</span>
      </div>
    @endif

    @if ($errors->any())
      <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative"
           role="alert">
        <strong class="font-bold">Error!</strong>
        <ul class="mt-2 list-disc list-inside">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="bg-gradient-to-br from-teal-600 to-teal-700 rounded-2xl p-8 mb-6">
      <div class="flex items-center space-x-3 mb-2">
        <div class="bg-white/20 p-3 rounded-lg">
          <svg class="w-8 h-8 text-white"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
        </div>
        <div>
          <h1 class="text-3xl font-bold text-white">Area Management</h1>
          <p class="text-teal-100">Kelola area lokasi (Room, Balcony, Lounge, dll)</p>
        </div>
      </div>
    </div>

    <!-- Stats Cards -->
    @include('areas._components.stats-cards')

    <!-- Main Content -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <!-- Search and Add Button -->
      <div class="p-4 sm:p-6 border-b border-gray-100">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div class="relative flex-1 max-w-md w-full">
            <input type="text"
                   id="searchArea"
                   placeholder="Cari area..."
                   class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent">
            <svg class="w-5 h-5 text-gray-400 absolute left-3 top-3"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <button onclick="openModal('add')"
                  class="bg-teal-600 hover:bg-teal-700 text-white px-6 py-2.5 rounded-lg font-semibold flex items-center justify-center space-x-2 transition">
            <svg class="w-5 h-5"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            <span>Tambah Area</span>
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-slate-50 border-b border-gray-200">
            <tr>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Kode</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Nama Area</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Deskripsi</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Kapasitas</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
              <th class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100"
                 id="areaTableBody">
            @forelse($areas as $area)
              <tr class="hover:bg-gray-50 transition area-row">
                <td class="px-6 py-4">
                  <span class="bg-slate-100 text-slate-700 px-3 py-1 rounded-md font-mono text-sm font-semibold">
                    {{ $area->code }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  <div class="font-semibold text-gray-800">{{ $area->name }}</div>
                  <div class="text-xs text-gray-500">ID: {{ $area->id }}</div>
                </td>
                <td class="px-6 py-4">
                  <p class="text-sm text-gray-600 max-w-xs">{{ $area->description ?? '-' }}</p>
                </td>
                <td class="px-6 py-4">
                  <div class="flex items-center space-x-2">
                    <svg class="w-4 h-4 text-gray-400"
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24">
                      <path stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <span class="text-sm font-medium text-gray-700">{{ $area->capacity ?? 0 }} pax</span>
                  </div>
                </td>
                <td class="px-6 py-4">
                  @if ($area->is_active)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                      <span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-2"></span>
                      Active
                    </span>
                  @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                      <span class="w-1.5 h-1.5 bg-gray-500 rounded-full mr-2"></span>
                      Inactive
                    </span>
                  @endif
                </td>
                <td class="px-6 py-4 text-right">
                  <div class="flex items-center justify-end space-x-2">
                    <button onclick='editArea(@json($area))'
                            class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition"
                            title="Edit">
                      <svg class="w-5 h-5"
                           fill="none"
                           stroke="currentColor"
                           viewBox="0 0 24 24">
                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </button>
                    <button onclick='deleteArea(@json($area))'
                            class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition"
                            title="Delete">
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
            @empty
              <tr>
                <td colspan="6"
                    class="px-6 py-12 text-center">
                  <div class="flex flex-col items-center justify-center">
                    <svg class="w-16 h-16 text-gray-300 mb-4"
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24">
                      <path stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                    <p class="text-gray-500 font-medium">Belum ada area</p>
                    <p class="text-gray-400 text-sm">Klik tombol "Tambah Area" untuk membuat area baru</p>
                  </div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Add/Edit -->
    @include('areas._components.add-edit-modal')
    <!-- Delete Confirmation Modal -->
    @include('areas._components.delete-confirmation-modal')
  </div>

  @push('scripts')
    <script>
      // Search functionality
      document.getElementById('searchArea').addEventListener('keyup', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('.area-row');

        rows.forEach(row => {
          const text = row.textContent.toLowerCase();
          row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
      });

      // Open Add Modal
      function openModal(mode = 'add') {
        document.getElementById('areaModal').classList.remove('hidden');
        if (mode === 'add') {
          document.getElementById('modalTitle').textContent = 'Tambah Area Baru';
          document.getElementById('submitButtonText').textContent = 'Tambah';
          document.getElementById('areaForm').action = '{{ route('admin.areas.store') }}';
          document.getElementById('formMethod').value = 'POST';
          document.getElementById('areaForm').reset();
          document.getElementById('is_active').checked = true;
        }
      }

      // Edit Area
      function editArea(area) {
        document.getElementById('areaModal').classList.remove('hidden');
        document.getElementById('modalTitle').textContent = 'Edit Area';
        document.getElementById('submitButtonText').textContent = 'Update';
        document.getElementById('areaForm').action = `/admin/areas/${area.id}`;
        document.getElementById('formMethod').value = 'PUT';

        // Fill form
        document.getElementById('name').value = area.name;
        document.getElementById('code').value = area.code;
        document.getElementById('capacity').value = area.capacity || '';
        document.getElementById('description').value = area.description || '';
        document.getElementById('sort_order').value = area.sort_order || 0;
        document.getElementById('is_active').checked = area.is_active;
      }

      // Close Modal
      function closeModal() {
        document.getElementById('areaModal').classList.add('hidden');
        document.getElementById('areaForm').reset();
      }

      // Delete Area
      function deleteArea(area) {
        document.getElementById('deleteModal').classList.remove('hidden');
        document.getElementById('deleteAreaName').textContent = area.name;
        document.getElementById('deleteForm').action = `/admin/areas/${area.id}`;
      }

      // Close Delete Modal
      function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
      }

      // Close modals on ESC key
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          closeModal();
          closeDeleteModal();
        }
      });

      // Close modals on outside click
      document.getElementById('areaModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
      });
      document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) closeDeleteModal();
      });
    </script>
  @endpush
</x-app-layout>
