<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeaderboardResource;
use App\Models\CustomerUser;
use App\Models\User;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    use ApiResponse;

    /** @var array<string> */
    private array $allowedSorts = ['spending', 'visits'];

    /** @var array<string> */
    private array $allowedPeriods = ['day', 'week', 'month', 'year'];

    /** @var array<string> */
    private array $paidStatuses = ['paid', 'partially_paid', 'partial_paid'];

    public function index(Request $request): JsonResponse
    {
        $sortBy = $request->get('sort_by', 'spending');
        $period = $request->get('period');

        if (! in_array($sortBy, $this->allowedSorts)) {
            $sortBy = 'spending';
        }

        if ($period !== null && ! in_array($period, $this->allowedPeriods)) {
            $period = null;
        }

        $orderColumn = $this->resolveOrderColumn($period, $sortBy);

        $leaderboard = $this->leaderboardQuery($period)
            ->orderByDesc($orderColumn)
            ->orderBy('customer_users.id')
            ->limit(50)
            ->get();

        $leaderboard->each(function ($customer, $index) {
            $customer->rank = $index + 1;
        });

        return $this->success([
            'period' => $period ?? 'all_time',
            'leaderboard' => LeaderboardResource::collection($leaderboard),
        ]);
    }

    public function myRank(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $user->customerUser) {
            return $this->success([
                'ranking' => null,
            ], 'Akun customer tidak ditemukan.');
        }

        $sortBy = $request->get('sort_by', 'spending');
        $period = $request->get('period');

        if (! in_array($sortBy, $this->allowedSorts)) {
            $sortBy = 'spending';
        }

        if ($period !== null && ! in_array($period, $this->allowedPeriods)) {
            $period = null;
        }

        $myCustomer = $this->leaderboardQuery($period)
            ->where('customer_users.id', $user->customerUser->id)
            ->first();

        if (! $myCustomer) {
            return $this->success([
                'ranking' => null,
            ], 'Data customer tidak ditemukan.');
        }

        $sortColumn = $this->resolveOrderColumn($period, $sortBy);
        $myValue = $myCustomer->{$sortColumn};

        $rank = $this->leaderboardQuery($period)
            ->where($sortColumn, '>', $myValue)
            ->count() + 1;

        $myCustomer->rank = $rank;

        return $this->success([
            'period' => $period ?? 'all_time',
            'ranking' => new LeaderboardResource($myCustomer),
        ]);
    }

    private function resolveOrderColumn(?string $period, string $sortBy): string
    {
        if ($period === null) {
            return $sortBy === 'visits' ? 'total_visits' : 'lifetime_spending';
        }

        return $sortBy === 'visits' ? 'period_visits' : 'period_spending';
    }

    /**
     * @return array{Carbon, Carbon}
     */
    private function resolveDateRange(?string $period): array
    {
        $now = Carbon::now();

        return match ($period) {
            'day' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            default => [Carbon::create(2000, 1, 1), $now->copy()->endOfDay()],
        };
    }

    private function buildLeaderboardQuery(Carbon $startDate, Carbon $endDate): Builder
    {
        $statuses = implode("','", $this->paidStatuses);

        return CustomerUser::with(['user', 'profile', 'tier'])
            ->whereHas('user')
            ->selectRaw("
                customer_users.*,
                COALESCE((
                    SELECT SUM(b.grand_total)
                    FROM billings b
                    JOIN table_sessions ts ON ts.id = b.table_session_id
                    WHERE ts.customer_id = customer_users.user_id
                      AND b.billing_status IN ('{$statuses}')
                      AND COALESCE(b.paid_at, b.created_at) BETWEEN ? AND ?
                ), 0) + COALESCE((
                    SELECT SUM(b.grand_total)
                    FROM billings b
                    JOIN orders o ON o.id = b.order_id
                    WHERE b.table_session_id IS NULL
                      AND o.customer_user_id = customer_users.id
                      AND b.billing_status IN ('{$statuses}')
                      AND COALESCE(b.paid_at, b.created_at) BETWEEN ? AND ?
                ), 0) AS period_spending,
                COALESCE((
                    SELECT COUNT(DISTINCT b.id)
                    FROM billings b
                    JOIN table_sessions ts ON ts.id = b.table_session_id
                    WHERE ts.customer_id = customer_users.user_id
                      AND b.billing_status IN ('{$statuses}')
                      AND COALESCE(b.paid_at, b.created_at) BETWEEN ? AND ?
                ), 0) + COALESCE((
                    SELECT COUNT(DISTINCT b.id)
                    FROM billings b
                    JOIN orders o ON o.id = b.order_id
                    WHERE b.table_session_id IS NULL
                      AND o.customer_user_id = customer_users.id
                      AND b.billing_status IN ('{$statuses}')
                      AND COALESCE(b.paid_at, b.created_at) BETWEEN ? AND ?
                ), 0) AS period_visits
            ", [
                $startDate, $endDate,
                $startDate, $endDate,
                $startDate, $endDate,
                $startDate, $endDate,
            ]);
    }

    private function leaderboardQuery(?string $period): Builder
    {
        if ($period === null) {
            return CustomerUser::with(['user', 'profile', 'tier'])
                ->whereHas('user')
                ->select('customer_users.*')
                ->selectRaw('customer_users.lifetime_spending as period_spending')
                ->selectRaw('customer_users.total_visits as period_visits');
        }

        [$startDate, $endDate] = $this->resolveDateRange($period);

        return $this->buildLeaderboardQuery($startDate, $endDate);
    }
}
