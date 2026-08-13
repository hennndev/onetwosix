<x-app-layout title="Manajemen Customer">
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
                  d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
          </svg>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Customer</h1>
          <p class="text-sm text-gray-500">Premium Management</p>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    @include('customers._components.tabs')

    <!-- Customers Tab -->
    <div id="customersContent">
      <!-- Search & Add -->
      <div class="mb-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <form method="GET"
              action="{{ route('admin.customers.index') }}"
              class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
          <input type="hidden"
                 name="tab"
                 value="customers">
          <div class="relative flex-1">
            <svg class="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 transform text-gray-400"
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
                   name="search"
                   value="{{ request('search') }}"
                   placeholder="Cari customer (nama, telepon, email)..."
                   class="w-full rounded-lg border border-gray-300 py-2 pl-10 pr-4 focus:border-transparent focus:ring-2 focus:ring-slate-500">
          </div>

          <div class="flex items-center gap-2">
            <label for="perPage"
                   class="text-sm font-medium text-gray-700 whitespace-nowrap">Rows per page</label>
            <select id="perPage"
                    name="per_page"
                    onchange="this.form.submit()"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-transparent focus:ring-2 focus:ring-slate-500">
              @foreach ($perPageOptions as $option)
                <option value="{{ $option }}"
                        @selected($perPage === $option)>{{ $option }}</option>
              @endforeach
            </select>

            <button type="submit"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
              Terapkan
            </button>
          </div>
        </form>

        <div class="flex justify-end">
          <button onclick="openModal('add')"
                  class="flex items-center gap-2 whitespace-nowrap rounded-lg bg-slate-800 px-4 py-2 text-white transition hover:bg-slate-900">
            <svg class="h-5 w-5"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 4v16m8-8H4" />
            </svg>
            Tambah Customer
          </button>
        </div>
      </div>

      <!-- Table Card -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-slate-800 text-white">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Nama</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Kontak</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Visits</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Total Spent</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Status Accurate</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Last Visit</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Action</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200"
                   id="customerTableBody">
              @forelse ($customers as $customer)
                <tr class="hover:bg-gray-50 transition">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div>
                      <div class="text-sm font-medium text-gray-900">{{ $customer->user->name }}</div>
                      <div class="text-xs text-gray-500">{{ $customer->customer_code ?? 'Belum ada kode' }}</div>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm">
                      <div class="flex items-center gap-1 text-gray-900">
                        <svg class="w-4 h-4 text-gray-400"
                             fill="none"
                             stroke="currentColor"
                             viewBox="0 0 24 24">
                          <path stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        {{ $customer->profile->phone ?? '-' }}
                      </div>
                      <div class="flex items-center gap-1 text-gray-500">
                        <svg class="w-4 h-4 text-gray-400"
                             fill="none"
                             stroke="currentColor"
                             viewBox="0 0 24 24">
                          <path stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        {{ $customer->user->email }}
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-center">
                    <div class="text-lg font-bold text-gray-900">{{ number_format($customer->total_visits, 0, ',', '.') }}</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-bold text-green-600">Rp {{ number_format((float) $customer->lifetime_spending, 0, ',', '.') }}</div>
                    <div class="text-xs text-gray-500">{{ number_format(((float) $customer->lifetime_spending) / 1000000, 1) }}jt</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    @if ($customer->customer_code && $customer->accurate_id)
                      <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200" title="Accurate ID: {{ $customer->accurate_id }}">
                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Synced ({{ $customer->customer_code }})
                      </span>
                    @else
                      <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                          <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                          Belum Sync
                        </span>
                        <form action="{{ route('admin.customers.sync-accurate', $customer->id) }}" method="POST" class="inline">
                          @csrf
                          <button type="submit" class="px-2.5 py-1 text-xs font-semibold bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                            Sync
                          </button>
                        </form>
                      </div>
                    @endif
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">{{ $customer->updated_at->format('d M Y') }}</div>
                    <div class="text-xs text-gray-500">{{ $customer->updated_at->format('H:i') }}</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center gap-2">
                      <button onclick="editCustomer({{ $customer->id }})"
                              class="px-3 py-1 text-sm border border-gray-300 text-gray-700 rounded hover:bg-gray-50 transition">
                        Edit
                      </button>
                      @if (! $customer->customer_code || ! $customer->accurate_id)
                        <form action="{{ route('admin.customers.sync-accurate', $customer->id) }}" method="POST" class="inline">
                          @csrf
                          <button type="submit"
                                  class="px-3 py-1 text-sm bg-indigo-50 border border-indigo-200 text-indigo-700 rounded hover:bg-indigo-100 transition">
                            Sync Accurate
                          </button>
                        </form>
                      @endif
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7"
                      class="px-6 py-8 text-center text-sm text-gray-500">Tidak ada data customer untuk ditampilkan.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="flex flex-col gap-3 border-t border-gray-200 bg-gray-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
          <p class="text-sm text-gray-600">
            Menampilkan
            <span class="font-semibold text-gray-800">{{ $customers->firstItem() ?? 0 }}</span>
            -
            <span class="font-semibold text-gray-800">{{ $customers->lastItem() ?? 0 }}</span>
            dari
            <span class="font-semibold text-gray-800">{{ number_format($customers->total(), 0, ',', '.') }}</span>
            customer
          </p>

          <div class="light-pagination">
            {{ $customers->onEachSide(1)->links() }}
          </div>
        </div>
      </div>
    </div>

    <!-- Leaderboard Tab -->
    @include('customers._components.leaderboard')
  </div>

  <!-- Add/Edit Modal -->
  @Include('customers._components.add-edit-modal')

  @push('scripts')
    <script>
      const customers = @json($customers->items());
      const initialTab = @json(request('tab') === 'leaderboard' ? 'leaderboard' : 'customers');

      function showTab(tab) {
        const customersTab = document.getElementById('customersTab');
        const leaderboardTab = document.getElementById('leaderboardTab');
        const customersContent = document.getElementById('customersContent');
        const leaderboardContent = document.getElementById('leaderboardContent');

        if (tab === 'customers') {
          customersTab.classList.remove('bg-gray-100', 'text-gray-700');
          customersTab.classList.add('bg-slate-800', 'text-white');
          leaderboardTab.classList.remove('bg-slate-800', 'text-white');
          leaderboardTab.classList.add('bg-gray-100', 'text-gray-700');
          customersContent.classList.remove('hidden');
          leaderboardContent.classList.add('hidden');
        } else {
          leaderboardTab.classList.remove('bg-gray-100', 'text-gray-700');
          leaderboardTab.classList.add('bg-slate-800', 'text-white');
          customersTab.classList.remove('bg-slate-800', 'text-white');
          customersTab.classList.add('bg-gray-100', 'text-gray-700');
          leaderboardContent.classList.remove('hidden');
          customersContent.classList.add('hidden');
        }
      }

      function resetCustomerFormButtons() {
        const submitBtn = document.getElementById('submitCustomerBtn');
        const cancelBtn = document.getElementById('cancelCustomerBtn');
        const spinner = document.getElementById('customerSubmitSpinner');
        const text = document.getElementById('customerSubmitText');

        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.classList.remove('opacity-75', 'cursor-not-allowed');
        }
        if (cancelBtn) {
          cancelBtn.disabled = false;
          cancelBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        }
        if (spinner) {
          spinner.classList.add('hidden');
        }
        if (text) {
          text.textContent = 'Simpan';
        }
      }

      function openModal(mode, customerId = null) {
        const modal = document.getElementById('customerModal');
        const form = document.getElementById('customerForm');
        const modalTitle = document.getElementById('modalTitle');
        const formMethod = document.getElementById('formMethod');
        const passwordRequired = document.getElementById('passwordRequired');
        const passwordHint = document.getElementById('passwordHint');
        const passwordInput = document.getElementById('password');
        const customerDataFields = document.getElementById('customerDataFields');

        resetCustomerFormButtons();

        if (mode === 'add') {
          modalTitle.textContent = 'Tambah Customer';
          form.action = '{{ route('admin.customers.store') }}';
          formMethod.value = 'POST';
          form.reset();
          if (passwordRequired) passwordRequired.style.display = 'none';
          if (passwordHint) {
            passwordHint.style.display = 'inline';
            passwordHint.textContent = '(opsional, min. 8 karakter)';
          }
          if (passwordInput) {
            passwordInput.required = false;
            passwordInput.removeAttribute('minlength');
          }
          customerDataFields.classList.add('hidden');
        } else if (mode === 'edit' && customerId) {
          const customer = customers.find(c => c.id === customerId);
          if (customer) {
            modalTitle.textContent = 'Edit Customer';
            form.action = `/admin/customers/${customerId}`;
            formMethod.value = 'PUT';

            document.getElementById('name').value = customer.user.name;
            document.getElementById('email').value = customer.user.email;
            document.getElementById('phone').value = customer.profile?.phone || '';
            document.getElementById('birth_date').value = customer.profile?.birth_date || '';
            document.getElementById('address').value = customer.profile?.address || '';
            document.getElementById('total_visits').value = customer.total_visits;
            document.getElementById('lifetime_spending').value = customer.lifetime_spending;

            if (passwordRequired) passwordRequired.style.display = 'none';
            if (passwordHint) {
              passwordHint.style.display = 'inline';
              passwordHint.textContent = '(kosongkan jika tidak diubah)';
            }
            if (passwordInput) {
              passwordInput.required = false;
              passwordInput.removeAttribute('minlength');
              passwordInput.value = '';
            }
            customerDataFields.classList.remove('hidden');
          }
        }

        modal.classList.remove('hidden');
      }

      function closeModal() {
        resetCustomerFormButtons();
        document.getElementById('customerModal').classList.add('hidden');
      }

      function editCustomer(customerId) {
        openModal('edit', customerId);
      }

      // Handle form submission to prevent double-click and show loading spinner
      document.getElementById('customerForm').addEventListener('submit', function(e) {
        if (!this.checkValidity()) {
          return;
        }

        const submitBtn = document.getElementById('submitCustomerBtn');
        const cancelBtn = document.getElementById('cancelCustomerBtn');
        const spinner = document.getElementById('customerSubmitSpinner');
        const text = document.getElementById('customerSubmitText');

        if (spinner) {
          spinner.classList.remove('hidden');
        }
        if (text) {
          text.textContent = 'Menyimpan...';
        }

        setTimeout(() => {
          if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
          }
          if (cancelBtn) {
            cancelBtn.disabled = true;
            cancelBtn.classList.add('opacity-50', 'cursor-not-allowed');
          }
        }, 0);
      });

      // Close modal on Escape key
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          closeModal();
        }
      });

      // Close modal on outside click
      document.getElementById('customerModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
      });

      showTab(initialTab);

      @if ($errors->any())
        openModal('{{ old('_method') === 'PUT' ? 'edit' : 'add' }}');
      @endif
    </script>

    <style>
      .light-pagination nav>div:first-child {
        display: none;
      }

      .light-pagination nav>div:last-child {
        display: flex;
        align-items: center;
        gap: 0.375rem;
      }

      .light-pagination span[aria-current="page"] span,
      .light-pagination a,
      .light-pagination span[aria-disabled="true"] span {
        border-radius: 0.5rem;
        border: 1px solid rgb(209 213 219);
        background-color: white;
        color: rgb(55 65 81);
        font-size: 0.875rem;
        line-height: 1.25rem;
        padding: 0.5rem 0.75rem;
        text-decoration: none;
      }

      .light-pagination a:hover {
        background-color: rgb(249 250 251);
      }

      .light-pagination span[aria-current="page"] span {
        border-color: rgb(15 23 42);
        background-color: rgb(15 23 42);
        color: white;
      }

      .light-pagination span[aria-disabled="true"] span {
        color: rgb(156 163 175);
        background-color: rgb(249 250 251);
      }
    </style>
  @endpush
</x-app-layout>
