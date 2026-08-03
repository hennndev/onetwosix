<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionCheckerController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        $areas = $user ? $user->getAccessibleAreas() : Area::where('is_active', true)->orderBy('sort_order')->get();
        $selectedAreaId = $user ? $user->resolveActiveAreaId($request->input('area_id'), $request->has('area_id')) : ($request->filled('area_id')
            ? ($request->input('area_id') === 'all' ? null : (int) $request->input('area_id'))
            : (session('active_area_id') && session('active_area_id') !== 'all' ? (int) session('active_area_id') : null));

        $tab = $request->get('tab', 'all');

        $areaFilter = fn ($q) => $q->when(
            $selectedAreaId,
            fn ($sq) => $sq->where(
                fn ($sub) => $sub
                    ->where('area_id', $selectedAreaId)
                    ->orWhereHas('tableSession.table', fn ($t) => $t->where('area_id', $selectedAreaId))
            )
        );

        $query = Order::with([
            'items',
            'tableSession.table',
            'tableSession.customer.profile',
            'customer.user',
        ])
            ->whereNotIn('status', ['cancelled'])
            ->tap($areaFilter);

        if ($tab === 'proses') {
            $query->whereIn('status', ['pending', 'preparing', 'ready']);
        } elseif ($tab === 'selesai') {
            $query->where('status', 'completed');
        } else {
            // default 'all': exclude completed
            $query->whereIn('status', ['pending', 'preparing', 'ready']);
        }

        $orders = $query->latest('ordered_at')->get();

        $totalOrders = Order::whereNotIn('status', ['cancelled'])->tap($areaFilter)->count();
        $baruOrders = Order::where('status', 'pending')->tap($areaFilter)->count();
        $prosesOrders = Order::whereIn('status', ['preparing', 'ready'])->tap($areaFilter)->count();
        $selesaiOrders = Order::where('status', 'completed')->tap($areaFilter)->count();

        return view('transaction-checker.index', compact(
            'orders',
            'tab',
            'totalOrders',
            'baruOrders',
            'prosesOrders',
            'selesaiOrders',
            'areas',
            'selectedAreaId',
        ));
    }

    public function checkItem(OrderItem $item): JsonResponse
    {
        $item->update([
            'status' => 'served',
            'served_at' => now(),
        ]);

        $item->order->updateStatus();

        $order = Order::with('items')->find($item->order_id);
        $servedCount = $order->items->where('status', 'served')->count();
        $totalCount = $order->items->where('status', '!=', 'cancelled')->count();

        return response()->json([
            'success' => true,
            'message' => 'Item ditandai sebagai selesai.',
            'order_status' => $order->status,
            'served_count' => $servedCount,
            'total_count' => $totalCount,
        ]);
    }

    public function checkAll(Order $order): JsonResponse
    {
        $order->items()
            ->whereNotIn('status', ['cancelled', 'served'])
            ->update([
                'status' => 'served',
                'served_at' => now(),
            ]);

        $order->updateStatus();

        return response()->json([
            'success' => true,
            'message' => 'Semua item ditandai sebagai selesai.',
            'order_status' => $order->fresh()->status,
        ]);
    }
}
