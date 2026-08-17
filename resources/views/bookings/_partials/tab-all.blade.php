{{-- ALL / MAP TAB --}}

@php
  $activeBookingsForJs = $activeBookingsByTable->mapWithKeys(
      fn($b) => [
          $b->id => [
              'id' => $b->id,
              'status' => $b->status,
              'booking_name' => $b->booking_name,
              'customer_name' => $b->customer?->name,
              'customer_phone' => $b->customer?->profile?->phone,
              'created_by_name' => $b->creator?->name,
              'created_by_type' => $b->creator?->customerUser ? 'Customer' : ($b->creator?->id ? 'User' : null),
              'table_number' => $b->table?->table_number,
              'area_name' => $b->table?->area?->name,
              'reservation_date' => $b->reservation_date,
              'reservation_time' => $b->reservation_time,
              'down_payment_amount' => (float) ($b->down_payment_amount ?? 0),
              'event_name' => $activeBookingEventAdjustments[$b->id]['event_name'] ?? null,
              'event_adjustment_label' => $activeBookingEventAdjustments[$b->id]['adjustment_label'] ?? null,
              'event_adjusted_minimum_charge' => (float) ($activeBookingEventAdjustments[$b->id]['adjusted_minimum_charge'] ?? 0),
              'note' => $b->note,
          ],
      ],
  );
@endphp
<script>
  window.activeBookingsById = @json($activeBookingsForJs);
</script>

<!-- Stats Row -->
<div class="grid grid-cols-3 gap-4 mb-5">
  <!-- Available -->
  <div class="bg-white border border-slate-200 rounded-xl px-5 py-4 flex items-center gap-4 shadow-sm">
    <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center">
      <svg class="w-5 h-5 text-emerald-600"
           fill="none"
           stroke="currentColor"
           viewBox="0 0 24 24">
        <path stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M3 10h11M9 21V3m12 10h-5m-5 4v2m5-6v6" />
      </svg>
    </div>
    <div>
      <div class="text-2xl font-bold text-slate-900">{{ $availableTablesCount }}</div>
      <div class="text-sm font-semibold text-slate-500">Available</div>
    </div>
  </div>

  <!-- Booked -->
  <div class="bg-white border border-slate-200 rounded-xl px-5 py-4 flex items-center gap-4 shadow-sm">
    <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center">
      <svg class="w-5 h-5 text-amber-600"
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
      <div class="text-2xl font-bold text-slate-900">{{ $bookedTablesCount }}</div>
      <div class="text-sm font-semibold text-slate-500">Booked</div>
    </div>
  </div>

  <!-- Checked-in -->
  <div class="bg-white border border-slate-200 rounded-xl px-5 py-4 flex items-center gap-4 shadow-sm">
    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
      <svg class="w-5 h-5 text-blue-600"
           fill="none"
           stroke="currentColor"
           viewBox="0 0 24 24">
        <path stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
      </svg>
    </div>
    <div>
      <div class="text-2xl font-bold text-slate-900">{{ $checkedInTablesCount }}</div>
      <div class="text-sm font-semibold text-slate-500">Checked-in</div>
    </div>
  </div>
</div>

<!-- Category Filter Cards -->
@php
  $areaTableCounts = $tables->groupBy('area_id');
  $areaColors = [
      'room' => ['bg' => 'bg-purple-50 border-purple-200', 'icon' => 'bg-purple-100 text-purple-600', 'text' => 'text-purple-700', 'count' => 'text-purple-900'],
      'vip' => ['bg' => 'bg-purple-50 border-purple-200', 'icon' => 'bg-purple-100 text-purple-600', 'text' => 'text-purple-700', 'count' => 'text-purple-900'],
      'balcony' => ['bg' => 'bg-violet-50 border-violet-200', 'icon' => 'bg-violet-100 text-violet-600', 'text' => 'text-violet-700', 'count' => 'text-violet-900'],
      'lounge' => ['bg' => 'bg-cyan-50 border-cyan-200', 'icon' => 'bg-cyan-100 text-cyan-600', 'text' => 'text-cyan-700', 'count' => 'text-cyan-900'],
  ];
  $defaultAreaColor = ['bg' => 'bg-gray-50 border-gray-200', 'icon' => 'bg-gray-100 text-gray-600', 'text' => 'text-gray-700', 'count' => 'text-gray-900'];
@endphp

<div class="flex flex-wrap gap-2 mb-6">
  <button @click="selectedCategory = null"
          :class="selectedCategory === null ? 'bg-slate-800 border-slate-800 text-white' : 'bg-white border-gray-200 text-gray-600 hover:border-gray-300 hover:text-gray-800'"
          class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-sm font-medium transition-all">
    Semua
    <span class="text-xs font-bold opacity-70">{{ $tables->count() }}</span>
  </button>

  @foreach ($areas as $area)
    @php
      $areaKey = strtolower($area->code ?? $area->name);
      $areaKey = str_contains($areaKey, 'room') || str_contains($areaKey, 'vip') ? 'room' : $areaKey;
      $areaKey = str_contains($areaKey, 'balcony') ? 'balcony' : $areaKey;
      $areaKey = str_contains($areaKey, 'lounge') ? 'lounge' : $areaKey;
      $color = $areaColors[$areaKey] ?? $defaultAreaColor;
      $count = $areaTableCounts->get($area->id)?->count() ?? 0;
    @endphp
    <button @click="selectedCategory = {{ $area->id }}"
            :class="selectedCategory === {{ $area->id }} ? 'bg-slate-800 border-slate-800 text-white' : 'bg-white border-gray-200 text-gray-600 hover:border-gray-300 hover:text-gray-800'"
            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-sm font-medium transition-all">
      {{ $area->name }}
      <span class="text-xs font-bold opacity-70">{{ $count }}</span>
    </button>
  @endforeach
</div>

<!-- Table Cards Grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
  @foreach ($tables as $table)
    @php
      $tableBooking = $activeBookingsByTable[$table->id] ?? null;
      $bookingStatus = (string) ($tableBooking?->status ?? '');
      $isCheckedIn = $bookingStatus === 'checked_in' || $table->status === 'occupied';
      $isBooked = !$isCheckedIn && ($bookingStatus === 'confirmed' || $table->status === 'reserved');
      $bookingEventAdjustment = $tableBooking ? $activeBookingEventAdjustments[$tableBooking->id] ?? null : null;
      $displayMinimumCharge = (float) ($bookingEventAdjustment['adjusted_minimum_charge'] ?? ($table->minimum_charge ?? 0));
      $bookingTitle = $tableBooking?->booking_name ?? ($tableBooking?->customer?->name ?? '-');
      $bookedByName = $tableBooking?->creator?->name ?? '-';
      $bookedByType = $tableBooking?->creator?->customerUser ? 'Customer' : ($tableBooking?->creator?->id ? 'User' : '-');
      $createdByText = $tableBooking?->creator ? $bookedByName . ' (' . $bookedByType . ')' : '—';
    @endphp
    <div x-show="selectedCategory === null || selectedCategory === {{ $table->area_id }}"
         class="rounded-xl p-4 border transition-all cursor-pointer hover:shadow-md
             {{ $isCheckedIn ? 'bg-blue-50 border-blue-300' : ($isBooked ? 'bg-amber-50 border-amber-300' : 'bg-white border-slate-200 hover:border-slate-300') }}"
         @if (!$isBooked && !$isCheckedIn) @click="openModal({{ $table->id }})"
         @elseif ($tableBooking) onclick="openBookingInfoModal({{ $tableBooking->id }})" @endif>

      <!-- Card Header -->
      <div class="flex items-start justify-between mb-1">
        <span class="font-bold text-base leading-tight text-slate-900">{{ $table->table_number }}</span>
        @if ($isCheckedIn)
          <span class="text-xs font-semibold text-blue-700 bg-blue-100 px-2 py-0.5 rounded-full">Aktif</span>
        @elseif ($isBooked)
          <span class="text-xs font-semibold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">Booked</span>
        @else
          <span class="text-xs font-semibold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">Tersedia</span>
        @endif
      </div>

      <!-- Meta -->
      <p class="text-sm text-slate-500 mb-3">
        {{ $table->area->name ?? '-' }} &middot; {{ $table->capacity }} seats
        @if ($displayMinimumCharge > 0)
          &middot; Min Rp {{ number_format($displayMinimumCharge / 1000, 0, ',', '.') }}k
        @endif
      </p>
      @if ($bookingEventAdjustment)
        <p class="text-xs font-medium text-purple-700 -mt-2 mb-3">
          Event: {{ $bookingEventAdjustment['event_name'] }} ({{ $bookingEventAdjustment['adjustment_label'] }})
        </p>
      @endif

      <!-- Footer -->
      @if ($isCheckedIn && $tableBooking)
        @php
          $billing = $tableBooking->tableSession?->billing;
          $sessionChargePreview = $tableBooking->tableSession ? $activeSessionChargePreviews[$tableBooking->tableSession->id] ?? null : null;
          $sessionEventAdjustment = $tableBooking->tableSession ? $activeSessionEventAdjustments[$tableBooking->tableSession->id] ?? null : null;
          $ordersForEligibility = (float) ($sessionChargePreview['orders_total'] ?? ($billing?->orders_total ?? 0));
          $checkerItems = $tableBooking->tableSession?->orders?->flatMap->items?->where('status', '!=', 'cancelled') ?? collect();
          $checkerTotalItems = $checkerItems->count();
          $checkerCheckedItems = $checkerItems->where('status', 'served')->count();
        @endphp
        <p class="text-sm font-semibold text-slate-800 truncate">{{ $bookingTitle }}</p>
        <p class="text-xs text-slate-500 mt-0.5 truncate">Dibuat oleh: {{ $createdByText }}</p>
        @if ($billing && in_array($billing->billing_status, ['draft', 'finalized']))
          @if ($ordersForEligibility >= (float) ($billing->minimum_charge ?? 0))
            <button type="button"
                    data-booking-id="{{ $tableBooking->id }}"
                    data-minimum-charge="{{ (float) $billing->minimum_charge }}"
                    data-orders-total="{{ $ordersForEligibility }}"
                    data-discount-amount="{{ (float) ($sessionChargePreview['discount_amount'] ?? 0) }}"
                    data-down-payment-amount="{{ (float) ($tableBooking->down_payment_amount ?? 0) }}"
                    data-service-charge="{{ (float) ($sessionChargePreview['service_charge'] ?? 0) }}"
                    data-tax="{{ (float) ($sessionChargePreview['tax'] ?? 0) }}"
                    data-service-charge-percentage="{{ (float) ($sessionChargePreview['service_charge_percentage'] ?? 0) }}"
                    data-tax-percentage="{{ (float) ($sessionChargePreview['tax_percentage'] ?? 0) }}"
                    data-grand-total="{{ (float) ($sessionChargePreview['grand_total'] ?? max($billing->minimum_charge, $billing->orders_total) - $billing->discount_amount) }}"
                    data-checker-checked="{{ $checkerCheckedItems }}"
                    data-checker-total="{{ $checkerTotalItems }}"
                    data-items-json="{{ json_encode($tableBooking->tableSession?->orders?->where('status', '!=', 'cancelled')?->flatMap(fn ($o) => $o->items?->where('status', '!=', 'cancelled')?->map(fn ($i) => ['id' => $i->id, 'name' => $i->item_name, 'quantity' => $i->quantity, 'subtotal' => (float) $i->subtotal]))?->values() ?? collect()) }}"
                    onclick="event.stopPropagation(); openCloseBillingModal(this)"
                    class="mt-2 w-full text-xs font-semibold px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white transition">
              Tutup Billing
            </button>
          @else
            <p class="mt-1 text-xs text-slate-500 font-medium">
              Kurang Rp {{ number_format(max((float) ($billing->minimum_charge ?? 0) - $ordersForEligibility, 0), 0, ',', '.') }}
            </p>
          @endif
        @endif
      @elseif ($isBooked && $tableBooking)
        <p class="text-sm font-semibold text-slate-800 truncate">{{ $bookingTitle }}</p>
        <p class="text-xs text-slate-500 mt-0.5 truncate">Dibuat oleh: {{ $createdByText }}</p>
        <div class="mt-2 grid grid-cols-3 gap-2">
          <form method="POST"
                action="{{ route('admin.bookings.updateStatus', $tableBooking) }}"
                onclick="event.stopPropagation()">
            @csrf
            @method('PATCH')
            <input type="hidden"
                   name="status"
                   value="checked_in">
            <button type="button"
                    onclick="event.stopPropagation(); openStatusConfirmModal(this.closest('form'), 'Konfirmasi Check-in', 'Yakin ubah status booking ini menjadi Checked-in?', 'Ya, Check-in', 'bg-green-600 hover:bg-green-700')"
                    class="w-full text-xs font-semibold px-3 py-1.5 rounded-lg bg-green-600 text-white hover:bg-green-700 transition">
              Check-in
            </button>
          </form>
          <form method="POST"
                action="{{ route('admin.bookings.updateStatus', $tableBooking) }}"
                onclick="event.stopPropagation()">
            @csrf
            @method('PATCH')
            <input type="hidden"
                   name="status"
                   value="cancelled">
            <button type="button"
                    onclick="event.stopPropagation(); openStatusConfirmModal(this.closest('form'), 'Konfirmasi Cancel', 'Yakin batalkan booking ini?', 'Ya, Cancel', 'bg-red-600 hover:bg-red-700')"
                    class="w-full text-xs font-semibold px-3 py-1.5 rounded-lg bg-red-100 text-red-800 hover:bg-red-200 transition">
              Cancel
            </button>
          </form>
          <button type="button"
                  onclick="event.stopPropagation(); openMoveTableModal({{ $tableBooking->id }}, '{{ $table->table_number }}')"
                  class="w-full text-xs font-semibold px-3 py-1.5 rounded-lg bg-amber-100 text-amber-800 hover:bg-amber-200 transition">
            Pindah Meja
          </button>
        </div>
      @endif
    </div>
  @endforeach
</div>

<!-- Pending Bookings Section -->
@if ($todayPendingBookings->isNotEmpty())
  <div class="mt-6">
    <div class="flex items-center gap-2 mb-3">
      <span class="w-2 h-2 rounded-full bg-yellow-400 inline-block"></span>
      <h3 class="text-sm font-semibold text-gray-700">Pending Bookings ({{ $todayPendingBookings->count() }})</h3>
      <span class="text-xs text-gray-400">— belum dikonfirmasi, tidak tampil di peta meja</span>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
      @foreach ($todayPendingBookings as $pendingBooking)
        @php
          $pendingCreatedByName = $pendingBooking->creator?->name ?? '-';
          $pendingCreatedByType = $pendingBooking->creator?->customerUser ? 'Customer' : ($pendingBooking->creator?->id ? 'User' : '-');
          $pendingCreatedByText = $pendingBooking->creator ? $pendingCreatedByName . ' (' . $pendingCreatedByType . ')' : '—';
        @endphp
        <div class="bg-white border border-yellow-200 rounded-xl p-4 flex items-center justify-between gap-3">
          <div class="min-w-0">
            <p class="text-sm text-gray-500">Nama Booking</p>
            <p class="text-sm font-semibold text-gray-900 truncate">{{ $pendingBooking->booking_name ?? '-' }}</p>
            <p class="text-xs text-gray-400 mt-0.5">Dibuat oleh: {{ $pendingCreatedByText }}</p>
            <p class="text-xs text-gray-500 mt-1">Nama</p>
            <p class="text-xs text-gray-700 truncate">{{ $pendingBooking->customer?->name ?? '-' }}</p>
            <p class="text-xs text-gray-500 mt-1">Reservation ID</p>
            <p class="text-xs font-medium text-gray-700">#{{ $pendingBooking->id }}</p>
            <p class="text-xs text-gray-400 mt-0.5">
              {{ $pendingBooking->table?->table_number ?? '-' }} &middot;
              {{ \Carbon\Carbon::parse($pendingBooking->reservation_date)->format('d M Y') }} &middot;
              {{ $pendingBooking->reservation_time ? \Carbon\Carbon::parse($pendingBooking->reservation_time)->format('H:i') : '-' }}
            </p>
          </div>
          <button onclick="openStatusModal({{ $pendingBooking->id }}, 'pending')"
                  class="flex-shrink-0 text-xs font-medium px-3 py-1.5 rounded-lg bg-yellow-100 text-yellow-800 hover:bg-yellow-200 transition">
            Ubah Status
          </button>
        </div>
      @endforeach
    </div>
  </div>
@endif

<div id="statusConfirmModal"
     class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-xl max-w-sm w-full">
    <div class="p-6 border-b border-gray-200">
      <h3 id="statusConfirmTitle"
          class="text-lg font-bold text-gray-900">Konfirmasi Status</h3>
      <p id="statusConfirmMessage"
         class="mt-1 text-sm text-gray-600">Yakin mengubah status booking ini?</p>
    </div>
    <div class="p-6 flex justify-end gap-3">
      <button type="button"
              onclick="closeStatusConfirmModal()"
              class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
        Batal
      </button>
      <button id="statusConfirmSubmitBtn"
              type="button"
              onclick="submitStatusConfirmation()"
              class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
        Ya
      </button>
    </div>
  </div>
</div>
