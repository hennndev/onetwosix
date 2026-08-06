<x-app-layout title="Manajemen Meja & Booking">
  @php
    $tablesJson = $tables->map(
        fn($t) => [
            'id' => $t->id,
            'table_number' => $t->table_number,
            'capacity' => $t->capacity,
            'minimum_charge' => $t->minimum_charge,
            'area_id' => $t->area_id,
            'area_name' => $t->area->name ?? '',
            'area_code' => $t->area->code ?? '',
            'notes' => $t->notes ?? '',
        ],
    );
  @endphp

  <div class="p-4 sm:p-6"
       x-data="bookingPage(@js($tablesJson), @js($activeBookingsByTable->keys()->values()), @js(collect()))">

    @if (session('success'))
      <div class="mb-4 px-4 py-3 bg-green-100 border border-green-400 text-green-700 rounded-lg text-sm">
        {{ session('success') }}
      </div>
    @endif

    @if (session('error'))
      <div class="mb-4 px-4 py-3 bg-red-100 border border-red-400 text-red-700 rounded-lg text-sm">
        {{ session('error') }}
      </div>
    @endif

    @if ($errors->any())
      <div class="mb-4 px-4 py-3 bg-red-100 border border-red-400 text-red-700 rounded-lg text-sm">
        <ul class="list-disc list-inside">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    @include('bookings._partials.header')

    <!-- Area Tabs (multi-area header) -->
    @if (($areas ?? collect())->count() > 1 && (! session('active_area_id') || session('active_area_id') === 'all'))
      <div class="flex gap-2 overflow-x-auto pb-2 mb-6">
        <a href="{{ route('admin.bookings.index', ['tab' => $tab ?? 'all']) }}"
           class="px-4 py-2 rounded-lg whitespace-nowrap transition {{ is_null($activeAreaId ?? null) ? 'bg-slate-800 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
          Semua Area
        </a>
        @foreach ($areas as $area)
          <a href="{{ route('admin.bookings.index', ['tab' => $tab ?? 'all', 'area_id' => $area->id]) }}"
             class="px-4 py-2 rounded-lg whitespace-nowrap transition {{ ($activeAreaId ?? null) === $area->id ? 'bg-slate-800 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
            {{ $area->name }}
          </a>
        @endforeach
      </div>
    @endif

    @include('bookings._partials.tab-nav')

    @if ($tab === 'pending')
      @include('bookings._partials.tab-pending')
    @elseif ($tab === 'active')
      @include('bookings._partials.tab-active')
    @elseif ($tab === 'partial')
      @include('bookings._partials.tab-partial')
    @elseif ($tab === 'history')
      @include('bookings._partials.tab-history')
    @else
      @include('bookings._partials.tab-all')
    @endif

    {{-- Modals --}}
    @include('bookings._components.add-edit-modal')
    @include('bookings._components.delete-confirmation-modal')
    @include('bookings._components.status-update-modal')
    @include('bookings._components.booking-info-modal')
    @include('bookings._components.close-billing-modal')
    @include('bookings._components.settle-payment-modal')
    @include('bookings._components.assign-waiter-modal')
    @include('bookings._components.move-table-modal')
    @include('bookings._components.order-history-modal')
    @include('bookings._components.active-delete-confirmation-modal')
  </div>

  @push('scripts')
    @include('bookings._partials.scripts')
  @endpush
</x-app-layout>
