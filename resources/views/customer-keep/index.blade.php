<x-app-layout title="Customer Keep">
  <div class="p-4 sm:p-6"
       x-data="keepManager()"
       x-cloak>

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
          <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
          </svg>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Customer Keep</h1>
          <p class="text-sm text-gray-500">Kelola minuman simpan customer</p>
        </div>
      </div>
      <button @click="openAddModal()"
              :disabled="todayCustomers.length === 0"
              :class="todayCustomers.length === 0 ? 'opacity-40 cursor-not-allowed bg-slate-800' : 'bg-slate-800 hover:bg-slate-700'"
              class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-white text-sm font-medium rounded-lg transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Tambah Keep
      </button>
    </div>

    <!-- Today Booking Info -->
    @if (empty($todayCustomersData))
      <div class="mb-6 px-4 py-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg flex items-center gap-2 text-sm">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        Tidak ada customer dengan booking hari ini. Tambah keep tidak tersedia.
      </div>
    @else
      <div class="mb-6 px-4 py-3 bg-blue-50 border border-blue-200 text-blue-800 rounded-lg flex items-center gap-2 text-sm">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span>
          <strong>{{ count($todayCustomersData) }} customer</strong> dengan booking hari ini
          &mdash; Sesi: <span class="font-semibold">{{ $todayLabel }}</span>
        </span>
      </div>
    @endif

    <!-- Stat Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
        <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-1">Total Aktif</p>
        <p class="text-2xl font-bold text-gray-900">{{ $totalActive }}</p>
      </div>
      <div class="bg-white rounded-xl border {{ now()->dayOfWeek >= 1 && now()->dayOfWeek <= 4 ? 'border-blue-300 ring-2 ring-blue-100' : 'border-gray-200' }} px-5 py-4">
        <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-1">Weekday Aktif</p>
        <p class="text-2xl font-bold text-blue-700">{{ $weekdayCount }}</p>
        @if(now()->dayOfWeek >= 1 && now()->dayOfWeek <= 4)
          <p class="text-xs text-blue-500 mt-0.5">● Hari ini</p>
        @endif
      </div>
      <div class="bg-white rounded-xl border {{ now()->dayOfWeek == 0 || now()->dayOfWeek >= 5 ? 'border-purple-300 ring-2 ring-purple-100' : 'border-gray-200' }} px-5 py-4">
        <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-1">Weekend Aktif</p>
        <p class="text-2xl font-bold text-purple-700">{{ $weekendCount }}</p>
        @if(now()->dayOfWeek == 0 || now()->dayOfWeek >= 5)
          <p class="text-xs text-purple-500 mt-0.5">● Hari ini</p>
        @endif
      </div>
      <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
        <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-1">Sudah Dipakai</p>
        <p class="text-2xl font-bold text-gray-400">{{ $totalUsed }}</p>
      </div>
    </div>

    <!-- Filter Tabs -->
    <div class="flex gap-2 mb-4 flex-wrap">
      @php
        $tabs = [
            'all'     => ['label' => 'Semua', 'count' => $totalItems],
            'active'  => ['label' => 'Aktif', 'count' => $totalActive],
            'used'    => ['label' => 'Digunakan', 'count' => $totalUsed],
            'weekday' => ['label' => 'Weekday', 'count' => $weekdayCount],
            'weekend' => ['label' => 'Weekend/Event', 'count' => $weekendCount],
        ];
      @endphp
      @foreach ($tabs as $key => $info)
        <a href="{{ route('admin.customer-keep.index', ['tab' => $key]) }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition
                  {{ $tab === $key ? 'bg-slate-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
          {{ $info['label'] }}
          <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-xs
                       {{ $tab === $key ? 'bg-white/20 text-white' : 'bg-gray-200 text-gray-600' }}">
            {{ $info['count'] }}
          </span>
        </a>
      @endforeach
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      @if ($keeps->isEmpty())
        <div class="px-6 py-12 text-center text-gray-400">
          <svg class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
          </svg>
          <p class="text-sm">Belum ada data keep</p>
        </div>
      @else
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Customer</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Item</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Tipe</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Qty</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Disimpan</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              @foreach ($keeps as $keep)
                <tr class="hover:bg-gray-50 transition-colors">
                  <td class="px-4 py-3">
                    <div class="font-medium text-gray-900">{{ $keep->customerUser?->profile?->name ?? $keep->customerUser?->user?->name ?? '—' }}</div>
                    <div class="text-xs text-gray-400">{{ $keep->customerUser?->customer_code ?? '' }}</div>
                  </td>
                  <td class="px-4 py-3">
                    <div class="font-medium text-gray-800">{{ $keep->item_name }}</div>
                    @if ($keep->notes)
                      <div class="text-xs text-gray-400 truncate max-w-[180px]">{{ $keep->notes }}</div>
                    @endif
                  </td>
                  <td class="px-4 py-3">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                 {{ $keep->type === 'weekday' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                      {{ $keep->type_label }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-gray-700 font-medium">
                    {{ $keep->quantity }} {{ $keep->unit }}
                  </td>
                  <td class="px-4 py-3 text-gray-500 text-xs">
                    {{ $keep->stored_at?->format('d M Y H:i') ?? '—' }}
                  </td>
                  <td class="px-4 py-3">
                    @if ($keep->status === 'active')
                      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                        ● Aktif
                      </span>
                    @else
                      <div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                          ✓ Digunakan
                        </span>
                        @if ($keep->opened_at)
                          <div class="text-xs text-gray-400 mt-0.5">{{ $keep->opened_at->format('d M Y H:i') }}</div>
                        @endif
                      </div>
                    @endif
                  </td>
                  <td class="px-4 py-3">
                    <div class="flex items-center justify-center gap-1">
                      @if ($keep->status === 'active')
                        <form action="{{ route('admin.customer-keep.mark-used', $keep) }}" method="POST" class="inline">
                          @csrf
                          @method('PATCH')
                          <button type="submit"
                                  title="Tandai sudah digunakan"
                                  onclick="return confirm('Tandai item ini sebagai sudah digunakan?')"
                                  class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-green-600 hover:bg-green-50 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                          </button>
                        </form>
                      @endif
                      <button @click="openEditModal({{ json_encode([
                                  'id' => $keep->id,
                                  'customer_user_id' => $keep->customer_user_id,
                                  'item_name' => $keep->item_name,
                                  'type' => $keep->type,
                                  'quantity' => $keep->quantity,
                                  'unit' => $keep->unit,
                                  'notes' => $keep->notes,
                              ]) }})"
                              title="Edit"
                              class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                      </button>
                      <button @click="confirmDelete({{ $keep->id }}, '{{ addslashes($keep->item_name) }}')"
                              title="Hapus"
                              class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-red-400 hover:bg-red-50 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
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
      @endif
    </div>

    <!-- Add / Edit Modal -->
    <div x-show="showModal"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click.self="closeModal()"
         class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
         style="display: none;">
      <div x-transition:enter="transition ease-out duration-200"
           x-transition:enter-start="opacity-0 scale-95"
           x-transition:enter-end="opacity-100 scale-100"
           x-transition:leave="transition ease-in duration-150"
           x-transition:leave-start="opacity-100 scale-100"
           x-transition:leave-end="opacity-0 scale-95"
           @click.stop
           class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden">

        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
          <h3 class="text-base font-semibold text-gray-900" x-text="modalTitle"></h3>
          <button @click="closeModal()"
                  class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Modal Form -->
        <form :action="formAction" method="POST" class="px-6 py-5 space-y-4">
          @csrf
          <template x-if="isEdit">
            <input type="hidden" name="_method" value="PUT">
          </template>

          <!-- Customer -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Customer</label>
            <select name="customer_user_id"
                    x-model="form.customer_user_id"
                    required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-400">
              <option value="">— Pilih Customer —</option>
              <template x-for="c in (isEdit ? allCustomers : todayCustomers)" :key="c.id">
                <option :value="String(c.id)" x-text="c.name + (c.code ? ' (' + c.code + ')' : '')"></option>
              </template>
            </select>
            <template x-if="!isEdit && todayCustomers.length === 0">
              <p class="mt-1 text-xs text-amber-600">Tidak ada customer dengan booking hari ini.</p>
            </template>
          </div>

          <!-- Item Name -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Item</label>
            <input type="text"
                   name="item_name"
                   x-model="form.item_name"
                   required
                   placeholder="cth. Whisky XO"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-400">
          </div>

          <!-- Type -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tipe Sesi</label>
            <div class="grid grid-cols-2 gap-3">
              <label :class="form.type === 'weekday' ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-200' : 'border-gray-200 hover:border-gray-300'"
                     class="relative flex items-center gap-3 border-2 rounded-xl p-3 cursor-pointer transition">
                <input type="radio" name="type" value="weekday" x-model="form.type" class="sr-only">
                <div class="w-8 h-8 flex-shrink-0 rounded-lg bg-blue-100 flex items-center justify-center text-sm">📅</div>
                <div>
                  <p class="text-sm font-medium text-gray-800">Weekday</p>
                  <p class="text-xs text-gray-400">Senin – Kamis</p>
                </div>
              </label>
              <label :class="form.type === 'weekend_event' ? 'border-purple-500 bg-purple-50 ring-2 ring-purple-200' : 'border-gray-200 hover:border-gray-300'"
                     class="relative flex items-center gap-3 border-2 rounded-xl p-3 cursor-pointer transition">
                <input type="radio" name="type" value="weekend_event" x-model="form.type" class="sr-only">
                <div class="w-8 h-8 flex-shrink-0 rounded-lg bg-purple-100 flex items-center justify-center text-sm">🎉</div>
                <div>
                  <p class="text-sm font-medium text-gray-800">Weekend/Event</p>
                  <p class="text-xs text-gray-400">Jumat – Minggu</p>
                </div>
              </label>
            </div>
          </div>

          <!-- Qty & Unit -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah</label>
              <input type="number"
                     name="quantity"
                     x-model="form.quantity"
                     min="0.1"
                     step="0.1"
                     required
                     class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-400">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Satuan</label>
              <select name="unit"
                      x-model="form.unit"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-400">
                <option value="Botol">Botol</option>
                <option value="Gelas">Gelas</option>
                <option value="Pcs">Pcs</option>
                <option value="ml">ml</option>
                <option value="Kaleng">Kaleng</option>
              </select>
            </div>
          </div>

          <!-- Notes -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Catatan <span class="text-gray-400 font-normal">(opsional)</span></label>
            <textarea name="notes"
                      x-model="form.notes"
                      rows="2"
                      placeholder="cth. Disimpan di lemari A"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-400 resize-none"></textarea>
          </div>

          <!-- Actions -->
          <div class="flex gap-3 pt-1">
            <button type="button"
                    @click="closeModal()"
                    class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
              Batal
            </button>
            <button type="submit"
                    class="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-slate-800 hover:bg-slate-700 rounded-lg transition">
              Simpan
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Modal -->
    <div x-show="showDeleteModal"
         x-transition.opacity
         @click.self="showDeleteModal = false"
         class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
         style="display: none;">
      <div @click.stop class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
          </div>
          <div>
            <h3 class="font-semibold text-gray-900">Hapus Item Keep</h3>
            <p class="text-sm text-gray-500">Item <span class="font-medium text-gray-800" x-text="deleteItemName"></span> akan dihapus.</p>
          </div>
        </div>
        <div class="flex gap-3">
          <button @click="showDeleteModal = false"
                  class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
            Batal
          </button>
          <form :action="deleteAction" method="POST" class="flex-1">
            @csrf
            @method('DELETE')
            <button type="submit"
                    class="w-full px-4 py-2.5 text-sm font-medium text-white bg-red-500 hover:bg-red-600 rounded-lg transition">
              Hapus
            </button>
          </form>
        </div>
      </div>
    </div>

  </div>

  @push('scripts')
    <script>
      function keepManager() {
        const todayType = '{{ $todayType }}';
        const todayCustomers = @json($todayCustomersData);
        const allCustomers = @json($allCustomersData);

        return {
          showModal: false,
          showDeleteModal: false,
          isEdit: false,
          modalTitle: 'Tambah Item Keep',
          formAction: '{{ route('admin.customer-keep.store') }}',
          deleteAction: '',
          deleteItemName: '',
          todayCustomers,
          allCustomers,
          form: {
            customer_user_id: '',
            item_name: '',
            type: todayType,
            quantity: 1,
            unit: 'Botol',
            notes: '',
          },
          openAddModal() {
            this.isEdit = false;
            this.modalTitle = 'Tambah Item Keep';
            this.formAction = '{{ route('admin.customer-keep.store') }}';
            this.form = {
              customer_user_id: '',
              item_name: '',
              type: todayType,
              quantity: 1,
              unit: 'Botol',
              notes: '',
            };
            this.showModal = true;
          },
          openEditModal(keep) {
            this.isEdit = true;
            this.modalTitle = 'Edit Item Keep';
            this.formAction = `/admin/customer-keep/${keep.id}`;
            this.form = {
              customer_user_id: String(keep.customer_user_id),
              item_name: keep.item_name,
              type: keep.type,
              quantity: keep.quantity,
              unit: keep.unit,
              notes: keep.notes || '',
            };
            this.showModal = true;
          },
          closeModal() {
            this.showModal = false;
          },
          confirmDelete(id, name) {
            this.deleteItemName = name;
            this.deleteAction = `/admin/customer-keep/${id}`;
            this.showDeleteModal = true;
          },
        };
      }
    </script>
  @endpush

</x-app-layout>
