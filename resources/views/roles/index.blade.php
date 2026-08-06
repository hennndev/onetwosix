<x-app-layout title="Manajemen Peran & Akses">
  <div class="py-6 px-4 sm:py-8 sm:px-6">
    <!-- Header -->
    <div class="mb-8">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
            <div class="w-10 h-10 bg-purple-600 rounded-lg flex items-center justify-center">
              <svg class="w-6 h-6 text-white"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
              </svg>
            </div>
            Role Management
          </h1>
          <p class="text-gray-600 mt-1">Kelola role dan permission sistem</p>
        </div>
        <button onclick="openModal()"
                class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition flex items-center justify-center gap-2">
          <svg class="w-5 h-5"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 4v16m8-8H4"></path>
          </svg>
          Tambah Role
        </button>
      </div>
    </div>

    <!-- Success/Error Messages -->
    @if (session('success'))
      <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center justify-between">
        <span>{{ session('success') }}</span>
        <button onclick="this.parentElement.remove()"
                class="text-green-700 hover:text-green-900">
          <svg class="w-5 h-5"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>
    @endif

    @if (session('error'))
      <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center justify-between">
        <span>{{ session('error') }}</span>
        <button onclick="this.parentElement.remove()"
                class="text-red-700 hover:text-red-900">
          <svg class="w-5 h-5"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>
    @endif

    @if ($errors->any())
      <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
        <ul class="list-disc list-inside">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
      <!-- Total Roles -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6 text-purple-600"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
            </svg>
          </div>
          <div>
            <p class="text-sm text-gray-600">Total Roles</p>
            <p class="text-3xl font-bold text-purple-600">{{ $totalRoles }}</p>
          </div>
        </div>
      </div>

      <!-- Total Permissions -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6 text-blue-600"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
            </svg>
          </div>
          <div>
            <p class="text-sm text-gray-600">Total Permissions</p>
            <p class="text-3xl font-bold text-blue-600">{{ $totalPermissions }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Search -->
    <div class="mb-6">
      <div class="relative">
        <input type="text"
               id="searchInput"
               placeholder="Cari role..."
               class="w-full px-4 py-2.5 pl-11 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
        <svg class="w-5 h-5 text-gray-400 absolute left-3 top-3"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
        </svg>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role Name</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Default Redirect</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Permissions</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Users</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200"
                 id="tableBody">
            @forelse($roles as $role)
              <tr class="hover:bg-gray-50 transition">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div>
                    <div class="font-medium text-gray-900">{{ $role->name }}</div>
                    <div class="text-xs text-gray-500">ID: ROLE-{{ strtoupper(substr($role->name, 0, 3)) }}</div>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <div class="text-sm text-gray-900">{{ $redirectOptions[$role->default_redirect_route] ?? '-' }}</div>
                </td>
                <td class="px-6 py-4">
                  <div class="text-sm text-gray-900">
                    @if ($role->name === 'Administrator')
                      Full system access
                    @elseif($role->name === 'Manager')
                      Manager with reporting access
                    @elseif($role->name === 'Cashier')
                      POS and booking access only
                    @elseif($role->name === 'Waiter/Server')
                      Limited POS access for serving
                    @else
                      -
                    @endif
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="px-3 py-1 text-sm font-medium text-blue-700 bg-blue-100 rounded-full">
                    {{ $role->permissions_count }} permissions
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="px-3 py-1 text-sm font-medium text-green-700 bg-green-100 rounded-full">
                    {{ $role->users_count }} users
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ $role->created_at->format('d/m/Y') }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <div class="flex items-center gap-2">
                    <button onclick='editRole(@json($role))'
                            class="text-blue-600 hover:text-blue-900">
                      <svg class="w-5 h-5"
                           fill="none"
                           stroke="currentColor"
                           viewBox="0 0 24 24">
                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                      </svg>
                    </button>
                    <button onclick='deleteRole(@json($role))'
                            class="text-red-600 hover:text-red-900">
                      <svg class="w-5 h-5"
                           fill="none"
                           stroke="currentColor"
                           viewBox="0 0 24 24">
                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7"
                    class="px-6 py-8 text-center text-gray-500">
                  Tidak ada data role
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Add/Edit Modal -->
  @include('roles._components.add-edit-modal')

  <!-- Delete Confirmation Modal -->
  @include('roles._components.delete-confirmation-modal')

  @push('scripts')
    <script>
      const csrfMeta = () => document.querySelector('meta[name="csrf-token"]').content;

      // Search functionality
      document.getElementById('searchInput').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('#tableBody tr');
        rows.forEach(row => {
          const text = row.textContent.toLowerCase();
          row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
      });

      // Always inject a fresh CSRF token before submitting
      document.getElementById('roleForm').addEventListener('submit', function() {
        document.getElementById('roleFormToken').value = csrfMeta();
      });

      // Open modal for adding
      function openModal() {
        document.getElementById('modalTitle').textContent = 'Tambah Role';
        document.getElementById('roleForm').reset();
        document.getElementById('formMethod').value = 'POST';
        document.getElementById('roleForm').action = '{{ route('admin.roles.store') }}';
        document.getElementById('default_redirect_route').value = '';
        document.querySelectorAll('input[name="permissions[]"]').forEach(cb => cb.checked = false);
        document.getElementById('roleModal').classList.remove('hidden');
      }

      // Open modal for editing
      function editRole(role) {
        document.getElementById('modalTitle').textContent = 'Edit Role';
        document.getElementById('formMethod').value = 'PUT';
        document.getElementById('roleForm').action = `/admin/roles/${role.id}`;
        document.getElementById('name').value = role.name;
        document.getElementById('default_redirect_route').value = role.default_redirect_route ?? '';

        // Uncheck all permissions first
        document.querySelectorAll('input[name="permissions[]"]').forEach(cb => cb.checked = false);

        // Check permissions that this role has
        if (role.permissions) {
          role.permissions.forEach(permission => {
            const checkbox = document.querySelector(`input[name="permissions[]"][value="${permission.id}"]`);
            if (checkbox) {
              checkbox.checked = true;
            }
          });
        }

        document.getElementById('roleModal').classList.remove('hidden');
      }

      // Close modal
      function closeModal() {
        document.getElementById('roleModal').classList.add('hidden');
      }

      // Open delete modal
      function deleteRole(role) {
        document.getElementById('deleteRoleName').textContent = role.name;
        document.getElementById('deleteForm').action = `/admin/roles/${role.id}`;
        document.getElementById('deleteFormToken').value = csrfMeta();
        document.getElementById('deleteModal').classList.remove('hidden');
      }

      // Close delete modal
      function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
      }

      // Close modals on outside click
      document.getElementById('roleModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
      });

      document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) closeDeleteModal();
      });
    </script>
  @endpush
</x-app-layout>
