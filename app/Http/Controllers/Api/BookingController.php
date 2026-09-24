<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\TableResource;
use App\Models\Area;
use App\Models\Tabel;
use App\Models\TableReservation;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $query = TableReservation::with([
            'table.area',
            'tableSession.billing',
            'tableSession.orders.items',
            'tableSession.songRequests',
            'tableSession.displayMessageRequests',
        ])->where('customer_id', $user->id);

        $status = $request->get('status');
        $validStatuses = ['pending', 'confirmed', 'checked_in', 'completed', 'cancelled', 'rejected', 'force_closed'];

        if ($status && in_array($status, $validStatuses)) {
            $query->where('status', $status);
        }

        $bookings = $query->latest('reservation_date')
            ->latest('reservation_time')
            ->paginate(15);

        return $this->success([
            'bookings' => BookingResource::collection($bookings),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ],
        ]);
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $validated = $request->validated();

        $table = Tabel::findOrFail($validated['table_id']);

        $isTableBooked = TableReservation::where('table_id', $table->id)
            ->whereDate('reservation_date', $validated['reservation_date'])
            ->whereNotIn('status', ['cancelled', 'rejected', 'completed', 'force_closed'])
            ->exists();

        if ($isTableBooked) {
            return $this->error('Meja sudah dipesan untuk tanggal tersebut.', 422);
        }

        $lastBooking = TableReservation::latest('id')->first();
        $bookingCode = $lastBooking ? $lastBooking->booking_code + 1 : 1;
        $qrCode = Str::uuid()->toString();

        $booking = TableReservation::create([
            'booking_code' => $bookingCode,
            'booking_name' => $user->name,
            'table_id' => $table->id,
            'customer_id' => $user->id,
            'reservation_date' => $validated['reservation_date'],
            'reservation_time' => $validated['reservation_time'],
            'status' => 'pending',
            'note' => $validated['note'] ?? null,
            'down_payment_amount' => (float) ($validated['down_payment_amount'] ?? 0),
            'check_in_qr_code' => $qrCode,
            'check_in_qr_expires_at' => now()->addDays(1),
        ]);

        $booking->load([
            'table.area',
            'tableSession.billing',
            'tableSession.orders.items',
            'tableSession.songRequests',
            'tableSession.displayMessageRequests',
        ]);

        return $this->success([
            'booking' => new BookingResource($booking),
        ], 'Reservasi berhasil dibuat.', 201);
    }

    public function show(TableReservation $booking): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if ($booking->customer_id !== $user->id) {
            return $this->error('Unauthorized.', 403);
        }

        $booking->load([
            'table.area',
            'tableSession.billing',
            'tableSession.orders.items',
            'tableSession.songRequests',
            'tableSession.displayMessageRequests',
        ]);

        return $this->success([
            'booking' => new BookingResource($booking),
        ]);
    }

    public function cancel(TableReservation $booking): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if ($booking->customer_id !== $user->id) {
            return $this->error('Unauthorized.', 403);
        }

        if (! in_array($booking->status, ['pending', 'confirmed'])) {
            return $this->error('Reservasi tidak dapat dibatalkan.', 422);
        }

        $booking->update(['status' => 'cancelled']);
        $booking->load([
            'table.area',
            'tableSession.billing',
            'tableSession.orders.items',
            'tableSession.songRequests',
            'tableSession.displayMessageRequests',
        ]);

        return $this->success([
            'booking' => new BookingResource($booking),
        ], 'Reservasi berhasil dibatalkan.');
    }

    public function availableTables(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $date = $request->get('date');

        $bookedTableIds = TableReservation::where('reservation_date', $date)
            ->whereNotIn('status', ['cancelled', 'rejected', 'completed', 'force_closed'])
            ->pluck('table_id');

        $areas = Area::with(['tables' => function ($query) use ($bookedTableIds) {
            $query->where('is_active', true)
                ->whereNotIn('id', $bookedTableIds)
                ->orderBy('table_number');
        }])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $result = $areas->map(function ($area) {
            return [
                'area' => [
                    'id' => $area->id,
                    'code' => $area->code,
                    'name' => $area->name,
                    'denah_url' => $area->image ? asset('storage/'.$area->image) : null,
                ],
                'tables' => TableResource::collection($area->tables),
            ];
        });

        return $this->success([
            'available_tables' => $result,
        ]);
    }
}
