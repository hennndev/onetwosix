@php
  $availableTablesCount = $tables->where('is_active', true)->count();
@endphp

<div id="bookingModal"
     class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
     x-data="bookingModal()">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">

    <!-- Modal Header -->
    <div class="flex items-start justify-between px-6 py-5 border-b border-gray-100">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 bg-slate-800 rounded-lg flex items-center justify-center">
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
          <h3 id="modalTitle"
              class="text-lg font-bold text-gray-900">Booking Baru</h3>
          <p class="text-xs text-gray-400 mt-0.5">Pilih kategori, meja, dan lengkapi data customer untuk membuat reservasi baru</p>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <span class="text-xs text-gray-500 font-medium">{{ $availableTablesCount }} meja tersedia</span>
        <button type="button"
                onclick="closeModal()"
                class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition">
          <svg class="w-4 h-4"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>

    <form id="bookingForm"
          method="POST"
          action="{{ route('admin.bookings.store') }}"
          data-store-action="{{ route('admin.bookings.store') }}"
          class="px-6 py-5 space-y-5">
      @csrf
      <input type="hidden"
             name="_method"
             value="POST"
             id="formMethod">
      <input type="hidden"
             name="table_id"
             id="table_id">
      <input type="hidden"
             name="status"
             id="status"
             value="pending">
      <input type="hidden"
             name="down_payment_amount"
             id="down_payment_amount"
             value="0">

      <!-- Meja yang Dipilih -->
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">
          Meja yang Dipilih <span class="text-red-500">*</span>
          <span class="ml-2 text-xs font-normal px-2 py-0.5 bg-blue-100 text-blue-600 rounded-full">Quick Booking</span>
        </label>

        <!-- Picker when nothing selected -->
        <div x-show="!selectedTable"
             class="grid grid-cols-2 gap-2 max-h-44 overflow-y-auto pr-1">
          @foreach ($tables as $table)
            @php
              $hasActiveBooking = collect($activeBookingsByTable ?? collect())->has($table->id);
              $hasActiveSession = collect($activeSessions ?? collect())->contains(fn($session) => (int) $session->table_id === (int) $table->id && $session->status === 'active');
              $isOccupied = $hasActiveBooking || $hasActiveSession;
            @endphp
            <button type="button"
                    @if (!$isOccupied) @click="selectTable({{ json_encode(['id' => $table->id, 'table_number' => $table->table_number, 'capacity' => $table->capacity, 'minimum_charge' => $table->minimum_charge, 'area_name' => $table->area->name ?? '']) }})" @endif
                    class="text-left px-3 py-2.5 rounded-lg border text-xs transition
                           {{ $isOccupied ? 'border-gray-200 bg-gray-50 opacity-50 cursor-not-allowed' : 'border-gray-200 hover:border-blue-400 hover:bg-blue-50 cursor-pointer' }}">
              <div class="flex items-center justify-between mb-0.5">
                <span class="font-semibold text-gray-800 truncate pr-1">{{ $table->table_number }}</span>
                @if ($isOccupied)
                  <span class="text-red-400 shrink-0">•Busy</span>
                @else
                  <span class="text-green-500 shrink-0">•Free</span>
                @endif
              </div>
              <span class="text-gray-400">{{ $table->area->name ?? '' }} · {{ $table->capacity }} pax</span>
            </button>
          @endforeach
        </div>

        <!-- Selected table card -->
        <div x-show="selectedTable"
             x-cloak
             class="rounded-xl border-2 border-blue-400 bg-blue-50 p-4">
          <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-2">
              <svg class="w-4 h-4 text-blue-600"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M3 10h18M3 14h18M10 4v16M14 4v16" />
              </svg>
              <span class="font-bold text-blue-800"
                    x-text="selectedTable?.table_number"></span>
            </div>
            <button type="button"
                    @click="clearTable()"
                    class="text-xs text-blue-500 hover:text-blue-700 underline">Ganti</button>
          </div>
          <div class="grid grid-cols-2 gap-3 text-xs">
            <div>
              <span class="text-blue-500">Kapasitas:</span>
              <span class="text-blue-800 font-semibold ml-1"
                    x-text="(selectedTable?.capacity ?? '') + ' orang'"></span>
            </div>
            <div>
              <span class="text-blue-500">Min Charge:</span>
              <span class="text-blue-800 font-semibold ml-1"
                    x-text="selectedTable?.minimum_charge ? 'Rp ' + Number(selectedTable.minimum_charge).toLocaleString('id-ID') : '-'"></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Customer -->
      <div>
        <label for="booking_name"
               class="block text-sm font-semibold text-gray-700 mb-2">
          Nama Booking <span class="text-gray-400 font-normal text-xs">(opsional — a.n. siapa reservasi ini)</span>
        </label>
        <input type="text"
               name="booking_name"
               id="booking_name"
               placeholder="Contoh: a.n. Budi Santoso"
               class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-400 bg-white">
      </div>

      <!-- Customer Mode Select -->
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">
          Pilih / Tambah Customer <span class="text-red-500">*</span>
        </label>
        <input type="hidden"
               name="customer_mode"
               :value="customerMode">

        <div class="flex items-center p-1 bg-gray-100 rounded-xl mb-3 border border-gray-200">
          <button type="button"
                  @click="setCustomerMode('existing')"
                  :class="customerMode === 'existing' ? 'bg-white text-slate-800 shadow-xs font-bold' : 'text-gray-500 hover:text-gray-700 font-medium'"
                  class="flex-1 py-1.5 text-xs rounded-lg transition text-center">
            Pilih Customer Terdaftar
          </button>
          <button type="button"
                  @click="setCustomerMode('new')"
                  :class="customerMode === 'new' ? 'bg-slate-800 text-white shadow-xs font-bold' : 'text-gray-500 hover:text-gray-700 font-medium'"
                  class="flex-1 py-1.5 text-xs rounded-lg transition text-center">
            + Customer Baru
          </button>
        </div>

        <!-- Mode Existing (Searchable Dropdown) -->
        <div x-show="customerMode === 'existing'"
             class="relative"
             @click.outside="isCustomerDropdownOpen = false">
          <input type="hidden"
                 name="customer_id"
                 id="customer_id"
                 :value="selectedCustomerId"
                 :required="customerMode === 'existing'">

          <!-- Search Input Box -->
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
              <svg class="w-4 h-4"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
            <input type="text"
                   x-model="customerSearchQuery"
                   @focus="isCustomerDropdownOpen = true"
                   @input="isCustomerDropdownOpen = true"
                   placeholder="Cari customer (Nama, Kode, No HP)..."
                   class="w-full pl-9 pr-8 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-400 bg-white">

            <!-- Clear button -->
            <button type="button"
                    x-show="selectedCustomerId || customerSearchQuery"
                    @click="clearCustomerSelection()"
                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 transition">
              <svg class="w-4 h-4"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Dropdown Suggestions List -->
          <div x-show="isCustomerDropdownOpen"
               x-cloak
               class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-56 overflow-y-auto divide-y divide-gray-100">
            <template x-for="c in filteredCustomers"
                      :key="c.id">
              <div @click="chooseCustomer(c)"
                   class="px-3.5 py-2.5 hover:bg-slate-50 cursor-pointer transition flex items-center justify-between text-xs">
                <div>
                  <div class="font-semibold text-gray-800 flex items-center gap-1.5">
                    <span x-text="c.name"></span>
                    <span x-show="c.customer_code"
                          class="text-slate-500 font-mono text-[11px]"
                          x-text="'[' + c.customer_code + ']'"></span>
                  </div>
                  <div class="text-gray-400 mt-0.5"
                       x-text="c.phone ? c.phone : 'Tanpa No. HP'"></div>
                </div>
                <div>
                  <span x-show="c.has_active_session"
                        class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full text-[10px] font-medium shrink-0">Sedang check-in</span>
                </div>
              </div>
            </template>

            <div x-show="filteredCustomers.length === 0"
                 class="px-4 py-3 text-xs text-gray-400 text-center">
              Customer tidak ditemukan
            </div>
          </div>
        </div>

        <!-- Mode New -->
        <div x-show="customerMode === 'new'"
             x-cloak
             class="space-y-3">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Nama Customer Baru <span class="text-red-500">*</span></label>
            <input type="text"
                   name="new_customer_name"
                   id="new_customer_name"
                   :required="customerMode === 'new'"
                   placeholder="Contoh: Budi Santoso"
                   class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-400 bg-white">
          </div>
        </div>
      </div>

      <!-- Phone + Email -->
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">No. Telepon <span class="text-red-500">*</span></label>
          <input type="text"
                 name="phone"
                 id="phone"
                 x-model="phoneValue"
                 placeholder="08xx-xxxx-xxxx"
                 class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-400 bg-white">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Email (Opsional)</label>
          <input type="email"
                 name="email"
                 id="email"
                 x-model="emailValue"
                 placeholder="customer@email.com"
                 class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-400 bg-white">
        </div>
      </div>

      <!-- Tanggal + Waktu -->
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">
            Tanggal <span class="text-red-500">*</span>
            <span class="ml-1 text-xs font-normal px-1.5 py-0.5 bg-blue-100 text-blue-600 rounded">Realtime</span>
          </label>
          <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <input type="date"
                   name="reservation_date"
                   id="reservation_date"
                   required
                   :value="today"
                   class="w-full pl-9 pr-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-400 bg-white">
          </div>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">
            Waktu <span class="text-red-500">*</span>
            <span class="ml-1 text-xs font-normal px-1.5 py-0.5 bg-blue-100 text-blue-600 rounded">Realtime</span>
          </label>
          <input type="time"
                 name="reservation_time"
                 id="reservation_time"
                 required
                 :value="currentTime"
                 class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-400 bg-white">
        </div>
      </div>

      <!-- Jumlah Tamu -->
      <div>
        <label for="guest_count"
               class="block text-sm font-semibold text-gray-700 mb-2">Jumlah Tamu</label>
        <input type="number"
               name="guest_count"
               id="guest_count"
               min="1"
               :value="selectedTable?.capacity ?? ''"
               placeholder="Jumlah tamu"
               class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-400 bg-white">
      </div>

      <div class="rounded-lg border border-gray-200 p-3 space-y-3 bg-gray-50">
        <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700 cursor-pointer">
          <input type="checkbox"
                 name="has_down_payment"
                 id="has_down_payment"
                 value="1"
                 @change="toggleDownPayment($event.target.checked)"
                 class="rounded border-gray-300 text-slate-700 focus:ring-slate-500">
          Apakah ingin menambahkan DP?
        </label>

        <div x-show="hasDownPayment"
             x-cloak>
          <label for="down_payment_amount_display"
                 class="block text-sm font-semibold text-gray-700 mb-2">Nominal DP</label>
          <input type="text"
                 id="down_payment_amount_display"
                 inputmode="numeric"
                 x-model="downPaymentDisplay"
                 @input="handleDownPaymentInput($event.target.value)"
                 placeholder="Rp 0"
                 class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-400 bg-white">
        </div>
      </div>

      <!-- Catatan -->
      <div>
        <label for="note"
               class="block text-sm font-semibold text-gray-700 mb-2">Catatan (Opsional)</label>
        <textarea name="note"
                  id="note"
                  rows="3"
                  placeholder="Permintaan khusus, preference makanan/minuman, dll"
                  class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-400 bg-white resize-none"></textarea>
      </div>

      <!-- Buttons -->
      <div class="flex justify-end gap-3 pt-2">
        <button type="button"
                onclick="closeModal()"
                class="px-5 py-2.5 text-sm font-medium text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
          Batal
        </button>
        <button type="submit"
                class="flex items-center gap-2 px-5 py-2.5 text-sm font-semibold bg-slate-800 hover:bg-slate-900 text-white rounded-lg transition">
          <svg class="w-4 h-4"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Buat Booking
        </button>
      </div>
    </form>
  </div>
</div>

<script>
  const bookingActiveSessionCustomerIds = @json($activeSessionCustomerIds ?? []);

  const bookingCustomers = {!! json_encode(
      $customers->map(
              fn($c) => [
                  'id' => $c->id,
                  'name' => $c->name,
                  'phone' => $c->profile?->phone ?? '',
                  'email' => $c->email ?? '',
                  'customer_code' => $c->customerUser?->customer_code ?? '',
                  'has_active_session' => collect($activeSessionCustomerIds ?? [])->contains($c->id),
              ],
          )->values(),
  ) !!};

  function bookingModal() {
    const getRealtimeBookingDefaults = () => {
      const now = new Date();
      const dateParts = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Jakarta',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
      }).formatToParts(now).reduce((parts, part) => {
        if (part.type !== 'literal') {
          parts[part.type] = part.value;
        }

        return parts;
      }, {});

      return {
        today: `${dateParts.year}-${dateParts.month}-${dateParts.day}`,
        currentTime: new Intl.DateTimeFormat('en-GB', {
          timeZone: 'Asia/Jakarta',
          hour: '2-digit',
          minute: '2-digit',
          hour12: false,
        }).format(now),
      };
    };

    const realtimeDefaults = getRealtimeBookingDefaults();

    return {
      selectedTable: null,
      today: realtimeDefaults.today,
      currentTime: realtimeDefaults.currentTime,
      customerMode: 'existing',
      selectedCustomerId: '',
      customerSearchQuery: '',
      isCustomerDropdownOpen: false,
      phoneValue: '',
      emailValue: '',
      hasDownPayment: false,
      downPaymentDisplay: 'Rp 0',

      get filteredCustomers() {
        if (!this.customerSearchQuery) {
          return bookingCustomers;
        }
        const q = this.customerSearchQuery.toLowerCase();

        return bookingCustomers.filter(c => {
          return (c.name && c.name.toLowerCase().includes(q)) ||
                 (c.customer_code && c.customer_code.toLowerCase().includes(q)) ||
                 (c.phone && c.phone.toLowerCase().includes(q));
        });
      },

      setCustomerMode(mode) {
        this.customerMode = mode;
        if (mode === 'new') {
          this.clearCustomerSelection();
        }
      },

      chooseCustomer(c) {
        if (!c) {
          this.clearCustomerSelection();
          return;
        }

        const selectedId = Number(c.id || 0);

        if (this.isCreateMode() && selectedId > 0 && bookingActiveSessionCustomerIds.includes(selectedId)) {
          this.clearCustomerSelection();
          alert('Customer sedang check-in di meja lain dan tidak bisa dibuat booking baru.');

          return;
        }

        this.selectedCustomerId = String(c.id);
        this.customerSearchQuery = c.name + (c.customer_code ? ' [' + c.customer_code + ']' : '');
        this.phoneValue = c.phone || '';
        this.emailValue = c.email || '';
        this.isCustomerDropdownOpen = false;
      },

      chooseCustomerById(id) {
        this.setCustomerMode('existing');
        const customer = bookingCustomers.find(c => String(c.id) === String(id));
        if (customer) {
          this.chooseCustomer(customer);
        } else {
          this.clearCustomerSelection();
        }
      },

      clearCustomerSelection() {
        this.selectedCustomerId = '';
        this.customerSearchQuery = '';
        this.phoneValue = '';
        this.emailValue = '';
        this.isCustomerDropdownOpen = false;
      },

      init() {
        document.addEventListener('table-selected', e => {
          this.selectTable(e.detail);
        });

        const bookingForm = document.getElementById('bookingForm');

        bookingForm?.addEventListener('submit', e => {
          const selectedCustomerId = document.getElementById('customer_id')?.value;
          const selectedId = Number(selectedCustomerId || 0);

          if (this.isCreateMode() && selectedId > 0 && bookingActiveSessionCustomerIds.includes(selectedId)) {
            e.preventDefault();
            alert('Customer sedang check-in di meja lain dan tidak bisa dibuat booking baru.');
          }
        });
      },

      isCreateMode() {
        return document.getElementById('formMethod')?.value === 'POST';
      },

      selectTable(table) {
        this.selectedTable = table;
        document.getElementById('table_id').value = table.id;
        const guestEl = document.getElementById('guest_count');
        if (guestEl && !guestEl.value) {
          guestEl.value = table.capacity;
        }
      },

      clearTable() {
        this.selectedTable = null;
        document.getElementById('table_id').value = '';
      },

      toggleDownPayment(enabled) {
        this.hasDownPayment = !!enabled;

        if (!this.hasDownPayment) {
          this.setDownPaymentAmount(0);
        }
      },

      setDownPaymentAmount(amount) {
        const normalizedAmount = Math.max(Number(amount || 0), 0);
        document.getElementById('down_payment_amount').value = String(normalizedAmount);
        this.downPaymentDisplay = 'Rp ' + new Intl.NumberFormat('id-ID').format(normalizedAmount);
      },

      handleDownPaymentInput(value) {
        const numericAmount = Number(String(value || '').replace(/[^0-9]/g, ''));
        this.setDownPaymentAmount(numericAmount);
      },

      selectCustomer(id) {
        this.chooseCustomerById(id);
      },

      setDownPaymentState(enabled, amount) {
        const downPaymentCheckbox = document.getElementById('has_down_payment');

        this.hasDownPayment = !!enabled;

        if (downPaymentCheckbox) {
          downPaymentCheckbox.checked = this.hasDownPayment;
        }

        this.setDownPaymentAmount(this.hasDownPayment ? amount : 0);
      },

      applyRealtimeDateTimeDefaults() {
        const realtime = getRealtimeBookingDefaults();
        this.today = realtime.today;
        this.currentTime = realtime.currentTime;

        const reservationDateInput = document.getElementById('reservation_date');
        const reservationTimeInput = document.getElementById('reservation_time');

        if (reservationDateInput) {
          reservationDateInput.value = realtime.today;
        }

        if (reservationTimeInput) {
          reservationTimeInput.value = realtime.currentTime;
        }
      },
    };
  }

  window.setBookingSelectedCustomer = function(customerId) {
    const modalEl = document.getElementById('bookingModal');
    const alpineData = modalEl?.__x?.$data;

    if (alpineData && typeof alpineData.chooseCustomerById === 'function') {
      alpineData.chooseCustomerById(customerId);
    }
  };

  window.setBookingDownPayment = function(enabled, amount) {
    const modalEl = document.getElementById('bookingModal');
    const alpineData = modalEl?.__x?.$data;

    if (alpineData && typeof alpineData.setDownPaymentState === 'function') {
      alpineData.setDownPaymentState(enabled, amount);
      return;
    }

    const downPaymentCheckbox = document.getElementById('has_down_payment');
    const downPaymentHidden = document.getElementById('down_payment_amount');
    const downPaymentDisplay = document.getElementById('down_payment_amount_display');
    const normalizedAmount = Math.max(Number(amount || 0), 0);

    if (downPaymentCheckbox) {
      downPaymentCheckbox.checked = !!enabled;
    }

    if (downPaymentHidden) {
      downPaymentHidden.value = String(enabled ? normalizedAmount : 0);
    }

    if (downPaymentDisplay) {
      downPaymentDisplay.value = 'Rp ' + new Intl.NumberFormat('id-ID').format(enabled ? normalizedAmount : 0);
    }
  };

  window.applyBookingRealtimeDateTimeDefaults = function() {
    const modalEl = document.getElementById('bookingModal');
    const alpineData = modalEl?.__x?.$data;

    if (alpineData && typeof alpineData.applyRealtimeDateTimeDefaults === 'function') {
      alpineData.applyRealtimeDateTimeDefaults();
      return;
    }

    const now = new Date();
    const dateParts = new Intl.DateTimeFormat('en-CA', {
      timeZone: 'Asia/Jakarta',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    }).formatToParts(now).reduce((parts, part) => {
      if (part.type !== 'literal') {
        parts[part.type] = part.value;
      }

      return parts;
    }, {});

    const today = `${dateParts.year}-${dateParts.month}-${dateParts.day}`;
    const currentTime = new Intl.DateTimeFormat('en-GB', {
      timeZone: 'Asia/Jakarta',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(now);

    const reservationDateInput = document.getElementById('reservation_date');
    const reservationTimeInput = document.getElementById('reservation_time');

    if (reservationDateInput) {
      reservationDateInput.value = today;
    }

    if (reservationTimeInput) {
      reservationTimeInput.value = currentTime;
    }
  };
</script>
