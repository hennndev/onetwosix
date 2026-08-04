<?php

namespace App\Jobs;

use App\Models\BarOrder;
use App\Models\KitchenOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Printer;
use App\Services\PrinterService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DispatchPrintTicketJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @param array<int, int> $itemIds */
    public function __construct(
        public string $ticketType,
        public int $orderId,
        public int $printerId,
        public string $sourceType,
        public ?int $sourceId,
        public array $itemIds,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(PrinterService $printerService): void
    {
        try {
            $printer = Printer::query()->findOrFail($this->printerId);
            $preparationOrder = $this->resolvePreparationOrder();

            $printed = match ($this->ticketType) {
                'bar' => $printerService->printBarTicket($preparationOrder, $printer),
                'checker' => $printerService->printCheckerTicket($preparationOrder, $printer),
                'cashier' => $printerService->printCashierTicket($preparationOrder, $printer),
                default => $printerService->printKitchenTicket($preparationOrder, $printer),
            };

            if (! $printed) {
                throw new \RuntimeException("Printer {$printer->name} menolak tiket {$this->ticketType}.");
            }
        } catch (\Throwable $exception) {
            if (config('queue.default') !== 'sync') {
                throw $exception;
            }

            Log::warning('Checkout print failed on sync queue', [
                'order_id' => $this->orderId,
                'printer_id' => $this->printerId,
                'ticket_type' => $this->ticketType,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function resolvePreparationOrder(): KitchenOrder|BarOrder
    {
        if ($this->sourceType === 'bar') {
            $order = BarOrder::query()->with(['order.tableSession.waiter.profile', 'table'])->findOrFail($this->sourceId);
            $items = OrderItem::query()->with('inventoryItem')->whereIn('id', $this->itemIds)->get();
            $order->setRelation('items', $items);

            return $order;
        }

        if ($this->sourceType === 'kitchen') {
            $order = KitchenOrder::query()->with(['order.tableSession.waiter.profile', 'table'])->findOrFail($this->sourceId);
            $items = OrderItem::query()->with('inventoryItem')->whereIn('id', $this->itemIds)->get();
            $order->setRelation('items', $items);

            return $order;
        }

        $parentOrder = Order::query()->with(['tableSession.table', 'tableSession.waiter.profile'])->findOrFail($this->orderId);
        $items = OrderItem::query()->with('inventoryItem')->whereIn('id', $this->itemIds)->get();
        $order = new KitchenOrder([
            'order_id' => $parentOrder->id,
            'order_number' => $parentOrder->order_number,
            'table_id' => $parentOrder->tableSession?->table_id,
            'total_amount' => $items->sum('subtotal'),
            'status' => 'baru',
            'progress' => 0,
        ]);
        $order->setRelation('order', $parentOrder);
        $order->setRelation('table', $parentOrder->tableSession?->table);
        $order->setRelation('items', $items);

        return $order;
    }
}
