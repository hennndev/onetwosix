<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\PosCategorySetting;
use App\Models\Printer;
use App\Services\AccurateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function __construct(protected AccurateService $accurateService) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search', ''));

        $inventoryItems = InventoryItem::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'pos_name', 'unit', 'is_visible_in_pos']);

        $inventoryCategoryTypes = InventoryItem::query()
            ->where('is_active', true)
            ->whereNotNull('category_type')
            ->where('category_type', '!=', '')
            ->distinct()
            ->orderBy('category_type')
            ->pluck('category_type');

        $menuCategoryTypes = PosCategorySetting::query()
            ->where('is_menu', true)
            ->orderBy('category_type')
            ->pluck('category_type');

        $menusByCategory = $menuCategoryTypes
            ->mapWithKeys(function (string $categoryType) {
                $menus = InventoryItem::query()
                    ->with(['printers:id,name,location'])
                    ->where('is_active', true)
                    ->where('category_type', $categoryType)
                    ->orderBy('name')
                    ->get(['id', 'code', 'name', 'pos_name', 'category_type', 'category_main', 'price', 'unit', 'include_tax', 'include_service_charge', 'is_item_group', 'is_group_sold_out', 'is_count_portion_possible', 'is_visible_in_pos', 'item_type']);

                return [$categoryType => $menus];
            });

        if ($search !== '') {
            $menusByCategory = $menusByCategory
                ->map(function ($menus) use ($search) {
                    return $menus->filter(function (InventoryItem $menu) use ($search): bool {
                        return str_contains(strtolower((string) $menu->name), strtolower($search))
                            || str_contains(strtolower((string) $menu->pos_name), strtolower($search))
                            || str_contains(strtolower((string) $menu->code), strtolower($search));
                    })->values();
                });

            $menuCategoryTypes = $menuCategoryTypes
                ->filter(fn (string $categoryType): bool => $menusByCategory->get($categoryType, collect())->isNotEmpty())
                ->values();
        }

        $printers = Printer::query()
            ->active()
            ->orderBy('location')
            ->orderBy('name')
            ->get(['id', 'name', 'location']);

        return view('menus.index', compact('inventoryItems', 'inventoryCategoryTypes', 'menuCategoryTypes', 'menusByCategory', 'printers', 'search'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code_mode' => ['required', 'string', 'in:manual,auto'],
            'no' => 'nullable|required_if:code_mode,manual|string|max:100',
            'name' => 'required|string|max:255',
            'item_type' => ['required', 'string', 'in:GROUP,INVENTORY'],
            'category_type' => ['required', 'string', 'max:255'],
            'category_main' => ['nullable', 'string', 'in:food,alcohol,beverage,cigarette,breakage,room,staff_meal,compliment,foc,LD'],
            'unit' => 'required|string|max:50',
            'selling_price' => 'required|numeric|min:0',
            'include_tax' => ['nullable', 'boolean'],
            'include_service_charge' => ['nullable', 'boolean'],
            'is_item_group' => ['nullable', 'boolean'],
            'is_visible_in_pos' => ['nullable', 'boolean'],
            'printer_ids' => ['nullable', 'array'],
            'printer_ids.*' => ['integer', 'exists:printers,id'],
            'pos_name' => ['nullable', 'string', 'max:255'],
            'detail_group' => 'nullable|array',
            'detail_group.*.item_no' => 'required|string|max:100',
            'detail_group.*.detail_name' => 'required|string|max:255',
            'detail_group.*.quantity' => 'required|integer|min:1',
        ], [
            'category_type.required' => 'Kategori menu wajib dipilih.',
        ]);

        $detailGroup = collect($validated['detail_group'] ?? [])
            ->values()
            ->map(fn ($row, $seq) => [
                'itemNo' => $row['item_no'],
                'detailName' => $row['detail_name'],
                'quantity' => (int) $row['quantity'],
            ])
            ->toArray();

        $payload = [
            'name' => $validated['name'],
            'itemType' => $validated['item_type'],
            'unit1Name' => $validated['unit'],
            'unitPrice' => $validated['selling_price'],
            'detailGroup' => $detailGroup,
        ];

        if (($validated['code_mode'] ?? 'manual') === 'manual' && ! empty($validated['no'])) {
            $payload['no'] = $validated['no'];
        }

        if (! empty($validated['category_type'])) {
            $payload['itemCategoryName'] = $validated['category_type'];
        }

        try {
            $result = $this->accurateService->saveItem($payload);

            $accurateId = $result['r']['id'] ?? null;
            $accurateNo = $validated['no']
                ?? $result['r']['no']
                ?? $result['d']['no']
                ?? null;

            if ($accurateId !== null) {
                $resolvedCode = $accurateNo ?? 'ACC-'.$accurateId;

                $attributes = [
                    'accurate_id' => $accurateId,
                    'code' => $resolvedCode,
                    'name' => $validated['name'],
                    'pos_name' => $validated['pos_name'] ?? $validated['name'],
                    'category_type' => $validated['category_type'] ?? '',
                    'category_main' => $validated['category_main'] ?? null,
                    'price' => $validated['selling_price'],
                    'include_tax' => (bool) ($validated['include_tax'] ?? false),
                    'include_service_charge' => (bool) ($validated['include_service_charge'] ?? false),
                    'is_item_group' => array_key_exists('is_item_group', $validated)
                        ? (bool) $validated['is_item_group']
                        : ($validated['item_type'] === 'GROUP'),
                    'is_visible_in_pos' => array_key_exists('is_visible_in_pos', $validated)
                        ? (bool) $validated['is_visible_in_pos']
                        : true,
                    'item_type' => $validated['item_type'] ?? 'INVENTORY',
                    'stock_quantity' => 0,
                    'unit' => $validated['unit'],
                    'is_active' => true,
                ];

                $inventoryItem = InventoryItem::query()
                    ->where('accurate_id', $accurateId)
                    ->orWhere('code', $resolvedCode)
                    ->first();

                if ($inventoryItem) {
                    $inventoryItem->update($attributes);
                } else {
                    $inventoryItem = InventoryItem::create($attributes);
                }

                $inventoryItem->printers()->sync($validated['printer_ids'] ?? []);
            }

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function updateTaxFlags(Request $request, InventoryItem $inventory): JsonResponse
    {
        $validated = $request->validate([
            'field' => ['required', 'string', 'in:include_tax,include_service_charge,is_item_group,is_group_sold_out,is_count_portion_possible,is_visible_in_pos'],
            'value' => ['required', 'boolean'],
        ]);

        $inventory->update([$validated['field'] => $validated['value']]);

        return response()->json(['success' => true, 'value' => $inventory->fresh()->{$validated['field']}]);
    }

    public function fetchDetail(InventoryItem $inventory): JsonResponse
    {
        $inventory->loadMissing(['printers:id']);

        if (! $inventory->accurate_id) {
            return response()->json(['success' => false, 'message' => 'Item tidak memiliki Accurate ID.'], 422);
        }

        $detail = $this->accurateService->getDetailItem($inventory->accurate_id);

        if (! $detail) {
            return response()->json(['success' => false, 'message' => 'Gagal mengambil detail dari Accurate.'], 502);
        }

        $detailGroup = collect($detail['detailGroup'] ?? [])->map(fn ($group) => [
            'item_id' => $group['itemId'] ?? null,
            'detail_name' => $group['detailName'] ?? null,
            'quantity' => $group['quantity'] ?? 0,
            'unit' => $group['itemUnit']['name'] ?? null,
            'seq' => $group['seq'] ?? null,
        ])->values()->all();

        return response()->json([
            'success' => true,
            'name' => $inventory->name,
            'pos_name' => $inventory->pos_name,
            'is_visible_in_pos' => (bool) $inventory->is_visible_in_pos,
            'is_group_sold_out' => (bool) $inventory->is_group_sold_out,
            'printer_ids' => $inventory->printers->pluck('id')->values()->all(),
            'detail_group' => $detailGroup,
        ]);
    }

    public function updatePosName(Request $request, InventoryItem $inventory): JsonResponse
    {
        $validated = $request->validate([
            'pos_name' => ['required', 'string', 'max:255'],
        ]);

        $inventory->update(['pos_name' => $validated['pos_name']]);

        return response()->json(['success' => true, 'pos_name' => $inventory->fresh()->pos_name]);
    }

    public function updateCategoryMain(Request $request, InventoryItem $inventory): JsonResponse
    {
        $validated = $request->validate([
            'category_main' => ['nullable', 'string', 'in:food,alcohol,beverage,cigarette,breakage,room,staff_meal,compliment,foc,LD'],
        ]);

        $inventory->update(['category_main' => $validated['category_main'] ?? null]);

        return response()->json(['success' => true, 'category_main' => $inventory->fresh()->category_main]);
    }

    public function updatePrinterTargets(Request $request, InventoryItem $inventory): JsonResponse
    {
        $validated = $request->validate([
            'printer_ids' => ['nullable', 'array'],
            'printer_ids.*' => ['integer', 'exists:printers,id'],
        ]);

        $inventory->printers()->sync($validated['printer_ids'] ?? []);

        $printers = $inventory->printers()
            ->select('printers.id', 'printers.name', 'printers.location')
            ->get()
            ->map(fn ($printer) => ['id' => $printer->id, 'name' => $printer->name, 'location' => $printer->location])
            ->all();

        return response()->json([
            'success' => true,
            'printers' => $printers,
        ]);
    }
}
