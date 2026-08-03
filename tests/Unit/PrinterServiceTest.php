<?php

use App\Models\Printer;
use App\Services\PrinterService;

it('uses text-only branding for network printers', function () {
    $service = new class extends PrinterService
    {
        public function shouldUseGraphics(Printer $printer): bool
        {
            return $this->shouldUseGraphicsBranding($printer);
        }
    };

    $networkPrinter = new Printer(['connection_type' => 'network']);
    $filePrinter = new Printer(['connection_type' => 'file']);

    expect($service->shouldUseGraphics($networkPrinter))->toBeFalse()
        ->and($service->shouldUseGraphics($filePrinter))->toBeTrue();
});

it('shows dp line and subtotal net of dp in simulation', function () {
    $service = new class extends PrinterService
    {
        public function simulationLines(array $payload, int $width, Printer $printer): array
        {
            return $this->buildClosedBillingSimulationLines($payload, $width, $printer);
        }
    };

    $printer = new Printer([
        'name' => 'Cashier',
        'location' => 'cashier',
        'connection_type' => 'network',
    ]);

    $payload = [
        'transaction_code' => 'TRX-001',
        'date' => '22 Mar 2026 20:00',
        'cashier' => 'Admin',
        'customer_name' => 'Customer',
        'type' => 'BOOKING',
        'table' => 'A1',
        'items' => [
            [
                'name' => 'Item A',
                'qty' => 1,
                'price' => 10000000,
                'subtotal' => 10000000,
            ],
        ],
        'minimum_charge' => 0,
        'subtotal' => 10000000,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'tax' => 0,
        'tax_percentage' => 0,
        'down_payment_amount' => 2000000,
        'grand_total' => 8000000,
        'payment_mode' => 'normal',
        'payment_method' => 'CASH',
        'payment_reference_number' => null,
        'split_cash_amount' => 0,
        'split_non_cash_amount' => 0,
        'split_non_cash_method' => 'NON-CASH',
        'split_non_cash_reference_number' => null,
        'split_second_non_cash_amount' => 0,
        'split_second_non_cash_method' => 'NON-CASH 2',
        'split_second_non_cash_reference_number' => null,
    ];

    $lines = $service->simulationLines($payload, 42, $printer);

    expect(collect($lines)->contains(fn (string $line): bool => str_contains($line, 'Sisa Bayar') && str_contains($line, 'Rp 8.000.000')))->toBeTrue()
        ->and(collect($lines)->contains(fn (string $line): bool => str_contains($line, 'DP') && str_contains($line, 'Rp 2.000.000')))->toBeTrue();
});
