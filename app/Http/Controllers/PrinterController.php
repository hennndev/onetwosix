<?php

namespace App\Http\Controllers;

use App\Http\Requests\PrinterRequest;
use App\Models\Area;
use App\Models\Order;
use App\Models\Printer;
use App\Services\PrinterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PrinterController extends Controller
{
    public function __construct(
        protected PrinterService $printerService
    ) {}

    /**
     * Display list of printers.
     */
    public function index(): View
    {
        $printers = Printer::orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        $user = auth()->user();
        $areas = $user ? $user->getAccessibleAreas() : Area::where('is_active', true)->orderBy('sort_order')->get();
        $printerLocations = $this->getPrinterLocations();

        return view('printers.index', compact('printers', 'printerLocations', 'areas'));
    }

    /**
     * Get valid printer service locations.
     */
    protected function getPrinterLocations(): array
    {
        return [
            'kitchen' => 'Kitchen',
            'bar' => 'Bar',
            'cashier' => 'Cashier',
            'checker' => 'Checker',
        ];
    }

    /**
     * Store a new printer.
     */
    public function store(PrinterRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        // If setting as default, remove default from others
        if (! empty($data['is_default'])) {
            Printer::query()->update(['is_default' => false]);
        }

        Printer::create($data);

        return back()->with('success', 'Printer created successfully.');
    }

    /**
     * Update a printer.
     */
    public function update(PrinterRequest $request, Printer $printer): RedirectResponse
    {
        $data = $request->validated();

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($printer->logo_path) {
                Storage::disk('public')->delete($printer->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        // If setting as default, remove default from others
        if (! empty($data['is_default'])) {
            Printer::where('id', '!=', $printer->id)->update(['is_default' => false]);
        }

        $printer->update($data);

        return back()->with('success', 'Printer updated successfully.');
    }

    /**
     * Delete a printer.
     */
    public function destroy(Printer $printer): RedirectResponse
    {
        // Delete logo if exists
        if ($printer->logo_path) {
            Storage::disk('public')->delete($printer->logo_path);
        }

        $printer->delete();

        return back()->with('success', 'Printer deleted successfully.');
    }

    /**
     * Set printer as default.
     */
    public function setDefault(Printer $printer): JsonResponse
    {
        Printer::query()->update(['is_default' => false]);
        $printer->update(['is_default' => true, 'is_active' => true]);

        return response()->json([
            'success' => true,
            'message' => "{$printer->name} set as default printer.",
        ]);
    }

    /**
     * Ping / connectivity check for a network printer (no actual print).
     */
    public function ping(Printer $printer): JsonResponse
    {
        if ($printer->connection_type !== 'network') {
            return response()->json([
                'success' => true,
                'message' => "Printer '{$printer->name}' tipe {$printer->connection_type} — tidak perlu ping jaringan.",
            ]);
        }

        $socket = @fsockopen($printer->ip, (int) $printer->port, $errno, $errstr, 3);

        if ($socket === false) {
            return response()->json([
                'success' => false,
                'message' => "Printer '{$printer->name}' ({$printer->ip}:{$printer->port}) tidak dapat dijangkau. Pastikan printer menyala dan terhubung ke jaringan. (Error: {$errstr})",
            ], 200);
        }

        fclose($socket);

        return response()->json([
            'success' => true,
            'message' => "Printer '{$printer->name}' ({$printer->ip}:{$printer->port}) online dan siap digunakan.",
        ]);
    }

    /**
     * Test print for specific printer.
     */
    public function testPrint(Printer $printer): JsonResponse
    {
        try {
            $this->printerService->testPrint($printer);

            $modeMessage = $printer->connection_type === 'log'
                ? 'Mode LOG (simulasi), jadi tidak mencetak kertas.'
                : 'Perintah test print sudah dikirim ke printer fisik.';

            return response()->json([
                'success' => true,
                'message' => "Test print berhasil ke {$printer->name} ({$printer->printer_type}/{$printer->location}). {$modeMessage}",
            ]);
        } catch (\Exception $e) {
            $context = $printer->connection_type === 'network'
                ? " (IP: {$printer->ip}, Port: {$printer->port})"
                : '';

            return response()->json([
                'success' => false,
                'message' => 'Test print gagal'.$context.': '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Print a receipt for the given order.
     */
    public function printReceipt(Order $order, ?Printer $printer = null): JsonResponse
    {
        try {
            $order->load(['items', 'tableSession.table']);

            $printer = $printer ?? Printer::getDefault(
                $order->tableSession?->table?->area_id ?? $order->area_id
            );

            if (! $printer) {
                return response()->json([
                    'success' => false,
                    'message' => 'No default printer configured.',
                ], 400);
            }

            $this->printerService->printReceipt($order, $printer);

            return response()->json([
                'success' => true,
                'message' => "Receipt for order {$order->order_number} printed successfully.",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to print receipt: '.$e->getMessage(),
            ], 500);
        }
    }
}
