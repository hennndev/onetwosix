<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Billing;
use App\Models\Event;
use App\Models\GeneralSetting;
use App\Models\Tabel;
use App\Models\TableSession;
use App\Support\RealtimeTopSpenderBanner;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TableController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    protected function activeTablesViewData(Request $request): array
    {
        $query = TableSession::with([
            'table.area',
            'customer.profile',
            'customer.customerUser',
            'reservation.customer.profile',
            'reservation.customer.customerUser',
            'waiter.profile',
            'billing',
            'orders.items.inventoryItem',
        ])
            ->where('status', 'active');

        $activeAreaId = $request->input('area_id', session('active_area_id'));
        $activeAreaId = ($activeAreaId && $activeAreaId !== 'all') ? (int) $activeAreaId : null;

        if ($activeAreaId) {
            $query->whereHas('table', function ($q) use ($activeAreaId) {
                $q->where('area_id', $activeAreaId);
            });
        }

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('session_code', 'like', "%{$search}%")
                    ->orWhereHas('table', function ($tableQuery) use ($search) {
                        $tableQuery->where('table_number', 'like', "%{$search}%");
                    })
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $sessions = $query->orderBy('checked_in_at', 'desc')->get();

        $activeSessionChargePreviews = $sessions->mapWithKeys(function (TableSession $session): array {
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

        $activeSessionEventAdjustments = $sessions->mapWithKeys(function (TableSession $session): array {
            $activeEvent = $this->resolveActiveEventForDate($session->reservation?->reservation_date, $session->table?->area_id);

            if (! $activeEvent) {
                return [$session->id => null];
            }

            $baseMinimumCharge = (float) ($session->billing?->minimum_charge ?? $session->table?->minimum_charge ?? 0);
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

        $activeSessionSubtotals = $sessions->mapWithKeys(function (TableSession $session): array {
            return [
                $session->id => $this->calculateActiveSessionItemSubtotal($session),
            ];
        });

        $sessions = $sessions->map(function (TableSession $session) use ($activeSessionSubtotals): TableSession {
            $session->setAttribute('realtime_subtotal', $this->resolveRealtimeSubtotal($session));
            $session->setAttribute('active_session_subtotal', $activeSessionSubtotals[$session->id] ?? 0);

            return $session;
        });

        return [
            'sessions' => $sessions,
            'areas' => auth()->user() ? auth()->user()->getAccessibleAreas() : Area::where('is_active', true)->orderBy('sort_order')->get(),
            'activeAreaId' => $activeAreaId,
            'totalActiveSessions' => \App\Models\TableSession::where('status', 'active')
                ->when($activeAreaId, fn ($q) => $q->whereHas('table', fn ($t) => $t->where('area_id', $activeAreaId)))
                ->count(),
            'totalRevenue' => Billing::whereHas('tableSession', function ($q) use ($activeAreaId) {
                $q->where('status', 'active')
                    ->when($activeAreaId, fn ($t) => $t->whereHas('table', fn ($tb) => $tb->where('area_id', $activeAreaId)));
            })
                ->where(function ($q) {
                    $q->whereNull('foc_comp_payment_method')
                        ->orWhereNotIn('foc_comp_payment_method', ['FOC', 'Compliment']);
                })
                ->sum('grand_total'),
            'topSpenders' => app(RealtimeTopSpenderBanner::class)->topSpenders(3, $activeAreaId),
            'activeSessionChargePreviews' => $activeSessionChargePreviews,
            'activeSessionEventAdjustments' => $activeSessionEventAdjustments,
            'activeSessionSubtotals' => $activeSessionSubtotals,
        ];
    }

    protected function resolveRealtimeSubtotal(TableSession $session): float
    {
        $realtimeSubtotal = (float) ($session->realtime_items_subtotal ?? 0)
            + (float) ($session->realtime_items_tax_total ?? 0)
            + (float) ($session->realtime_items_service_charge_total ?? 0);

        if ($realtimeSubtotal > 0) {
            return $realtimeSubtotal;
        }

        return (float) ($session->billing?->subtotal ?? $session->billing?->orders_total ?? 0);
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

    protected function calculateActiveSessionItemSubtotal(TableSession $session): float
    {
        return (float) $session->orders
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

    // HALAMAN TABLE MANAGEMENT
    public function index(Request $request)
    {
        $query = Tabel::with('area');

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('table_number', 'like', "%{$search}%")
                    ->orWhereHas('area', function ($areaQuery) use ($search) {
                        $areaQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $activeAreaId = auth()->user()
            ? auth()->user()->resolveActiveAreaId($request->input('area_id'), $request->has('area_id'))
            : null;

        if ($activeAreaId) {
            $query->where('area_id', $activeAreaId);
        }

        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        $tables = $query->orderBy('area_id')->orderBy('table_number')->get();

        $areas = auth()->user() ? auth()->user()->getAccessibleAreas() : Area::where('is_active', true)->orderBy('sort_order')->get();
        $areaStats = Area::whereIn('id', $areas->pluck('id'))
            ->when($activeAreaId, fn ($q) => $q->where('id', $activeAreaId))
            ->withCount(['tables' => function ($q) {
                $q->where('is_active', true);
            }])
            ->orderBy('sort_order')
            ->get();

        $totalTables = $areaStats->sum('tables_count');
        $availableTables = $tables->where('status', 'available')->where('is_active', true)->count();
        $totalCapacity = $tables->where('is_active', true)->sum('capacity');

        // Get active reservations for reserved tables
        $reservations = \App\Models\TableReservation::with(['customer.profile', 'customer.customerUser', 'table.area'])
            ->whereIn('status', ['confirmed', 'checked_in'])
            ->whereIn('table_id', $tables->pluck('id'))
            ->get()
            ->keyBy('table_id');

        return view('tables.index', compact(
            'tables',
            'totalTables',
            'availableTables',
            'totalCapacity',
            'areas',
            'areaStats',
            'reservations'
        ));
    }

    // CREATE NEW TABLE
    public function store(Request $request)
    {
        $validated = $request->validate([
            'area_id' => 'required|exists:areas,id',
            'table_number' => 'required|string|max:50',
            'capacity' => 'required|integer|min:1',
            'minimum_charge' => 'nullable|numeric|min:0',
            'status' => 'required|in:available,reserved,occupied,maintenance',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $validated['qr_code'] = 'QR-'.strtoupper(Str::random(12));
        $validated['is_active'] = $validated['is_active'] ?? true;
        DB::beginTransaction();
        try {
            Tabel::create($validated);
            DB::commit();

            return redirect()->route('admin.tables.index')
                ->with('success', 'Meja berhasil ditambahkan');
        } catch (\Exception $e) {
            DB::rollback();

            return back()->withErrors(['error' => 'Gagal menambahkan meja: '.$e->getMessage()]);
        }
    }

    // UPDATE TABLE
    public function update(Request $request, Tabel $table)
    {
        $hasActiveSession = TableSession::query()
            ->where('table_id', $table->id)
            ->where('status', 'active')
            ->exists();

        if ($hasActiveSession) {
            return back()->withErrors([
                'error' => 'Meja tidak bisa diedit atau dinonaktifkan karena masih memiliki sesi aktif.',
            ])->withInput();
        }

        $validated = $request->validate([
            'area_id' => 'required|exists:areas,id',
            'table_number' => 'required|string|max:50',
            'capacity' => 'required|integer|min:1',
            'minimum_charge' => 'nullable|numeric|min:0',
            'status' => 'required|in:available,reserved,occupied,maintenance',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);
        $validated['is_active'] = $validated['is_active'] ?? false;
        DB::beginTransaction();
        try {
            $table->update($validated);
            DB::commit();

            return redirect()->route('admin.tables.index')
                ->with('success', 'Meja berhasil diupdate');
        } catch (\Exception $e) {
            DB::rollback();

            return back()->withErrors(['error' => 'Gagal mengupdate meja: '.$e->getMessage()]);
        }
    }

    // DELETE TABLE
    public function destroy(Tabel $table)
    {
        DB::beginTransaction();
        try {
            $table->delete();
            DB::commit();

            return redirect()->route('admin.tables.index')
                ->with('success', 'Meja berhasil dihapus');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Gagal menghapus meja: '.$e->getMessage()]);
        }
    }

    // HALAMAN ACTIVE TABLES
    public function activeTables(Request $request)
    {
        return view('active-tables.index', $this->activeTablesViewData($request));
    }

    public function activeTablesReadonly(Request $request)
    {
        $data = $this->activeTablesViewData($request);

        if ($request->headers->get('X-Live')) {
            $partial = $request->get('live') === 'table'
                ? 'active-tables._partials.table'
                : 'active-tables._partials.stats';

            return response(
                view($partial, $data)
            )->withHeaders(['X-Live' => '1']);
        }

        return view('active-tables.readonly', $data);
    }

    // UPDATE PAX PADA ACTIVE TABLE
    public function updatePax(Request $request, TableSession $session): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'pax' => 'required|integer|min:1|max:9999',
        ]);

        $session->update(['pax' => $validated['pax']]);

        return response()->json(['success' => true, 'pax' => $session->pax]);
    }

    // HALAMAN TABLE SCANNER
    public function scanner()
    {
        return view('table-scanner.index');
    }

    // SCAN QR MEJA
    public function scanQR(Request $request)
    {
        $validated = $request->validate([
            'qr_code' => 'required|string',
        ]);

        try {
            $table = Tabel::with(['area'])
                ->where('qr_code', $validated['qr_code'])
                ->first();

            if (! $table) {
                return response()->json([
                    'success' => false,
                    'message' => 'QR Code tidak valid atau meja tidak ditemukan',
                ], 404);
            }

            // Get active reservation for this table
            $reservation = \App\Models\TableReservation::with(['customer.profile', 'customer.customerUser'])
                ->where('table_id', $table->id)
                ->whereIn('status', ['confirmed', 'checked_in'])
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'table' => $table,
                    'reservation' => $reservation,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: '.$e->getMessage(),
            ], 500);
        }
    }

    public function generateCheckInQR(Request $request)
    {
        $validated = $request->validate([
            'reservation_id' => 'required|exists:table_reservations,id',
        ]);

        try {
            $reservation = \App\Models\TableReservation::with(['table', 'customer'])
                ->findOrFail($validated['reservation_id']);

            // Generate or regenerate QR code if expired
            if (! $reservation->check_in_qr_code || ! $reservation->check_in_qr_expires_at || $reservation->check_in_qr_expires_at < now()) {
                $reservation->update([
                    'check_in_qr_code' => 'CHECKIN-'.strtoupper(Str::random(16)),
                    'check_in_qr_expires_at' => now()->addMinutes(5), // QR valid for 5 minutes
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'reservation' => $reservation,
                    'qr_code' => $reservation->check_in_qr_code,
                    'expires_at' => $reservation->check_in_qr_expires_at->format('Y-m-d H:i:s'),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: '.$e->getMessage(),
            ], 500);
        }
    }

    public function processCheckIn(Request $request)
    {
        $validated = $request->validate([
            'qr_code' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            // Find reservation by QR code
            $reservation = \App\Models\TableReservation::with(['table', 'customer'])
                ->where('check_in_qr_code', $validated['qr_code'])
                ->first();

            if (! $reservation) {
                return response()->json([
                    'success' => false,
                    'message' => 'QR Code tidak valid',
                ], 404);
            }

            // Check if QR expired
            if (! $reservation->check_in_qr_expires_at || $reservation->check_in_qr_expires_at < now()) {
                return response()->json([
                    'success' => false,
                    'message' => 'QR Code sudah expired. Silakan generate ulang.',
                ], 400);
            }

            // Check if already checked in
            if ($reservation->status === 'checked_in') {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer sudah check-in',
                ], 400);
            }

            // Step 1: Create table session first (without billing_id)
            // Assign waiter if the check-in is performed by a Waiter role user
            $waiterId = auth()->user()?->hasRole('Waiter/Server') ? auth()->id() : null;

            $session = \App\Models\TableSession::create([
                'table_reservation_id' => $reservation->id,
                'table_id' => $reservation->table_id,
                'customer_id' => $reservation->customer_id,
                'waiter_id' => $waiterId,
                'session_code' => 'SES-'.strtoupper(Str::random(10)),
                'checked_in_at' => now(),
                'status' => 'active',
            ]);

            // Step 2: Create billing for this session
            $minimumCharge = $reservation->table->minimum_charge ?? 0;
            $billing = Billing::create([
                'area_id' => $reservation->table?->area_id,
                'table_session_id' => $session->id,
                'minimum_charge' => $minimumCharge,
                'orders_total' => 0,
                'subtotal' => 0,
                'tax' => 0,
                'tax_percentage' => 10.00,
                'discount_amount' => 0,
                'grand_total' => 0,
                'paid_amount' => 0,
                'billing_status' => 'draft',
            ]);

            // Step 3: Update session with billing_id
            $session->update([
                'billing_id' => $billing->id,
            ]);

            // Update reservation status and clear QR code
            $reservation->update([
                'status' => 'checked_in',
                'check_in_qr_code' => null,
                'check_in_qr_expires_at' => null,
            ]);

            // Update table status to occupied
            $reservation->table->update(['status' => 'occupied']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $waiterId ? 'Check-in berhasil! Kamu di-assign sebagai waiter.' : 'Check-in berhasil!',
                'data' => [
                    'session' => $session,
                    'customer' => $reservation->customer->name,
                    'table' => $reservation->table->table_number,
                    'waiter_assigned' => $waiterId !== null,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: '.$e->getMessage(),
            ], 500);
        }
    }
}
