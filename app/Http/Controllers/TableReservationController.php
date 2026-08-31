<?php

namespace App\Http\Controllers;

use App\Models\Billing;
use App\Models\CustomerUser;
use App\Models\DailyAuthCode;
use App\Models\Event;
use App\Models\GeneralSetting;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Printer;
use App\Models\Tabel;
use App\Models\TableReservation;
use App\Models\TableSession;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\AccurateService;
use App\Services\DashboardSyncService;
use App\Services\PrinterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TableReservationController extends Controller
{
    public function __construct(
        protected AccurateService $accurateService,
        protected DashboardSyncService $dashboardSyncService,
        protected PrinterService $printerService,
    ) {}

    public function index(Request $request)
    {
        $generalSettings = \App\Models\GeneralSetting::instance();

        $this->reconcileTableStatuses();

        $query = TableReservation::with(['table.area', 'customer.profile', 'customer.customerUser', 'creator.customerUser', 'tableSession.billing']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('booking_code', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhereHas('profile', function ($profileQuery) use ($search) {
                                $profileQuery->where('phone', 'like', "%{$search}%");
                            });
                    })
                    ->orWhereHas('table', function ($tableQuery) use ($search) {
                        $tableQuery->where('table_number', 'like', "%{$search}%");
                    })
                    ->orWhereHas('tableSession.billing', function ($billingQuery) use ($search) {
                        $billingQuery->where('transaction_code', 'like', "%{$search}%");
                    })
                    ->orWhereHas('tableSession.orders', function ($orderQuery) use ($search) {
                        $orderQuery->where('order_number', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('session_id')) {
            $sessionId = (int) $request->input('session_id');

            if ($sessionId > 0) {
                $query->whereHas('tableSession', function ($tableSessionQuery) use ($sessionId) {
                    $tableSessionQuery->where('id', $sessionId);
                });
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $activeAreaId = auth()->user()?->resolveActiveAreaId($request->input('area_id'), $request->has('area_id'));

        if ($activeAreaId) {
            $query->whereHas('table.area', function ($areaQuery) use ($activeAreaId) {
                $areaQuery->where('id', $activeAreaId);
            });
        }

        $tab = $request->get('tab', 'all');

        if ($tab === 'active') {
            $query->whereIn('status', ['confirmed', 'checked_in']);
        } elseif ($tab === 'pending') {
            $query->where('status', 'pending');

            if ($request->filled('date_from')) {
                $query->where('reservation_date', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->where('reservation_date', '<=', $request->date_to);
            }
        } elseif ($tab === 'partial') {
            $query->with(['tableSession.orders.items', 'tableSession.billing.payments', 'creator.customerUser']);
            $query->whereHas('tableSession.billing', function ($bQuery) {
                $bQuery->where('remaining_balance', '>', 0);
            });

            if ($request->filled('date_from')) {
                $query->where('reservation_date', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->where('reservation_date', '<=', $request->date_to);
            }
        } elseif ($tab === 'history') {
            $query->with(['tableSession.orders.items', 'creator.customerUser']);
            $query->whereIn('status', ['completed', 'cancelled', 'rejected', 'force_closed']);

            if ($request->filled('date_from')) {
                $query->where('reservation_date', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->where('reservation_date', '<=', $request->date_to);
            }
        }

        $bookings = $query->latest('reservation_date')->latest('reservation_time');

        if ($tab === 'history') {
            $bookings = $bookings->paginate(10)->withQueryString();
        } else {
            $bookings = $bookings->get();
        }

        $areaScope = fn ($q) => $q->when($activeAreaId, fn ($sub) => $sub->whereHas('table.area', fn ($t) => $t->where('id', $activeAreaId)));

        $totalBookings = TableReservation::tap($areaScope)->count();
        $pendingBookings = TableReservation::where('status', 'pending')->tap($areaScope)->count();
        $confirmedBookings = TableReservation::where('status', 'confirmed')->tap($areaScope)->count();
        $checkedInBookings = TableReservation::where('status', 'checked_in')->tap($areaScope)->count();
        $partialBookingsCount = TableReservation::whereHas('tableSession.billing', function ($bQuery) {
            $bQuery->where('remaining_balance', '>', 0);
        })->tap($areaScope)->count();

        $tables = Tabel::with('area')->where('is_active', true)
            ->when($activeAreaId, fn ($q) => $q->where('area_id', $activeAreaId))
            ->orderBy('table_number')->get();
        $customers = User::whereHas('customerUser')->with(['profile', 'customerUser'])->orderBy('name')->get();
        $user = auth()->user();
        $areas = $user ? $user->getAccessibleAreas() : \App\Models\Area::where('is_active', true)->orderBy('sort_order')->get();

        // Derive table status counts from the tables themselves (consistent with updateStatus logic)
        $availableTablesCount = $tables->where('status', 'available')->count();
        $bookedTablesCount = $tables->where('status', 'reserved')->count();
        $checkedInTablesCount = $tables->where('status', 'occupied')->count();

        // Latest checked-in/confirmed booking per table (ensures reserved cards always map to booking data)
        $activeBookingsByTable = TableReservation::with(['customer.profile', 'customer.customerUser', 'creator.customerUser', 'tableSession.billing', 'tableSession.orders.items'])
            ->whereIn('status', ['checked_in', 'confirmed'])
            ->whereNotNull('table_id')
            ->tap($areaScope)
            ->orderByRaw("CASE WHEN status = 'checked_in' THEN 0 ELSE 1 END")
            ->orderByDesc('reservation_date')
            ->orderByDesc('reservation_time')
            ->get()
            ->unique('table_id')
            ->keyBy('table_id');

        $activeSessions = TableSession::with([
            'table.area',
            'reservation.customer.profile',
            'reservation.customer.customerUser',
            'billing',
            'waiter.profile',
            'orders.items.inventoryItem',
        ])
            ->where('status', 'active')
            ->when($activeAreaId, fn ($q) => $q->whereHas('table', fn ($t) => $t->where('area_id', $activeAreaId)))
            ->orderBy('checked_in_at')
            ->get();

        $activeSessionChargePreviews = $activeSessions->mapWithKeys(function (TableSession $session) {
            $billing = $session->billing;

            return [
                $session->id => $this->calculateSessionBillingTotals(
                    $session,
                    (float) ($billing?->discount_amount ?? 0),
                    (float) ($billing?->minimum_charge ?? 0),
                    (float) ($session->reservation?->down_payment_amount ?? 0),
                ),
            ];
        });

        $activeSessionEventAdjustments = $activeSessions->mapWithKeys(function (TableSession $session) {
            $reservationDate = $session->reservation?->reservation_date;
            $activeEvent = $this->resolveActiveEventForDate($reservationDate, $session->table?->area_id);

            if (! $activeEvent) {
                return [$session->id => null];
            }

            $baseMinimumCharge = (float) ($session->table?->minimum_charge ?? 0);
            $adjustedMinimumCharge = $this->applyEventAdjustmentToMinimumCharge($baseMinimumCharge, $activeEvent);

            return [$session->id => [
                'event_name' => (string) $activeEvent->name,
                'adjustment_type' => (string) $activeEvent->price_adjustment_type,
                'adjustment_value' => (float) $activeEvent->price_adjustment_value,
                'adjustment_label' => (string) $activeEvent->getPriceAdjustmentFormatted(),
                'base_minimum_charge' => $baseMinimumCharge,
                'adjusted_minimum_charge' => $adjustedMinimumCharge,
            ]];
        });

        $activeBookingEventAdjustments = $activeBookingsByTable->mapWithKeys(function (TableReservation $booking) {
            $activeEvent = $this->resolveActiveEventForDate($booking->reservation_date, $booking->table?->area_id);

            if (! $activeEvent) {
                return [$booking->id => null];
            }

            $baseMinimumCharge = (float) ($booking->table?->minimum_charge ?? 0);
            $adjustedMinimumCharge = $this->applyEventAdjustmentToMinimumCharge($baseMinimumCharge, $activeEvent);

            return [$booking->id => [
                'event_name' => (string) $activeEvent->name,
                'adjustment_type' => (string) $activeEvent->price_adjustment_type,
                'adjustment_value' => (float) $activeEvent->price_adjustment_value,
                'adjustment_label' => (string) $activeEvent->getPriceAdjustmentFormatted(),
                'base_minimum_charge' => $baseMinimumCharge,
                'adjusted_minimum_charge' => $adjustedMinimumCharge,
            ]];
        });

        $activeBookingSubtotals = $activeBookingsByTable->mapWithKeys(function (TableReservation $booking) {
            return [
                $booking->id => $this->calculateActiveBookingItemSubtotal($booking),
            ];
        });

        $todayPendingBookings = TableReservation::with(['table.area', 'customer.profile', 'customer.customerUser', 'creator.customerUser'])
            ->where('status', 'pending')
            ->tap($areaScope)
            ->orderBy('reservation_date')
            ->orderBy('reservation_time')
            ->get();

        // Pending tab: identify competing bookings (same table + date, >1 pending)
        $conflictingPendingKeys = TableReservation::where('status', 'pending')
            ->tap($areaScope)
            ->selectRaw('table_id, reservation_date, COUNT(*) as cnt')
            ->groupBy('table_id', 'reservation_date')
            ->having('cnt', '>', 1)
            ->get()
            ->map(fn ($r) => $r->table_id.'_'.($r->reservation_date instanceof \Carbon\Carbon ? $r->reservation_date->toDateString() : $r->reservation_date))
            ->toArray();

        // Pending tab: slots already taken by a confirmed/checked-in booking
        $blockedPendingKeys = TableReservation::whereIn('status', ['confirmed', 'checked_in'])
            ->tap($areaScope)
            ->selectRaw('DISTINCT table_id, reservation_date')
            ->get()
            ->map(fn ($r) => $r->table_id.'_'.($r->reservation_date instanceof \Carbon\Carbon ? $r->reservation_date->toDateString() : $r->reservation_date))
            ->toArray();

        // History stats
        $historyTotalCount = TableReservation::whereIn('status', ['completed', 'cancelled', 'rejected', 'force_closed'])->tap($areaScope)->count();
        $historyCompletedCount = TableReservation::where('status', 'completed')->tap($areaScope)->count();
        $historyForceClosedCount = TableReservation::where('status', 'force_closed')->tap($areaScope)->count();
        $historyTotalRevenue = \App\Models\Billing::whereHas('tableSession', function ($q) use ($activeAreaId): void {
            $q->whereHas('reservation', function ($q2) use ($activeAreaId): void {
                $q2->whereIn('status', ['completed', 'force_closed'])
                    ->when($activeAreaId, fn ($t) => $t->whereHas('table.area', fn ($a) => $a->where('id', $activeAreaId)));
            });
        })->sum('grand_total');
        $historyAvgSpending = $historyCompletedCount > 0
            ? $historyTotalRevenue / $historyCompletedCount
            : 0;

        $waiters = User::whereHas('roles', fn ($q) => $q->where('name', 'Waiter/Server'))
            ->with('profile')
            ->orderBy('name')
            ->get();

        $activeSessionCustomerIds = TableSession::query()
            ->where('status', 'active')
            ->when($activeAreaId, fn ($q) => $q->whereHas('table', fn ($t) => $t->where('area_id', $activeAreaId)))
            ->pluck('customer_id')
            ->unique()
            ->values();

        // JSON response for waiter mobile scanner search
        if ($request->get('format') === 'json' || $request->wantsJson()) {
            $bookingsCollection = $bookings instanceof LengthAwarePaginator
                ? $bookings->getCollection()
                : $bookings;

            return response()->json([
                'reservations' => $bookingsCollection->map(fn ($b) => [
                    'id' => $b->id,
                    'status' => $b->status,
                    'customer' => $b->customer ? [
                        'name' => $b->customer->name,
                        'email' => $b->customer->email,
                    ] : null,
                    'created_by_name' => $b->creator?->name,
                    'created_by_type' => $b->creator?->customerUser ? 'Customer' : ($b->creator?->id ? 'User' : null),
                    'table' => $b->table ? [
                        'table_number' => $b->table->table_number,
                    ] : null,
                    'reservation_date' => $b->reservation_date,
                    'reservation_time' => $b->reservation_time,
                    'booking_code' => $b->booking_code,
                ])->values(),
            ]);
        }

        return view('bookings.index', compact(
            'bookings',
            'totalBookings',
            'pendingBookings',
            'confirmedBookings',
            'checkedInBookings',
            'partialBookingsCount',
            'tables',
            'customers',
            'areas',
            'activeAreaId',
            'tab',
            'activeBookingsByTable',
            'activeSessions',
            'waiters',
            'todayPendingBookings',
            'conflictingPendingKeys',
            'blockedPendingKeys',
            'availableTablesCount',
            'bookedTablesCount',
            'checkedInTablesCount',
            'activeSessionChargePreviews',
            'activeSessionEventAdjustments',
            'activeBookingEventAdjustments',
            'activeBookingSubtotals',
            'historyTotalCount',
            'historyCompletedCount',
            'historyForceClosedCount',
            'historyTotalRevenue',
            'historyAvgSpending',
            'activeSessionCustomerIds',
            'generalSettings'
        ));
    }

    public function store(Request $request)
    {
        $customerMode = $request->input('customer_mode', 'existing');
        if ($request->input('customer_id') === 'new') {
            $customerMode = 'new';
        }

        $rules = [
            'table_id' => 'required|exists:tables,id',
            'booking_name' => 'nullable|string|max:255',
            'reservation_date' => 'required|date',
            'reservation_time' => 'required',
            'note' => 'nullable|string|max:1000',
            'has_down_payment' => 'nullable|boolean',
            'down_payment_amount' => 'nullable|numeric|min:0',
        ];

        if ($customerMode === 'new') {
            $rules['new_customer_name'] = 'required|string|max:255';
            $rules['phone'] = 'required|string|max:20';
            $rules['email'] = 'nullable|email';
        } else {
            $rules['customer_id'] = 'required|exists:users,id';
            $rules['phone'] = 'nullable|string|max:20';
            $rules['email'] = 'nullable|email';
        }

        $validated = $request->validate($rules);

        $validated['down_payment_amount'] = (bool) ($validated['has_down_payment'] ?? false)
            ? (float) ($validated['down_payment_amount'] ?? 0)
            : 0;

        unset($validated['has_down_payment']);
        $validated['created_by'] = auth()->id();

        try {
            DB::beginTransaction();

            if ($customerMode === 'new') {
                $name = trim($validated['new_customer_name']);
                $phone = trim($validated['phone']);
                $email = ! empty($validated['email'])
                    ? trim($validated['email'])
                    : Str::slug($name).'_'.time().'@126club.local';

                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make(Str::random(16)),
                ]);

                $profile = UserProfile::create([
                    'user_id' => $user->id,
                    'phone' => $phone,
                ]);

                CustomerUser::create([
                    'user_id' => $user->id,
                    'user_profile_id' => $profile->id,
                    'total_visits' => 0,
                    'lifetime_spending' => 0,
                ]);

                $customerId = $user->id;
            } else {
                $customerId = (int) $validated['customer_id'];

                $user = User::find($customerId);
                if ($user) {
                    if (! empty($validated['email']) && empty($user->email)) {
                        $user->update(['email' => trim($validated['email'])]);
                    }
                    if (! empty($validated['phone'])) {
                        UserProfile::updateOrCreate(
                            ['user_id' => $user->id],
                            ['phone' => trim($validated['phone'])]
                        );
                    }
                }
            }

            unset($validated['new_customer_name'], $validated['phone'], $validated['email']);
            $validated['customer_id'] = $customerId;

            $hasActiveSession = TableSession::query()
                ->where('customer_id', $validated['customer_id'])
                ->where('status', 'active')
                ->exists();

            if ($hasActiveSession) {
                DB::rollBack();

                return back()->withErrors([
                    'customer_id' => 'Customer sedang check-in di meja lain dan tidak bisa dibuat booking baru.',
                ])->withInput();
            }

            // New bookings always start as pending — admin must confirm explicitly
            $validated['status'] = 'pending';

            // Generate unique booking code
            $lastBooking = TableReservation::latest('id')->first();
            $validated['booking_code'] = $lastBooking ? $lastBooking->booking_code + 1 : 1;

            TableReservation::create($validated);

            DB::commit();

            return redirect()->route('admin.bookings.index')
                ->with('success', 'Booking berhasil ditambahkan. Status: Pending — silakan konfirmasi setelah diverifikasi.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Gagal menambahkan booking: '.$e->getMessage()])
                ->withInput();
        }
    }

    public function update(Request $request, TableReservation $booking)
    {
        $validated = $request->validate([
            'table_id' => 'required|exists:tables,id',
            'customer_id' => 'required|exists:users,id',
            'booking_name' => 'nullable|string|max:255',
            'reservation_date' => 'required|date',
            'reservation_time' => 'required',
            'status' => 'required|in:pending,confirmed,checked_in,completed,cancelled,rejected',
            'note' => 'nullable|string|max:1000',
            'has_down_payment' => 'nullable|boolean',
            'down_payment_amount' => 'nullable|numeric|min:0',
        ]);

        $validated['down_payment_amount'] = (bool) ($validated['has_down_payment'] ?? false)
            ? (float) ($validated['down_payment_amount'] ?? 0)
            : 0;

        unset($validated['has_down_payment']);

        try {
            // Check for conflicts whenever confirming or checking in
            if (in_array($validated['status'], ['confirmed', 'checked_in'])) {
                $existingBooking = TableReservation::where('table_id', $validated['table_id'])
                    ->whereIn('status', ['confirmed', 'checked_in'])
                    ->where('reservation_date', $validated['reservation_date'])
                    ->where('id', '!=', $booking->id)
                    ->first();

                if ($existingBooking) {
                    $table = Tabel::with('area')->find($validated['table_id']);
                    $customerName = $existingBooking->customer->name ?? 'Customer lain';

                    return back()->withErrors([
                        'status' => "Tidak dapat mengkonfirmasi booking. Meja {$table->area->name} - Nomor {$table->table_number} sudah direservasi oleh {$customerName} pada tanggal yang sama. Silakan ubah status ke 'Cancelled' dan tambahkan catatan untuk customer.",
                    ])->withInput();
                }
            }

            $oldTableId = $booking->table_id;
            $oldStatus = $booking->status;

            $booking->update($validated);

            // Update old table status to available if table changed
            if ($oldTableId != $validated['table_id']) {
                Tabel::where('id', $oldTableId)->update(['status' => 'available']);
            }

            // Update new table status based on booking status
            if ($validated['status'] === 'confirmed') {
                Tabel::where('id', $validated['table_id'])->update(['status' => 'reserved']);
            } elseif ($validated['status'] === 'checked_in') {
                Tabel::where('id', $validated['table_id'])->update(['status' => 'occupied']);
            } elseif ($validated['status'] === 'completed' || $validated['status'] === 'cancelled') {
                Tabel::where('id', $validated['table_id'])->update(['status' => 'available']);
            }

            return redirect()->route('admin.bookings.index')
                ->with('success', 'Booking berhasil diupdate');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal mengupdate booking: '.$e->getMessage()])
                ->withInput();
        }
    }

    public function destroy(TableReservation $booking)
    {
        try {
            DB::transaction(function () use ($booking) {
                $tableId = $booking->table_id;

                TableSession::query()
                    ->where('table_reservation_id', $booking->id)
                    ->where('status', 'active')
                    ->delete();

                $booking->delete();

                if ($tableId) {
                    Tabel::query()->where('id', $tableId)->update(['status' => 'available']);
                }
            });

            return redirect()->route('admin.bookings.index')
                ->with('success', 'Booking berhasil dihapus');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal menghapus booking: '.$e->getMessage()]);
        }
    }

    public function updateStatus(Request $request, TableReservation $booking)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,checked_in,completed,cancelled,rejected',
        ]);

        try {
            DB::transaction(function () use ($booking, $validated) {
                // Check for conflicts inside the transaction with a row-level lock
                // to prevent race conditions when multiple admins confirm simultaneously
                if (in_array($validated['status'], ['confirmed', 'checked_in'])) {
                    $existingBooking = TableReservation::where('table_id', $booking->table_id)
                        ->whereIn('status', ['confirmed', 'checked_in'])
                        ->where('reservation_date', $booking->reservation_date)
                        ->where('id', '!=', $booking->id)
                        ->lockForUpdate()
                        ->first();

                    if ($existingBooking) {
                        $table = Tabel::with('area')->find($booking->table_id);
                        $customerName = $existingBooking->customer->name ?? 'Customer lain';

                        throw new \Exception("Tidak dapat mengkonfirmasi booking. Meja {$table->area->name} - Nomor {$table->table_number} sudah direservasi oleh {$customerName} pada tanggal yang sama.");
                    }
                }

                $booking->update(['status' => $validated['status']]);

                // Update table status based on booking status
                $table = Tabel::find($booking->table_id);
                if ($table) {
                    if ($validated['status'] === 'confirmed') {
                        $table->update(['status' => 'reserved']);
                    } elseif ($validated['status'] === 'checked_in') {
                        $table->update(['status' => 'occupied']);
                    } elseif (in_array($validated['status'], ['completed', 'cancelled', 'rejected'])) {
                        $table->update(['status' => 'available']);
                    }
                }

                // Create TableSession + Billing when checking in manually
                if ($validated['status'] === 'checked_in') {
                    $existingSession = $booking->tableSession;

                    if (! $existingSession) {
                        $session = TableSession::create([
                            'table_reservation_id' => $booking->id,
                            'table_id' => $booking->table_id,
                            'customer_id' => $booking->customer_id,
                            'session_code' => 'SES-'.strtoupper(Str::random(10)),
                            'checked_in_at' => now(),
                            'status' => 'active',
                        ]);

                        $minimumCharge = $this->calculateBookingMinimumCharge(
                            (float) ($booking->table?->minimum_charge ?? 0),
                            $booking->reservation_date,
                            $booking->table?->area_id,
                        );
                        $billing = Billing::create([
                            'area_id' => $booking->table?->area_id,
                            'table_session_id' => $session->id,
                            'is_walk_in' => false,
                            'is_booking' => true,
                            'minimum_charge' => $minimumCharge,
                            'orders_total' => 0,
                            'subtotal' => 0,
                            'tax' => 0,
                            'tax_percentage' => 0,
                            'discount_amount' => 0,
                            'grand_total' => 0,
                            'paid_amount' => 0,
                            'billing_status' => 'draft',
                        ]);

                        $session->update(['billing_id' => $billing->id]);
                    }
                }

                // Close TableSession when booking is completed
                if ($validated['status'] === 'completed') {
                    $session = $booking->tableSession;
                    if ($session && $session->status === 'active') {
                        $session->update([
                            'checked_out_at' => now(),
                            'status' => 'completed',
                        ]);
                    }
                }
            });

            $statusMessages = [
                'confirmed' => 'Booking berhasil dikonfirmasi',
                'checked_in' => 'Customer berhasil check-in',
                'completed' => 'Booking berhasil diselesaikan',
                'cancelled' => 'Booking berhasil dibatalkan',
                'rejected' => 'Booking berhasil ditolak',
            ];

            $message = $statusMessages[$validated['status']] ?? 'Status booking berhasil diupdate';

            return redirect()->route('admin.bookings.index')->with('success', $message);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal mengupdate status: '.$e->getMessage()]);
        }
    }

    public function closeBilling(Request $request, TableReservation $booking)
    {
        $validated = $request->validate([
            'payment_mode' => 'required|in:normal,split,partial,debt',
            'payment_method' => 'required_if:payment_mode,normal,partial,debt|nullable|in:cash,kredit,debit,qris,transfer,FOC,Compliment',
            'partial_paid_amount' => 'nullable|numeric|min:0',
            'foc_comp_payment_method' => 'nullable|in:FOC,Compliment',
            'foc_comp_auth_code' => 'nullable|digits:4',
            'payment_reference_number' => 'nullable|string|max:100',
            'split_cash_amount' => 'nullable|numeric|min:0',
            'split_non_cash_amount' => 'nullable|numeric|min:0',
            'split_non_cash_method' => 'nullable|in:debit,kredit,qris,transfer,ewallet,lainnya',
            'split_non_cash_reference_number' => 'nullable|string|max:100',
            'split_second_non_cash_amount' => 'nullable|numeric|min:0',
            'split_second_non_cash_method' => 'nullable|in:debit,kredit,qris,transfer,ewallet,lainnya',
            'split_second_non_cash_reference_number' => 'nullable|string|max:100',
            'discount_type' => 'nullable|in:percentage,nominal,item',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'discount_nominal' => 'nullable|numeric|min:0',
            'discount_auth_code' => 'nullable|digits:4',
            'discount_order_item_ids' => 'nullable|array',
            'discount_order_item_ids.*' => 'integer|exists:order_items,id',
            'discount_items' => 'nullable|array',
            'discount_items.*' => 'numeric|min:0',
            'discount_item_type' => 'nullable|in:percentage,nominal',
            'discount_item_value' => 'nullable|numeric|min:0',
        ]);

        $session = TableSession::with(['billing', 'orders.items'])
            ->where('table_reservation_id', $booking->id)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if (! $session) {
            $session = $booking->load('tableSession.billing.tableSession.orders.items')->tableSession;
        }

        if (! $session) {
            return response()->json(['success' => false, 'message' => 'Table session tidak ditemukan.'], 404);
        }

        $billing = $session->billing;

        if (! $billing) {
            return response()->json(['success' => false, 'message' => 'Billing tidak ditemukan.'], 404);
        }

        if ($this->hasIncompleteTransactionChecker($session)) {
            return response()->json([
                'success' => false,
                'message' => 'Billing tidak bisa ditutup karena masih ada item di Transaction Checker yang belum selesai.',
            ], 422);
        }

        if ($billing->billing_status === 'paid') {
            return response()->json(['success' => false, 'message' => 'Billing sudah ditutup.'], 422);
        }

        // Validate DP (down payment) matches order items
        $downPaymentAmount = (float) ($booking->down_payment_amount ?? 0);
        if ($downPaymentAmount > 0) {
            $session->loadMissing('orders');
            $ordersTotal = (float) $session->orders
                ->where('status', '!=', 'cancelled')
                ->sum(fn ($order) => (float) ($order->total ?? 0));

            if ($ordersTotal < $downPaymentAmount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order items total (Rp '.number_format($ordersTotal, 0, ',', '.').') tidak sesuai dengan DP yang diambil (Rp '.number_format($downPaymentAmount, 0, ',', '.').'). Silakan tambahkan order lebih banyak atau batalkan booking.',
                ], 422);
            }
        }

        try {
            DB::transaction(function () use ($booking, $session, $billing, $validated) {
                $session->loadMissing('orders.items.inventoryItem');

                $focCompPaymentMethod = $validated['foc_comp_payment_method'] ?? null;
                $discountType = $validated['discount_type'] ?? null;
                $discountPercentage = (float) ($validated['discount_percentage'] ?? 0);
                $discountNominal = (float) ($validated['discount_nominal'] ?? 0);
                $discountAuthCode = (string) ($validated['discount_auth_code'] ?? '');
                $focCompAuthCode = (string) ($validated['foc_comp_auth_code'] ?? '');
                $generalSettings = \App\Models\GeneralSetting::instance();
                $isFocComp = in_array((string) ($focCompPaymentMethod ?? ''), ['FOC', 'Compliment'], true);

                // Diskon FOC/Compliment dari setting (default: Compliment 100%, FOC 0%).
                if ($focCompPaymentMethod === 'Compliment') {
                    $discountType = 'percentage';
                    $discountPercentage = $generalSettings->complimentDiscountPercentage();
                } elseif ($focCompPaymentMethod === 'FOC') {
                    $discountType = $generalSettings->focDiscountPercentage() > 0 ? 'percentage' : 'none';
                    $discountPercentage = $generalSettings->focDiscountPercentage();
                }
                $discountOrderItemIds = collect($validated['discount_order_item_ids'] ?? [])->map(fn ($id): int => (int) $id)->unique()->values();
                // Mode item → pakai discount_item_type; mode %/nominal lama → per-item default semua item.
                $discountItemType = $validated['discount_item_type'] ?? ($discountType === 'percentage' ? 'percentage' : ($discountType === 'nominal' ? 'nominal' : null));
                $discountItemValue = $discountItemType === 'nominal' && filled($validated['discount_item_value'] ?? null)
                    ? (float) $validated['discount_item_value']
                    : ($discountItemType === 'percentage' && filled($validated['discount_item_value'] ?? null)
                        ? (float) $validated['discount_item_value']
                        : ($discountType === 'nominal' ? (float) ($validated['discount_nominal'] ?? 0) : (float) ($validated['discount_percentage'] ?? 0)));
                // Map id→nilai (nilai berbeda per item); fallback legacy bila map kosong.
                $discountItemValues = $this->normalizeDiscountItems($validated['discount_items'] ?? [], $discountOrderItemIds->all(), $discountItemValue);

                $activeItems = $session->orders
                    ->where('status', '!=', 'cancelled')
                    ->flatMap(fn ($order) => $order->items)
                    ->where('status', '!=', 'cancelled');
                $discountItemIdsMap = array_keys($discountItemValues);
                $selectedItems = $activeItems->whereIn('id', $discountItemIdsMap)->values();

                // Validasi kepemilikan item diskon: semua id harus dari billing aktif.
                if ($discountItemIdsMap !== [] && $selectedItems->count() !== count($discountItemIdsMap)) {
                    throw ValidationException::withMessages([
                        'discount_order_item_ids' => 'Item diskon harus berasal dari billing aktif ini.',
                    ]);
                }

                // FOC/Compliment → semua item bulk; biasa → item terpilih (default semua bila tidak ada id).
                $targetItems = $isFocComp ? $activeItems : ($selectedItems->isNotEmpty() ? $selectedItems : $activeItems);

                $itemDiscountSum = 0.0;
                $discountedOrderIds = [];
                foreach ($targetItems as $item) {
                    $old = (float) $item->discount_amount;
                    $subtotal = (float) $item->subtotal;

                    if ($isFocComp) {
                        $pct = (float) $discountPercentage;
                        $new = $pct > 0 ? round($subtotal * $pct / 100, 2) : 0.0;
                    } elseif ($discountItemType === 'percentage') {
                        $pct = min(max((float) ($discountItemValues[$item->id] ?? $discountItemValue), 0), 100);
                        $new = $pct > 0 ? round($subtotal * $pct / 100, 2) : 0.0;
                    } elseif ($discountItemType === 'nominal') {
                        $new = min((float) ($discountItemValues[$item->id] ?? $discountItemValue), $subtotal);
                        $pct = $subtotal > 0 ? round($new / $subtotal * 100, 2) : 0.0;
                    } else {
                        $pct = 0.0;
                        $new = 0.0;
                    }

                    $item->update([
                        'is_discount' => $new > 0,
                        'discount_pct' => $new > 0 ? $pct : 0,
                        'discount_amount' => $new,
                    ]);
                    $itemDiscountSum += $new - $old; // increment — hindari double count item yang sudah is_discount
                    $discountedOrderIds[$item->order_id] = true;
                }

                // Recalculate order totals agar subtotal/billing jadi net (tax/service atas nilai setelah diskon).
                $session->orders
                    ->whereIn('id', array_keys($discountedOrderIds))
                    ->each(fn (Order $order) => $order->updateTotals());
                $session->load('orders.items.inventoryItem');

                $baseTotals = $this->calculateSessionBillingTotals(
                    $session,
                    0,
                    (float) $billing->minimum_charge,
                    (float) ($booking->down_payment_amount ?? 0),
                );

                $discountBaseTotal = (float) ($baseTotals['discount_base_total'] ?? $baseTotals['grand_total_before_down_payment']);

                // Per-item/FOC: diskon = jumlah rupiah item. Nominal global (tanpa item): clamp ke base.
                $requestedDiscountAmount = ($discountItemIdsMap !== [] || $isFocComp || $discountItemType !== null)
                    ? max($itemDiscountSum, 0)
                    : min(max((float) ($discountType === 'nominal' ? $discountNominal : round($discountBaseTotal * $discountPercentage / 100, 2)), 0), $discountBaseTotal);

                // Auth code: regular discount selalu; FOC/Compliment sesuai setting (tidak otomatis).
                $focTypeRequiresAuth = ($focCompPaymentMethod === 'FOC' && $generalSettings->focRequiresAuthCode())
                    || ($focCompPaymentMethod === 'Compliment' && $generalSettings->complimentRequiresAuthCode());

                $requiresAuthCode = $focTypeRequiresAuth || (! $isFocComp && $requestedDiscountAmount > 0);

                if ($requiresAuthCode) {
                    // FOC/Compliment pakai field auth sendiri; diskon biasa pakai discount_auth_code.
                    $authCode = $isFocComp ? $focCompAuthCode : $discountAuthCode;

                    if ($authCode === '') {
                        throw ValidationException::withMessages([
                            $isFocComp ? 'foc_comp_auth_code' : 'discount_auth_code' => $isFocComp
                                ? 'Auth code wajib diisi untuk FOC, Compliment.'
                                : 'Auth code wajib diisi untuk diskon.',
                        ]);
                    }

                    $today = now()->format('Y-m-d');
                    $authRecord = DailyAuthCode::forDate($today);

                    if ($authCode !== $authRecord->active_code) {
                        throw ValidationException::withMessages([
                            $isFocComp ? 'foc_comp_auth_code' : 'discount_auth_code' => $isFocComp
                                ? 'Auth code FOC / Compliment tidak valid.'
                                : 'Auth code diskon tidak valid.',
                        ]);
                    }
                }

                // Percentage per-item: subtotal sudah net (updateTotals), diskon dilaporkan
                // terpisah (tidak dikurang dua kali). Nominal global: dikurangkan dari grand total.
                // Item-based (per-item/FOC/nominal-with-items): subtotal sudah net via updateTotals,
                // diskon hanya dilaporkan (pass 0). Nominal global tanpa item: diskon transaksi dikurang.
                $isItemBasedDiscount = $discountItemIdsMap !== [] || $isFocComp || $discountItemType !== null;
                $totals = $this->calculateSessionBillingTotals(
                    $session,
                    $isItemBasedDiscount ? 0 : $requestedDiscountAmount,
                    (float) $billing->minimum_charge,
                    (float) ($booking->down_payment_amount ?? 0),
                );
                $totals['discount_amount'] = $requestedDiscountAmount;

                $billingSequence = Billing::query()
                    ->where('is_booking', true)
                    ->whereDate('created_at', today())
                    ->count() + 1;
                $transactionCode = 'BILLING-'.str_pad((string) $billingSequence, 6, '0', STR_PAD_LEFT);

                $paymentMode = $validated['payment_mode'];
                $paymentMethod = $paymentMode === 'split'
                    ? null
                    : $validated['payment_method'];
                $focCompPaymentMethod = $validated['foc_comp_payment_method'] ?? null;

                // FOC/Compliment → payment_method otomatis, tanpa metode normal.
                if (in_array($focCompPaymentMethod, ['FOC', 'Compliment'], true)) {
                    $paymentMethod = $focCompPaymentMethod;
                }

                $paymentReferenceNumber = $paymentMode === 'normal'
                    ? (in_array($paymentMethod, ['cash', 'FOC', 'Compliment'], true) ? null : ($validated['payment_reference_number'] ?? null))
                    : null;

                if ($paymentMode === 'normal' && ! in_array($paymentMethod, ['cash', 'FOC', 'Compliment'], true) && blank($paymentReferenceNumber)) {
                    throw ValidationException::withMessages([
                        'payment_reference_number' => 'Nomor referensi pembayaran non-cash wajib diisi.',
                    ]);
                }

                $splitCashAmount = null;
                $splitDebitAmount = null;
                $splitNonCashMethod = null;
                $splitNonCashReferenceNumber = null;
                $splitSecondNonCashAmount = null;
                $splitSecondNonCashMethod = null;
                $splitSecondNonCashReferenceNumber = null;

                if ($paymentMode === 'split') {
                    $splitCashAmount = (float) ($validated['split_cash_amount'] ?? 0);
                    $splitDebitAmount = (float) ($validated['split_non_cash_amount'] ?? 0);
                    $splitNonCashMethod = $validated['split_non_cash_method'] ?? null;
                    $splitNonCashReferenceNumber = $validated['split_non_cash_reference_number'] ?? null;
                    $splitSecondNonCashAmount = (float) ($validated['split_second_non_cash_amount'] ?? 0);
                    $splitSecondNonCashMethod = $validated['split_second_non_cash_method'] ?? null;
                    $splitSecondNonCashReferenceNumber = $validated['split_second_non_cash_reference_number'] ?? null;
                    $grandTotal = round((float) $totals['grand_total'], 0);
                    $splitTotal = round($splitCashAmount + $splitDebitAmount + $splitSecondNonCashAmount, 0);
                    $activeNonCashCount = collect([
                        ['amount' => $splitDebitAmount, 'method' => $splitNonCashMethod, 'reference' => $splitNonCashReferenceNumber],
                        ['amount' => $splitSecondNonCashAmount, 'method' => $splitSecondNonCashMethod, 'reference' => $splitSecondNonCashReferenceNumber],
                    ])->filter(fn (array $entry): bool => (float) $entry['amount'] > 0)->count();

                    $hasCash = $splitCashAmount > 0;

                    if (! $hasCash && $activeNonCashCount < 2) {
                        throw ValidationException::withMessages([
                            'split_total' => 'Untuk split non-cash + non-cash, isi dua nominal non-cash lebih dari 0.',
                        ]);
                    }

                    if ($hasCash && $activeNonCashCount < 1) {
                        throw ValidationException::withMessages([
                            'split_total' => 'Untuk split cash + non-cash, minimal satu nominal non-cash harus lebih dari 0.',
                        ]);
                    }

                    if ($activeNonCashCount === 0) {
                        throw ValidationException::withMessages([
                            'split_total' => 'Split bill memerlukan minimal satu pembayaran non-cash.',
                        ]);
                    }

                    if ($splitCashAmount < 0 || $splitDebitAmount < 0 || $splitSecondNonCashAmount < 0) {
                        throw ValidationException::withMessages([
                            'split_total' => 'Nominal split bill tidak boleh minus.',
                        ]);
                    }

                    if (abs($splitTotal - $grandTotal) > 0.01) {
                        $isDiscountApplied = $requestedDiscountAmount > 0;

                        if ($isDiscountApplied && $splitCashAmount > 0 && $splitCashAmount < $grandTotal && $splitSecondNonCashAmount <= 0) {
                            $splitDebitAmount = round($grandTotal - $splitCashAmount, 0);
                            $splitTotal = round($splitCashAmount + $splitDebitAmount + $splitSecondNonCashAmount, 0);
                        }
                    }

                    if (abs($splitTotal - $grandTotal) > 0.01) {
                        throw ValidationException::withMessages([
                            'split_total' => 'Total pembayaran split harus sama dengan grand total.',
                        ]);
                    }

                    if ($splitDebitAmount > 0 && blank($splitNonCashMethod)) {
                        throw ValidationException::withMessages([
                            'split_non_cash_method' => 'Metode non-cash pertama untuk split bill wajib dipilih.',
                        ]);
                    }

                    if ($splitDebitAmount > 0 && blank($splitNonCashReferenceNumber)) {
                        throw ValidationException::withMessages([
                            'split_non_cash_reference_number' => 'Nomor referensi non-cash pertama untuk split bill wajib diisi.',
                        ]);
                    }

                    if ($splitSecondNonCashAmount > 0 && blank($splitSecondNonCashMethod)) {
                        throw ValidationException::withMessages([
                            'split_second_non_cash_method' => 'Metode non-cash kedua untuk split bill wajib dipilih.',
                        ]);
                    }

                    if ($splitSecondNonCashAmount > 0 && blank($splitSecondNonCashReferenceNumber)) {
                        throw ValidationException::withMessages([
                            'split_second_non_cash_reference_number' => 'Nomor referensi non-cash kedua untuk split bill wajib diisi.',
                        ]);
                    }
                }

                $isPartialPayment = in_array($paymentMode, ['partial', 'debt'], true);
                $partialPaidAmount = (float) ($validated['partial_paid_amount'] ?? 0);

                if ($isPartialPayment) {
                    if ($partialPaidAmount <= 0 || $partialPaidAmount >= (float) $totals['grand_total']) {
                        throw ValidationException::withMessages([
                            'partial_paid_amount' => 'Nominal bayar sebagian/DP harus lebih besar dari 0 dan kurang dari total tagihan.',
                        ]);
                    }
                    $paidAmount = $partialPaidAmount;
                    $remainingBalance = max(0, (float) $totals['grand_total'] - $partialPaidAmount);
                    $billingStatus = 'partially_paid';
                    $isDebt = true;
                } else {
                    $paidAmount = (float) $totals['grand_total'];
                    $remainingBalance = 0;
                    $billingStatus = 'paid';
                    $isDebt = false;
                }

                $billing->update([
                    'area_id' => $billing->area_id ?? $booking->table?->area_id ?? auth()->user()?->resolveActiveArea()?->id,
                    'orders_total' => (float) $totals['orders_total'],
                    'subtotal' => (float) $totals['subtotal'],
                    'discount_amount' => (float) $totals['discount_amount'],
                    'tax_percentage' => (float) $totals['tax_percentage'],
                    'tax' => (float) $totals['tax'],
                    'service_charge_percentage' => (float) $totals['service_charge_percentage'],
                    'service_charge' => (float) $totals['service_charge'],
                    'grand_total' => (float) $totals['grand_total'],
                    'paid_amount' => $paidAmount,
                    'remaining_balance' => $remainingBalance,
                    'is_debt' => $isDebt,
                    'is_parsial_payment' => $isPartialPayment,
                    'billing_status' => $billingStatus,
                    'paid_at' => now('Asia/Jakarta'),
                    'transaction_code' => $transactionCode,
                    'payment_method' => $paymentMethod,
                    'foc_comp_payment_method' => $focCompPaymentMethod,
                    'payment_reference_number' => $paymentReferenceNumber,
                    'payment_mode' => $paymentMode,
                    'split_cash_amount' => $splitCashAmount,
                    'split_debit_amount' => $splitDebitAmount,
                    'split_non_cash_method' => $splitNonCashMethod,
                    'split_non_cash_reference_number' => $splitNonCashReferenceNumber,
                    'split_second_non_cash_amount' => $splitSecondNonCashAmount,
                    'split_second_non_cash_method' => $splitSecondNonCashMethod,
                    'split_second_non_cash_reference_number' => $splitSecondNonCashReferenceNumber,
                ]);

                \App\Models\BillingPayment::create([
                    'billing_id' => $billing->id,
                    'amount_paid' => $paidAmount,
                    'payment_method' => $paymentMethod ?? 'cash',
                    'payment_reference_number' => $paymentReferenceNumber,
                    'payment_type' => $isDebt ? 'initial_partial' : 'full_payment',
                    'notes' => $isDebt ? 'Pembayaran awal DP/Parsial' : 'Pembayaran lunas saat close billing',
                    'created_by' => auth()->id(),
                    'paid_at' => now('Asia/Jakarta'),
                ]);

                $customerUser = CustomerUser::query()
                    ->where('user_id', $booking->customer_id)
                    ->lockForUpdate()
                    ->first();

                if ($customerUser) {
                    $customerUser->increment('total_visits');

                    // FOC/Compliment tidak masuk spending (bukan revenue).
                    if (! in_array((string) ($focCompPaymentMethod ?? ''), ['FOC', 'Compliment'], true)) {
                        $customerUser->increment('lifetime_spending', (float) $totals['grand_total']);
                    }
                }

                $session->update([
                    'checked_out_at' => now(),
                    'status' => 'completed',
                ]);

                TableSession::query()
                    ->where('table_reservation_id', $booking->id)
                    ->where('status', 'active')
                    ->where('id', '!=', $session->id)
                    ->update([
                        'checked_out_at' => now(),
                        'status' => 'completed',
                    ]);

                $booking->update(['status' => 'completed']);

                $tableIdsToSync = collect([
                    $booking->table_id,
                    $session->table_id,
                ])->filter()->unique()->values();

                foreach ($tableIdsToSync as $tableId) {
                    $hasOtherActiveSession = TableSession::query()
                        ->where('table_id', $tableId)
                        ->where('status', 'active')
                        ->exists();

                    Tabel::query()
                        ->where('id', $tableId)
                        ->update(['status' => $hasOtherActiveSession ? 'occupied' : 'available']);
                }
            });

            $billing->refresh();
            $session->load('orders.items');

            try {
                $this->dashboardSyncService->sync();
            } catch (\Throwable $e) {
                Log::warning('Dashboard sync failed after close billing', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $receiptPrinted = $this->printClosedBillingReceipt($session, $billing);

            // Build items list from all orders in the session
            $allItems = $session->orders->flatMap(fn ($order) => $order->items)->groupBy('item_name')->map(function ($group) {
                $first = $group->first();

                return [
                    'name' => $first->item_name,
                    'qty' => $group->sum('quantity'),
                    'price' => (float) $first->price,
                    'subtotal' => $group->sum('subtotal'),
                    'discount_amount' => $group->sum('discount_amount'),
                ];
            })->values();

            $customerName = $booking->customer->profile->name ?? $booking->customer->customerUser->name ?? $booking->customer->name ?? '-';

            // Push to Accurate: Sales Order + Sales Invoice (non-blocking)
            $this->pushBillingToAccurate($booking, $session, $billing);

            return response()->json([
                'success' => true,
                'message' => 'Billing berhasil ditutup',
                'receipt_printed' => $receiptPrinted,
                'receipt' => [
                    'transaction_code' => $billing->transaction_code,
                    'date' => now()->format('d M Y H:i'),
                    'cashier' => auth()->user()->name,
                    'customer_name' => $customerName,
                    'table' => $booking->table?->table_number ?? '-',
                    'items' => $allItems,
                    'minimum_charge' => (float) $billing->minimum_charge,
                    'orders_total' => (float) $billing->orders_total,
                    'subtotal' => (float) $billing->subtotal,
                    'tax' => (float) $billing->tax,
                    'tax_percentage' => (float) $billing->tax_percentage,
                    'service_charge' => (float) $billing->service_charge,
                    'service_charge_percentage' => (float) $billing->service_charge_percentage,
                    'discount_amount' => (float) $billing->discount_amount,
                    'down_payment_amount' => (float) ($booking->down_payment_amount ?? 0),
                    'grand_total' => (float) $billing->grand_total,
                    'payment_mode' => strtoupper($billing->payment_mode ?? 'NORMAL'),
                    'payment_method' => strtoupper($billing->payment_method ?? ($billing->payment_mode === 'split' ? 'split' : '-')),
                    'payment_reference_number' => $billing->payment_reference_number,
                    'split_cash_amount' => (float) ($billing->split_cash_amount ?? 0),
                    'split_debit_amount' => (float) ($billing->split_debit_amount ?? 0),
                    'split_non_cash_method' => strtoupper((string) ($billing->split_non_cash_method ?? '')),
                    'split_non_cash_reference_number' => $billing->split_non_cash_reference_number,
                    'split_second_non_cash_amount' => (float) ($billing->split_second_non_cash_amount ?? 0),
                    'split_second_non_cash_method' => strtoupper((string) ($billing->split_second_non_cash_method ?? '')),
                    'split_second_non_cash_reference_number' => $billing->split_second_non_cash_reference_number,
                ],
                'receipt_url' => route('admin.bookings.receipt', $booking->id),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->validator->errors()->first() ?: 'Data pembayaran tidak valid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menutup billing: '.$e->getMessage()], 500);
        }
    }

    public function discountItems(TableReservation $booking): JsonResponse
    {
        $session = TableSession::query()
            ->with('orders.items.inventoryItem')
            ->where('table_reservation_id', $booking->id)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if (! $session) {
            return response()->json(['items' => []]);
        }

        $items = $session->orders
            ->where('status', '!=', 'cancelled')
            ->flatMap(fn (Order $order) => $order->items)
            ->where('status', '!=', 'cancelled')
            ->map(fn (OrderItem $item): array => [
                'id' => $item->id,
                'name' => $item->item_name,
                'quantity' => $item->quantity,
                'subtotal' => (float) $item->subtotal,
                'discount_amount' => (float) $item->discount_amount,
                'include_tax' => (bool) ($item->inventoryItem?->include_tax ?? true),
                'include_service_charge' => (bool) ($item->inventoryItem?->include_service_charge ?? true),
            ])
            ->values();

        return response()->json(['items' => $items]);
    }

    public function reSyncAccurate(TableReservation $booking)
    {
        Log::info('Re-sync Accurate triggered for booking', [
            'booking_id' => $booking->id,
            'user_id' => auth()->id(),
        ]);

        $booking->loadMissing([
            'table.area',
            'tableSession.orders.items.inventoryItem',
            'tableSession.billing',
        ]);

        $session = $booking->tableSession;
        $billing = $session?->billing;

        if (! $session || ! $billing) {
            Log::warning('Re-sync Accurate failed: billing missing', ['booking_id' => $booking->id]);

            return back()->with('error', 'Billing tidak ditemukan untuk booking ini.');
        }

        if ($billing->accurate_so_number && $billing->accurate_inv_number) {
            return back()->with('success', 'SO dan Invoice Accurate sudah tersedia.');
        }

        $this->pushBillingToAccurate($booking, $session, $billing);
        $billing->refresh();

        if (! $billing->accurate_so_number || ! $billing->accurate_inv_number) {
            Log::error('Re-sync Accurate failed', [
                'booking_id' => $booking->id,
                'billing_id' => $billing->id,
                'error_message' => $billing->error_message,
            ]);

            return back()->with('error', $billing->error_message ?: 'Re-sync ke Accurate gagal. Silakan coba lagi.');
        }

        Log::info('Re-sync Accurate successful', [
            'booking_id' => $booking->id,
            'so_number' => $billing->accurate_so_number,
            'inv_number' => $billing->accurate_inv_number,
        ]);

        return back()->with('success', 'Re-sync Accurate berhasil.');
    }

    public function updateHistoryPayment(Request $request, TableReservation $booking)
    {
        $validated = $request->validate([
            'payment_mode' => 'required|in:normal,split',
            'payment_method' => 'required_if:payment_mode,normal|nullable|in:cash,kredit,debit,qris,transfer',
            'payment_reference_number' => 'nullable|string|max:100',
            'split_cash_amount' => 'nullable|numeric|min:0',
            'split_non_cash_amount' => 'nullable|numeric|min:0',
            'split_non_cash_method' => 'nullable|in:debit,kredit,qris,transfer,ewallet,lainnya',
            'split_non_cash_reference_number' => 'nullable|string|max:100',
            'split_second_non_cash_amount' => 'nullable|numeric|min:0',
            'split_second_non_cash_method' => 'nullable|in:debit,kredit,qris,transfer,ewallet,lainnya',
            'split_second_non_cash_reference_number' => 'nullable|string|max:100',
        ]);

        $booking->loadMissing('tableSession.billing');
        $billing = $booking->tableSession?->billing;

        if (! $billing) {
            return response()->json([
                'success' => false,
                'message' => 'Billing tidak ditemukan.',
            ], 404);
        }

        if ($billing->billing_status !== 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Payment hanya bisa diedit untuk billing yang sudah dibayar.',
            ], 422);
        }

        try {
            $paymentMode = (string) $validated['payment_mode'];
            $paymentMethod = null;
            $paymentReferenceNumber = null;
            $splitCashAmount = null;
            $splitDebitAmount = null;
            $splitNonCashMethod = null;
            $splitNonCashReferenceNumber = null;
            $splitSecondNonCashAmount = null;
            $splitSecondNonCashMethod = null;
            $splitSecondNonCashReferenceNumber = null;

            if ($paymentMode === 'normal') {
                $paymentMethod = (string) $validated['payment_method'];
                $paymentReferenceNumber = $paymentMethod === 'cash'
                    ? null
                    : ((string) ($validated['payment_reference_number'] ?? ''));

                if ($paymentMethod !== 'cash' && blank($paymentReferenceNumber)) {
                    throw ValidationException::withMessages([
                        'payment_reference_number' => 'Nomor referensi pembayaran non-cash wajib diisi.',
                    ]);
                }
            }

            if ($paymentMode === 'split') {
                $splitCashAmount = (float) ($validated['split_cash_amount'] ?? 0);
                $splitDebitAmount = (float) ($validated['split_non_cash_amount'] ?? 0);
                $splitNonCashMethod = $validated['split_non_cash_method'] ?? null;
                $splitNonCashReferenceNumber = $validated['split_non_cash_reference_number'] ?? null;
                $splitSecondNonCashAmount = (float) ($validated['split_second_non_cash_amount'] ?? 0);
                $splitSecondNonCashMethod = $validated['split_second_non_cash_method'] ?? null;
                $splitSecondNonCashReferenceNumber = $validated['split_second_non_cash_reference_number'] ?? null;

                $requiresReferenceNumber = static function (?string $method): bool {
                    $normalizedMethod = strtolower(trim((string) $method));

                    return $normalizedMethod !== '' && ! in_array($normalizedMethod, ['cash', 'tunai'], true);
                };

                $grandTotal = round((float) $billing->grand_total, 0);
                $splitTotal = round($splitCashAmount + $splitDebitAmount + $splitSecondNonCashAmount, 0);

                $activeNonCashCount = collect([
                    ['amount' => $splitDebitAmount, 'method' => $splitNonCashMethod, 'reference' => $splitNonCashReferenceNumber],
                    ['amount' => $splitSecondNonCashAmount, 'method' => $splitSecondNonCashMethod, 'reference' => $splitSecondNonCashReferenceNumber],
                ])->filter(fn (array $entry): bool => (float) $entry['amount'] > 0)->count();

                $hasCash = $splitCashAmount > 0;

                if (! $hasCash && $activeNonCashCount < 2) {
                    throw ValidationException::withMessages([
                        'split_total' => 'Untuk split non-cash + non-cash, isi dua nominal non-cash lebih dari 0.',
                    ]);
                }

                if ($hasCash && $activeNonCashCount < 1) {
                    throw ValidationException::withMessages([
                        'split_total' => 'Untuk split cash + non-cash, minimal satu nominal non-cash harus lebih dari 0.',
                    ]);
                }

                if ($activeNonCashCount === 0) {
                    throw ValidationException::withMessages([
                        'split_total' => 'Split bill memerlukan minimal satu pembayaran non-cash.',
                    ]);
                }

                if (abs($splitTotal - $grandTotal) > 0.01) {
                    throw ValidationException::withMessages([
                        'split_total' => 'Total pembayaran split harus sama dengan grand total.',
                    ]);
                }

                if ($splitDebitAmount > 0 && blank($splitNonCashMethod)) {
                    throw ValidationException::withMessages([
                        'split_non_cash_method' => 'Metode non-cash pertama untuk split bill wajib dipilih.',
                    ]);
                }

                if ($splitDebitAmount > 0 && $requiresReferenceNumber($splitNonCashMethod) && blank($splitNonCashReferenceNumber)) {
                    throw ValidationException::withMessages([
                        'split_non_cash_reference_number' => 'Nomor referensi non-cash pertama untuk split bill wajib diisi.',
                    ]);
                }

                if ($splitSecondNonCashAmount > 0 && blank($splitSecondNonCashMethod)) {
                    throw ValidationException::withMessages([
                        'split_second_non_cash_method' => 'Metode non-cash kedua untuk split bill wajib dipilih.',
                    ]);
                }

                if ($splitSecondNonCashAmount > 0 && $requiresReferenceNumber($splitSecondNonCashMethod) && blank($splitSecondNonCashReferenceNumber)) {
                    throw ValidationException::withMessages([
                        'split_second_non_cash_reference_number' => 'Nomor referensi non-cash kedua untuk split bill wajib diisi.',
                    ]);
                }
            }

            $billing->update([
                'payment_mode' => $paymentMode,
                'payment_method' => $paymentMethod,
                'payment_reference_number' => $paymentReferenceNumber,
                'split_cash_amount' => $splitCashAmount,
                'split_debit_amount' => $splitDebitAmount,
                'split_non_cash_method' => $splitNonCashMethod,
                'split_non_cash_reference_number' => $splitNonCashReferenceNumber,
                'split_second_non_cash_amount' => $splitSecondNonCashAmount,
                'split_second_non_cash_method' => $splitSecondNonCashMethod,
                'split_second_non_cash_reference_number' => $splitSecondNonCashReferenceNumber,
            ]);

            try {
                $this->dashboardSyncService->sync();
            } catch (\Throwable $e) {
                Log::warning('Dashboard sync failed after history payment update', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment berhasil diperbarui.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->validator->errors()->first() ?: 'Data payment tidak valid.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    protected function calculateBookingMinimumCharge(float $baseMinimumCharge, mixed $reservationDate, ?int $areaId = null): float
    {
        $activeEvent = $this->resolveActiveEventForDate($reservationDate, $areaId);

        if (! $activeEvent) {
            return $baseMinimumCharge;
        }

        return $this->applyEventAdjustmentToMinimumCharge($baseMinimumCharge, $activeEvent);
    }

    protected function resolveActiveEventForDate(mixed $reservationDate, ?int $areaId = null): ?Event
    {
        if (blank($reservationDate)) {
            return null;
        }

        $dateString = $reservationDate instanceof \Carbon\Carbon
            ? $reservationDate->toDateString()
            : (string) $reservationDate;

        return Event::query()
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $dateString)
            ->whereDate('end_date', '>=', $dateString)
            ->when($areaId, function ($q) use ($areaId) {
                $q->where(function ($sub) use ($areaId) {
                    $sub->whereNull('area_id')->orWhere('area_id', $areaId);
                });
            }, function ($q) {
                $q->whereNull('area_id');
            })
            ->orderByRaw('CASE WHEN area_id IS NOT NULL THEN 1 ELSE 2 END')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();
    }

    protected function applyEventAdjustmentToMinimumCharge(float $baseMinimumCharge, Event $event): float
    {
        return match ($event->price_adjustment_type) {
            'percentage' => round($baseMinimumCharge * (1 + ((float) $event->price_adjustment_value / 100)), 2),
            'fixed' => round($baseMinimumCharge + (float) $event->price_adjustment_value, 2),
            default => $baseMinimumCharge,
        };
    }

    protected function calculateActiveBookingItemSubtotal(TableReservation $booking): float
    {
        $tableSession = $booking->tableSession;

        if (! $tableSession) {
            return 0;
        }

        return (float) $tableSession->orders
            ->flatMap->items
            ->where('status', '!=', 'cancelled')
            ->sum(fn ($item) => (float) ($item->subtotal ?? 0) + (float) ($item->tax_amount ?? 0) + (float) ($item->service_charge_amount ?? 0));
    }

    /**
     * @return array<string, float>
     */
    protected function calculateSessionBillingTotals(TableSession $session, float $discountAmount, float $minimumCharge, float $downPaymentAmount = 0): array
    {
        $settings = GeneralSetting::instance();
        $orders = $session->orders
            ->where('status', '!=', 'cancelled')
            ->values();

        $ordersTotal = (float) $orders->sum(fn ($order) => (float) ($order->total ?? 0));
        $subtotal = max($minimumCharge, $ordersTotal);

        $bases = $this->resolveSessionChargeableBases($orders);

        $taxRate = ((float) $settings->tax_percentage) / 100;
        $serviceChargeRate = ((float) $settings->service_charge_percentage) / 100;

        $tax = round(max($bases['tax_base'], 0) * $taxRate, 2);

        $serviceChargeBaseWithTax = max($bases['service_charge_base'], 0);
        if ($taxRate > 0) {
            $serviceChargeBaseWithTax += max($bases['tax_and_service_base'], 0) * $taxRate;
        }

        $serviceCharge = round($serviceChargeBaseWithTax * $serviceChargeRate, 2);
        $discountBaseTotal = $subtotal + $serviceCharge + $tax;
        $discountAmount = min(max($discountAmount, 0), $discountBaseTotal);
        $subtotalAfterDiscount = max($subtotal - min($discountAmount, $subtotal), 0);
        $grandTotalBeforeDownPayment = max($discountBaseTotal - $discountAmount, 0);
        $downPaymentAmount = min(max($downPaymentAmount, 0), $grandTotalBeforeDownPayment);

        return [
            'orders_total' => $ordersTotal,
            'minimum_charge' => $minimumCharge,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'subtotal_after_discount' => $subtotalAfterDiscount,
            'discount_base_total' => $discountBaseTotal,
            'service_charge_percentage' => (float) $settings->service_charge_percentage,
            'service_charge' => $serviceCharge,
            'tax_percentage' => (float) $settings->tax_percentage,
            'tax' => $tax,
            'down_payment_amount' => $downPaymentAmount,
            'grand_total_before_down_payment' => $grandTotalBeforeDownPayment,
            'grand_total' => max($grandTotalBeforeDownPayment - $downPaymentAmount, 0),
        ];
    }

    /**
     * @param  Collection<int, mixed>  $orders
     * @return array<string, float>
     */
    protected function resolveSessionChargeableBases(Collection $orders): array
    {
        $serviceChargeBase = 0;
        $taxBase = 0;
        $taxAndServiceBase = 0;

        foreach ($orders as $order) {
            $orderItems = $order->items->where('status', '!=', 'cancelled')->values();
            $orderNetTotal = (float) ($order->total ?? 0);

            if ($orderItems->isEmpty()) {
                $serviceChargeBase += max($orderNetTotal, 0);
                $taxBase += max($orderNetTotal, 0);
                $taxAndServiceBase += max($orderNetTotal, 0);

                continue;
            }

            $itemsSubtotal = (float) $orderItems->sum(fn ($item) => (float) ($item->subtotal ?? 0));
            $ratio = $itemsSubtotal > 0 ? max($orderNetTotal, 0) / $itemsSubtotal : 0;

            foreach ($orderItems as $orderItem) {
                $itemNetSubtotal = (float) ($orderItem->subtotal ?? 0) * $ratio;
                $includeTax = (bool) ($orderItem->inventoryItem?->include_tax ?? true);
                $includeServiceCharge = (bool) ($orderItem->inventoryItem?->include_service_charge ?? true);

                if ($includeServiceCharge) {
                    $serviceChargeBase += $itemNetSubtotal;
                }

                if ($includeTax) {
                    $taxBase += $itemNetSubtotal;
                }

                if ($includeTax && $includeServiceCharge) {
                    $taxAndServiceBase += $itemNetSubtotal;
                }
            }
        }

        return [
            'service_charge_base' => $serviceChargeBase,
            'tax_base' => $taxBase,
            'tax_and_service_base' => $taxAndServiceBase,
        ];
    }

    protected function reconcileTableStatuses(): void
    {
        $occupiedTableIds = TableSession::query()
            ->where('status', 'active')
            ->pluck('table_id')
            ->filter()
            ->unique()
            ->values();

        $reservedTableIds = TableReservation::query()
            ->where('status', 'confirmed')
            ->pluck('table_id')
            ->filter()
            ->unique()
            ->reject(fn ($tableId) => $occupiedTableIds->contains($tableId))
            ->values();

        Tabel::query()
            ->whereIn('id', $occupiedTableIds)
            ->where('status', '!=', 'maintenance')
            ->update(['status' => 'occupied']);

        Tabel::query()
            ->whereIn('id', $reservedTableIds)
            ->where('status', '!=', 'maintenance')
            ->update(['status' => 'reserved']);

        Tabel::query()
            ->whereNotIn('id', $occupiedTableIds->merge($reservedTableIds)->unique()->values())
            ->whereIn('status', ['occupied', 'reserved'])
            ->where('status', '!=', 'maintenance')
            ->update(['status' => 'available']);
    }

    /**
     * Push a closed billing to Accurate as Sales Order + Sales Invoice.
     * All orders in the session are consolidated into a single SO and invoice.
     * Failures are logged but do not interrupt the close-billing response.
     */
    protected function pushBillingToAccurate(TableReservation $booking, $session, $billing): void
    {
        try {
            $customerNo = $this->ensureAccurateCustomer((int) $booking->customer_id);

            if (! $customerNo) {
                $billing->update([
                    'error_message' => 'Customer Accurate tidak ditemukan untuk booking ini.',
                ]);

                return;
            }

            $transDate = now()->format('d/m/Y');
            $reference = $billing->transaction_code;

            // Consolidate all order items across all orders in the session
            $session->loadMissing('orders.items.inventoryItem');
            $warehouseName = GeneralSetting::instance()->getAccurateWarehouseName();
            $billing = $session->billing;
            $discountAmount = (float) ($billing->discount_amount ?? 0);
            $itemsTotal = (float) $session->orders
                ->flatMap(fn ($order) => $order->items)
                ->where('status', '!=', 'cancelled')
                ->sum(fn ($item) => (float) $item->subtotal);
            $discountPercent = $itemsTotal > 0 ? round(($discountAmount / $itemsTotal) * 100, 2) : 0;

            $detailItem = $session->orders
                ->flatMap(fn ($order) => $order->items)
                ->where('status', '!=', 'cancelled')
                ->map(function ($item) use ($warehouseName, $discountPercent) {
                    return [
                        'itemNo' => $item->inventoryItem?->code ?? $item->item_code,
                        'quantity' => $item->quantity,
                        'unitPrice' => (float) $item->price,
                        'discountPercent' => $discountPercent,
                        'warehouseName' => $warehouseName,
                    ];
                })
                ->values()
                ->toArray();

            $taxAmount = (float) ($billing->tax ?? 0);
            $serviceChargeAmount = (float) ($billing->service_charge ?? 0);

            if (empty($detailItem)) {
                $billing->update([
                    'error_message' => 'Item order tidak ditemukan untuk dikirim ke Accurate.',
                ]);

                return;
            }

            // Generate SO number with format [SO_PREFIX][BILLING|WALKIN]-[YYYYMMDD]-[5 random digits]
            $area = $booking->table?->area ?? auth()->user()?->resolveActiveArea();
            $areaPrefix = $area ? $area->so_prefix : 'ROOM-';
            $randomNumber = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
            $dateString = now()->format('Ymd');
            $prefix = $billing->is_walk_in ? 'WALKIN' : 'BILLING';
            $soNumber = "{$areaPrefix}{$prefix}-{$dateString}-{$randomNumber}";

            // 1. Save Sales Order
            $soPayload = [
                'customerNo' => $customerNo,
                'transDate' => $transDate,
                'memo' => 'Booking POS — '.$reference,
                'number' => $soNumber,
                'detailItem' => $detailItem,
            ];

            if ($serviceChargeAmount > 0) {
                $soPayload['detailExpense'][] = [
                    'accountNo' => GeneralSetting::instance()->accurate_service_charge_account_no ?? '210202',
                    'expenseAmount' => $serviceChargeAmount,
                    'expenseName' => 'Service Charge',
                ];
            }

            if ($taxAmount > 0) {
                $soPayload['detailExpense'][] = [
                    'accountNo' => GeneralSetting::instance()->accurate_tax_account_no ?? '210201',
                    'expenseAmount' => $taxAmount,
                    'expenseName' => 'PB 1',
                ];
            }

            $soResult = $this->accurateService->saveSalesOrder($soPayload);

            $downPaymentAmount = (float) ($booking->down_payment_amount ?? 0);
            $downPaymentInvoiceNumber = null;

            if ($downPaymentAmount > 0) {
                $downPaymentPayload = [
                    'customerNo' => $customerNo,
                    'inputDownPayment' => $downPaymentAmount,
                    'invoiceDp' => true,
                    'orderDownPaymentNumber' => $soNumber,
                ];

                $downPaymentResult = $this->accurateService->saveSalesInvoice($downPaymentPayload);
                $downPaymentInvoiceNumber = $downPaymentResult['r']['number'] ?? $downPaymentResult['d']['number'] ?? null;

                if (! $downPaymentInvoiceNumber) {
                    throw new \RuntimeException('Invoice DP dari Accurate tidak mengembalikan nomor invoice.');
                }
            }

            // 2. Save Sales Invoice
            $invPayload = [
                'customerNo' => $customerNo,
                'transDate' => $transDate,
                'memo' => 'Booking POS — '.$reference,
                'number' => $soNumber,
                'detailItem' => array_map(
                    fn (array $item): array => array_merge($item, ['salesOrderNumber' => $soNumber]),
                    $detailItem
                ),
            ];

            if ($taxAmount > 0) {
                $invPayload['detailExpense'][] = [
                    'accountNo' => GeneralSetting::instance()->accurate_tax_account_no ?? '210201',
                    'expenseAmount' => $taxAmount,
                    'expenseName' => 'PB 1',
                ];
            }

            if ($serviceChargeAmount > 0) {
                $invPayload['detailExpense'][] = [
                    'accountNo' => GeneralSetting::instance()->accurate_service_charge_account_no ?? '210202',
                    'expenseAmount' => $serviceChargeAmount,
                    'expenseName' => 'Service Charge',
                ];
            }

            if ($downPaymentAmount > 0) {
                $invPayload['detailDownPayment'] = [
                    [
                        'paymentAmount' => $downPaymentAmount,
                        'invoiceNumber' => $downPaymentInvoiceNumber,
                    ],
                ];
            }
            $invResult = $this->accurateService->saveSalesInvoice($invPayload);
            $invNumber = $invResult['r']['number'] ?? $invResult['d']['number'] ?? $soNumber;

            // 3. Save Sales Receipt (Penerimaan Penjualan) for single or split payments
            $this->syncSalesReceipts($customerNo, $transDate, $soNumber, $invNumber, $reference, $billing);

            // 4. Persist Accurate numbers on the billing record
            $billing->update([
                'accurate_so_number' => $soNumber,
                'accurate_inv_number' => $invNumber,
                'error_message' => null,
            ]);
        } catch (\Exception $e) {
            $billing->update([
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Accurate Billing Sync: FAILED', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    protected function syncSalesReceipts(string $customerNo, string $transDate, string $soNumber, ?string $invNumber, string $reference, $billing, $order = null): void
    {
        $settings = GeneralSetting::instance();
        $cashAccountNo = $settings->accurate_cash_account_no ?: '110101';
        $bankAccountNo = $settings->accurate_bank_account_no ?: '110102';

        $paymentsToSync = [];

        $billingPayments = $billing?->payments ? $billing->payments : collect();

        if ($billingPayments->count() > 1) {
            foreach ($billingPayments as $paymentRecord) {
                $amount = (float) $paymentRecord->amount_paid;
                if ($amount <= 0) {
                    continue;
                }
                $method = strtolower((string) $paymentRecord->payment_method);
                $isCash = in_array($method, ['cash', 'tunai'], true);
                $bankNo = $isCash ? $cashAccountNo : $bankAccountNo;
                $methodLabel = $isCash ? 'Tunai' : strtoupper($method);

                $paymentsToSync[] = [
                    'amount' => $amount,
                    'bankNo' => $bankNo,
                    'method_label' => $methodLabel,
                ];
            }
        } elseif (($billing?->payment_mode ?? null) === 'split') {
            $splitCash = (float) ($billing->split_cash_amount ?? 0);
            $splitNonCash1Amount = (float) ($billing->split_debit_amount ?? 0);
            $splitNonCash1Method = strtolower((string) ($billing->split_non_cash_method ?? 'non_cash_1'));

            $splitNonCash2Amount = (float) ($billing->split_second_non_cash_amount ?? 0);
            $splitNonCash2Method = strtolower((string) ($billing->split_second_non_cash_method ?? 'non_cash_2'));

            if ($splitCash > 0) {
                $paymentsToSync[] = [
                    'amount' => $splitCash,
                    'bankNo' => $cashAccountNo,
                    'method_label' => 'Tunai',
                ];
            }

            if ($splitNonCash1Amount > 0) {
                $isCash1 = in_array($splitNonCash1Method, ['cash', 'tunai'], true);
                $bankNo1 = $isCash1 ? $cashAccountNo : $bankAccountNo;
                $methodLabel1 = $isCash1 ? 'Tunai' : strtoupper((string) ($billing->split_non_cash_method ?: 'NON-CASH 1'));

                $paymentsToSync[] = [
                    'amount' => $splitNonCash1Amount,
                    'bankNo' => $bankNo1,
                    'method_label' => $methodLabel1,
                ];
            }

            if ($splitNonCash2Amount > 0) {
                $isCash2 = in_array($splitNonCash2Method, ['cash', 'tunai'], true);
                $bankNo2 = $isCash2 ? $cashAccountNo : $bankAccountNo;
                $methodLabel2 = $isCash2 ? 'Tunai' : strtoupper((string) ($billing->split_second_non_cash_method ?: 'NON-CASH 2'));

                $paymentsToSync[] = [
                    'amount' => $splitNonCash2Amount,
                    'bankNo' => $bankNo2,
                    'method_label' => $methodLabel2,
                ];
            }
        } else {
            $paidAmount = (float) ($billing?->paid_amount ?? $billing?->grand_total ?? $order?->total ?? 0);
            if ($paidAmount > 0) {
                $method = strtolower((string) ($billing?->payment_method ?? $order?->payment_method ?? 'cash'));
                $isCash = in_array($method, ['cash', 'tunai'], true);
                $bankNo = $isCash ? $cashAccountNo : $bankAccountNo;
                $methodLabel = $isCash ? 'Tunai' : strtoupper($method);

                $paymentsToSync[] = [
                    'amount' => $paidAmount,
                    'bankNo' => $bankNo,
                    'method_label' => $methodLabel,
                ];
            }
        }

        $targetInvoiceNo = $invNumber ?: $soNumber ?: $billing?->accurate_inv_number ?: $billing?->accurate_so_number;

        foreach ($paymentsToSync as $paymentItem) {
            if ($paymentItem['amount'] <= 0) {
                continue;
            }

            try {
                $receiptPayload = [
                    'customerNo' => $customerNo,
                    'transDate' => $transDate,
                    'bankNo' => $paymentItem['bankNo'],
                    'chequeAmount' => $paymentItem['amount'],
                    'description' => "Pembayaran POS ({$paymentItem['method_label']}) — {$reference}",
                    'detailInvoice' => [
                        [
                            'invoiceNo' => $targetInvoiceNo,
                            'paymentAmount' => $paymentItem['amount'],
                        ],
                    ],
                ];
                $response = $this->accurateService->saveSalesReceipt($receiptPayload);
                $receiptNo = $response['r']['number'] ?? $response['d']['number'] ?? null;

                if ($receiptNo && $billing) {
                    $methodStr = strtolower((string) $paymentItem['method_label']);
                    $paymentRecord = \App\Models\BillingPayment::query()
                        ->where('billing_id', $billing->id)
                        ->where(function ($q) use ($methodStr, $paymentItem) {
                            $q->where('payment_method', $methodStr)
                                ->orWhere('amount_paid', $paymentItem['amount']);
                        })
                        ->whereNull('accurate_sales_receipt_number')
                        ->first();

                    if (! $paymentRecord) {
                        $paymentRecord = \App\Models\BillingPayment::query()
                            ->where('billing_id', $billing->id)
                            ->whereNull('accurate_sales_receipt_number')
                            ->first();
                    }

                    if ($paymentRecord) {
                        $paymentRecord->update([
                            'accurate_sales_receipt_number' => $receiptNo,
                            'accurate_sync_status' => 'synced',
                        ]);
                    } else {
                        \App\Models\BillingPayment::create([
                            'billing_id' => $billing->id,
                            'amount_paid' => $paymentItem['amount'],
                            'payment_method' => strtolower((string) $paymentItem['method_label']),
                            'payment_type' => 'full_payment',
                            'accurate_sales_receipt_number' => $receiptNo,
                            'accurate_sync_status' => 'synced',
                            'paid_at' => now('Asia/Jakarta'),
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Accurate Sales Receipt Sync: FAILED', [
                    'reference' => $reference,
                    'method' => $paymentItem['method_label'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function ensureAccurateCustomer(int $userId): ?string
    {
        $customerUser = CustomerUser::where('user_id', $userId)->first();
        if ($customerUser?->customer_code && $customerUser?->accurate_id) {
            return $customerUser->customer_code;
        }

        $user = User::find($userId);
        if (! $user) {
            return null;
        }

        $payload = [
            'name' => $user->name,
            'email' => $user->email,
        ];

        if ($customerUser?->accurate_id) {
            $payload['id'] = $customerUser->accurate_id;
        }

        try {
            $response = $this->accurateService->saveCustomer($payload);
            $accurateId = $response['r']['id'] ?? $response['d']['id'] ?? null;
            $customerNo = $response['r']['customerNo'] ?? $response['d']['customerNo'] ?? null;

            if ($customerNo) {
                if (! $customerUser) {
                    $profile = UserProfile::firstOrCreate(['user_id' => $user->id]);
                    $customerUser = CustomerUser::create([
                        'user_id' => $user->id,
                        'user_profile_id' => $profile->id,
                        'accurate_id' => $accurateId,
                        'customer_code' => $customerNo,
                        'total_visits' => 0,
                        'lifetime_spending' => 0,
                    ]);
                } else {
                    $customerUser->update([
                        'accurate_id' => $accurateId,
                        'customer_code' => $customerNo,
                    ]);
                }

                return $customerNo;
            }
        } catch (\Exception $e) {
            Log::error('Failed to auto-create Accurate customer in booking sync: '.$e->getMessage());
        }

        return null;
    }

    public function assignWaiter(Request $request, TableReservation $booking)
    {
        $validated = $request->validate([
            'waiter_id' => 'nullable|exists:users,id',
        ]);

        $session = $booking->tableSession;

        if (! $session) {
            return back()->withErrors(['error' => 'Sesi aktif tidak ditemukan untuk booking ini.']);
        }

        $previousWaiterId = $session->waiter_id;
        $newWaiterId = $validated['waiter_id'] ?? null;

        $session->update(['waiter_id' => $newWaiterId]);

        // Send notification to newly assigned waiter (not when unassigning)
        if ($newWaiterId && $newWaiterId !== $previousWaiterId) {
            $waiter = User::find($newWaiterId);
            $waiter?->notify(new \App\Notifications\WaiterAssignedNotification($booking->load(['table.area', 'customer.profile', 'customer.customerUser'])));
        }

        $waiterName = $newWaiterId
            ? (User::find($newWaiterId)?->profile?->name ?? User::find($newWaiterId)?->name ?? '-')
            : 'tidak ada';

        return back()->with('success', "Waiter berhasil di-assign: {$waiterName}.");
    }

    public function requestTableMove(Request $request, TableReservation $booking)
    {
        $validated = $request->validate([
            'new_table_id' => 'required|integer|exists:tables,id',
        ]);

        try {
            $oldTableNumber = (string) ($booking->table?->table_number ?? '-');
            $newTableNumber = '-';

            DB::transaction(function () use ($booking, $validated, &$newTableNumber): void {
                if (! in_array($booking->status, ['confirmed', 'checked_in'], true)) {
                    throw ValidationException::withMessages([
                        'new_table_id' => 'Pindah meja hanya bisa dilakukan untuk booking berstatus confirmed atau checked-in.',
                    ]);
                }

                $session = null;

                if ($booking->status === 'checked_in') {
                    $session = TableSession::query()
                        ->where('table_reservation_id', $booking->id)
                        ->where('status', 'active')
                        ->latest('id')
                        ->first();

                    if (! $session) {
                        throw ValidationException::withMessages([
                            'new_table_id' => 'Sesi aktif tidak ditemukan untuk booking checked-in.',
                        ]);
                    }
                }

                $newTableId = (int) $validated['new_table_id'];

                if ((int) $booking->table_id === $newTableId) {
                    throw ValidationException::withMessages([
                        'new_table_id' => 'Meja tujuan harus berbeda dari meja saat ini.',
                    ]);
                }

                $targetTable = Tabel::query()
                    ->where('id', $newTableId)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if (! $targetTable) {
                    throw ValidationException::withMessages([
                        'new_table_id' => 'Meja tujuan tidak aktif atau tidak ditemukan.',
                    ]);
                }

                if (! in_array($targetTable->status, ['available', 'reserved'], true)) {
                    throw ValidationException::withMessages([
                        'new_table_id' => 'Meja tujuan sedang tidak tersedia.',
                    ]);
                }

                $isUsedByAnotherActiveSession = TableSession::query()
                    ->where('table_id', $newTableId)
                    ->where('status', 'active')
                    ->where('table_reservation_id', '!=', $booking->id)
                    ->exists();

                if ($isUsedByAnotherActiveSession) {
                    throw ValidationException::withMessages([
                        'new_table_id' => 'Meja tujuan sedang dipakai oleh sesi aktif lain.',
                    ]);
                }

                $conflictingBookings = TableReservation::query()
                    ->where('table_id', $newTableId)
                    ->whereIn('status', ['confirmed', 'checked_in'])
                    ->where('id', '!=', $booking->id)
                    ->lockForUpdate()
                    ->get();

                if ($conflictingBookings->contains(fn (TableReservation $conflictBooking) => $conflictBooking->status === 'checked_in')) {
                    throw ValidationException::withMessages([
                        'new_table_id' => 'Meja tujuan sedang dipakai booking checked-in lain.',
                    ]);
                }

                $conflictingConfirmedIds = $conflictingBookings
                    ->where('status', 'confirmed')
                    ->pluck('id')
                    ->values();

                if ($conflictingConfirmedIds->isNotEmpty()) {
                    TableReservation::query()
                        ->whereIn('id', $conflictingConfirmedIds)
                        ->update(['status' => 'pending']);
                }

                $booking->update(['table_id' => $newTableId]);

                if ($session) {
                    $session->update(['table_id' => $newTableId]);
                }

                $newTableNumber = (string) ($targetTable->table_number ?? '-');
            });

            $this->reconcileTableStatuses();

            return back()->with('success', "Request pindah meja berhasil: {$oldTableNumber} → {$newTableNumber}.");
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()->withErrors([
                'new_table_id' => 'Gagal memproses request pindah meja. '.$e->getMessage(),
            ]);
        }
    }

    public function moveOrder(Request $request, TableReservation $booking)
    {
        $validated = $request->validate([
            'order_item_ids' => 'required|array|min:1',
            'order_item_ids.*' => 'integer|exists:order_items,id',
            'target_table_session_id' => 'required|integer|exists:table_sessions,id',
        ]);

        try {
            DB::transaction(function () use ($booking, $validated): void {
                $sourceSession = TableSession::query()
                    ->where('table_reservation_id', $booking->id)
                    ->where('status', 'active')
                    ->latest('id')
                    ->first();

                if (! $sourceSession) {
                    throw ValidationException::withMessages([
                        'order_item_ids' => 'Sesi aktif sumber tidak ditemukan.',
                    ]);
                }

                $selectedItemIds = collect($validated['order_item_ids'])
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                $selectedItems = OrderItem::query()
                    ->whereIn('id', $selectedItemIds)
                    ->whereHas('order', fn ($query) => $query->where('table_session_id', $sourceSession->id))
                    ->with('order')
                    ->lockForUpdate()
                    ->get();

                if ($selectedItems->count() !== $selectedItemIds->count()) {
                    throw ValidationException::withMessages([
                        'order_item_ids' => 'Sebagian item tidak ditemukan pada sesi aktif ini.',
                    ]);
                }

                if ($selectedItems->contains(fn (OrderItem $item) => $item->order?->status === 'cancelled')) {
                    throw ValidationException::withMessages([
                        'order_item_ids' => 'Item dari order berstatus cancelled tidak bisa dipindahkan.',
                    ]);
                }

                if ($selectedItems->contains(fn (OrderItem $item) => $item->status === 'cancelled')) {
                    throw ValidationException::withMessages([
                        'order_item_ids' => 'Item berstatus cancelled tidak bisa dipindahkan.',
                    ]);
                }

                $targetSession = TableSession::query()
                    ->where('id', (int) $validated['target_table_session_id'])
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();

                if (! $targetSession) {
                    throw ValidationException::withMessages([
                        'target_table_session_id' => 'Sesi tujuan tidak ditemukan atau sudah tidak aktif.',
                    ]);
                }

                if ((int) $targetSession->id === (int) $sourceSession->id) {
                    throw ValidationException::withMessages([
                        'target_table_session_id' => 'Sesi tujuan harus berbeda dari sesi asal.',
                    ]);
                }

                $firstSourceOrder = $selectedItems->first()?->order;

                $newOrder = Order::create([
                    'table_session_id' => $targetSession->id,
                    'created_by' => auth()->id() ?? $firstSourceOrder?->created_by,
                    'order_number' => $this->generateOrderNumber(),
                    'status' => 'pending',
                    'items_total' => 0,
                    'discount_amount' => 0,
                    'total' => 0,
                    'ordered_at' => now(),
                    'notes' => $firstSourceOrder?->notes,
                ]);

                OrderItem::query()
                    ->whereIn('id', $selectedItemIds)
                    ->update(['order_id' => $newOrder->id]);

                $newOrder->refresh();
                $newOrder->updateTotals();
                $newOrder->updateStatus();

                $affectedOrderIds = $selectedItems->pluck('order_id')->unique()->values();

                foreach ($affectedOrderIds as $affectedOrderId) {
                    $sourceOrder = Order::query()
                        ->where('id', (int) $affectedOrderId)
                        ->lockForUpdate()
                        ->first();

                    if (! $sourceOrder) {
                        continue;
                    }

                    $remainingItemsTotal = (float) $sourceOrder->items()->sum('subtotal');
                    $currentDiscount = (float) ($sourceOrder->discount_amount ?? 0);

                    if ($currentDiscount > $remainingItemsTotal) {
                        $sourceOrder->discount_amount = $remainingItemsTotal;
                        $sourceOrder->save();
                    }

                    $activeItemsCount = $sourceOrder->items()->where('status', '!=', 'cancelled')->count();

                    if ($activeItemsCount === 0) {
                        $sourceOrder->update([
                            'status' => 'cancelled',
                            'cancelled_at' => now(),
                            'cancelled_by' => auth()->id(),
                            'items_total' => 0,
                            'total' => 0,
                        ]);
                    } else {
                        $sourceOrder->updateTotals();
                        $sourceOrder->updateStatus();
                    }
                }
            });

            return back()->with('success', 'Item order berhasil dipindahkan dan dibuatkan order baru di sesi tujuan.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()->withErrors([
                'order_item_ids' => 'Gagal memindahkan item order. '.$e->getMessage(),
            ]);
        }
    }

    protected function generateOrderNumber(): string
    {
        $baseSequence = Order::query()
            ->whereDate('created_at', today())
            ->count() + 1;

        $attempt = 0;

        do {
            $sequence = $baseSequence + $attempt;
            $orderNumber = 'ORD-'.date('Ymd').'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $exists = Order::query()->where('order_number', $orderNumber)->exists();
            $attempt++;
        } while ($exists);

        return $orderNumber;
    }

    public function cancelOrder(Request $request, TableReservation $booking)
    {
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'cancel_auth_code' => 'required|string',
        ]);

        try {
            DB::transaction(function () use ($booking, $validated): void {
                $session = TableSession::query()
                    ->where('table_reservation_id', $booking->id)
                    ->where('status', 'active')
                    ->latest('id')
                    ->first();

                if (! $session) {
                    throw ValidationException::withMessages([
                        'order_id' => 'Sesi aktif tidak ditemukan untuk booking ini.',
                    ]);
                }

                $order = Order::query()
                    ->where('id', (int) $validated['order_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $order || (int) $order->table_session_id !== (int) $session->id) {
                    throw ValidationException::withMessages([
                        'order_id' => 'Order tidak ditemukan pada sesi aktif booking ini.',
                    ]);
                }

                if ($order->status !== 'pending') {
                    throw ValidationException::withMessages([
                        'order_id' => 'Hanya order berstatus pending yang bisa dibatalkan.',
                    ]);
                }

                $authCode = trim((string) ($validated['cancel_auth_code'] ?? ''));
                $today = now()->format('Y-m-d');
                $authRecord = DailyAuthCode::forDate($today);

                if ($authCode !== $authRecord->active_code) {
                    throw ValidationException::withMessages([
                        'cancel_auth_code' => 'Daily auth code tidak valid.',
                    ]);
                }

                $order->items()
                    ->where('status', '!=', 'cancelled')
                    ->update(['status' => 'cancelled']);

                $order->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_by' => auth()->id(),
                ]);
            });

            return back()->with('success', 'Order pending berhasil dibatalkan.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()->withErrors([
                'order_id' => 'Gagal membatalkan order. '.$e->getMessage(),
            ]);
        }
    }

    public function deleteOrderItem(Request $request, TableReservation $booking)
    {
        $validated = $request->validate([
            'order_item_id' => 'required|integer|exists:order_items,id',
            'delete_auth_code' => 'required|string',
        ]);

        try {
            DB::transaction(function () use ($booking, $validated): void {
                $session = TableSession::query()
                    ->where('table_reservation_id', $booking->id)
                    ->where('status', 'active')
                    ->latest('id')
                    ->first();

                if (! $session) {
                    throw ValidationException::withMessages([
                        'order_item_id' => 'Sesi aktif tidak ditemukan untuk booking ini.',
                    ]);
                }

                $authCode = trim((string) ($validated['delete_auth_code'] ?? ''));
                $today = now()->format('Y-m-d');
                $authRecord = DailyAuthCode::forDate($today);

                if ($authCode !== $authRecord->active_code) {
                    throw ValidationException::withMessages([
                        'delete_auth_code' => 'Daily auth code tidak valid.',
                    ]);
                }

                $orderItem = OrderItem::query()
                    ->where('id', (int) $validated['order_item_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $orderItem) {
                    throw ValidationException::withMessages([
                        'order_item_id' => 'Item order tidak ditemukan.',
                    ]);
                }

                $order = Order::query()
                    ->where('id', (int) $orderItem->order_id)
                    ->lockForUpdate()
                    ->first();

                if (! $order || (int) $order->table_session_id !== (int) $session->id) {
                    throw ValidationException::withMessages([
                        'order_item_id' => 'Item order tidak ditemukan pada sesi aktif booking ini.',
                    ]);
                }

                if ($order->status !== 'pending') {
                    throw ValidationException::withMessages([
                        'order_item_id' => 'Item hanya bisa dihapus jika order masih berstatus pending.',
                    ]);
                }

                $orderItem->delete();

                $remainingItemsCount = (int) $order->items()->count();

                if ($remainingItemsCount === 0) {
                    $order->update([
                        'status' => 'cancelled',
                        'cancelled_at' => now(),
                        'cancelled_by' => auth()->id(),
                        'items_total' => 0,
                        'total' => 0,
                    ]);

                    return;
                }

                $remainingItemsTotal = (float) $order->items()->sum('subtotal');
                $currentDiscount = (float) ($order->discount_amount ?? 0);

                if ($currentDiscount > $remainingItemsTotal) {
                    $order->discount_amount = $remainingItemsTotal;
                    $order->save();
                }

                $order->updateTotals();
            });

            return back()->with('success', 'Item order berhasil dihapus.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()->withErrors([
                'order_item_id' => 'Gagal menghapus item order. '.$e->getMessage(),
            ]);
        }
    }

    public function printRunningReceipt(TableReservation $booking)
    {
        $session = TableSession::with(['billing', 'orders.items.inventoryItem', 'table', 'customer', 'reservation'])
            ->where('table_reservation_id', $booking->id)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if (! $session) {
            return back()->withErrors([
                'error' => 'Table session aktif tidak ditemukan untuk booking ini.',
            ]);
        }

        $billing = $session->billing;

        if (! $billing) {
            return back()->withErrors([
                'error' => 'Billing belum tersedia untuk sesi ini.',
            ]);
        }

        try {
            DB::transaction(function () use ($session, $billing): void {
                $totals = $this->calculateSessionBillingTotals(
                    $session,
                    (float) ($billing->discount_amount ?? 0),
                    0,
                    (float) ($session->reservation?->down_payment_amount ?? 0),
                );

                $billing->update([
                    'orders_total' => (float) $totals['orders_total'],
                    'subtotal' => (float) $totals['subtotal'],
                    'tax_percentage' => (float) $totals['tax_percentage'],
                    'tax' => (float) $totals['tax'],
                    'service_charge_percentage' => (float) $totals['service_charge_percentage'],
                    'service_charge' => (float) $totals['service_charge'],
                    'grand_total' => (float) $totals['grand_total'],
                ]);
            });

            $billing->refresh();

            $printed = $this->printClosedBillingReceipt($session, $billing);

            if (! $printed) {
                return back()->withErrors([
                    'error' => 'Struk sesi berjalan gagal dicetak. Periksa konfigurasi printer.',
                ]);
            }

            return back()->with('success', 'Struk sesi berjalan berhasil dicetak.');
        } catch (\Throwable $e) {
            return back()->withErrors([
                'error' => 'Gagal mencetak struk sesi berjalan: '.$e->getMessage(),
            ]);
        }
    }

    public function receipt(TableReservation $booking)
    {
        $booking->load([
            'table.area',
            'customer.profile',
            'customer.customerUser',
            'tableSession.billing',
            'tableSession.orders.items.inventoryItem',
        ]);

        $session = $booking->tableSession;
        $billing = $session?->billing;

        $allItems = $session?->orders->flatMap(fn ($order) => $order->items)->groupBy('item_name')->map(function ($group) {
            $first = $group->first();

            return [
                'name' => $first->item_name,
                'qty' => $group->sum('quantity'),
                'price' => (float) $first->price,
                'subtotal' => $group->sum('subtotal'),
                'discount_amount' => $group->sum('discount_amount'),
            ];
        })->values() ?? collect();

        $customerName = $booking->customer->profile->name ?? $booking->customer->customerUser->name ?? $booking->customer->name ?? '-';

        return view('bookings.receipt', compact('booking', 'billing', 'allItems', 'customerName'));
    }

    public function reprintReceipt(TableReservation $booking)
    {
        $booking->load([
            'tableSession.billing',
            'tableSession.orders.items.inventoryItem',
            'tableSession.table',
            'tableSession.customer',
            'tableSession.reservation',
        ]);

        $session = $booking->tableSession;

        if (! $session) {
            return back()->withErrors([
                'error' => 'Table session tidak ditemukan untuk booking ini.',
            ]);
        }

        $billing = $session->billing;

        if (! $billing) {
            $billing = Billing::query()
                ->where('table_session_id', $session->id)
                ->latest('id')
                ->first();
        }

        if (! $billing) {
            return back()->withErrors([
                'error' => 'Billing tidak ditemukan untuk booking ini.',
            ]);
        }

        $printed = $this->printClosedBillingReceipt($session, $billing);

        if (! $printed) {
            return back()->withErrors([
                'error' => 'Print ulang struk gagal. Periksa konfigurasi printer.',
            ]);
        }

        return back()->with('success', 'Print ulang struk berhasil dikirim ke printer.');
    }

    protected function hasIncompleteTransactionChecker(TableSession $session): bool
    {
        $checkerItems = $session->orders
            ->flatMap(fn ($order) => $order->items)
            ->where('status', '!=', 'cancelled');

        if ($checkerItems->isEmpty()) {
            return false;
        }

        return $checkerItems->where('status', 'served')->count() < $checkerItems->count();
    }

    protected function printClosedBillingReceipt(TableSession $session, Billing $billing): bool
    {
        $areaId = $session->table?->area_id;

        try {
            $printer = $this->resolveClosedBillingReceiptPrinter($areaId);

            if (! $printer) {
                Log::warning('Close billing receipt auto print skipped because no printer is configured', [
                    'table_session_id' => $session->id,
                    'billing_id' => $billing->id,
                    'area_id' => $areaId,
                    'area_name' => $session->table?->area?->name,
                ]);

                return false;
            }

            $session->loadMissing(['table', 'customer', 'reservation', 'orders.items.inventoryItem']);

            Log::info('Close billing auto receipt print selected printer', [
                'table_session_id' => $session->id,
                'billing_id' => $billing->id,
                'area_id' => $areaId,
                'selected_printer_id' => $printer->id,
                'selected_printer_name' => $printer->name,
                'selected_printer_type' => $printer->printer_type,
                'selected_printer_location' => $printer->location,
                'selected_printer_area_id' => $printer->area_id,
                'connection_type' => $printer->connection_type,
            ]);

            $this->printerService->printClosedBillingReceipt($billing, $session, $printer);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Close billing receipt auto print failed', [
                'table_session_id' => $session->id,
                'billing_id' => $billing->id,
                'area_id' => $areaId,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function resolveClosedBillingReceiptPrinter(?int $areaId = null): ?Printer
    {
        $settings = GeneralSetting::instance();
        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        // Struk close billing ikut area meja; bila meja tanpa area (mis. sesi lama),
        // pakai area aktif user agar tidak jatuh ke printer area lain.
        $contextAreaId = $areaId ?: $user?->resolveActiveAreaId();
        $configuredPrinterId = $settings->getPrinterIdForArea($contextAreaId, 'closed_billing');

        if ($configuredPrinterId && $configuredPrinterId > 0) {
            $configuredPrinter = Printer::active()->find($configuredPrinterId);

            if ($configuredPrinter) {
                return $configuredPrinter;
            }
        }

        return Printer::getForService('cashier', $contextAreaId) ?? Printer::getDefault($contextAreaId);
    }

    public function settlePayment(Request $request, TableReservation $booking): JsonResponse
    {
        $validated = $request->validate([
            'amount_paid' => 'required|numeric|gt:0',
            'payment_method' => 'required|in:cash,kredit,debit,qris,transfer',
            'payment_reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:255',
        ]);

        $session = $booking->tableSession;
        $billing = $session?->billing ?? ($session ? \App\Models\Billing::query()->where('table_session_id', $session->id)->first() : null);

        if (! $billing) {
            return response()->json(['success' => false, 'message' => 'Billing tidak ditemukan.'], 404);
        }

        $remainingBalance = (float) $billing->remaining_balance;
        if ($remainingBalance <= 0) {
            return response()->json(['success' => false, 'message' => 'Billing ini sudah lunas, tidak ada sisa tagihan/piutang.'], 422);
        }

        $amountPaid = (float) $validated['amount_paid'];
        if ($amountPaid > $remainingBalance + 0.01) {
            return response()->json([
                'success' => false,
                'message' => 'Jumlah pelunasan (Rp '.number_format($amountPaid, 0, ',', '.').') melebihi sisa tagihan (Rp '.number_format($remainingBalance, 0, ',', '.').').',
            ], 422);
        }

        $payment = \App\Models\BillingPayment::create([
            'billing_id' => $billing->id,
            'amount_paid' => $amountPaid,
            'payment_method' => $validated['payment_method'],
            'payment_reference_number' => $validated['payment_reference_number'] ?? null,
            'payment_type' => 'debt_settlement',
            'notes' => $validated['notes'] ?? 'Pelunasan sisa piutang',
            'created_by' => auth()->id(),
            'paid_at' => now('Asia/Jakarta'),
        ]);

        $billing->recalculatePaymentStatus();

        // Accurate Online Sales Receipt sync
        $targetInvoiceNo = $billing->accurate_inv_number ?: $billing->accurate_so_number;
        if ($targetInvoiceNo) {
            try {
                $customerNo = $this->ensureAccurateCustomer((int) $booking->customer_id);
                $settlementMethod = strtolower(trim((string) $validated['payment_method']));
                $isCash = in_array($settlementMethod, ['cash', 'tunai'], true);
                $settings = GeneralSetting::instance();
                $bankNo = $isCash
                    ? ($settings->accurate_cash_account_no ?: '110101')
                    : ($settings->accurate_bank_account_no ?: '110102');

                $receiptData = [
                    'customerNo' => $customerNo,
                    'transDate' => now('Asia/Jakarta')->format('d/m/Y'),
                    'bankNo' => $bankNo,
                    'chequeAmount' => $amountPaid,
                    'description' => 'Pelunasan Piutang POS #'.$billing->transaction_code,
                    'detailInvoice' => [
                        [
                            'invoiceNo' => $targetInvoiceNo,
                            'paymentAmount' => $amountPaid,
                        ],
                    ],
                ];
                $response = $this->accurateService->saveSalesReceipt($receiptData);
                if (! empty($response['r']['number']) || ! empty($response['d']['number'])) {
                    $receiptNo = $response['r']['number'] ?? $response['d']['number'];
                    $payment->update([
                        'accurate_sales_receipt_number' => $receiptNo,
                        'accurate_sync_status' => 'synced',
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Accurate sales receipt sync error on booking debt settlement', ['error' => $e->getMessage()]);
                $payment->update(['accurate_sync_status' => 'failed']);
            }
        }

        $receiptPrinted = false;
        try {
            $receiptPrinted = $this->printClosedBillingReceipt($session, $billing);
        } catch (\Throwable $e) {
            Log::error('Debt settlement receipt print failed', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pelunasan sisa tagihan sebesar Rp '.number_format($amountPaid, 0, ',', '.').' berhasil dicatat!',
            'receipt_printed' => $receiptPrinted,
            'billing' => [
                'billing_status' => $billing->billing_status,
                'paid_amount' => (float) $billing->paid_amount,
                'remaining_balance' => (float) $billing->remaining_balance,
                'is_debt' => (bool) $billing->is_debt,
            ],
        ]);
    }

    /**
     * Normalisasi diskon per item menjadi map id→nilai.
     * Map baru (discount_items) menang; bila kosong, fallback legacy ids + satu nilai.
     */
    protected function normalizeDiscountItems(array $raw = [], array $legacyIds = [], ?float $legacyValue = null): array
    {
        $map = [];
        foreach ($raw as $id => $value) {
            $map[(int) $id] = (float) $value;
        }

        if ($map === [] && $legacyIds !== []) {
            $v = (float) $legacyValue;
            foreach ($legacyIds as $id) {
                $map[(int) $id] = $v;
            }
        }

        return $map;
    }
}
