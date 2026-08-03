<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AccurateService
{
    /**
     * Generic method untuk mengambil list data dari Accurate API
     *
     * @param  string  $endpoint  - API endpoint (e.g., 'purchase-order', 'sales-order')
     * @param  array  $defaultFields  - Field yang akan diambil
     * @param  string  $sortBy  - Default sorting
     * @param  int  $pageSize  - Jumlah data per halaman
     */
    protected function getList(
        string $endpoint,
        Request $request,
        array $defaultFields,
        string $sortBy = 'transDate desc',
        int $pageSize = 20
    ): Collection {
        try {
            $fieldsToRequest = ! empty($defaultFields)
                ? implode(',', array_unique(array_merge(['id', 'name', 'no', 'unitPrice', 'unit1Price', 'itemCategory', 'notes', 'detailGroup', 'itemUnit', 'suspended', 'availableToSellInAllUnit', 'allQuantity', 'quantity'], $defaultFields)))
                : 'id,name,no,unitPrice,unit1Price,itemCategory,notes,detailGroup,itemUnit,suspended,availableToSellInAllUnit,allQuantity,quantity';

            $params = [
                'fields' => $fieldsToRequest,
                'sort' => $sortBy,
                'sp.page' => $request->get('page', 1),
                'sp.pageSize' => $request->get('pageSize', $pageSize),
            ];

            // Filter tanggal (opsional)
            if ($request->filled(['start_date', 'end_date'])) {
                $params['filter.transDate.op'] = 'BETWEEN';
                $params['filter.transDate.val[0]'] = $request->start_date;
                $params['filter.transDate.val[1]'] = $request->end_date;
            }

            // Filter pencarian (opsional)
            if ($request->filled('search')) {
                $params['filter.keywords.op'] = 'CONTAIN';
                $params['filter.keywords.val'] = $request->search;
            }

            // Filter berdasarkan item_type (untuk item/raw material)
            if ($request->filled('item_type')) {
                $params['filter.itemType.op'] = 'EQUAL';
                $params['filter.itemType.val'] = $request->item_type;
            }

            $response = $this->dataClient()->get("/api/{$endpoint}/list.do", $params);

            if ($response->failed()) {
                Log::error("Gagal mengambil daftar {$endpoint} dari Accurate", ['response' => $response->json()]);

                return collect([]);
            }

            return collect($response->json()['d'] ?? []);
        } catch (\Throwable $e) {
            Log::error("Exception saat mengambil daftar {$endpoint}", ['message' => $e->getMessage()]);

            return collect([]);
        }
    }

    /**
     * Generic method untuk mengambil list data dari Accurate API
     *
     * @param  string  $endpoint  - API endpoint (e.g., 'purchase-order', 'sales-order')
     * @param  array  $defaultFields  - Field yang akan diambil
     * @param  string  $sortBy  - Default sorting
     * @param  int  $pageSize  - Jumlah data per halaman
     */
    protected function getStockList(
        string $endpoint,
        Request $request,
        array $defaultFields,
        string $sortBy = 'transDate desc',
        int $pageSize = 20
    ): Collection {
        try {
            $baseParams = [
                'fields' => implode(',', $defaultFields),
                'sort' => $sortBy,
                'sp.pageSize' => $pageSize,
            ];

            // Filter tanggal (opsional)
            if ($request->filled(['start_date', 'end_date'])) {
                $baseParams['filter.transDate.op'] = 'BETWEEN';
                $baseParams['filter.transDate.val[0]'] = $request->start_date;
                $baseParams['filter.transDate.val[1]'] = $request->end_date;
            }

            // Filter pencarian (opsional)
            if ($request->filled('search')) {
                $baseParams['filter.keywords.op'] = 'CONTAIN';
                $baseParams['filter.keywords.val'] = $request->search;
            }

            // Filter berdasarkan item_type (untuk item/raw material)
            if ($request->filled('item_type')) {
                $baseParams['filter.itemType.op'] = 'EQUAL';
                $baseParams['filter.itemType.val'] = $request->item_type;
            }

            if ($request->filled('warehouse_name')) {
                $baseParams['warehouseName'] = $request->string('warehouse_name')->toString();
            }

            $allItems = collect();
            $page = 1;

            do {
                $response = $this->dataClient()->get("/api/{$endpoint}/list-stock.do", array_merge($baseParams, ['sp.page' => $page]));
                if ($response->failed()) {
                    Log::error("Gagal mengambil daftar {$endpoint} dari Accurate", ['page' => $page, 'response' => $response->json()]);
                    break;
                }

                $items = collect($response->json()['d'] ?? []);
                $allItems = $allItems->merge($items);
                $page++;
            } while ($items->count() >= $pageSize);

            return $allItems;
        } catch (\Throwable $e) {
            Log::error("Exception saat mengambil daftar {$endpoint}", ['message' => $e->getMessage()]);

            return collect([]);
        }
    }

    /**
     * Generic method untuk mengambil detail data dari Accurate API
     *
     * @param  string  $endpoint  - API endpoint (e.g., 'purchase-order', 'sales-order')
     */
    protected function getDetail(string $endpoint, int $id): ?array
    {
        try {
            $response = $this->dataClient()->get("/api/{$endpoint}/detail.do", ['id' => $id]);

            Log::info('response', ['response' => $response->json(), 'endpoint' => $endpoint, 'id' => $id]);

            if ($response->failed()) {
                return null;
            }

            return $response->json()['d'] ?? null;
        } catch (\Exception $e) {
            Log::error("Exception saat mengambil detail {$endpoint}", [
                'id' => $id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Generic method untuk mencari data berdasarkan nomor/field tertentu
     *
     * @param  string  $field  - Field untuk filter (e.g., 'number', 'name')
     * @param  string  $value  - Nilai yang dicari
     * @param  array  $fields  - Field yang akan diambil di list
     */
    protected function getByField(string $endpoint, string $field, string $value, array $fields): ?array
    {
        try {
            $params = [
                'fields' => implode(',', $fields),
                "filter.{$field}.op" => 'EQUAL',
                "filter.{$field}.val" => $value,
            ];

            $response = $this->dataClient()->get("/api/{$endpoint}/list.do", $params);

            if ($response->failed()) {
                Log::error("Gagal mencari {$endpoint} berdasarkan {$field}", [
                    'field' => $field,
                    'value' => $value,
                    'response' => $response->json(),
                ]);

                return null;
            }

            $list = collect($response->json()['d'] ?? []);

            if ($list->isEmpty()) {
                Log::warning("{$endpoint} tidak ditemukan", ["{$field}" => $value]);

                return null;
            }

            // Ambil yang exact match
            $exactMatch = $list->firstWhere($field, $value);

            if (! $exactMatch) {
                Log::warning("{$endpoint} {$field} tidak exact match", [
                    'requested' => $value,
                    'found' => $list->pluck($field)->toArray(),
                ]);

                return null;
            }

            $id = $exactMatch['id'] ?? null;
            if (! $id) {
                Log::warning("{$endpoint} tidak punya ID", ["{$field}" => $value]);

                return null;
            }

            // Ambil detail berdasarkan ID
            return $this->getDetail($endpoint, (int) $id);
        } catch (\Throwable $e) {
            Log::error("Exception saat mengambil {$endpoint}", [
                'field' => $field,
                'value' => $value,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Generic method untuk menyimpan data ke Accurate API
     *
     * @param  string  $endpoint  - API endpoint (e.g., 'delivery-order', 'material-slip')
     * @param  string  $action  - Action name untuk logging (default: 'save')
     *
     * @throws Exception
     */
    protected function saveData(string $endpoint, array $data, string $action = 'save'): array
    {
        try {
            $response = $this->dataClient()->post("/api/{$endpoint}/{$action}.do", $data);
            $result = $response->json();

            if ($response->failed()) {
                throw new Exception('HTTP Error '.$response->status());
            }
            if (is_array($result) && ! isset($result['d']) && ! isset($result['s']) && ! isset($result['r'])) {
                throw new Exception(is_array($result) ? implode(', ', $result) : json_encode($result));
            }

            if (isset($result['s']) && $result['s'] === false) {
                $errorMsg = $result['m'] ?? (isset($result['d'])
                  ? (is_array($result['d']) ? implode(', ', $result['d']) : $result['d'])
                  : 'Unknown error');
                throw new Exception($errorMsg);
            }

            return $result;
        } catch (\Exception $e) {
            Log::info('error', ['message' => $e->getMessage(), 'endpoint' => $endpoint, 'action' => $action, 'data' => $data]);
            throw new Exception('Accurate Error: '.$e->getMessage());
        }
    }

    protected function getStock(string $no, ?string $warehouseName = null): ?array
    {
        try {
            $params = ['no' => $no];

            if ($warehouseName !== null && $warehouseName !== '') {
                $params['warehouseName'] = $warehouseName;
            }

            $response = $this->dataClient()->get('/api/item/get-stock.do', $params);

            if ($response->failed()) {
                Log::error('Gagal mengambil detail item dari Accurate', [
                    'no' => $no,
                    'warehouse_name' => $warehouseName,
                    'response' => $response->json(),
                ]);

                return null;
            }

            return $response->json()['d'] ?? null;
        } catch (\Exception $e) {
            Log::error('Exception saat mengambil detail item', [
                'no' => $no,
                'warehouse_name' => $warehouseName,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Generic method untuk menghapus data dari Accurate API
     *
     * @param  string  $endpoint  - API endpoint (e.g., 'item-category', 'unit')
     * @param  int  $id  - ID data yang akan dihapus
     *
     * @throws Exception
     */
    protected function deleteData(string $endpoint, int $id): array
    {
        try {
            $response = $this->dataClient()->post("/api/{$endpoint}/delete.do", ['id' => $id]);
            $result = $response->json();

            if ($response->failed()) {
                throw new Exception('HTTP Error '.$response->status());
            }

            if (is_array($result) && ! isset($result['d']) && ! isset($result['s']) && ! isset($result['r'])) {
                Log::error('Format response tidak valid', ['response' => $result]);
                throw new Exception(is_array($result) ? implode(', ', $result) : json_encode($result));
            }

            if (isset($result['s']) && $result['s'] === false) {
                $errorMsg = $result['m'] ?? (isset($result['d'])
                  ? (is_array($result['d']) ? implode(', ', $result['d']) : $result['d'])
                  : 'Unknown error');
                Log::error("Gagal menghapus {$endpoint}", ['error' => $errorMsg]);
                throw new Exception($errorMsg);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error("Exception saat menghapus {$endpoint}", [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw new Exception('Accurate Error: '.$e->getMessage());
        }
    }

    public function getDatabaseList(): array
    {
        if (! session()->has('accurate_access_token')) {
            throw new Exception('Tidak bisa mengambil daftar database tanpa Access Token.');
        }

        $response = Http::withToken(session('accurate_access_token'))
            ->get(env('ACCURATE_API_URL').'/api/db-list.do');

        if ($response->failed()) {
            throw new Exception('Gagal mendapatkan daftar database dari Accurate.');
        }

        return $response->json()['d'] ?? [];
    }

    public function getDatabaseHost()
    {
        $response = $this->client()->post('/api/api-token.do');
        if ($response->failed() || ! isset($response->json()['d']['database']['host'])) {
            Log::error('ACCURATE_ERROR - Gagal mendapatkan host database', $response->json() ?? ['body' => $response->body()]);
            throw new Exception('Gagal mendapatkan host database dari Accurate.');
        }
        $host = $response->json()['d']['database']['host'];
        session(['accurate_host' => $host]);

        return $host;
    }

    protected function dataClient()
    {
        // Use static API token if configured (works in queue workers too)
        if (config('accurate.api_token')) {
            return $this->apiTokenClient();
        }

        // Fallback to OAuth session (legacy web flow)
        $accessToken = Cache::get('accurate_access_token') ?? session('accurate_access_token');
        $database = Cache::get('accurate_database') ?? session('accurate_database');

        if (! $accessToken) {
            throw new Exception('Token Akses Accurate tidak ditemukan.');
        }
        if (! $database) {
            throw new Exception('Database Accurate belum dipilih.');
        }

        return Http::withToken($accessToken)
            ->withHeaders(['X-Session-ID' => $database['session']])
            ->acceptJson()
            ->baseUrl($database['host'].'/accurate');
    }

    /**
     * HTTP client menggunakan static API Token + HMAC-SHA256 signature.
     * Tidak bergantung pada session — aman untuk queue workers.
     */
    protected function apiTokenClient()
    {
        $apiToken = config('accurate.api_token');
        $secret = config('accurate.signature_secret');
        $host = $this->resolveApiTokenHost();

        $timestamp = now('Asia/Jakarta')->format('d/m/Y H:i:s');
        $signature = hash_hmac('sha256', $timestamp, $secret);

        return Http::withToken($apiToken)
            ->withHeaders([
                'X-Api-Timestamp' => $timestamp,
                'X-Api-Signature' => $signature,
            ])
            ->acceptJson()
            ->timeout(config('accurate.api_timeout', 30))
            ->baseUrl($host.'/accurate');
    }

    /**
     * Resolve database host untuk API Token mode.
     * Di-cache selama 8 jam agar tidak perlu round-trip setiap request.
     */
    protected function resolveApiTokenHost(): string
    {
        return Cache::remember('accurate_api_token_host', now()->addHours(8), function () {
            $apiToken = config('accurate.api_token');
            $secret = config('accurate.signature_secret');
            $timestamp = now('Asia/Jakarta')->format('d/m/Y H:i:s');
            $signature = hash_hmac('sha256', $timestamp, $secret);

            $response = Http::withToken($apiToken)
                ->withHeaders([
                    'X-Api-Timestamp' => $timestamp,
                    'X-Api-Signature' => $signature,
                ])
                ->acceptJson()
                ->post(config('accurate.api_url').'/api/api-token.do');

            if ($response->failed() || ! isset($response->json()['d']['database']['host'])) {
                Log::error('ACCURATE_ERROR - Gagal mendapatkan host dari API token', [
                    'response' => $response->json(),
                ]);
                throw new Exception('Gagal mendapatkan host database dari Accurate.');
            }

            return $response->json()['d']['database']['host'];
        });
    }

    public function openDatabaseById(int $dbId): ?array
    {
        if (! session()->has('accurate_access_token')) {
            throw new Exception('Tidak bisa membuka database tanpa Access Token.');
        }

        try {
            $response = Http::withOptions([
                'track_redirects' => true,
            ])->withToken(session('accurate_access_token'))
                ->post(env('ACCURATE_API_URL').'/api/open-db.do', ['id' => $dbId]);

            if ($response->failed()) {
                return null;
            }

            $responseData = $response->json();

            // Cek apakah ada riwayat pengalihan
            $redirectHistory = $response->handlerStats()['redirect_history'] ?? [];
            if (! empty($redirectHistory)) {
                $lastUrl = end($redirectHistory);
                $parsedUrl = parse_url($lastUrl);
                $newHost = ($parsedUrl['scheme'] ?? 'https').'://'.$parsedUrl['host'];
                $responseData['host'] = $newHost;
                Log::info('Accurate host redirected and updated.', ['old_host' => session('accurate_database.host'), 'new_host' => $newHost]);
            }

            return $responseData;
        } catch (Exception $e) {
            Log::error('ACCURATE_ERROR - Gagal membuka database ID: '.$dbId, ['error' => $e->getMessage()]);

            return null;
        }
    }

    // ===ITEMS SCOPED===
    public function getItems(Request $request, array $fields = [])
    {
        return $this->getList('item', $request, $fields);
    }

    public function getStockItems(Request $request)
    {
        return $this->getStockList('item', $request, []);
    }

    public function getStockItem(string $no, ?string $warehouseName = null)
    {
        return $this->getStock($no, $warehouseName);
    }

    public function saveItem(array $data)
    {
        return $this->saveData('item', $data, 'save');
    }

    public function deleteItem(int $id)
    {
        return $this->deleteData('item', $id);
    }

    public function getDetailItem(int $id)
    {
        return $this->getDetail('item', $id);
    }

    /**
     * Returns the detailGroup components of an Accurate item (group/composite type).
     * Each element contains: itemId, detailName, quantity, itemUnit, seq.
     * Returns an empty array if the item has no group components or the request fails.
     */
    public function getItemGroupComponents(int $accurateId): array
    {
        $detail = $this->getDetailItem($accurateId);

        return $detail['detailGroup'] ?? [];
    }

    // ===ITEMS CATEGORY SCOPED===
    public function getItemCategories(Request $request)
    {
        return $this->getList('item-category', $request, []);
    }

    public function saveItemCategory(array $data)
    {
        return $this->saveData('item-category', $data, 'save');
    }

    public function deleteItemCategory(int $id)
    {
        return $this->deleteData('item-category', $id);
    }

    public function getDetailItemCategory(int $id)
    {
        return $this->getDetail('item-category', $id);
    }

    // ===UNIT/UOM SCOPED===
    public function getUnits(Request $request)
    {
        return $this->getList('unit', $request, []);
    }

    public function saveUnit(array $data)
    {
        return $this->saveData('unit', $data, 'save');
    }

    public function deleteUnit(int $id)
    {
        return $this->deleteData('unit', $id);
    }

    public function getDetailUnit(int $id)
    {
        return $this->getDetail('unit', $id);
    }

    // ===WAREHOUSE SCOPED===
    public function getWarehouses(Request $request)
    {
        return $this->getList('warehouse', $request, []);
    }

    public function getDetailWarehouse(int $id)
    {
        return $this->getDetail('warehouse', $id);
    }

    public function saveWarehouse(array $data)
    {
        return $this->saveData('warehouse', $data, 'save');
    }

    public function deleteWarehouse(int $id)
    {
        return $this->deleteData('warehouse', $id);
    }

    // ===CUSTOMER SCOPED===
    public function getCustomers(Request $request)
    {
        return $this->getList('customer', $request, []);
    }

    public function getDetailCustomer(int $id)
    {
        return $this->getDetail('customer', $id);
    }

    public function saveCustomer(array $data)
    {
        return $this->saveData('customer', $data, 'save');
    }

    public function deleteCustomer(int $id)
    {
        return $this->deleteData('customer', $id);
    }

    // ===PRODUCTION===
    // ===MANUFACTURE ORDER===
    public function listManufactureOrders(Request $request)
    {
        return $this->getList('manufacture-order', $request, []);
    }

    // ===WORK ORDER===
    public function saveWorkOrder(array $data)
    {
        return $this->saveData('work-order', $data, 'save');
    }

    public function deleteWorkOrder(int $id)
    {
        return $this->deleteData('work-order', $id);
    }

    // ===MATERIAL SLIP===
    public function saveMaterialSlip(array $data)
    {
        return $this->saveData('material-slip', $data, 'save');
    }

    public function deleteMaterialSlip(int $id)
    {
        return $this->deleteData('material-slip', $id);
    }

    // ===FINISHED GOOD SLIP===
    public function saveFinishedGoodSlip(array $data)
    {
        return $this->saveData('finished-good-slip', $data, 'save');
    }

    public function deleteFinishedGoodSlip(int $id)
    {
        return $this->deleteData('finished-good-slip', $id);
    }

    // ===SALES ORDER SCOPED===

    /** @var array Fields standar untuk list sales order */
    protected array $salesOrderFields = [
        'id', 'number', 'transDate', 'dueDate',
        'customer.name', 'customer.no',
        'totalAmount', 'status', 'memo',
        'branchName',
    ];

    /** @var array Fields standar untuk list sales invoice */
    protected array $salesInvoiceFields = [
        'id', 'number', 'transDate', 'dueDate',
        'customer.name', 'customer.no',
        'totalAmount', 'remainingAmount', 'status',
        'memo', 'branchName',
    ];

    public function getSalesOrders(Request $request, array $fields = [])
    {
        return $this->getList('sales-order', $request, $fields ?: $this->salesOrderFields);
    }

    public function getDetailSalesOrder(int $id): ?array
    {
        return $this->getDetail('sales-order', $id);
    }

    public function saveSalesOrder(array $data): array
    {
        return $this->saveData('sales-order', $data, 'save');
    }

    public function deleteSalesOrder(int $id): array
    {
        return $this->deleteData('sales-order', $id);
    }

    // Alias untuk backward compatibility
    public function getListSalesOrders(Request $request)
    {
        return $this->getSalesOrders($request);
    }

    // ===SALES INVOICE SCOPED===

    public function getSalesInvoices(Request $request, array $fields = [])
    {
        return $this->getList('sales-invoice', $request, $fields ?: $this->salesInvoiceFields);
    }

    public function getDetailSalesInvoice(int $id): ?array
    {
        return $this->getDetail('sales-invoice', $id);
    }

    public function saveSalesInvoice(array $data): array
    {
        return $this->saveData('sales-invoice', $data, 'save');
    }

    public function deleteSalesInvoice(int $id): array
    {
        return $this->deleteData('sales-invoice', $id);
    }

    public function saveSalesReceipt(array $data): array
    {
        return $this->saveData('sales-receipt', $data, 'save');
    }

    // INBOUND
    public function getListPurchaseOrders(Request $request)
    {
        return $this->getList('purchase-order', $request, ['id', 'number', 'transDate', 'vendor', 'totalAmount', 'status', 'itemDetails']);
    }

    public function getDetailPurchaseOrder(int $id)
    {
        return $this->getDetail('purchase-order', $id);
    }

    // ===SHIPMENT SCOPED===
    public function saveShipment(array $data)
    {
        return $this->saveData('shipment', $data, 'save');
    }

    public function deleteShipment(int $id)
    {
        return $this->deleteData('shipment', $id);
    }

    // ===EMPLOYEE SCOPED===
    public function getEmployees(Request $request, int $pageSize = 200): Collection
    {
        try {
            $allEmployees = collect();
            $page = 1;
            $perPage = (int) $request->get('pageSize', $pageSize);

            do {
                $params = [
                    'fields' => 'id,name,email,position,phone,mobilePhone,suspended',
                    'sort' => 'name asc',
                    'sp.page' => $page,
                    'sp.pageSize' => $perPage,
                ];

                if ($request->filled('search')) {
                    $params['filter.keywords.op'] = 'CONTAIN';
                    $params['filter.keywords.val'] = $request->search;
                }

                $response = $this->dataClient()->get('/api/employee/list.do', $params);

                if ($response->failed()) {
                    Log::error('Gagal mengambil daftar employee dari Accurate', ['response' => $response->json()]);

                    return collect([]);
                }

                $employees = collect($response->json()['d'] ?? []);

                $employeesWithDetail = $employees
                    ->map(function ($employee) {
                        $employeeData = is_array($employee) ? $employee : (array) $employee;
                        $employeeId = (int) ($employeeData['id'] ?? 0);

                        if ($employeeId <= 0) {
                            return $employeeData;
                        }

                        $employeeDetail = $this->getDetailEmployee($employeeId);

                        return is_array($employeeDetail) ? $employeeDetail : $employeeData;
                    })
                    ->values();

                $allEmployees = $allEmployees->merge($employeesWithDetail);
                $page++;
            } while ($employees->count() >= $perPage);

            return $allEmployees;
        } catch (\Throwable $e) {
            Log::error('Exception saat mengambil daftar employee', ['message' => $e->getMessage()]);

            return collect([]);
        }
    }

    public function saveEmployee(array $data)
    {
        return $this->saveData('employee', $data, 'save');
    }

    public function getDetailEmployee(int $id): ?array
    {
        return $this->getDetail('employee', $id);
    }

    public function deleteEmployee(int $id)
    {
        return $this->deleteData('employee', $id);
    }
}
