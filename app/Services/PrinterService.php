<?php

namespace App\Services;

use App\Models\BarOrder;
use App\Models\Billing;
use App\Models\GeneralSetting;
use App\Models\KitchenOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Printer;
use App\Models\TableSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer as EscposPrinter;

class PrinterService
{
    /**
     * Print closed billing receipt in booking template style.
     */
    public function printClosedBillingReceipt(Billing $billing, TableSession $session, Printer $printer): bool
    {
        $session->loadMissing(['table', 'customer', 'reservation', 'orders.items']);

        $payload = $this->buildClosedBillingPayload($billing, $session);

        return $this->printBillingTemplatePayload($payload, $printer, 'CLOSED BILLING RECEIPT', 'CLOSED BILLING RECEIPT PREVIEW');
    }

    /**
     * Print walk-in receipt in booking template style.
     */
    public function printWalkInBillingReceipt(Order $order, Billing $billing, Printer $printer): bool
    {
        $order->loadMissing(['items', 'customer.user', 'customer.profile', 'createdBy']);

        $payload = $this->buildWalkInBillingPayload($order, $billing);

        return $this->printBillingTemplatePayload($payload, $printer, 'WALK-IN RECEIPT', 'WALK-IN RECEIPT PREVIEW');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function printWalkInDraftReceipt(array $payload, Printer $printer): bool
    {
        return $this->printBillingTemplatePayload($payload, $printer, 'WALK-IN DRAFT RECEIPT', 'WALK-IN DRAFT RECEIPT PREVIEW');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function printBillingTemplatePayload(array $payload, Printer $printer, string $logTitle, string $previewTitle): bool
    {
        $width = max((int) ($printer->width ?: 42), 42);

        if ($printer->connection_type === 'log') {
            $lines = $this->buildClosedBillingSimulationLines($payload, $width, $printer);
            $lines[] = 'Status : SUCCESS (LOG MODE)';
            $this->logPrint($logTitle, $lines);

            return true;
        }

        $connector = $this->createConnector($printer);
        $escpos = new EscposPrinter($connector);

        try {
            $separator = str_repeat('-', $width);

            $escpos->setJustification(EscposPrinter::JUSTIFY_CENTER);
            $escpos->setEmphasis(true);
            $escpos->setTextSize(2, 2);
            $escpos->text("126\n");
            $escpos->setTextSize(1, 1);
            if ($this->shouldUseGraphicsBranding($printer)) {
                $this->printVenusRingBrandText($escpos, 'One·two·six');
            } else {
                $escpos->text("One·two·six\n");
            }
            $escpos->text("Ruko The Boulevard, Blok VD05.\n");
            $escpos->text("126, Jl.Ecopolis Citra Raya No.126, Mekar Bakti\n");
            $escpos->text("Kec. Cikupa, Kabupaten Tangerang, Banten.\n");
            $escpos->text("0811-839-126\n");
            $escpos->setEmphasis(false);
            $escpos->setJustification(EscposPrinter::JUSTIFY_LEFT);

            $escpos->text($separator."\n");
            $escpos->text($this->formatClosedBillingPair('No. Transaksi', $payload['transaction_code'], $width)."\n");
            $escpos->text($this->formatClosedBillingPair('Tanggal', $payload['date'], $width)."\n");
            $escpos->text($this->formatClosedBillingPair('Kasir', $payload['cashier'], $width)."\n");
            $escpos->text($separator."\n");

            $escpos->text($this->formatClosedBillingPair('Pelanggan', $payload['customer_name'], $width)."\n");
            $escpos->text($this->formatClosedBillingPair('Tipe', $payload['type'], $width)."\n");
            $escpos->text($this->formatClosedBillingPair('Meja', $payload['table'], $width)."\n");
            $escpos->text($separator."\n");

            foreach ($payload['items'] as $item) {
                $escpos->setEmphasis(true);
                $escpos->text("{$item['name']} {$item['qty']}x\n");
                $escpos->setEmphasis(false);
                $escpos->text($this->formatClosedBillingPair('Harga: Rp '.number_format((float) $item['price'], 0, ',', '.'), 'Total: Rp '.number_format((float) $item['subtotal'], 0, ',', '.'), $width)."\n");
                $escpos->text(str_repeat('-', $width)."\n");
            }

            $downPaymentAmount = (float) ($payload['down_payment_amount'] ?? 0);
            $totalBill = (float) ($payload['subtotal'] ?? 0);
            $discountAmount = (float) ($payload['discount_amount'] ?? 0);
            $tax = (float) ($payload['tax'] ?? 0);
            $serviceCharge = (float) ($payload['service_charge'] ?? 0);
            $subTotal = $totalBill + $tax + $serviceCharge;

            $escpos->text($this->formatClosedBillingPair('Total Bill', 'Rp '.number_format($totalBill, 0, ',', '.'), $width)."\n");

            if ($tax > 0) {
                $escpos->text($this->formatClosedBillingPair('PB1 ('.(int) $payload['tax_percentage'].'%)', 'Rp '.number_format($tax, 0, ',', '.'), $width)."\n");
            }

            if ($serviceCharge > 0) {
                $escpos->text($this->formatClosedBillingPair('Service Charge ('.(int) $payload['service_charge_percentage'].'%)', 'Rp '.number_format($serviceCharge, 0, ',', '.'), $width)."\n");
            }

            $escpos->text($this->formatClosedBillingPair('Sub Total', 'Rp '.number_format($subTotal, 0, ',', '.'), $width)."\n");

            if ($discountAmount > 0) {
                $escpos->text($this->formatClosedBillingPair('Diskon', '- Rp '.number_format($discountAmount, 0, ',', '.'), $width)."\n");
            }

            if ($downPaymentAmount > 0) {
                $escpos->text($this->formatClosedBillingPair('DP', 'Rp '.number_format($downPaymentAmount, 0, ',', '.'), $width)."\n");
            }
            $escpos->setEmphasis(true);
            $escpos->text($this->formatClosedBillingPair('Sisa Bayar', 'Rp '.number_format((float) $payload['grand_total'], 0, ',', '.'), $width)."\n");
            $escpos->setEmphasis(false);

            $escpos->text($separator."\n");
            $escpos->text($this->formatClosedBillingPair('Metode Pembayaran', $payload['payment_method'], $width)."\n");

            if ($payload['payment_mode'] !== 'split' && filled($payload['payment_reference_number'])) {
                $escpos->text($this->formatClosedBillingPair('No. Referensi', (string) $payload['payment_reference_number'], $width)."\n");
            }

            if ($payload['payment_mode'] === 'split') {
                $escpos->text($this->formatClosedBillingPair('Mode Pembayaran', 'SPLIT BILL', $width)."\n");
                if ((float) $payload['split_cash_amount'] > 0) {
                    $escpos->text($this->formatClosedBillingPair('Cash', 'Rp '.number_format((float) $payload['split_cash_amount'], 0, ',', '.'), $width)."\n");
                }

                if ((float) $payload['split_non_cash_amount'] > 0) {
                    $escpos->text($this->formatClosedBillingPair((string) $payload['split_non_cash_method'], 'Rp '.number_format((float) $payload['split_non_cash_amount'], 0, ',', '.'), $width)."\n");

                    if (filled($payload['split_non_cash_reference_number'])) {
                        $escpos->text($this->formatClosedBillingPair('Ref 1', (string) $payload['split_non_cash_reference_number'], $width)."\n");
                    }
                }

                if ((float) ($payload['split_second_non_cash_amount'] ?? 0) > 0) {
                    $escpos->text($this->formatClosedBillingPair((string) ($payload['split_second_non_cash_method'] ?? 'NON-CASH 2'), 'Rp '.number_format((float) ($payload['split_second_non_cash_amount'] ?? 0), 0, ',', '.'), $width)."\n");

                    if (filled($payload['split_second_non_cash_reference_number'] ?? null)) {
                        $escpos->text($this->formatClosedBillingPair('Ref 2', (string) ($payload['split_second_non_cash_reference_number'] ?? ''), $width)."\n");
                    }
                }
            }

            $escpos->text($separator."\n");

            $escpos->setJustification(EscposPrinter::JUSTIFY_CENTER);
            $escpos->setEmphasis(true);
            $escpos->text("Terima Kasih Atas Kunjungan\nAnda!\n");
            $escpos->setEmphasis(false);
            $escpos->text("Barang yang sudah dibeli tidak dapat\n");
            $escpos->text("ditukar/dikembalikan\n");
            $escpos->text("Simpan struk ini sebagai bukti\n");
            $escpos->text("pembayaran yang sah\n\n");
            $escpos->setEmphasis(true);
            $escpos->text("FOLLOW US\n");
            $escpos->setEmphasis(false);
            $escpos->text("ig & tiktok : onetwosix.official\n");
            // $escpos->text("Powered by 126 Club POS System\n");

            $escpos->feed(3);
            $escpos->cut();

            $previewLines = $this->buildClosedBillingSimulationLines($payload, $width, $printer);
            $previewLines[] = 'Status : SUCCESS (SENT TO PRINTER)';
            $this->logPrint($previewTitle, $previewLines);

            return true;
        } finally {
            $escpos->close();
        }
    }

    protected function shouldUseGraphicsBranding(Printer $printer): bool
    {
        return $printer->connection_type !== 'network';
    }

    protected function printVenusRingBrandText(EscposPrinter $escpos, string $text): void
    {
        $fontPath = public_path('fonts/Venus Rising Rg.otf');

        if (! is_file($fontPath) || ! function_exists('imagettfbbox') || ! function_exists('imagettftext')) {
            $escpos->text($text."\n");

            return;
        }

        $fontSize = 20;
        $paddingX = 14;
        $paddingY = 10;
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);

        if ($bbox === false) {
            $escpos->text($text."\n");

            return;
        }

        $textWidth = (int) abs($bbox[2] - $bbox[0]);
        $textHeight = (int) abs($bbox[7] - $bbox[1]);
        $imageWidth = max($textWidth + ($paddingX * 2), 1);
        $imageHeight = max($textHeight + ($paddingY * 2), 1);

        $image = imagecreatetruecolor($imageWidth, $imageHeight);

        if ($image === false) {
            $escpos->text($text."\n");

            return;
        }

        $tmpDir = storage_path('app/tmp');
        $tmpPath = $tmpDir.'/venus-ring-'.uniqid('', true).'.png';

        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        try {
            $white = imagecolorallocate($image, 255, 255, 255);
            $black = imagecolorallocate($image, 0, 0, 0);

            imagefilledrectangle($image, 0, 0, $imageWidth, $imageHeight, $white);
            imagettftext(
                $image,
                $fontSize,
                0,
                $paddingX,
                $paddingY + $textHeight,
                $black,
                $fontPath,
                $text
            );

            imagepng($image, $tmpPath);

            $imageForPrinter = EscposImage::load($tmpPath);
            $escpos->graphics($imageForPrinter);
            $escpos->feed(1);
        } catch (\Throwable $e) {
            $escpos->text($text."\n");
        } finally {
            imagedestroy($image);

            if (is_file($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }

    /**
     * Check if a network printer is reachable before attempting to connect.
     *
     * @throws \RuntimeException
     */
    protected function checkNetworkReachable(string $ip, int $port, int $timeoutSeconds = 3): void
    {
        Log::info('Checking network printer reachability', [
            'ip' => $ip,
            'port' => $port,
            'timeout_seconds' => $timeoutSeconds,
        ]);

        $socket = @fsockopen($ip, $port, $errno, $errstr, $timeoutSeconds);

        if ($socket === false) {
            Log::warning('Network printer is not reachable', [
                'ip' => $ip,
                'port' => $port,
                'error' => $errstr,
                'code' => $errno,
            ]);

            throw new \RuntimeException(
                "Printer {$ip}:{$port} tidak dapat dijangkau. ".
                "Pastikan printer menyala, terhubung ke jaringan, dan port {$port} terbuka. ".
                "(Error: {$errstr})"
            );
        }

        fclose($socket);

        Log::info('Network printer is reachable and listening', [
            'ip' => $ip,
            'port' => $port,
        ]);
    }

    /**
     * Create the appropriate print connector based on printer model.
     */
    protected function createConnector(Printer $printer): NetworkPrintConnector|FilePrintConnector|WindowsPrintConnector
    {
        if ($printer->connection_type === 'network') {
            $this->checkNetworkReachable(
                $printer->ip,
                (int) $printer->port,
                min((int) $printer->timeout, 5)
            );
        }

        return match ($printer->connection_type) {
            'network' => new NetworkPrintConnector(
                $printer->ip,
                (int) $printer->port,
                (int) $printer->timeout
            ),
            'file' => new FilePrintConnector($printer->path),
            'windows' => new WindowsPrintConnector($printer->path),
            default => throw new \InvalidArgumentException("Unknown printer connection type: {$printer->connection_type}"),
        };
    }

    /**
     * Write a human-readable print simulation to storage/logs/printer.log.
     */
    protected function logPrint(string $title, array $lines): void
    {
        $separator = str_repeat('-', 42);
        $content = implode("\n", [
            '',
            $separator,
            '  [PRINT SIMULATION] '.$title,
            '  '.now()->format('d/m/Y H:i:s'),
            $separator,
            ...array_map(fn ($l) => '  '.$l, $lines),
            $separator,
        ]);

        file_put_contents(
            storage_path('logs/printer.log'),
            $content."\n",
            FILE_APPEND
        );

        Log::info('Printer simulation log', [
            'title' => $title,
            'lines' => $lines,
        ]);
    }

    /**
     * Print a receipt for the given order.
     */
    public function printReceipt(Order $order, Printer $printer): bool
    {
        $receiptTotals = $this->calculateReceiptTotals($order);
        Log::info('data', ['data' => $receiptTotals]);

        if ($printer->connection_type === 'log') {
            $lines = $this->buildReceiptSimulationLines($order, $printer, $receiptTotals);
            $lines[] = 'Status : SUCCESS (LOG MODE)';
            $this->logPrint('RECEIPT', $lines);

            return true;
        }

        $connector = $this->createConnector($printer);
        $escpos = new EscposPrinter($connector);

        Log::info('connector', ['connector' => $connector]);

        try {
            // Logo (if configured)
            $this->printLogo($escpos, $printer);

            // Header
            $this->printHeader($escpos, $printer);

            // Order info
            $escpos->setEmphasis(true);
            $escpos->text("Order: {$order->order_number}\n");
            $escpos->setEmphasis(false);
            $escpos->text("Date: {$order->ordered_at->format('d/m/Y H:i')}\n");
            $tableName = $order->tableSession?->table?->table_number ?? 'N/A';
            $escpos->text("Table: {$tableName}\n");
            $escpos->text(str_repeat('-', $printer->width)."\n");

            // Items
            $escpos->setEmphasis(true);
            $escpos->text($this->padLine('Item', 'Qty', 'Price', 'Subtotal', $printer->width));
            $escpos->setEmphasis(false);
            $escpos->text(str_repeat('-', $printer->width)."\n");

            foreach ($order->items as $item) {
                $escpos->text($this->formatItemLine($item, $printer->width));
            }

            $escpos->text(str_repeat('-', $printer->width)."\n");

            // Totals
            if ($receiptTotals !== null) {
                $escpos->text($this->padLine('Subtotal', '', '', 'Rp '.number_format($receiptTotals['items_total'], 0, ',', '.'), $printer->width));

                if ($receiptTotals['discount_amount'] > 0) {
                    $escpos->text($this->padLine('Diskon', '', '', 'Rp '.number_format($receiptTotals['discount_amount'], 0, ',', '.'), $printer->width));
                }

                if ($receiptTotals['service_charge'] > 0) {
                    $escpos->text($this->padLine('Service', '', '', 'Rp '.number_format($receiptTotals['service_charge'], 0, ',', '.'), $printer->width));
                }

                if ($receiptTotals['tax'] > 0) {
                    $escpos->text($this->padLine('PB1', '', '', 'Rp '.number_format($receiptTotals['tax'], 0, ',', '.'), $printer->width));
                }

                $escpos->text(str_repeat('-', $printer->width)."\n");
            }

            $escpos->setEmphasis(true);
            $escpos->text($this->padLine('TOTAL', '', '', 'Rp '.number_format($order->total, 0, ',', '.'), $printer->width));
            $escpos->setEmphasis(false);

            // QR Code (optional)
            if ($printer->show_qr_code) {
                $escpos->feed(2);
                $escpos->qrCode($order->order_number, EscposPrinter::QR_ECLEVEL_M, 4);
            }

            // Footer
            $this->printFooter($escpos, $printer);

            // Cut paper
            $escpos->feed(3);
            $escpos->cut();

            $previewLines = $this->buildReceiptSimulationLines($order, $printer, $receiptTotals);
            $previewLines[] = 'Status : SUCCESS (SENT TO PRINTER)';
            $this->logPrint('RECEIPT PREVIEW', $previewLines);

            return true;
        } finally {
            $escpos->close();
        }
    }

    /**
     * @param  array{items_total: float, discount_amount: float, service_charge: float, tax: float}|null  $receiptTotals
     * @return array<int, string>
     */
    protected function buildReceiptSimulationLines(Order $order, Printer $printer, ?array $receiptTotals): array
    {
        $lines = [
            "Order  : {$order->order_number}",
            'Date   : '.$order->ordered_at->format('d/m/Y H:i'),
            'Table  : '.($order->tableSession?->table?->table_number ?? 'N/A'),
            "Printer: {$printer->name} ({$printer->location}) #{$printer->id}",
            '',
        ];

        foreach ($order->items as $item) {
            $lines[] = "  {$item->quantity}x {$item->item_name}  Rp ".number_format($this->resolvePrintableItemSubtotal($item), 0, ',', '.');
        }

        $lines[] = '';

        if ($receiptTotals !== null) {
            $lines[] = 'Subtotal: Rp '.number_format($receiptTotals['items_total'], 0, ',', '.');

            if ($receiptTotals['discount_amount'] > 0) {
                $lines[] = 'Diskon  : Rp '.number_format($receiptTotals['discount_amount'], 0, ',', '.');
            }

            if ($receiptTotals['service_charge'] > 0) {
                $lines[] = 'Service : Rp '.number_format($receiptTotals['service_charge'], 0, ',', '.');
            }

            if ($receiptTotals['tax'] > 0) {
                $lines[] = 'PB1     : Rp '.number_format($receiptTotals['tax'], 0, ',', '.');
            }
        }

        $lines[] = 'TOTAL  : Rp '.number_format($order->total, 0, ',', '.');

        return $lines;
    }

    /**
     * Print a test receipt to verify printer connection.
     */
    public function testPrint(Printer $printer): bool
    {
        Log::info('Starting printer test print', [
            'printer_id' => $printer->id,
            'name' => $printer->name,
            'printer_type' => $printer->printer_type,
            'location' => $printer->location,
            'connection_type' => $printer->connection_type,
            'ip' => $printer->ip,
            'port' => $printer->port,
        ]);

        if ($printer->connection_type === 'log') {
            $this->logPrint('TEST PRINT', [
                "Printer    : {$printer->name}",
                'Type       : '.($printer->printer_type ?: '-'),
                "Location   : {$printer->location}",
                'Connection : log (simulation)',
                'Status     : OK — printer simulation working!',
            ]);

            return true;
        }

        $connector = $this->createConnector($printer);
        $escpos = new EscposPrinter($connector);

        try {
            // Logo (if configured)
            $this->printLogo($escpos, $printer);

            $escpos->setJustification(EscposPrinter::JUSTIFY_CENTER);
            $escpos->text("=== PRINTER TEST ===\n");
            $escpos->text("Printer: {$printer->name}\n");
            $escpos->text("Connection: {$printer->connection_type}\n");
            $escpos->text('Time: '.now()->format('d/m/Y H:i:s')."\n");
            $escpos->text(str_repeat('-', $printer->width)."\n");
            $escpos->text("Printer is working correctly!\n");
            $escpos->setJustification(EscposPrinter::JUSTIFY_LEFT);
            $escpos->feed(3);
            $escpos->cut();

            return true;
        } finally {
            $escpos->close();
        }
    }

    /**
     * Print logo if configured.
     */
    protected function printLogo(EscposPrinter $escpos, Printer $printer): void
    {
        if (! $printer->logo_path) {
            return;
        }

        $logoPath = storage_path('app/public/'.$printer->logo_path);

        if (! file_exists($logoPath)) {
            return;
        }

        try {
            $img = EscposImage::load($logoPath);
            $escpos->setJustification(EscposPrinter::JUSTIFY_CENTER);
            $escpos->graphics($img);
            $escpos->feed(1);
        } catch (\Exception $e) {
            // Silently fail if image cannot be loaded
        }
    }

    /**
     * Print the receipt header.
     */
    protected function printHeader(EscposPrinter $escpos, Printer $printer): void
    {
        $escpos->setJustification(EscposPrinter::JUSTIFY_CENTER);
        $escpos->setEmphasis(true);
        $escpos->setTextSize(2, 2);
        $escpos->text($printer->header."\n");
        $escpos->setTextSize(1, 1);
        $escpos->setEmphasis(false);
        $escpos->setJustification(EscposPrinter::JUSTIFY_LEFT);
        $escpos->feed(1);
    }

    /**
     * Print the receipt footer.
     */
    protected function printFooter(EscposPrinter $escpos, Printer $printer): void
    {
        $escpos->feed(2);
        $escpos->setJustification(EscposPrinter::JUSTIFY_CENTER);
        $escpos->text($printer->footer."\n");
        $escpos->setJustification(EscposPrinter::JUSTIFY_LEFT);
    }

    /**
     * Format an order item line for printing.
     */
    protected function formatItemLine($item, int $width): string
    {
        $name = $this->truncate($item->item_name, 12);
        $qty = (string) $item->quantity;
        $price = number_format($this->resolvePrintableItemPrice($item), 0, ',', '.');
        $subtotal = number_format($this->resolvePrintableItemSubtotal($item), 0, ',', '.');

        return $this->padLine($name, $qty, $price, $subtotal, $width)."\n";
    }

    /**
     * Pad a line with columns for receipt printing.
     */
    protected function padLine(string $col1, string $col2, string $col3, string $col4, int $width): string
    {
        $col1Width = 14;
        $col2Width = 4;
        $col3Width = 10;
        $col4Width = $width - $col1Width - $col2Width - $col3Width;

        return str_pad($this->truncate($col1, $col1Width), $col1Width)
            .str_pad($col2, $col2Width, ' ', STR_PAD_LEFT)
            .str_pad($col3, $col3Width, ' ', STR_PAD_LEFT)
            .str_pad($col4, $col4Width, ' ', STR_PAD_LEFT);
    }

    /**
     * Truncate a string to the specified length.
     */
    protected function truncate(string $str, int $length): string
    {
        if (strlen($str) <= $length) {
            return $str;
        }

        return substr($str, 0, $length - 1).'.';
    }

    /**
     * @return array<string, float>|null
     */
    protected function calculateReceiptTotals(Order $order): ?array
    {
        $generalSettings = GeneralSetting::instance();
        $itemsTotal = (float) $order->items_total;
        $discountAmount = (float) $order->discount_amount;
        $subtotalAfterDiscount = max($itemsTotal - $discountAmount, 0);
        $discountRatio = $itemsTotal > 0 ? min(max($discountAmount / $itemsTotal, 0), 1) : 0;

        $serviceChargeBase = 0;
        $taxBase = 0;
        $taxAndServiceBase = 0;

        foreach ($order->items as $item) {
            $subtotal = (float) ($item->subtotal ?? ((float) $item->price * (int) $item->quantity));
            $includeTax = (bool) ($item->inventoryItem?->include_tax ?? true);
            $includeServiceCharge = (bool) ($item->inventoryItem?->include_service_charge ?? true);

            if ($includeServiceCharge) {
                $serviceChargeBase += $subtotal;
            }

            if ($includeTax) {
                $taxBase += $subtotal;
            }

            if ($includeTax && $includeServiceCharge) {
                $taxAndServiceBase += $subtotal;
            }
        }

        $serviceChargeBaseAfterDiscount = max($serviceChargeBase * (1 - $discountRatio), 0);
        $taxBaseAfterDiscount = max($taxBase * (1 - $discountRatio), 0);
        $taxAndServiceBaseAfterDiscount = max($taxAndServiceBase * (1 - $discountRatio), 0);

        $serviceCharge = round($serviceChargeBaseAfterDiscount * (((float) $generalSettings->service_charge_percentage) / 100));
        $serviceChargeTaxableAmount = round($taxAndServiceBaseAfterDiscount * (((float) $generalSettings->service_charge_percentage) / 100));
        $tax = round(($taxBaseAfterDiscount + $serviceChargeTaxableAmount) * (((float) $generalSettings->tax_percentage) / 100));

        return [
            'items_total' => $itemsTotal,
            'discount_amount' => $discountAmount,
            'service_charge' => $serviceCharge,
            'tax' => $tax,
        ];
    }

    protected function resolvePrintableItemPrice(OrderItem $item): float
    {
        return (float) ($item->price ?? 0);
    }

    protected function resolvePrintableItemSubtotal(OrderItem $item): float
    {
        return (float) ($item->subtotal ?? ((float) $item->price * (int) $item->quantity));
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildClosedBillingPayload(Billing $billing, TableSession $session): array
    {
        $items = $session->orders
            ->flatMap(fn (Order $order) => $order->items)
            ->groupBy('item_name')
            ->map(function ($group): array {
                $first = $group->first();

                return [
                    'name' => (string) $first->item_name,
                    'qty' => (int) $group->sum('quantity'),
                    'price' => $this->resolvePrintableItemPrice($first),
                    'subtotal' => (float) $group->sum(fn ($item): float => $this->resolvePrintableItemSubtotal($item)),
                ];
            })
            ->values()
            ->all();

        $customerName = $session->customer?->name
            ?? $session->reservation?->customer?->name
            ?? '-';

        $paymentMethod = strtoupper((string) ($billing->payment_method ?: (($billing->payment_mode ?? 'normal') === 'split' ? 'split' : '-')));
        $paymentMode = (string) ($billing->payment_mode ?? 'normal');
        $splitNonCashMethod = strtoupper((string) ($billing->split_non_cash_method ?? 'NON-CASH'));
        $splitSecondNonCashMethod = strtoupper((string) ($billing->split_second_non_cash_method ?? 'NON-CASH 2'));

        return [
            'transaction_code' => (string) ($billing->order?->order_number ?? $billing->transaction_code ?? '-'),
            'date' => ($billing->updated_at ?? now())->format('d M Y H:i'),
            'cashier' => Auth::user()?->name ?? 'System Administrator',
            'customer_name' => $customerName,
            'type' => 'BOOKING',
            'table' => $session->table?->table_number ?? '-',
            'items' => $items,
            'minimum_charge' => (float) ($billing->minimum_charge ?? 0),
            'subtotal' => (float) ($billing->subtotal ?? 0),
            'discount_amount' => (float) ($billing->discount_amount ?? 0),
            'service_charge' => (float) ($billing->service_charge ?? 0),
            'service_charge_percentage' => (float) ($billing->service_charge_percentage ?? 0),
            'tax' => (float) ($billing->tax ?? 0),
            'tax_percentage' => (float) ($billing->tax_percentage ?? 0),
            'down_payment_amount' => (float) ($session->reservation?->down_payment_amount ?? 0),
            'grand_total' => (float) ($billing->grand_total ?? 0),
            'payment_mode' => $paymentMode,
            'payment_method' => $paymentMethod,
            'payment_reference_number' => $billing->payment_reference_number,
            'split_cash_amount' => (float) ($billing->split_cash_amount ?? 0),
            'split_non_cash_amount' => (float) ($billing->split_debit_amount ?? 0),
            'split_non_cash_method' => $splitNonCashMethod,
            'split_non_cash_reference_number' => $billing->split_non_cash_reference_number,
            'split_second_non_cash_amount' => (float) ($billing->split_second_non_cash_amount ?? 0),
            'split_second_non_cash_method' => $splitSecondNonCashMethod,
            'split_second_non_cash_reference_number' => $billing->split_second_non_cash_reference_number,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildWalkInBillingPayload(Order $order, Billing $billing): array
    {
        $items = $order->items
            ->groupBy('item_name')
            ->map(function ($group): array {
                $first = $group->first();

                return [
                    'name' => (string) $first->item_name,
                    'qty' => (int) $group->sum('quantity'),
                    'price' => $this->resolvePrintableItemPrice($first),
                    'subtotal' => (float) $group->sum(fn ($item): float => $this->resolvePrintableItemSubtotal($item)),
                ];
            })
            ->values()
            ->all();

        $customerName = $order->customer?->user?->name
            ?? $order->customer?->profile?->name
            ?? 'Walk-in';

        $paymentMethod = strtoupper((string) ($billing->payment_method ?: (($billing->payment_mode ?? 'normal') === 'split' ? 'split' : '-')));
        $paymentMode = (string) ($billing->payment_mode ?? 'normal');
        $splitNonCashMethod = strtoupper((string) ($billing->split_non_cash_method ?? 'NON-CASH'));
        $splitSecondNonCashMethod = strtoupper((string) ($billing->split_second_non_cash_method ?? 'NON-CASH 2'));

        return [
            'transaction_code' => (string) ($order->order_number ?? $billing->transaction_code ?? '-'),
            'date' => ($billing->updated_at ?? $order->ordered_at ?? now())->format('d M Y H:i'),
            'cashier' => $order->createdBy?->name ?? Auth::user()?->name ?? 'System Administrator',
            'customer_name' => $customerName,
            'type' => 'WALK-IN',
            'table' => '-',
            'items' => $items,
            'minimum_charge' => (float) ($billing->minimum_charge ?? 0),
            'subtotal' => (float) ($billing->subtotal ?? 0),
            'discount_amount' => (float) ($billing->discount_amount ?? 0),
            'service_charge' => (float) ($billing->service_charge ?? 0),
            'service_charge_percentage' => (float) ($billing->service_charge_percentage ?? 0),
            'tax' => (float) ($billing->tax ?? 0),
            'tax_percentage' => (float) ($billing->tax_percentage ?? 0),
            'down_payment_amount' => 0,
            'grand_total' => (float) ($billing->grand_total ?? 0),
            'payment_mode' => $paymentMode,
            'payment_method' => $paymentMethod,
            'payment_reference_number' => $billing->payment_reference_number,
            'split_cash_amount' => (float) ($billing->split_cash_amount ?? 0),
            'split_non_cash_amount' => (float) ($billing->split_debit_amount ?? 0),
            'split_non_cash_method' => $splitNonCashMethod,
            'split_non_cash_reference_number' => $billing->split_non_cash_reference_number,
            'split_second_non_cash_amount' => (float) ($billing->split_second_non_cash_amount ?? 0),
            'split_second_non_cash_method' => $splitSecondNonCashMethod,
            'split_second_non_cash_reference_number' => $billing->split_second_non_cash_reference_number,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    protected function buildClosedBillingSimulationLines(array $payload, int $width, Printer $printer): array
    {
        $separator = str_repeat('-', $width);
        $lines = [
            '126',
            'One·two·six',
            'Ruko The Boulevard, Blok VD05.',
            '126, Jl.Ecopolis Citra Raya No.126, Mekar Bakti',
            'Kec. Cikupa, Kabupaten Tangerang, Banten.',
            '0811-839-126',
            $separator,
            $this->formatClosedBillingPair('No. Transaksi', (string) $payload['transaction_code'], $width),
            $this->formatClosedBillingPair('Tanggal', (string) $payload['date'], $width),
            $this->formatClosedBillingPair('Kasir', (string) $payload['cashier'], $width),
            $separator,
            $this->formatClosedBillingPair('Pelanggan', (string) $payload['customer_name'], $width),
            $this->formatClosedBillingPair('Tipe', (string) $payload['type'], $width),
            $this->formatClosedBillingPair('Meja', (string) $payload['table'], $width),
            $separator,
        ];

        foreach ($payload['items'] as $item) {
            $lines[] = "{$item['name']} {$item['qty']}x";
            $lines[] = $this->formatClosedBillingPair(
                'Harga: Rp '.number_format((float) $item['price'], 0, ',', '.'),
                'Total: Rp '.number_format((float) $item['subtotal'], 0, ',', '.'),
                $width
            );
            $lines[] = str_repeat('-', $width);
        }

        $downPaymentAmount = (float) ($payload['down_payment_amount'] ?? 0);
        $totalBill = (float) ($payload['subtotal'] ?? 0);
        $discountAmount = (float) ($payload['discount_amount'] ?? 0);
        $tax = (float) ($payload['tax'] ?? 0);
        $serviceCharge = (float) ($payload['service_charge'] ?? 0);
        $subTotal = $totalBill + $tax + $serviceCharge;

        $lines[] = $this->formatClosedBillingPair('Total Bill', 'Rp '.number_format($totalBill, 0, ',', '.'), $width);

        if ($tax > 0) {
            $lines[] = $this->formatClosedBillingPair(
                'PB1 ('.(int) $payload['tax_percentage'].'%)',
                'Rp '.number_format($tax, 0, ',', '.'),
                $width
            );
        }

        if ($serviceCharge > 0) {
            $lines[] = $this->formatClosedBillingPair(
                'Service Charge ('.(int) $payload['service_charge_percentage'].'%)',
                'Rp '.number_format($serviceCharge, 0, ',', '.'),
                $width
            );
        }

        $lines[] = $this->formatClosedBillingPair('Sub Total', 'Rp '.number_format($subTotal, 0, ',', '.'), $width);

        if ($discountAmount > 0) {
            $lines[] = $this->formatClosedBillingPair('Diskon', '- Rp '.number_format($discountAmount, 0, ',', '.'), $width);
        }

        if ($downPaymentAmount > 0) {
            $lines[] = $this->formatClosedBillingPair('DP', 'Rp '.number_format($downPaymentAmount, 0, ',', '.'), $width);
        }

        $lines[] = $this->formatClosedBillingPair('Sisa Bayar', 'Rp '.number_format((float) $payload['grand_total'], 0, ',', '.'), $width);
        $lines[] = $separator;
        $lines[] = $this->formatClosedBillingPair('Metode Pembayaran', (string) $payload['payment_method'], $width);

        if ($payload['payment_mode'] !== 'split' && filled($payload['payment_reference_number'])) {
            $lines[] = $this->formatClosedBillingPair('No. Referensi', (string) $payload['payment_reference_number'], $width);
        }

        if ($payload['payment_mode'] === 'split') {
            $lines[] = $this->formatClosedBillingPair('Mode Pembayaran', 'SPLIT BILL', $width);
            if ((float) $payload['split_cash_amount'] > 0) {
                $lines[] = $this->formatClosedBillingPair('Cash', 'Rp '.number_format((float) $payload['split_cash_amount'], 0, ',', '.'), $width);
            }

            if ((float) $payload['split_non_cash_amount'] > 0) {
                $lines[] = $this->formatClosedBillingPair((string) $payload['split_non_cash_method'], 'Rp '.number_format((float) $payload['split_non_cash_amount'], 0, ',', '.'), $width);

                if (filled($payload['split_non_cash_reference_number'])) {
                    $lines[] = $this->formatClosedBillingPair('Ref 1', (string) $payload['split_non_cash_reference_number'], $width);
                }
            }

            if ((float) ($payload['split_second_non_cash_amount'] ?? 0) > 0) {
                $lines[] = $this->formatClosedBillingPair((string) ($payload['split_second_non_cash_method'] ?? 'NON-CASH 2'), 'Rp '.number_format((float) ($payload['split_second_non_cash_amount'] ?? 0), 0, ',', '.'), $width);

                if (filled($payload['split_second_non_cash_reference_number'] ?? null)) {
                    $lines[] = $this->formatClosedBillingPair('Ref 2', (string) ($payload['split_second_non_cash_reference_number'] ?? ''), $width);
                }
            }
        }

        $lines[] = $separator;
        $lines[] = 'Terima Kasih Atas Kunjungan Anda!';
        $lines[] = "Printer: {$printer->name} ({$printer->location}) #{$printer->id}";

        return $lines;
    }

    protected function formatClosedBillingPair(string $label, string $value, int $width): string
    {
        $labelText = $this->truncate($label, (int) floor($width * 0.48));
        $valueText = $this->truncate($value, (int) floor($width * 0.48));
        $spaces = max($width - strlen($labelText) - strlen($valueText), 1);

        return $labelText.str_repeat(' ', $spaces).$valueText;
    }

    /**
     * Print a kitchen order ticket.
     */
    public function printKitchenTicket(KitchenOrder|BarOrder $kitchenOrder, Printer $printer): bool
    {
        return $this->printCheckerTicket($kitchenOrder, $printer);
    }

    /**
     * Print a bar order ticket.
     */
    public function printBarTicket(KitchenOrder|BarOrder $barOrder, Printer $printer): bool
    {
        return $this->printCheckerTicket($barOrder, $printer);
    }

    /**
     * Print a checker ticket (serve notification for floor staff).
     */
    public function printCheckerTicket(KitchenOrder|BarOrder $order, Printer $printer): bool
    {
        $order->loadMissing(['order.tableSession.waiter.profile']);
        $waiterName = $this->resolveBookingWaiterName($order);

        if ($printer->connection_type === 'log') {
            $lines = [
                "Order : #{$order->order_number}",
                'Table : '.($order->table?->table_number ?? 'N/A'),
                ...(filled($waiterName) ? ["Waiter: {$waiterName}"] : []),
                'Time  : '.now()->format('H:i'),
                "Printer: {$printer->name} ({$printer->location}) #{$printer->id}",
                '',
            ];
            foreach ($order->items as $item) {
                $name = filled($item->inventoryItem?->pos_name)
                    ? (string) $item->inventoryItem->pos_name
                    : (($item->inventoryItem?->name ?? 'Unknown'));
                $lines[] = "  {$item->quantity}x {$name}";

                $notes = trim((string) ($item->notes ?? ''));
                if ($notes !== '') {
                    $lines[] = "    NOTE: {$notes}";
                }
            }
            $this->logPrint('CHECKER', $lines);

            return true;
        }

        $connector = $this->createConnector($printer);
        $escpos = new EscposPrinter($connector);

        try {
            $escpos->setJustification(EscposPrinter::JUSTIFY_CENTER);
            $escpos->setEmphasis(true);
            $escpos->setTextSize(2, 2);
            $escpos->text("CHECKER\n");
            $escpos->setTextSize(1, 1);
            $escpos->text("Order #{$order->order_number}\n");
            $escpos->setEmphasis(false);
            $escpos->setJustification(EscposPrinter::JUSTIFY_LEFT);
            $escpos->feed(1);

            $tableName = $order->table?->table_number ?? 'N/A';
            $escpos->text("Table: {$tableName}\n");
            if (filled($waiterName)) {
                $escpos->text("Waiter: {$waiterName}\n");
            }
            $escpos->text('Time: '.now()->format('H:i')."\n");
            $escpos->text(str_repeat('-', $printer->width)."\n");

            $escpos->setEmphasis(true);
            $escpos->text("SAJIKAN\n");
            $escpos->setEmphasis(false);

            foreach ($order->items as $item) {
                $name = filled($item->inventoryItem?->pos_name)
                    ? (string) $item->inventoryItem->pos_name
                    : (($item->inventoryItem?->name ?? 'Unknown'));
                $notes = trim((string) ($item->notes ?? ''));

                $escpos->setEmphasis(true);
                $escpos->setTextSize(1, 2);
                $escpos->text("  {$item->quantity}x {$name}\n");

                if ($notes !== '') {
                    $escpos->text("    NOTE: {$notes}\n");
                }

                $escpos->setTextSize(1, 1);
                $escpos->setEmphasis(false);
            }

            $escpos->text(str_repeat('-', $printer->width)."\n");
            $escpos->setJustification(EscposPrinter::JUSTIFY_CENTER);
            $escpos->text("*** SIAP DISAJIKAN ***\n");

            $escpos->feed(3);
            $escpos->cut();

            return true;
        } finally {
            $escpos->close();
        }
    }

    protected function resolveBookingWaiterName(KitchenOrder|BarOrder $order): ?string
    {
        $waiter = $order->order?->tableSession?->waiter;

        if (! $waiter) {
            return null;
        }

        return $waiter->profile?->name
            ?? $waiter->name
            ?? null;
    }

    /**
     * Print a cashier notification ticket (order summary for cashier awareness).
     */
    public function printCashierTicket(KitchenOrder|BarOrder $order, Printer $printer): bool
    {
        return $this->printCheckerTicket($order, $printer);
    }

    /**
     * @param  array<string, mixed>  $recapData
     */
    public function printEndDayRecap(array $recapData, Printer $printer, bool $includeTransactionHistory = true): bool
    {
        $width = max((int) ($printer->width ?: 42), 32);
        $lines = $this->buildEndDayRecapLines($recapData, $printer, $width, $includeTransactionHistory);
        $dashboardPreview = (array) ($recapData['dashboardPreview'] ?? []);
        $kitchenItemsOut = (int) ($dashboardPreview['total_kitchen_items'] ?? $recapData['kitchenQtyTotal'] ?? 0);
        $barItemsOut = (int) ($dashboardPreview['total_bar_items'] ?? $recapData['barQtyTotal'] ?? 0);

        if ($printer->connection_type === 'log') {
            $logLines = [...$lines, 'Status : SUCCESS (LOG MODE)'];
            $this->logPrint('END DAY RECAP', $logLines);

            return true;
        }

        $connector = $this->createConnector($printer);
        $escpos = new EscposPrinter($connector);

        try {
            $separator = str_repeat('-', $width);

            $escpos->setJustification(EscposPrinter::JUSTIFY_CENTER);
            $escpos->setEmphasis(true);
            $escpos->text("REKAP END DAY\n");
            $escpos->setEmphasis(false);
            $escpos->text(($recapData['selectedStartDatetime'] ?? '-').' - '.($recapData['selectedEndDatetime'] ?? '-')."\n");
            $escpos->text(now()->format('d/m/Y H:i:s')."\n");
            $escpos->setJustification(EscposPrinter::JUSTIFY_LEFT);
            $escpos->text($separator."\n");

            $escpos->text($this->formatClosedBillingPair('Transaksi Kasir', number_format((float) ($recapData['cashierCount'] ?? 0), 0, ',', '.'), $width)."\n");
            $escpos->text($this->formatClosedBillingPair('Gross Sales', 'Rp '.number_format((float) ($recapData['grossSales'] ?? 0), 0, ',', '.'), $width)."\n");
            $escpos->text($this->formatClosedBillingPair('Net Sales', 'Rp '.number_format((float) ($recapData['netSales'] ?? 0), 0, ',', '.'), $width)."\n");
            $escpos->text($this->formatClosedBillingPair('Total Pajak', 'Rp '.number_format((float) ($recapData['totalTax'] ?? 0), 0, ',', '.'), $width)."\n");
            $escpos->text($this->formatClosedBillingPair('Total Service', 'Rp '.number_format((float) ($recapData['totalServiceCharge'] ?? 0), 0, ',', '.'), $width)."\n");
            $escpos->text($this->formatClosedBillingPair('Item Keluar Kitchen', number_format($kitchenItemsOut, 0, ',', '.'), $width)."\n");
            $escpos->text($this->formatClosedBillingPair('Item Keluar Bar', number_format($barItemsOut, 0, ',', '.'), $width)."\n");

            $escpos->text($separator."\n");
            $escpos->setEmphasis(true);
            $escpos->text("RINGKASAN PEMBAYARAN\n");
            $escpos->setEmphasis(false);

            $paymentMethodTotals = (array) ($recapData['paymentMethodTotals'] ?? []);
            $escpos->text($this->formatClosedBillingPair('Tunai', 'Rp '.number_format((float) ($paymentMethodTotals['cash'] ?? 0), 0, ',', '.'), $width)."\n");
            $escpos->text($this->formatClosedBillingPair('Transfer', 'Rp '.number_format((float) ($paymentMethodTotals['transfer'] ?? 0), 0, ',', '.'), $width)."\n");
            $escpos->text($this->formatClosedBillingPair('Debit', 'Rp '.number_format((float) ($paymentMethodTotals['debit'] ?? 0), 0, ',', '.'), $width)."\n");
            $escpos->text($this->formatClosedBillingPair('Kredit', 'Rp '.number_format((float) ($paymentMethodTotals['kredit'] ?? 0), 0, ',', '.'), $width)."\n");
            $escpos->text($this->formatClosedBillingPair('QRIS', 'Rp '.number_format((float) ($paymentMethodTotals['qris'] ?? 0), 0, ',', '.'), $width)."\n");

            $totalDiscount = (float) ($recapData['totalDiscount'] ?? 0);
            if ($totalDiscount > 0) {
                $escpos->text($this->formatClosedBillingPair('Total Diskon', '- Rp '.number_format($totalDiscount, 0, ',', '.'), $width)."\n");
            }

            $totalDownPayment = (float) ($recapData['totalDownPayment'] ?? 0);
            if ($totalDownPayment > 0) {
                $escpos->text($this->formatClosedBillingPair('Total DP', 'Rp '.number_format($totalDownPayment, 0, ',', '.'), $width)."\n");
            }

            $escpos->text($separator."\n");
            $escpos->setEmphasis(true);
            $escpos->text("INFO ROKOK\n");
            $escpos->setEmphasis(false);

            $rokokItems = collect($recapData['rokokItems'] ?? []);
            if ($rokokItems->isEmpty()) {
                $escpos->text("Tidak ada item rokok.\n");
            } else {
                foreach ($rokokItems as $rokokItem) {
                    $escpos->text(((string) ($rokokItem['name'] ?? '-'))."\n");
                    $escpos->text('  Qty: '.number_format((int) ($rokokItem['quantity'] ?? 0), 0, ',', '.')."x\n");
                }
            }

            if ($includeTransactionHistory) {
                $escpos->text($separator."\n");
                $escpos->setEmphasis(true);
                $escpos->text("DAFTAR TRANSAKSI\n");
                $escpos->setEmphasis(false);

                $cashierTransactions = collect($recapData['cashierTransactions'] ?? []);
                foreach ($cashierTransactions as $transaction) {
                    $escpos->setEmphasis(true);
                    $escpos->text(((string) ($transaction['order_number'] ?? '-'))."\n");
                    $escpos->setEmphasis(false);
                    $escpos->text('Waktu: '.((string) ($transaction['datetime'] ?? '-'))."\n");
                    $escpos->text('Metode: '.((string) ($transaction['payment_method'] ?? '-'))."\n");
                    $escpos->text('Ref: '.((string) (($transaction['payment_reference_number'] ?? '-') ?: '-'))."\n");

                    $items = collect($transaction['items'] ?? []);
                    foreach ($items as $item) {
                        $escpos->text('  '.((int) ($item['quantity'] ?? 0)).'x '.((string) ($item['name'] ?? '-'))."\n");
                        $escpos->text('  Subtotal: Rp '.number_format((float) ($item['subtotal'] ?? 0), 0, ',', '.')."\n");

                        if ((float) ($item['tax_amount'] ?? 0) > 0) {
                            $escpos->text('  PB1: Rp '.number_format((float) $item['tax_amount'], 0, ',', '.')."\n");
                        }

                        if ((float) ($item['service_charge_amount'] ?? 0) > 0) {
                            $escpos->text('  Service: Rp '.number_format((float) $item['service_charge_amount'], 0, ',', '.')."\n");
                        }
                    }

                    if ((float) ($transaction['discount_amount'] ?? 0) > 0) {
                        $escpos->text('  Diskon: - Rp '.number_format((float) $transaction['discount_amount'], 0, ',', '.')."\n");
                    }

                    if ((float) ($transaction['down_payment_amount'] ?? 0) > 0) {
                        $escpos->text('  DP: Rp '.number_format((float) $transaction['down_payment_amount'], 0, ',', '.')."\n");
                    }

                    $escpos->text('  '.trim($this->formatClosedBillingPair('Total Bill', 'Rp '.number_format((float) ($transaction['total_bill'] ?? 0), 0, ',', '.'), $width))."\n");
                    $escpos->text('  '.trim($this->formatClosedBillingPair('PB1', 'Rp '.number_format((float) ($transaction['tax_total'] ?? 0), 0, ',', '.'), $width))."\n");
                    $escpos->text('  '.trim($this->formatClosedBillingPair('Service Charge', 'Rp '.number_format((float) ($transaction['service_charge_total'] ?? 0), 0, ',', '.'), $width))."\n");
                    $escpos->text('  '.trim($this->formatClosedBillingPair('Sub Total', 'Rp '.number_format((float) ($transaction['sub_total'] ?? 0), 0, ',', '.'), $width))."\n");

                    $escpos->text($this->formatClosedBillingPair('Qty', (string) ($transaction['items_count'] ?? 0), $width)."\n");
                    $escpos->setEmphasis(true);
                    $escpos->text($this->formatClosedBillingPair('Sisa Bayar', 'Rp '.number_format((float) ($transaction['total'] ?? 0), 0, ',', '.'), $width)."\n");
                    $escpos->setEmphasis(false);
                    $escpos->text($separator."\n");
                }
            }

            $escpos->feed(3);
            $escpos->cut();

            $previewLines = [...$lines, 'Status : SUCCESS (SENT TO PRINTER)'];
            $this->logPrint('END DAY RECAP PREVIEW', $previewLines);

            return true;
        } finally {
            $escpos->close();
        }
    }

    /**
     * @param  array<string, mixed>  $recapData
     * @return array<int, string>
     */
    protected function buildEndDayRecapLines(array $recapData, Printer $printer, int $width, bool $includeTransactionHistory = true): array
    {
        $separator = str_repeat('-', $width);
        $paymentMethodTotals = (array) ($recapData['paymentMethodTotals'] ?? []);
        $dashboardPreview = (array) ($recapData['dashboardPreview'] ?? []);
        $kitchenItemsOut = (int) ($dashboardPreview['total_kitchen_items'] ?? $recapData['kitchenQtyTotal'] ?? 0);
        $barItemsOut = (int) ($dashboardPreview['total_bar_items'] ?? $recapData['barQtyTotal'] ?? 0);
        $ldQuantity = (int) ($dashboardPreview['total_ld_quantity'] ?? $recapData['totalLdQuantity'] ?? 0);
        $complimentQuantity = (int) ($dashboardPreview['total_compliment_quantity'] ?? $recapData['totalComplimentQuantity'] ?? 0);
        $focQuantity = (int) ($dashboardPreview['total_foc_quantity'] ?? $recapData['totalFocQuantity'] ?? 0);

        $lines = [
            'REKAP END DAY',
            ($recapData['selectedStartDatetime'] ?? '-').' - '.($recapData['selectedEndDatetime'] ?? '-'),
            'Dicetak: '.now()->format('d/m/Y H:i:s'),
            "Printer: {$printer->name} ({$printer->location}) #{$printer->id}",
            $separator,
            $this->formatClosedBillingPair('Transaksi Kasir', number_format((float) ($recapData['cashierCount'] ?? 0), 0, ',', '.'), $width),
            $this->formatClosedBillingPair('Gross Sales', 'Rp '.number_format((float) ($recapData['grossSales'] ?? 0), 0, ',', '.'), $width),
            $this->formatClosedBillingPair('Net Sales', 'Rp '.number_format((float) ($recapData['netSales'] ?? 0), 0, ',', '.'), $width),
            $this->formatClosedBillingPair('Total Pajak', 'Rp '.number_format((float) ($recapData['totalTax'] ?? 0), 0, ',', '.'), $width),
            $this->formatClosedBillingPair('Total Service', 'Rp '.number_format((float) ($recapData['totalServiceCharge'] ?? 0), 0, ',', '.'), $width),
            $this->formatClosedBillingPair('Item Keluar Kitchen', number_format($kitchenItemsOut, 0, ',', '.'), $width),
            $this->formatClosedBillingPair('Item Keluar Bar', number_format($barItemsOut, 0, ',', '.'), $width),
            $this->formatClosedBillingPair('Total Staff Meal', 'Rp '.number_format((float) ($dashboardPreview['total_staff_meal'] ?? $recapData['totalStaffMeal'] ?? 0), 0, ',', '.'), $width),
            $this->formatClosedBillingPair('Total Compliment (Qty)', number_format($complimentQuantity, 0, ',', '.'), $width),
            $this->formatClosedBillingPair('Total FOC (Qty)', number_format($focQuantity, 0, ',', '.'), $width),
            $this->formatClosedBillingPair('Total LD Qty', number_format($ldQuantity, 0, ',', '.'), $width),
            $separator,
            'RINGKASAN PEMBAYARAN',
            $this->formatClosedBillingPair('Tunai', 'Rp '.number_format((float) ($paymentMethodTotals['cash'] ?? 0), 0, ',', '.'), $width),
            $this->formatClosedBillingPair('Transfer', 'Rp '.number_format((float) ($paymentMethodTotals['transfer'] ?? 0), 0, ',', '.'), $width),
            $this->formatClosedBillingPair('Debit', 'Rp '.number_format((float) ($paymentMethodTotals['debit'] ?? 0), 0, ',', '.'), $width),
            $this->formatClosedBillingPair('Kredit', 'Rp '.number_format((float) ($paymentMethodTotals['kredit'] ?? 0), 0, ',', '.'), $width),
            $this->formatClosedBillingPair('QRIS', 'Rp '.number_format((float) ($paymentMethodTotals['qris'] ?? 0), 0, ',', '.'), $width),
        ];

        $totalDiscount = (float) ($recapData['totalDiscount'] ?? 0);
        if ($totalDiscount > 0) {
            $lines[] = $this->formatClosedBillingPair('Total Diskon', '- Rp '.number_format($totalDiscount, 0, ',', '.'), $width);
        }

        $totalDownPayment = (float) ($recapData['totalDownPayment'] ?? 0);
        if ($totalDownPayment > 0) {
            $lines[] = $this->formatClosedBillingPair('Total DP', 'Rp '.number_format($totalDownPayment, 0, ',', '.'), $width);
        }

        $lines[] = $separator;
        $lines[] = 'INFO ROKOK';

        $rokokItems = collect($recapData['rokokItems'] ?? []);
        if ($rokokItems->isEmpty()) {
            $lines[] = 'Tidak ada item rokok.';
        } else {
            foreach ($rokokItems as $rokokItem) {
                $rokokItemData = is_array($rokokItem) ? $rokokItem : (array) $rokokItem;
                $lines[] = (string) ($rokokItemData['name'] ?? '-');
                $lines[] = '  Qty: '.number_format((int) ($rokokItemData['quantity'] ?? 0), 0, ',', '.').'x';
            }
        }

        if ($includeTransactionHistory) {
            $lines[] = $separator;
            $lines[] = 'DAFTAR TRANSAKSI';

            $cashierTransactions = collect($recapData['cashierTransactions'] ?? []);

            foreach ($cashierTransactions as $transaction) {
                $transactionData = is_array($transaction) ? $transaction : (array) $transaction;

                $lines[] = (string) ($transactionData['order_number'] ?? '-');
                $lines[] = 'Waktu: '.((string) ($transactionData['datetime'] ?? '-'));
                $lines[] = 'Metode: '.((string) ($transactionData['payment_method'] ?? '-'));
                $lines[] = 'Ref: '.((string) (($transactionData['payment_reference_number'] ?? '-') ?: '-'));

                $items = collect($transactionData['items'] ?? []);
                foreach ($items as $item) {
                    $itemData = is_array($item) ? $item : (array) $item;

                    $lines[] = '  '.((int) ($itemData['quantity'] ?? 0)).'x '.((string) ($itemData['name'] ?? '-'));
                    $lines[] = '  Subtotal: Rp '.number_format((float) ($itemData['subtotal'] ?? 0), 0, ',', '.');

                    if ((float) ($itemData['tax_amount'] ?? 0) > 0) {
                        $lines[] = '  PB1: Rp '.number_format((float) $itemData['tax_amount'], 0, ',', '.');
                    }

                    if ((float) ($itemData['service_charge_amount'] ?? 0) > 0) {
                        $lines[] = '  Service: Rp '.number_format((float) $itemData['service_charge_amount'], 0, ',', '.');
                    }
                }

                if ((float) ($transactionData['discount_amount'] ?? 0) > 0) {
                    $lines[] = '  Diskon: - Rp '.number_format((float) $transactionData['discount_amount'], 0, ',', '.');
                }

                if ((float) ($transactionData['down_payment_amount'] ?? 0) > 0) {
                    $lines[] = '  DP: Rp '.number_format((float) $transactionData['down_payment_amount'], 0, ',', '.');
                }

                $lines[] = '  '.trim($this->formatClosedBillingPair('Total Bill', 'Rp '.number_format((float) ($transactionData['total_bill'] ?? 0), 0, ',', '.'), $width));
                $lines[] = '  '.trim($this->formatClosedBillingPair('PB1', 'Rp '.number_format((float) ($transactionData['tax_total'] ?? 0), 0, ',', '.'), $width));
                $lines[] = '  '.trim($this->formatClosedBillingPair('Service Charge', 'Rp '.number_format((float) ($transactionData['service_charge_total'] ?? 0), 0, ',', '.'), $width));
                $lines[] = '  '.trim($this->formatClosedBillingPair('Sub Total', 'Rp '.number_format((float) ($transactionData['sub_total'] ?? 0), 0, ',', '.'), $width));

                $lines[] = $this->formatClosedBillingPair('Qty', (string) ($transactionData['items_count'] ?? 0), $width);
                $lines[] = $this->formatClosedBillingPair('Sisa Bayar', 'Rp '.number_format((float) ($transactionData['total'] ?? 0), 0, ',', '.'), $width);
                $lines[] = $separator;
            }
        }

        return $lines;
    }

    /**
     * @param  array<int, array{name: string, quantity: int}>  $items
     */
    public function printEndDayKitchenSummary(array $items, string $endDay, Printer $printer): bool
    {
        return $this->printEndDayItemSummary('KITCHEN', $items, $endDay, $printer);
    }

    /**
     * @param  array<int, array{name: string, quantity: int}>  $items
     */
    public function printEndDayBarSummary(array $items, string $endDay, Printer $printer): bool
    {
        return $this->printEndDayItemSummary('BAR', $items, $endDay, $printer);
    }

    /**
     * @param  array<int, array{name: string, quantity: int}>  $items
     */
    protected function printEndDayItemSummary(string $section, array $items, string $endDay, Printer $printer): bool
    {
        $width = max((int) ($printer->width ?: 42), 32);
        $separator = str_repeat('-', $width);
        $totalQty = collect($items)->sum('quantity');

        $lines = [
            "END DAY {$section}",
            'Tanggal: '.$endDay,
            'Dicetak: '.now()->format('d/m/Y H:i:s'),
            "Printer: {$printer->name} ({$printer->location}) #{$printer->id}",
            $separator,
        ];

        foreach ($items as $item) {
            $lines[] = (string) $item['name'];
            $lines[] = $this->formatClosedBillingPair('Qty', number_format((int) $item['quantity'], 0, ',', '.'), $width);
            $lines[] = str_repeat('-', $width);
        }

        $lines[] = $this->formatClosedBillingPair('TOTAL ITEM', number_format((int) $totalQty, 0, ',', '.'), $width);

        if ($printer->connection_type === 'log') {
            $this->logPrint("END DAY {$section}", [...$lines, 'Status : SUCCESS (LOG MODE)']);

            return true;
        }

        $connector = $this->createConnector($printer);
        $escpos = new EscposPrinter($connector);

        try {
            $escpos->setJustification(EscposPrinter::JUSTIFY_CENTER);
            $escpos->setEmphasis(true);
            $escpos->text("END DAY {$section}\n");
            $escpos->setEmphasis(false);
            $escpos->text('Tanggal: '.$endDay."\n");
            $escpos->text(now()->format('d/m/Y H:i:s')."\n");
            $escpos->setJustification(EscposPrinter::JUSTIFY_LEFT);
            $escpos->text($separator."\n");

            foreach ($items as $item) {
                $escpos->setEmphasis(true);
                $escpos->text(((string) $item['name'])."\n");
                $escpos->setEmphasis(false);
                $escpos->text($this->formatClosedBillingPair('Qty', number_format((int) $item['quantity'], 0, ',', '.'), $width)."\n");
                $escpos->text(str_repeat('-', $width)."\n");
            }

            $escpos->setEmphasis(true);
            $escpos->text($this->formatClosedBillingPair('TOTAL ITEM', number_format((int) $totalQty, 0, ',', '.'), $width)."\n");
            $escpos->setEmphasis(false);
            $escpos->feed(3);
            $escpos->cut();

            $this->logPrint("END DAY {$section} PREVIEW", [...$lines, 'Status : SUCCESS (SENT TO PRINTER)']);

            return true;
        } finally {
            $escpos->close();
        }
    }
}
