<?php

namespace App\Http\Controllers;

use App\Models\CustomerUser;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\AccurateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CustomerController extends Controller
{
    protected $accurateService;

    public function __construct(AccurateService $accurateService)
    {
        $this->accurateService = $accurateService;
    }

    public function index(Request $request)
    {
        $perPageOptions = [10, 25, 50, 100];
        $perPage = (int) $request->integer('per_page', 10);

        if (! in_array($perPage, $perPageOptions, true)) {
            $perPage = 10;
        }

        $leaderboardLimitOptions = [10, 20, 30, 40, 50];
        $leaderboardLimit = (int) $request->integer('leaderboard_limit', 10);

        if (! in_array($leaderboardLimit, $leaderboardLimitOptions, true)) {
            $leaderboardLimit = 10;
        }

        $query = $this->customerQueryWithTransactionStats()->with(['user', 'profile']);

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('customer_code', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('profile', function ($profileQuery) use ($search) {
                        $profileQuery->where('phone', 'like', "%{$search}%");
                    });
            });
        }

        $customers = (clone $query)
            ->latest('updated_at')
            ->paginate($perPage)
            ->withQueryString();

        $totalCustomers = CustomerUser::count();

        $summaryStats = (clone $query)
            ->reorder()
            ->select(DB::raw('SUM(COALESCE(booking_billing_agg.booking_spending, 0) + COALESCE(walk_in_transaction_agg.walk_in_spending, 0)) as aggregate_total_spending, SUM(COALESCE(booking_billing_agg.booking_visits, 0) + COALESCE(walk_in_transaction_agg.walk_in_visits, 0)) as aggregate_total_visits'))
            ->first();

        $totalSpending = (float) ($summaryStats->aggregate_total_spending ?? 0);
        $totalVisits = (int) ($summaryStats->aggregate_total_visits ?? 0);
        $avgSpending = $totalCustomers > 0 ? $totalSpending / $totalCustomers : 0;

        // Leaderboard data (points + visits)
        $leaderboard = $this->customerQueryWithTransactionStats()
            ->with(['user', 'profile'])
            ->orderByDesc('leaderboard_score')
            ->orderByDesc('transaction_lifetime_spending')
            ->take($leaderboardLimit)
            ->get();

        // Today's leaderboard data (based on active operational window)
        [$todayStartTime, $todayEndTime] = \App\Models\RecapHistory::resolveActiveWindow();

        $leaderboardToday = $this->customerQueryWithTransactionStatsByDateRange($todayStartTime, $todayEndTime)
            ->with(['user', 'profile'])
            ->orderByDesc('daily_leaderboard_score')
            ->orderByDesc('transaction_daily_spending')
            ->take($leaderboardLimit)
            ->get();

        return view('customers.index', compact(
            'customers',
            'totalCustomers',
            'totalSpending',
            'totalVisits',
            'avgSpending',
            'leaderboard',
            'leaderboardToday',
            'leaderboardLimit',
            'leaderboardLimitOptions',
            'perPage',
            'perPageOptions'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:users,email',
            'password' => 'nullable|string|min:8',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'birth_date' => 'nullable|date',
        ]);

        $email = ! empty($validated['email'])
            ? $validated['email']
            : 'customer_'.time().'_'.random_int(1000, 9999).'@126club.internal';

        $password = ! empty($validated['password'])
            ? Hash::make($validated['password'])
            : Hash::make(\Illuminate\Support\Str::random(16));

        try {
            DB::beginTransaction();

            $user = User::create([
                'name' => $validated['name'],
                'email' => $email,
                'password' => $password,
            ]);

            $profile = UserProfile::create([
                'user_id' => $user->id,
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
            ]);

            CustomerUser::create([
                'accurate_id' => null,
                'customer_code' => null,
                'user_id' => $user->id,
                'user_profile_id' => $profile->id,
                'total_visits' => 0,
                'lifetime_spending' => 0,
            ]);

            DB::commit();

            return redirect()->route('admin.customers.index')
                ->with('success', 'Customer berhasil ditambahkan');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Gagal menambahkan customer: '.$e->getMessage()]);
        }
    }

    public function update(Request $request, CustomerUser $customer)
    {
        $customer->loadMissing(['user', 'profile']);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:users,email,'.($customer->user_id ?? 0),
            'password' => 'nullable|string|min:8',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'birth_date' => 'nullable|date',
            'total_visits' => 'nullable|integer|min:0',
            'lifetime_spending' => 'nullable|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $email = ! empty($validated['email'])
                ? $validated['email']
                : ($customer->user?->email ?: 'customer_'.time().'_'.random_int(1000, 9999).'@126club.internal');

            if (! $customer->user) {
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $email,
                    'password' => \Illuminate\Support\Facades\Hash::make($validated['password'] ?? \Illuminate\Support\Str::random(16)),
                ]);
                $customer->update(['user_id' => $user->id]);
                $customer->setRelation('user', $user);
            } else {
                $userData = [
                    'name' => $validated['name'],
                    'email' => $email,
                ];
                if (! empty($validated['password'])) {
                    $userData['password'] = \Illuminate\Support\Facades\Hash::make($validated['password']);
                }
                $customer->user->update($userData);
            }

            if ($customer->profile) {
                $customer->profile->update([
                    'phone' => $validated['phone'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'birth_date' => $validated['birth_date'] ?? null,
                ]);
            } else {
                $profile = UserProfile::create([
                    'user_id' => $customer->user_id,
                    'phone' => $validated['phone'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'birth_date' => $validated['birth_date'] ?? null,
                ]);
                $customer->update(['user_profile_id' => $profile->id]);
            }

            $customer->update([
                'total_visits' => $validated['total_visits'] ?? $customer->total_visits,
                'lifetime_spending' => $validated['lifetime_spending'] ?? $customer->lifetime_spending,
            ]);

            DB::commit();

            return redirect()->route('admin.customers.index')
                ->with('success', 'Customer berhasil diupdate');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Gagal mengupdate customer: '.$e->getMessage()]);
        }
    }

    public function destroy(CustomerUser $customer)
    {
        try {
            // Delete will cascade to user and profile
            $customer->user->delete();

            return redirect()->route('admin.customers.index')
                ->with('success', 'Customer berhasil dihapus');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal menghapus customer: '.$e->getMessage()]);
        }
    }

    protected function customerQueryWithTransactionStats()
    {
        $bookingBillingAgg = DB::table('billings')
            ->join('table_sessions', 'table_sessions.id', '=', 'billings.table_session_id')
            ->where('billings.billing_status', 'paid')
            ->where('billings.is_booking', true)
            // FOC/Compliment bukan spending (bukan revenue).
            ->where(function ($q) {
                $q->whereNull('billings.foc_comp_payment_method')
                    ->orWhereNotIn('billings.foc_comp_payment_method', ['FOC', 'Compliment']);
            })
            ->groupBy('table_sessions.customer_id')
            ->selectRaw('table_sessions.customer_id as user_id')
            ->selectRaw('SUM(billings.grand_total) as booking_spending')
            ->selectRaw('COUNT(billings.id) as booking_visits');

        $walkInTransactionAgg = DB::table('orders')
            ->whereNull('orders.table_session_id')
            ->whereNotNull('orders.customer_user_id')
            ->where('orders.status', '!=', 'cancelled')
            // FOC/Compliment bukan spending — exclude order dengan billing FOC.
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('billings')
                    ->whereColumn('billings.order_id', 'orders.id')
                    ->whereIn('billings.foc_comp_payment_method', ['FOC', 'Compliment']);
            })
            ->groupBy('orders.customer_user_id')
            ->selectRaw('orders.customer_user_id as customer_user_id')
            ->selectRaw('SUM(orders.total) as walk_in_spending')
            ->selectRaw('COUNT(orders.id) as walk_in_visits');

        return CustomerUser::query()
            ->leftJoinSub($bookingBillingAgg, 'booking_billing_agg', function ($join): void {
                $join->on('booking_billing_agg.user_id', '=', 'customer_users.user_id');
            })
            ->leftJoinSub($walkInTransactionAgg, 'walk_in_transaction_agg', function ($join): void {
                $join->on('walk_in_transaction_agg.customer_user_id', '=', 'customer_users.id');
            })
            ->select('customer_users.*')
            ->selectRaw('COALESCE(booking_billing_agg.booking_spending, 0) + COALESCE(walk_in_transaction_agg.walk_in_spending, 0) as transaction_lifetime_spending')
            ->selectRaw('COALESCE(booking_billing_agg.booking_visits, 0) + COALESCE(walk_in_transaction_agg.walk_in_visits, 0) as transaction_total_visits')
            ->selectRaw('FLOOR((COALESCE(booking_billing_agg.booking_spending, 0) + COALESCE(walk_in_transaction_agg.walk_in_spending, 0)) / 10000) as transaction_points')
            ->selectRaw('FLOOR((COALESCE(booking_billing_agg.booking_spending, 0) + COALESCE(walk_in_transaction_agg.walk_in_spending, 0)) / 10000) + (COALESCE(booking_billing_agg.booking_visits, 0) + COALESCE(walk_in_transaction_agg.walk_in_visits, 0)) as leaderboard_score');
    }

    protected function customerQueryWithTransactionStatsByDateRange($startTime, $endTime)
    {
        // Booking orders: resolve customer via table_sessions.customer_id -> customer_users.user_id
        $bookingOrderAgg = DB::table('orders')
            ->join('table_sessions', 'table_sessions.id', '=', 'orders.table_session_id')
            ->join('customer_users as booking_customers', 'booking_customers.user_id', '=', 'table_sessions.customer_id')
            ->whereNotNull('orders.table_session_id')
            ->where('orders.status', '!=', 'cancelled')
            ->whereBetween('orders.created_at', [$startTime, $endTime])
            ->groupBy('booking_customers.id')
            ->selectRaw('booking_customers.id as customer_user_id')
            ->selectRaw('SUM(orders.total) as booking_order_spending')
            ->selectRaw('COUNT(orders.id) as booking_order_visits');

        // Walk-in: if table_session_id is null, take from billings in the same time window
        $walkInBillingAgg = DB::table('billings')
            ->join('orders', 'orders.id', '=', 'billings.order_id')
            ->whereNull('billings.table_session_id')
            ->whereNotNull('orders.customer_user_id')
            ->where('billings.billing_status', 'paid')
            ->whereBetween('billings.created_at', [$startTime, $endTime])
            ->groupBy('orders.customer_user_id')
            ->selectRaw('orders.customer_user_id as customer_user_id')
            ->selectRaw('SUM(billings.grand_total) as walk_in_billing_spending')
            ->selectRaw('COUNT(billings.id) as walk_in_billing_visits');

        return CustomerUser::query()
            ->leftJoinSub($bookingOrderAgg, 'booking_order_agg', function ($join): void {
                $join->on('booking_order_agg.customer_user_id', '=', 'customer_users.id');
            })
            ->leftJoinSub($walkInBillingAgg, 'walk_in_billing_agg', function ($join): void {
                $join->on('walk_in_billing_agg.customer_user_id', '=', 'customer_users.id');
            })
            ->select('customer_users.*')
            ->selectRaw('COALESCE(booking_order_agg.booking_order_spending, 0) + COALESCE(walk_in_billing_agg.walk_in_billing_spending, 0) as transaction_daily_spending')
            ->selectRaw('COALESCE(booking_order_agg.booking_order_visits, 0) + COALESCE(walk_in_billing_agg.walk_in_billing_visits, 0) as transaction_daily_visits')
            ->selectRaw('FLOOR((COALESCE(booking_order_agg.booking_order_spending, 0) + COALESCE(walk_in_billing_agg.walk_in_billing_spending, 0)) / 10000) as transaction_daily_points')
            ->selectRaw('FLOOR((COALESCE(booking_order_agg.booking_order_spending, 0) + COALESCE(walk_in_billing_agg.walk_in_billing_spending, 0)) / 10000) + (COALESCE(booking_order_agg.booking_order_visits, 0) + COALESCE(walk_in_billing_agg.walk_in_billing_visits, 0)) as daily_leaderboard_score');
    }

    public function syncAccurate(CustomerUser $customer)
    {
        try {
            $customerNo = $this->ensureAccurateCustomer($customer);

            if (! $customerNo) {
                return back()->with('error', 'Gagal sinkronisasi Accurate: Nomor customer tidak dikembalikan.');
            }

            return back()->with('success', "Customer {$customer->user?->name} berhasil disinkronkan ke Accurate ({$customerNo}).");
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal sinkronisasi ke Accurate: '.$e->getMessage());
        }
    }

    protected function ensureAccurateCustomer(CustomerUser $customerUser): ?string
    {
        $customerUser->loadMissing(['user', 'profile']);

        if ($customerUser->customer_code && $customerUser->accurate_id) {
            return $customerUser->customer_code;
        }

        $user = $customerUser->user;

        if (! $user) {
            return null;
        }

        $payload = [
            'name' => $user->name,
            'email' => $user->email,
        ];

        if ($customerUser->accurate_id) {
            $payload['id'] = $customerUser->accurate_id;
        }

        $response = $this->accurateService->saveCustomer($payload);
        $accurateId = $response['r']['id'] ?? $response['d']['id'] ?? null;
        $customerNo = $response['r']['customerNo'] ?? $response['d']['customerNo'] ?? null;

        if (! $customerNo) {
            throw new \RuntimeException('Accurate customer number was not returned.');
        }

        $customerUser->update([
            'accurate_id' => $accurateId,
            'customer_code' => $customerNo,
        ]);

        return $customerNo;
    }
}
