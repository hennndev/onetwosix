<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Services\AccurateService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(protected AccurateService $accurateService) {}

    public function index(Request $request)
    {
        $query = InventoryItem::with('category')
            ->whereNotIn('category_type', ['food', 'bar']);

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('category_type', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category_type', $request->category);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->boolean('status'));
        }

        if ($request->filled('item_group')) {
            $query->where('is_item_group', $request->boolean('item_group'));
        }

        if ($request->stock_filter == 'low') {
            $query->whereColumn('stock_quantity', '<=', 'threshold');
        }

        $items = $query->orderBy('name')
            ->paginate((int) $request->integer('per_page', 20))
            ->withQueryString();

        // Modal edit threshold butuh semua item sekaligus — koleksi terpisah dari paginator.
        $allItems = InventoryItem::orderBy('name')->get(['id', 'name', 'threshold', 'unit', 'stock_quantity']);

        $totalItems = InventoryItem::count();
        $totalStockValue = InventoryItem::selectRaw('SUM(price * stock_quantity) as total')->value('total') ?? 0;
        $lowStockCount = InventoryItem::whereColumn('stock_quantity', '<=', 'threshold')->count();
        $categoryTypes = InventoryItem::distinct()->orderBy('category_type')->pluck('category_type');

        return view('inventory.index', compact(
            'items',
            'allItems',
            'totalItems',
            'totalStockValue',
            'lowStockCount',
            'categoryTypes',
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:inventory_items,code',
            'category_type' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'threshold' => 'required|integer|min:0',
            'unit' => 'required|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        try {
            InventoryItem::create($validated);

            return redirect()->route('admin.inventory.index')
                ->with('success', 'Inventory item berhasil ditambahkan');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal menambahkan item: '.$e->getMessage()])
                ->withInput();
        }
    }

    public function toggleActive(InventoryItem $inventory)
    {
        try {
            if (! $inventory->accurate_id) {
                return back()->withErrors(['error' => 'Gagal mengubah status item: Accurate ID tidak ditemukan.']);
            }

            $nextIsActive = ! $inventory->is_active;

            $this->accurateService->saveItem([
                'id' => $inventory->accurate_id,
                'suspended' => ! $nextIsActive,
            ]);

            $inventory->update([
                'is_active' => $nextIsActive,
            ]);

            $status = $inventory->fresh()->is_active ? 'aktif' : 'nonaktif';

            return redirect()->route('admin.inventory.index')
                ->with('success', "Status item berhasil diubah menjadi {$status}.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal mengubah status item: '.$e->getMessage()]);
        }
    }

    public function update(Request $request, InventoryItem $inventory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:inventory_items,code,'.$inventory->id,
            'category_type' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'threshold' => 'required|integer|min:0',
            'unit' => 'required|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        try {
            $inventory->update($validated);

            return redirect()->route('admin.inventory.index')
                ->with('success', 'Inventory item berhasil diupdate');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal mengupdate item: '.$e->getMessage()])
                ->withInput();
        }
    }

    public function destroy(InventoryItem $inventory)
    {
        try {
            $inventory->delete();

            return redirect()->route('admin.inventory.index')
                ->with('success', 'Inventory item berhasil dihapus');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal menghapus item: '.$e->getMessage()]);
        }
    }

    public function fetchDetail(InventoryItem $inventory): \Illuminate\Http\JsonResponse
    {
        if (! $inventory->accurate_id) {
            return response()->json(['success' => false, 'message' => 'Item tidak memiliki Accurate ID.'], 422);
        }

        $detail = $this->accurateService->getDetailItem($inventory->accurate_id);

        if (! $detail) {
            return response()->json(['success' => false, 'message' => 'Gagal mengambil detail dari Accurate.'], 502);
        }

        $detailGroup = collect($detail['detailGroup'] ?? [])->map(fn ($g) => [
            'item_id' => $g['itemId'] ?? null,
            'detail_name' => $g['detailName'] ?? null,
            'quantity' => $g['quantity'] ?? 0,
            'unit' => $g['itemUnit']['name'] ?? null,
            'seq' => $g['seq'] ?? null,
        ])->values()->all();

        return response()->json(['success' => true, 'name' => $inventory->name, 'detail_group' => $detailGroup]);
    }

    public function updateThreshold(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:inventory_items,id',
            'items.*.threshold' => 'required|integer|min:0',
        ]);

        try {
            foreach ($validated['items'] as $itemData) {
                InventoryItem::where('id', $itemData['id'])->update([
                    'threshold' => $itemData['threshold'],
                ]);
            }

            return redirect()->route('admin.inventory.index')
                ->with('success', 'Threshold berhasil diupdate');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal mengupdate threshold: '.$e->getMessage()]);
        }
    }
}
