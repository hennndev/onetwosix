<x-app-layout title="Manajemen User & Staf">
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
        <div class="w-12 h-12 bg-blue-500 rounded-xl flex items-center justify-center">
          <svg class="w-6 h-6 text-white"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
          </svg>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">User Management</h1>
          <p class="text-sm text-gray-500">Kelola akun pengguna sistem</p>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button data-sync-btn
                onclick="syncFromAccurate()"
                class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors flex items-center justify-center gap-2 whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed">
          <span data-sync-icon
                class="flex items-center">
            <svg class="w-5 h-5"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
          </span>
          <span data-sync-text>Sync dari Accurate</span>
        </button>

        <button onclick="openModal('add')"
                class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition flex items-center gap-2">
          <svg class="w-5 h-5"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 4v16m8-8H4" />
          </svg>
          Tambah User
        </button>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
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
                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
          </div>
          <div>
            <p class="text-sm text-gray-500">Total Users</p>
            <p class="text-2xl font-bold text-blue-500">{{ $totalUsers }}</p>
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
            <p class="text-sm text-gray-500">Active</p>
            <p class="text-2xl font-bold text-green-500">{{ $activeUsers }}</p>
          </div>
        </div>
      </div>
      <div class="bg-white rounded-xl p-6 border border-gray-200">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center">
            <svg class="w-6 h-6 text-red-500"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
            </svg>
          </div>
          <div>
            <p class="text-sm text-gray-500">Inactive</p>
            <p class="text-2xl font-bold text-red-500">{{ $inactiveUsers }}</p>
          </div>
        </div>
      </div>
    </div>

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
                 placeholder="Cari username, nama, email, atau role..."
                 class="pl-10 pr-4 py-2 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Lengkap</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Area</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status Accurate</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Login</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200"
                 id="userTableBody">
            @foreach ($users as $user)
              <tr class="hover:bg-gray-50 transition">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div>
                    <div class="text-sm font-medium text-gray-900">{{ strtolower(str_replace(' ', '', $user->name)) }}</div>
                    <div class="text-xs text-gray-500">ID: USER-{{ $user->id }}</div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">{{ $user->name }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">{{ $user->email }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">{{ $user->profile->phone ?? '-' }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  @if ($user->roles->first())
                    @php
                      $roleName = $user->roles->first()->name;
                      $roleColor = match ($roleName) {
                          'Administrator' => 'bg-purple-100 text-purple-700',
                          'Manager' => 'bg-blue-100 text-blue-700',
                          'Cashier' => 'bg-pink-100 text-pink-700',
                          'Waiter/Server' => 'bg-indigo-100 text-indigo-700',
                          default => 'bg-gray-100 text-gray-700',
                      };
                    @endphp
                    <span class="px-2 py-1 text-xs font-medium rounded-full {{ $roleColor }}">
                      {{ $roleName }}
                    </span>
                  @else
                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-700">No Role</span>
                  @endif
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">
                    @if ($user->internalUser && $user->internalUser->area)
                      {{ $user->internalUser->area->name }}
                    @elseif ($user->internalUser && $user->internalUser->area_id === null)
                      <span class="px-2 py-1 text-xs font-medium rounded-full bg-slate-100 text-slate-700">Semua Area</span>
                    @else
                      -
                    @endif
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  @if ($user->internalUser->is_active)
                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-700">Active</span>
                  @else
                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-700">Inactive</span>
                  @endif
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  @if ($user->internalUser && $user->internalUser->accurate_id)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">
                      <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                      </svg>
                      Synced (ID: {{ $user->internalUser->accurate_id }})
                    </span>
                  @else
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 border border-amber-200">
                      <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                      </svg>
                      Belum Sync
                    </span>
                  @endif
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">{{ $user->updated_at ? $user->updated_at->format('d/m/Y, H:i') : '-' }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <div class="flex items-center gap-2">
                    <button onclick="editUser({{ $user->id }})"
                            title="Edit User"
                            class="text-blue-600 hover:text-blue-900">
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
                    @if (! $user->internalUser?->accurate_id)
                      <form action="{{ route('admin.users.sync-accurate-single', $user->id) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit"
                                class="px-2.5 py-1 text-xs font-semibold bg-indigo-50 border border-indigo-200 text-indigo-700 rounded hover:bg-indigo-100 transition">
                          Sync Accurate
                        </button>
                      </form>
                    @endif
                    <button onclick="deleteUser({{ $user->id }})"
                            title="Hapus User"
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
  @include('users._components.add-edit-modal')

  <div id="syncResultModal"
       class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
      <div class="p-6">
        <div id="syncResultIcon"
             class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4">
        </div>
        <h3 id="syncResultTitle"
            class="text-lg font-bold text-gray-900 text-center mb-1"></h3>
        <p id="syncResultMessage"
           class="text-sm text-gray-500 text-center mb-4"></p>
        <pre id="syncResultOutput"
             class="hidden bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs text-gray-600 max-h-40 overflow-y-auto whitespace-pre-wrap mb-4"></pre>
        <button onclick="document.getElementById('syncResultModal').classList.add('hidden'); window.location.reload();"
                class="w-full px-4 py-2.5 bg-slate-800 text-white rounded-xl hover:bg-slate-900 font-semibold transition">
          Tutup &amp; Refresh
        </button>
      </div>
    </div>
  </div>

  <!-- Delete Modal -->
  @include('users._components.delete-confirmation-modal')

  <!-- Force Delete Modal (Accurate Error) -->
  <div id="forceDeleteModal"
       class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl max-w-md w-full">
      <div class="p-6">
        <div class="flex items-center justify-center w-12 h-12 mx-auto bg-yellow-100 rounded-full mb-4">
          <svg class="w-6 h-6 text-yellow-600"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        <h3 class="text-lg font-bold text-gray-900 text-center mb-2">Terjadi Kesalahan Sinkronisasi Accurate</h3>
        <p id="forceDeleteMessage" class="text-sm text-gray-500 text-center mb-6">Karyawan sudah dihapus atau tidak ditemukan dari sisi Accurate. Apakah Anda ingin tetap melanjutkan penghapusan user ini dari sistem lokal?</p>
        <form id="forceDeleteForm"
              method="POST"
              class="flex gap-3">
          @csrf
          @method('DELETE')
          <input type="hidden" name="force" value="1">
          <button type="button"
                  onclick="document.getElementById('forceDeleteModal').classList.add('hidden')"
                  class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
            Batal
          </button>
          <button type="submit"
                  class="flex-1 px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition">
            Tetap Hapus
          </button>
        </form>
      </div>
    </div>
  </div>

  @push('scripts')
    <script>
      const users = @json($users);
      const roles = @json($roles);
      const userSyncUrl = "{{ route('admin.users.sync-accurate') }}";
      const SYNC_ICON_HTML = document.querySelector('[data-sync-icon]').innerHTML;

      function handleRoleAreaVisibility() {
        const roleId = document.getElementById('role_id').value;
        const allAreaOption = document.getElementById('allAreaOption');
        const selectedRole = roles.find(r => r.id == roleId);

        const isAdmin = selectedRole && (
          selectedRole.name.toLowerCase().includes('admin') ||
          selectedRole.name.toLowerCase().includes('super') ||
          selectedRole.name.toLowerCase().includes('manager')
        );

        if (isAdmin) {
          allAreaOption.classList.remove('hidden');
        } else {
          allAreaOption.classList.add('hidden');
          if (document.getElementById('area_id').value === 'all') {
            document.getElementById('area_id').value = '';
          }
        }
      }

      // Check if session has accurate_error_id to show force delete modal
      @if(session('accurate_error_id'))
        document.addEventListener('DOMContentLoaded', function() {
            const userId = {{ session('accurate_error_id') }};
            const errorMessage = {!! json_encode(session('accurate_error_message', 'Karyawan sudah dihapus atau tidak ditemukan dari sisi Accurate.')) !!};
            
            const form = document.getElementById('forceDeleteForm');
            form.action = `/admin/users/${userId}`;
            
            document.getElementById('forceDeleteMessage').innerText = errorMessage + '\n\nApakah Anda ingin tetap melanjutkan penghapusan user ini dari sistem lokal?';
            
            document.getElementById('forceDeleteModal').classList.remove('hidden');
        });
      @endif

      function syncFromAccurate() {
        const btn = document.querySelector('[data-sync-btn]');
        const icon = document.querySelector('[data-sync-icon]');
        const text = document.querySelector('[data-sync-text]');

        btn.disabled = true;
        icon.innerHTML = `<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>`;
        text.textContent = 'Syncing...';

        fetch(userSyncUrl, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
          })
          .then(async res => {
            const data = await res.json();

            if (!res.ok) {
              throw new Error(data.message || 'Sync gagal diproses.');
            }

            return data;
          })
          .then(data => {
            btn.disabled = false;
            icon.innerHTML = SYNC_ICON_HTML;
            text.textContent = 'Sync dari Accurate';
            showSyncResult(data.success, data.message, data.output ?? null);
          })
          .catch(err => {
            btn.disabled = false;
            icon.innerHTML = SYNC_ICON_HTML;
            text.textContent = 'Sync dari Accurate';
            showSyncResult(false, 'Koneksi gagal: ' + err.message, null);
          });
      }

      function showSyncResult(success, message, output) {
        const modal = document.getElementById('syncResultModal');
        const icon = document.getElementById('syncResultIcon');
        const title = document.getElementById('syncResultTitle');
        const msg = document.getElementById('syncResultMessage');
        const pre = document.getElementById('syncResultOutput');

        if (success) {
          icon.className = 'w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4 bg-green-100';
          icon.innerHTML = `<svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>`;
          title.textContent = 'Sync Berhasil!';
        } else {
          icon.className = 'w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4 bg-red-100';
          icon.innerHTML = `<svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>`;
          title.textContent = 'Sync Gagal';
        }

        msg.textContent = message;

        if (output && output.trim()) {
          pre.textContent = output.trim();
          pre.classList.remove('hidden');
        } else {
          pre.classList.add('hidden');
        }

        modal.classList.remove('hidden');
      }

      function openModal(mode, userId = null) {
        const modal = document.getElementById('userModal');
        const form = document.getElementById('userForm');
        const modalTitle = document.getElementById('modalTitle');
        const formMethod = document.getElementById('formMethod');
        const passwordRequired = document.getElementById('passwordRequired');
        const passwordHint = document.getElementById('passwordHint');
        const passwordInput = document.getElementById('password');

        if (mode === 'add') {
          modalTitle.textContent = 'Tambah User';
          form.action = '{{ route('admin.users.store') }}';
          formMethod.value = 'POST';
          form.reset();
          document.getElementById('area_id').value = '';
          document.getElementById('is_active').checked = true;
          passwordRequired.style.display = 'inline';
          passwordHint.style.display = 'none';
          passwordInput.required = true;
          handleRoleAreaVisibility();
        } else if (mode === 'edit' && userId) {
          const user = users.find(u => u.id === userId);
          if (user) {
            modalTitle.textContent = 'Edit User';
            form.action = `/admin/users/${userId}`;
            formMethod.value = 'PUT';

            document.getElementById('name').value = user.name;
            document.getElementById('email').value = user.email;
            document.getElementById('phone').value = user.profile?.phone || '';
            document.getElementById('birth_date').value = user.profile?.birth_date || '';
            document.getElementById('address').value = user.profile?.address || '';
            document.getElementById('role_id').value = user.roles[0]?.id || '';

            handleRoleAreaVisibility();

            if (user.internal_user?.area_id) {
              document.getElementById('area_id').value = user.internal_user.area_id;
            } else {
              document.getElementById('area_id').value = 'all';
            }

            document.getElementById('is_active').checked = user.internal_user?.is_active || false;

            passwordRequired.style.display = 'none';
            passwordHint.style.display = 'block';
            passwordInput.required = false;
            passwordInput.value = '';
          }
        }

        modal.classList.remove('hidden');
      }

      function closeModal() {
        document.getElementById('userModal').classList.add('hidden');
      }

      function editUser(userId) {
        openModal('edit', userId);
      }

      function deleteUser(userId) {
        const form = document.getElementById('deleteForm');
        form.action = `/admin/users/${userId}`;
        document.getElementById('deleteModal').classList.remove('hidden');
      }

      function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
      }

      // Search functionality
      document.getElementById('searchInput').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const tableBody = document.getElementById('userTableBody');
        const rows = tableBody.getElementsByTagName('tr');

        Array.from(rows).forEach(row => {
          const text = row.textContent.toLowerCase();
          row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
      });

      // Close modal on Escape key
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          closeModal();
          closeDeleteModal();
          const forceModal = document.getElementById('forceDeleteModal');
          if (forceModal) forceModal.classList.add('hidden');
        }
      });

      // Close modal on outside click
      document.getElementById('userModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
      });

      document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) closeDeleteModal();
      });
      
      const forceModal = document.getElementById('forceDeleteModal');
      if (forceModal) {
          forceModal.addEventListener('click', function(e) {
              if (e.target === this) this.classList.add('hidden');
          });
      }
    </script>
  @endpush
</x-app-layout>
