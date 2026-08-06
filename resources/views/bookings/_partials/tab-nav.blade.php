{{-- Tab Navigation --}}
<div class="flex items-center gap-1 mb-6 overflow-x-auto">
  <a href="{{ route('admin.bookings.index', ['tab' => 'all']) }}"
     class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium transition whitespace-nowrap
            {{ $tab === 'all' || ($tab !== 'active' && $tab !== 'pending' && $tab !== 'history') ? 'bg-slate-800 text-white shadow-sm' : 'text-gray-500 hover:bg-gray-100' }}">
    <svg class="w-4 h-4"
         fill="none"
         stroke="currentColor"
         viewBox="0 0 24 24">
      <path stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
    </svg>
    Booking
  </a>
  <a href="{{ route('admin.bookings.index', ['tab' => 'pending']) }}"
     class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium transition whitespace-nowrap
            {{ $tab === 'pending' ? 'bg-yellow-500 text-white shadow-sm' : 'text-gray-500 hover:bg-gray-100' }}">
    <svg class="w-4 h-4"
         fill="none"
         stroke="currentColor"
         viewBox="0 0 24 24">
      <path stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
    Pending
    @if ($pendingBookings > 0)
      <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full text-xs font-bold
                   {{ $tab === 'pending' ? 'bg-white text-yellow-600' : 'bg-yellow-500 text-white' }}">
        {{ $pendingBookings }}
      </span>
    @endif
  </a>
  <a href="{{ route('admin.bookings.index', ['tab' => 'active']) }}"
     class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium transition whitespace-nowrap
            {{ $tab === 'active' ? 'bg-slate-800 text-white shadow-sm' : 'text-gray-500 hover:bg-gray-100' }}">
    <svg class="w-4 h-4"
         fill="none"
         stroke="currentColor"
         viewBox="0 0 24 24">
      <path stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
    Active Tables
    @if ($activeSessions->count() > 0)
      <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full text-xs font-bold
                   {{ $tab === 'active' ? 'bg-white text-slate-700' : 'bg-blue-500 text-white' }}">
        {{ $activeSessions->count() }}
      </span>
    @endif
  </a>
  <a href="{{ route('admin.bookings.index', ['tab' => 'partial']) }}"
     class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium transition whitespace-nowrap
            {{ $tab === 'partial' ? 'bg-orange-600 text-white shadow-sm' : 'text-gray-500 hover:bg-gray-100' }}">
    <svg class="w-4 h-4"
         fill="none"
         stroke="currentColor"
         viewBox="0 0 24 24">
      <path stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
    </svg>
    Parsial / Piutang
    @if (($partialBookingsCount ?? 0) > 0)
      <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full text-xs font-bold
                   {{ $tab === 'partial' ? 'bg-white text-orange-600' : 'bg-orange-500 text-white' }}">
        {{ $partialBookingsCount }}
      </span>
    @endif
  </a>
  <a href="{{ route('admin.bookings.index', ['tab' => 'history']) }}"
     class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium transition whitespace-nowrap
            {{ $tab === 'history' ? 'bg-slate-800 text-white shadow-sm' : 'text-gray-500 hover:bg-gray-100' }}">
    <svg class="w-4 h-4"
         fill="none"
         stroke="currentColor"
         viewBox="0 0 24 24">
      <path stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
    History
  </a>
</div>
