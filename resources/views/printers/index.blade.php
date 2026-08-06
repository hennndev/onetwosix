<x-app-layout title="Manajemen Printer">
  <div class="p-4 sm:p-6">
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

    <div class="bg-gradient-to-br from-indigo-600 to-indigo-700 rounded-2xl p-8 mb-6">
      <div class="flex items-center space-x-3 mb-2">
        <div class="bg-white/20 p-3 rounded-lg">
          <svg class="w-8 h-8 text-white"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
          </svg>
        </div>
        <div>
          <h1 class="text-3xl font-bold text-white">Printer Management</h1>
          <p class="text-indigo-100">Kelola printer POS untuk cetak struk</p>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="p-4 sm:p-6 border-b border-gray-100">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <h2 class="text-lg font-semibold text-gray-800">Daftar Printer</h2>
            <p class="text-sm text-gray-500">{{ $printers->count() }} printer terdaftar</p>
          </div>
          <button onclick="openModal('add')"
                  class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-lg font-semibold flex items-center justify-center space-x-2 transition">
            <svg class="w-5 h-5"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            <span>Tambah Printer</span>
          </button>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-slate-50 border-b border-gray-200">
            <tr>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Nama</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Lokasi</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tipe</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Koneksi</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Default</th>
              <th class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            @forelse($printers as $printer)
              <tr class="hover:bg-gray-50 transition">
                <td class="px-6 py-4">
                  <div class="font-semibold text-gray-800">{{ $printer->name }}</div>
                  @if ($printer->isNetwork())
                    <div class="text-xs text-gray-500">{{ $printer->ip }}:{{ $printer->port }}</div>
                  @else
                    <div class="text-xs text-gray-500">{{ $printer->path }}</div>
                  @endif
                </td>
                <td class="px-6 py-4">
                  <span class="text-sm text-gray-600">{{ $printer->location ?? '-' }}</span>
                </td>
                <td class="px-6 py-4">
                  @php
                    $typeColors = [
                        'kitchen' => 'bg-amber-100 text-amber-700',
                        'bar' => 'bg-purple-100 text-purple-700',
                        'cashier' => 'bg-green-100 text-green-700',
                        'checker' => 'bg-blue-100 text-blue-700',
                    ];
                    $typeLabels = ['kitchen' => 'Kitchen', 'bar' => 'Bar', 'cashier' => 'Kasir', 'checker' => 'Checker'];
                  @endphp
                  @if ($printer->printer_type)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $typeColors[$printer->printer_type] ?? 'bg-gray-100 text-gray-500' }}">
                      {{ $typeLabels[$printer->printer_type] ?? $printer->printer_type }}
                    </span>
                  @else
                    <span class="text-xs text-gray-400">-</span>
                  @endif
                </td>
                <td class="px-6 py-4">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    {{ ucfirst($printer->connection_type) }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  @if ($printer->is_active)
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
                <td class="px-6 py-4">
                  @if ($printer->is_default)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">
                      <svg class="w-3 h-3 mr-1"
                           fill="currentColor"
                           viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 1.929a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-1.929a1 1 0 00-1.175 0l-2.8 1.929c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                      </svg>
                      Default
                    </span>
                  @else
                    <button onclick="setDefault({{ $printer->id }})"
                            class="text-xs text-gray-500 hover:text-amber-600 transition">
                      Set as default
                    </button>
                  @endif
                </td>
                <td class="px-6 py-4 text-right">
                  <div class="flex items-center justify-end space-x-2">
                    @if ($printer->connection_type === 'network')
                      <button onclick="pingPrinter({{ $printer->id }})"
                              class="p-2 text-cyan-600 hover:bg-cyan-50 rounded-lg transition"
                              title="Ping / Cek Koneksi">
                        <svg class="w-5 h-5"
                             fill="none"
                             stroke="currentColor"
                             viewBox="0 0 24 24">
                          <path stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                        </svg>
                      </button>
                    @endif
                    <button onclick="testPrint({{ $printer->id }})"
                            class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition"
                            title="Test Print">
                      <svg class="w-5 h-5"
                           fill="none"
                           stroke="currentColor"
                           viewBox="0 0 24 24">
                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                      </svg>
                    </button>
                    <button onclick='editPrinter(@json($printer))'
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
                    <form action="{{ route('admin.printers.destroy', $printer) }}"
                          method="POST"
                          class="inline"
                          onsubmit="return confirm('Hapus printer {{ $printer->name }}?')">
                      @csrf
                      @method('DELETE')
                      <button type="submit"
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
                    </form>
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
                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    <p class="text-gray-500 font-medium">Belum ada printer</p>
                    <p class="text-gray-400 text-sm">Klik tombol "Tambah Printer" untuk menambah printer baru</p>
                  </div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    @include('printers.partials.form')
  </div>

  @push('scripts')
    <script>
      function openModal(mode = 'add') {
        document.getElementById('printerModal').classList.remove('hidden');
        if (mode === 'add') {
          document.getElementById('modalTitle').textContent = 'Tambah Printer Baru';
          document.getElementById('submitButtonText').textContent = 'Tambah';
          document.getElementById('printerForm').action = '{{ route('admin.printers.store') }}';
          document.getElementById('formMethod').value = 'POST';
          document.getElementById('printerForm').reset();
          document.getElementById('is_active').checked = true;
          document.getElementById('connection_type').value = 'network';
          document.getElementById('area_id').value = '';
          toggleConnectionFields();
          resetLogoPreview();
        }
      }

      function editPrinter(printer) {
        document.getElementById('printerModal').classList.remove('hidden');
        document.getElementById('modalTitle').textContent = 'Edit Printer';
        document.getElementById('submitButtonText').textContent = 'Update';
        document.getElementById('printerForm').action = `/admin/printers/${printer.id}`;
        document.getElementById('formMethod').value = 'PUT';

        document.getElementById('name').value = printer.name;
        document.getElementById('location').value = printer.location || '';
        document.getElementById('area_id').value = printer.area_id || '';
        document.getElementById('printer_type').value = printer.printer_type || '';
        document.getElementById('connection_type').value = printer.connection_type;
        document.getElementById('ip').value = printer.ip || '';
        document.getElementById('port').value = printer.port || 9100;
        document.getElementById('path').value = printer.path || '';
        document.getElementById('timeout').value = printer.timeout || 30;
        document.getElementById('header').value = printer.header || '126 Club';
        document.getElementById('footer').value = printer.footer || 'Thank you!';
        document.getElementById('width').value = printer.width || 42;
        document.getElementById('show_qr_code').checked = printer.show_qr_code;
        document.getElementById('is_default').checked = printer.is_default;
        document.getElementById('is_active').checked = printer.is_active;

        // Update logo preview
        if (printer.logo_path) {
          document.getElementById('logoPreview').innerHTML = `<img src="{{ asset('storage') }}/${printer.logo_path}" class="w-full h-full object-contain" alt="Logo">`;
        } else {
          resetLogoPreview();
        }

        toggleConnectionFields();
      }

      function closeModal() {
        document.getElementById('printerModal').classList.add('hidden');
        document.getElementById('printerForm').reset();
        resetLogoPreview();
      }

      function resetLogoPreview() {
        document.getElementById('logoPreview').innerHTML = `<svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>`;
      }

      function toggleConnectionFields() {
        const type = document.getElementById('connection_type').value;
        const networkFields = document.getElementById('networkFields');
        const pathFields = document.getElementById('pathFields');

        if (type === 'network') {
          networkFields.classList.remove('hidden');
          pathFields.classList.add('hidden');
        } else if (type === 'file' || type === 'windows') {
          networkFields.classList.add('hidden');
          pathFields.classList.remove('hidden');
        } else {
          // log / simulasi — no extra fields needed
          networkFields.classList.add('hidden');
          pathFields.classList.add('hidden');
        }
      }

      function setDefault(printerId) {
        fetch(`/admin/printers/${printerId}/set-default`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
          }).then(response => response.json())
          .then(data => {
            if (data.success) {
              location.reload();
            }
          });
      }

      function pingPrinter(printerId) {
        fetch(`/admin/printers/${printerId}/ping`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
          }).then(r => r.json())
          .then(data => {
            alert((data.success ? '✅ ' : '❌ ') + data.message);
          })
          .catch(() => alert('❌ Gagal menghubungi server.'));
      }

      function testPrint(printerId) {
        fetch(`/admin/printers/${printerId}/test`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
          }).then(r => r.json())
          .then(data => {
            alert((data.success ? '✅ ' : '❌ ') + data.message);
          })
          .catch(() => alert('❌ Gagal menghubungi server.'));
      }

      // Logo preview on file select
      document.getElementById('logo').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
          const reader = new FileReader();
          reader.onload = function(e) {
            document.getElementById('logoPreview').innerHTML = `<img src="${e.target.result}" class="w-full h-full object-contain" alt="Logo Preview">`;
          };
          reader.readAsDataURL(file);
        }
      });

      document.getElementById('connection_type').addEventListener('change', toggleConnectionFields);

      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
      });

      document.getElementById('printerModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
      });
    </script>
  @endpush
</x-app-layout>
