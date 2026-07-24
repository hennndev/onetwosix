<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WaiterPerformanceController extends Controller
{
    public function index(Request $request): View
    {
        $period = $request->get('period', 'today');
        $mode = $request->get('mode', 'individual');
        $waiterId = $request->get('waiter_id');
        $selectedDate = $request->filled('date') ? (string) $request->string('date') : null;
        $historyPerPage = (int) $request->get('history_per_page', 10);
        $allWaitersPerPage = (int) $request->get('all_waiters_per_page', 20);
        if (! in_array($historyPerPage, [1, 5, 10, 20, 50], true)) {
            $historyPerPage = 10;
        }
        if ($allWaitersPerPage < 5) {
            $allWaitersPerPage = 5;
        }
        if (! in_array($allWaitersPerPage, [5, 10, 20, 50], true)) {
            $allWaitersPerPage = 20;
        }

        $dateRange = $this->getDateRange($period, $selectedDate);
        $periodLabel = $this->resolvePeriodLabel($period, $selectedDate);

        $waiters = User::whereHas('roles', fn ($q) => $q->where('name', 'Waiter/Server'))
            ->whereHas('internalUser', fn ($q) => $q->where('is_active', true))
            ->with(['internalUser.area'])
            ->get();

        $selectedWaiter = null;
        $stats = null;
        $topProducts = collect();
        $recentSessions = collect();
        $dailyHistory = collect();
        $allWaitersStats = collect();
        $rank = null;

        if ($mode === 'individual') {
            $selectedWaiter = $waiters->firstWhere('id', $waiterId) ?? $waiters->first();

            if ($selectedWaiter) {
                // Orders credited to sessions this waiter handled
                $ordersBase = DB::table('orders')
                    ->join('table_sessions', 'orders.table_session_id', '=', 'table_sessions.id')
                    ->where('table_sessions.waiter_id', $selectedWaiter->id)
                    ->where('orders.status', '!=', 'cancelled')
                    ->whereBetween('orders.created_at', [$dateRange['start'], $dateRange['end']]);

                $totalOrderRevenue = (clone $ordersBase)->sum('orders.total');
                $totalTransactions = (clone $ordersBase)->count();
                $avgPerTransaction = $totalTransactions > 0 ? $totalOrderRevenue / $totalTransactions : 0;

                // Sessions handled in period
                $sessionsBase = DB::table('table_sessions')
                    ->where('waiter_id', $selectedWaiter->id)
                    ->whereBetween('checked_in_at', [$dateRange['start'], $dateRange['end']]);

                $customersHandled = (clone $sessionsBase)->count();
                $completedSessions = (clone $sessionsBase)->where('status', 'completed')->count();

                // Total billing revenue (orders + min charge) from their completed sessions
                $sessionRevenue = DB::table('billings')
                    ->join('table_sessions', 'billings.table_session_id', '=', 'table_sessions.id')
                    ->where('table_sessions.waiter_id', $selectedWaiter->id)
                    ->where('billings.billing_status', 'paid')
                    ->whereBetween('table_sessions.checked_in_at', [$dateRange['start'], $dateRange['end']])
                    ->sum('billings.grand_total');

                // Avg session duration from completed sessions
                $completedRows = DB::table('table_sessions')
                    ->where('waiter_id', $selectedWaiter->id)
                    ->whereNotNull('checked_in_at')
                    ->whereNotNull('checked_out_at')
                    ->whereBetween('checked_in_at', [$dateRange['start'], $dateRange['end']])
                    ->select('checked_in_at', 'checked_out_at')
                    ->get();

                $avgDurationMinutes = $completedRows->isNotEmpty()
                    ? (int) round($completedRows->avg(fn ($r) => abs(
                        \Carbon\Carbon::parse($r->checked_out_at)->diffInMinutes(\Carbon\Carbon::parse($r->checked_in_at))
                    )))
                    : 0;

                // Rank by session revenue among all waiters
                $allRevenues = [];
                foreach ($waiters as $w) {
                    $allRevenues[$w->id] = DB::table('billings')
                        ->join('table_sessions', 'billings.table_session_id', '=', 'table_sessions.id')
                        ->where('table_sessions.waiter_id', $w->id)
                        ->where('billings.billing_status', 'paid')
                        ->whereBetween('table_sessions.checked_in_at', [$dateRange['start'], $dateRange['end']])
                        ->sum('billings.grand_total');
                }
                arsort($allRevenues);
                $rank = array_search($selectedWaiter->id, array_keys($allRevenues)) + 1;

                $stats = compact(
                    'totalOrderRevenue', 'totalTransactions', 'avgPerTransaction',
                    'customersHandled', 'completedSessions', 'sessionRevenue', 'avgDurationMinutes'
                );

                // Top 5 products from sessions assigned to this waiter
                $topProducts = DB::table('order_items')
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->join('table_sessions', 'orders.table_session_id', '=', 'table_sessions.id')
                    ->where('table_sessions.waiter_id', $selectedWaiter->id)
                    ->where('orders.status', '!=', 'cancelled')
                    ->where('order_items.status', '!=', 'cancelled')
                    ->whereBetween('orders.created_at', [$dateRange['start'], $dateRange['end']])
                    ->select(
                        'order_items.item_name',
                        DB::raw('SUM(order_items.quantity) as total_qty'),
                        DB::raw('SUM(order_items.subtotal) as total_revenue')
                    )
                    ->groupBy('order_items.item_name')
                    ->orderByDesc('total_qty')
                    ->limit(5)
                    ->get();

                // Recent sessions handled (with customer name, table, billing total, duration)
                $recentSessions = DB::table('table_sessions')
                    ->join('tables', 'table_sessions.table_id', '=', 'tables.id')
                    ->leftJoin('billings', 'billings.table_session_id', '=', 'table_sessions.id')
                    ->leftJoin('users', 'table_sessions.customer_id', '=', 'users.id')
                    ->where('table_sessions.waiter_id', $selectedWaiter->id)
                    ->whereBetween('table_sessions.checked_in_at', [$dateRange['start'], $dateRange['end']])
                    ->select(
                        'table_sessions.id',
                        'table_sessions.session_code',
                        'table_sessions.checked_in_at',
                        'table_sessions.checked_out_at',
                        'table_sessions.status',
                        'tables.table_number',
                        'billings.grand_total',
                        'billings.orders_total',
                        'billings.tax_percentage',
                        'billings.discount_amount',
                        'billings.billing_status',
                        'users.name as customer_name'
                    )
                    ->orderByDesc('table_sessions.checked_in_at')
                    ->limit(10)
                    ->get();

                $historyDays = 60;
                $historyAnchorDate = null;

                if ($selectedDate !== null) {
                    try {
                        $historyDays = 1;
                        $historyAnchorDate = Carbon::parse($selectedDate, 'Asia/Jakarta');
                    } catch (\Throwable) {
                        // ignore invalid date and fallback to rolling history
                    }
                }

                $dailyHistory = $this->paginateDailyHistory(
                    $this->buildDailyHistory($selectedWaiter, $historyDays, $historyAnchorDate),
                    $historyPerPage,
                    $request
                );
            }
        } else {
            // All waiters comparison
            foreach ($waiters as $w) {
                $sessQ = DB::table('table_sessions')
                    ->where('waiter_id', $w->id)
                    ->whereBetween('checked_in_at', [$dateRange['start'], $dateRange['end']]);

                $ordQ = DB::table('orders')
                    ->join('table_sessions', 'orders.table_session_id', '=', 'table_sessions.id')
                    ->where('table_sessions.waiter_id', $w->id)
                    ->where('orders.status', '!=', 'cancelled')
                    ->whereBetween('orders.created_at', [$dateRange['start'], $dateRange['end']]);

                $sessionRevenue = DB::table('billings')
                    ->join('table_sessions', 'billings.table_session_id', '=', 'table_sessions.id')
                    ->where('table_sessions.waiter_id', $w->id)
                    ->where('billings.billing_status', 'paid')
                    ->whereBetween('table_sessions.checked_in_at', [$dateRange['start'], $dateRange['end']])
                    ->sum('billings.grand_total');

                $customersHandled = (clone $sessQ)->count();
                $txCount = (clone $ordQ)->count();

                $allWaitersStats->push((object) [
                    'user' => $w,
                    'sessionRevenue' => $sessionRevenue,
                    'customersHandled' => $customersHandled,
                    'totalTransactions' => $txCount,
                    'avgPerCustomer' => $customersHandled > 0 ? $sessionRevenue / $customersHandled : 0,
                ]);
            }
            $allWaitersStats = $this->paginateAllWaitersStats(
                $allWaitersStats->sortByDesc('sessionRevenue')->values(),
                $allWaitersPerPage,
                $request
            );
        }

        return view('waiter-performance.index', compact(
            'period', 'mode', 'waiters', 'selectedWaiter', 'stats',
            'topProducts', 'recentSessions', 'dailyHistory', 'allWaitersStats', 'rank', 'dateRange', 'selectedDate', 'periodLabel'
        ));
    }

    public function monthlyHistory(Request $request, User $waiter): View
    {
        $timezone = 'Asia/Jakarta';
        $monthInput = trim((string) $request->string('month', now($timezone)->format('Y-m')));
        $days = (int) $request->integer('days', 31);

        if ($days < 1) {
            $days = 1;
        }

        if ($days > 31) {
            $days = 31;
        }

        try {
            $monthStart = Carbon::createFromFormat('Y-m', $monthInput, $timezone)->startOfMonth();
        } catch (\Throwable) {
            $monthStart = now($timezone)->startOfMonth();
        }

        $monthEnd = $monthStart->copy()->endOfMonth();
        $monthlyHistory = $this->buildDailyHistory($waiter, $days, $monthEnd)
            ->filter(function ($history) use ($monthStart, $monthEnd): bool {
                return $history->end_day >= $monthStart->toDateString()
                    && $history->end_day <= $monthEnd->toDateString();
            })
            ->values();

        $summary = [
            'days' => $monthlyHistory->count(),
            'transactions' => (int) $monthlyHistory->sum('total_transactions'),
            'customers' => (int) $monthlyHistory->sum('customers_handled'),
            'revenue' => (float) $monthlyHistory->sum('session_revenue'),
        ];

        return view('waiter-performance.monthly-history', [
            'waiter' => $waiter,
            'monthInput' => $monthStart->format('Y-m'),
            'monthLabel' => $monthStart->translatedFormat('F Y'),
            'days' => $days,
            'monthlyHistory' => $monthlyHistory,
            'summary' => $summary,
        ]);
    }

    private function getDateRange(string $period, ?string $selectedDate = null): array
    {
        if ($selectedDate !== null) {
            try {
                $endDay = Carbon::parse($selectedDate, 'Asia/Jakarta');
                [$start, $end] = \App\Models\RecapHistory::resolveWindowForDate($endDay);

                return [
                    'start' => $start->toDateTimeString(),
                    'end' => $end->toDateTimeString(),
                ];
            } catch (\Throwable) {
                // ignore invalid date and fallback to period
            }
        }

        if ($period === 'today') {
            [$start, $end] = $this->currentOperationalWindow();

            return [
                'start' => $start->toDateTimeString(),
                'end' => $end->toDateTimeString(),
            ];
        }

        return match ($period) {
            'week' => [
                'start' => now()->startOfWeek()->toDateTimeString(),
                'end' => now()->endOfWeek()->toDateTimeString(),
            ],
            default => [
                'start' => now()->startOfMonth()->toDateTimeString(),
                'end' => now()->endOfMonth()->toDateTimeString(),
            ],
        };
    }

    private function resolvePeriodLabel(string $period, ?string $selectedDate = null): string
    {
        if ($selectedDate !== null) {
            try {
                return Carbon::parse($selectedDate, 'Asia/Jakarta')->translatedFormat('d F Y');
            } catch (\Throwable) {
                // ignore invalid date and fallback to period label
            }
        }

        return match ($period) {
            'today' => 'Hari Ini',
            'week' => 'Minggu Ini',
            default => 'Bulan Ini',
        };
    }

    private function currentOperationalWindow(): array
    {
        return \App\Models\RecapHistory::resolveActiveWindow();
    }

    private function buildDailyHistory(User $waiter, int $days = 14, ?Carbon $latestEndDay = null): Collection
    {
        $timezone = 'Asia/Jakarta';
        if ($latestEndDay === null) {
            $latestEndDay = Carbon::parse(\App\Models\RecapHistory::resolveNextEndDay(), $timezone);
        }

        $history = collect();

        for ($offset = 0; $offset < $days; $offset++) {
            $endDay = $latestEndDay->copy()->timezone($timezone)->subDays($offset);
            [$startAt, $endAt] = \App\Models\RecapHistory::resolveWindowForDate($endDay);

            $ordersBase = DB::table('orders')
                ->join('table_sessions', 'orders.table_session_id', '=', 'table_sessions.id')
                ->where('table_sessions.waiter_id', $waiter->id)
                ->where('orders.status', '!=', 'cancelled')
                ->whereBetween('orders.created_at', [$startAt, $endAt]);

            $totalTransactions = (clone $ordersBase)->count();

            $customersHandled = DB::table('table_sessions')
                ->where('waiter_id', $waiter->id)
                ->whereBetween('checked_in_at', [$startAt, $endAt])
                ->count();

            $sessionRevenue = DB::table('billings')
                ->join('table_sessions', 'billings.table_session_id', '=', 'table_sessions.id')
                ->where('table_sessions.waiter_id', $waiter->id)
                ->where('billings.billing_status', 'paid')
                ->whereBetween('table_sessions.checked_in_at', [$startAt, $endAt])
                ->sum('billings.grand_total');

            $orderRows = DB::table('orders')
                ->join('table_sessions', 'orders.table_session_id', '=', 'table_sessions.id')
                ->leftJoin('users', 'table_sessions.customer_id', '=', 'users.id')
                ->leftJoin('tables', 'table_sessions.table_id', '=', 'tables.id')
                ->leftJoin('table_reservations', 'table_reservations.id', '=', 'table_sessions.table_reservation_id')
                ->leftJoin('billings', 'billings.table_session_id', '=', 'table_sessions.id')
                ->where('table_sessions.waiter_id', $waiter->id)
                ->where('orders.status', '!=', 'cancelled')
                ->whereBetween('orders.created_at', [$startAt, $endAt])
                ->select(
                    'orders.id',
                    'orders.order_number',
                    'orders.table_session_id',
                    'orders.created_at as ordered_at',
                    'orders.discount_amount as order_discount_amount',
                    'orders.total as order_total',
                    'users.name as customer_name',
                    'tables.table_number',
                    'table_reservations.down_payment_amount',
                    'billings.id as billing_id',
                    'billings.is_booking',
                    'billings.is_walk_in',
                    'billings.orders_total',
                    'billings.subtotal',
                    'billings.payment_method',
                    'billings.payment_mode',
                    'billings.payment_reference_number',
                    'billings.discount_amount',
                    'billings.tax',
                    'billings.tax_percentage',
                    'billings.service_charge',
                    'billings.service_charge_percentage',
                    'billings.paid_amount',
                    'billings.transaction_code',
                    'billings.split_cash_amount',
                    'billings.split_debit_amount',
                    'billings.split_non_cash_method',
                    'billings.split_non_cash_reference_number',
                    'billings.split_second_non_cash_amount',
                    'billings.split_second_non_cash_method',
                    'billings.split_second_non_cash_reference_number',
                    'billings.grand_total'
                )
                ->orderByDesc('orders.created_at')
                ->get();

            $orderItemsByOrder = collect();
            if ($orderRows->isNotEmpty()) {
                $orderItemsByOrder = DB::table('order_items')
                    ->whereIn('order_id', $orderRows->pluck('id'))
                    ->where('status', '!=', 'cancelled')
                    ->select('order_id', 'item_name', 'quantity', 'subtotal', 'discount_amount', 'tax_amount', 'service_charge_amount')
                    ->orderBy('order_id')
                    ->orderBy('item_name')
                    ->get()
                    ->groupBy('order_id');
            }

            $detailOrders = $orderRows->map(function ($order) use ($orderItemsByOrder) {
                $orderItems = ($orderItemsByOrder->get($order->id, collect()))->values();
                $itemSubtotalTotal = (float) $orderItems->sum(fn ($item) => (float) ($item->subtotal ?? 0));
                $itemDiscountTotal = (float) $orderItems->sum(fn ($item) => (float) ($item->discount_amount ?? 0));
                $itemTaxTotal = (float) $orderItems->sum(fn ($item) => (float) ($item->tax_amount ?? 0));
                $itemServiceChargeTotal = (float) $orderItems->sum(fn ($item) => (float) ($item->service_charge_amount ?? 0));

                $hasBilling = ! is_null($order->billing_id);

                $referenceSource = 'Riwayat Transaksi';
                if ($hasBilling && (bool) ($order->is_booking ?? false)) {
                    $referenceSource = 'Billing';
                }

                return (object) [
                    'id' => (int) $order->id,
                    'order_number' => $order->order_number,
                    'table_session_id' => (int) $order->table_session_id,
                    'ordered_at' => Carbon::parse($order->ordered_at, 'Asia/Jakarta'),
                    'order_total' => (float) ($order->order_total ?? 0),
                    'is_booking' => (bool) ($order->is_booking ?? false),
                    'is_walk_in' => (bool) ($order->is_walk_in ?? false),
                    'reference_source' => $referenceSource,
                    'customer_name' => $order->customer_name,
                    'table_number' => $order->table_number,
                    'payment_method' => $order->payment_method,
                    'payment_mode' => $order->payment_mode,
                    'payment_reference_number' => $order->payment_reference_number,
                    'orders_total' => $itemSubtotalTotal > 0 ? $itemSubtotalTotal : (float) ($order->orders_total ?? $order->order_total ?? 0),
                    'subtotal' => $itemSubtotalTotal > 0 ? $itemSubtotalTotal : (float) ($order->subtotal ?? $order->order_total ?? 0),
                    'discount_amount' => $itemDiscountTotal,
                    'down_payment_amount' => (float) ($order->down_payment_amount ?? 0),
                    'tax' => $itemTaxTotal,
                    'tax_percentage' => (float) ($order->tax_percentage ?? 0),
                    'service_charge' => $itemServiceChargeTotal,
                    'service_charge_percentage' => (float) ($order->service_charge_percentage ?? 0),
                    'grand_total' => (float) ($order->grand_total ?? $order->order_total ?? 0),
                    'paid_amount' => (float) ($order->paid_amount ?? $order->order_total ?? 0),
                    'transaction_code' => $order->transaction_code ?? null,
                    'split_cash_amount' => (float) ($order->split_cash_amount ?? 0),
                    'split_debit_amount' => (float) ($order->split_debit_amount ?? 0),
                    'split_non_cash_method' => $order->split_non_cash_method,
                    'split_non_cash_reference_number' => $order->split_non_cash_reference_number,
                    'split_second_non_cash_amount' => (float) ($order->split_second_non_cash_amount ?? 0),
                    'split_second_non_cash_method' => $order->split_second_non_cash_method,
                    'split_second_non_cash_reference_number' => $order->split_second_non_cash_reference_number,
                    'items' => $orderItems,
                ];
            })->values();

            if ($totalTransactions === 0 && $customersHandled === 0 && (float) $sessionRevenue === 0.0) {
                continue;
            }

            $history->push((object) [
                'end_day' => $endDay->toDateString(),
                'window_start' => $startAt,
                'window_end' => $endAt,
                'customers_handled' => (int) $customersHandled,
                'total_transactions' => (int) $totalTransactions,
                'session_revenue' => (float) $sessionRevenue,
                'avg_per_customer' => $customersHandled > 0 ? (float) $sessionRevenue / $customersHandled : 0.0,
                'orders' => $detailOrders,
            ]);
        }

        return $history->values();
    }

    private function paginateDailyHistory(Collection $history, int $perPage, Request $request): LengthAwarePaginator
    {
        $page = max((int) $request->get('page', 1), 1);
        $items = $history->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $history->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    private function paginateAllWaitersStats(Collection $stats, int $perPage, Request $request): LengthAwarePaginator
    {
        $page = max((int) $request->get('page', 1), 1);
        $items = $stats->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $stats->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }
}
// DEBUG
