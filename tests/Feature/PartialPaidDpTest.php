<?php

use App\Models\Area;
use App\Models\Billing;
use App\Models\GeneralSetting;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Printer;
use App\Models\Tabel;
use App\Models\TableReservation;
use App\Models\TableSession;
use App\Models\User;
use App\Services\PrinterService;
use App\Services\SessionBillingCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Regresi kasus produksi 24/09 (struk FREDY VIP-5A):
 * Sub Total 5.054.940, DP 500.000 -> Total Tagihan 4.554.940 (benar),
 * tapi Sisa Tagihan masih 5.054.940 (remaining basi, DP tidak dikurangi).
 */
function fredyFixture(User $admin, float $ordersTotal = 4140000.0): array
{
    GeneralSetting::instance()->update(['operational_anchor_time' => '09:00', 'tax_percentage' => 10, 'service_charge_percentage' => 11]);

    $customer = User::factory()->create();
    $area = Area::create(['code' => 'LNG', 'name' => 'LOUNGE', 'is_active' => true, 'sort_order' => 1]);
    $table = Tabel::create(['area_id' => $area->id, 'table_number' => 'VIP - 5A', 'qr_code' => 'QR-'.uniqid(), 'capacity' => 4, 'minimum_charge' => 0, 'status' => 'occupied', 'is_active' => true]);
    $booking = TableReservation::create([
        'booking_code' => random_int(100000, 999999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => '2026-09-24',
        'reservation_time' => '20:00',
        'status' => 'checked_in',
        'down_payment_amount' => 500000,
    ]);
    $session = TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'session_code' => 'S-'.uniqid(),
        'checked_in_at' => now(),
        'status' => 'active',
    ]);
    $billing = Billing::create([
        'table_session_id' => $session->id,
        'area_id' => $area->id,
        'is_walk_in' => false,
        'is_booking' => true,
        'minimum_charge' => 0,
        'orders_total' => 0,
        'subtotal' => 0,
        'tax' => 0,
        'tax_percentage' => 0,
        'service_charge' => 0,
        'service_charge_percentage' => 0,
        'discount_amount' => 0,
        'grand_total' => 0,
        'paid_amount' => 0,
        'remaining_balance' => 0,
        'billing_status' => 'draft',
    ]);
    $session->update(['billing_id' => $billing->id]);

    $inv = InventoryItem::create(['code' => 'ITM-'.uniqid(), 'accurate_id' => random_int(100000, 999999), 'name' => 'Paket', 'category_type' => 'beverage', 'price' => $ordersTotal, 'stock_quantity' => 99, 'threshold' => 5, 'unit' => 'x', 'is_active' => true]);
    $order = Order::create(['table_session_id' => $session->id, 'created_by' => $admin->id, 'area_id' => $area->id, 'order_number' => 'ORD-'.uniqid(), 'status' => 'completed', 'items_total' => $ordersTotal, 'discount_amount' => 0, 'total' => $ordersTotal, 'ordered_at' => now()]);
    OrderItem::create(['order_id' => $order->id, 'inventory_item_id' => $inv->id, 'item_name' => 'Paket', 'item_code' => 'ITM', 'quantity' => 1, 'price' => $ordersTotal, 'subtotal' => $ordersTotal, 'discount_amount' => 0, 'preparation_location' => 'bar', 'status' => 'served']);

    return [$booking, $session, $billing];
}

function renderStrukPartial(Billing $billing, TableSession $session): array
{
    $svc = app(PrinterService::class);
    $printer = new Printer(['name' => 'Test', 'location' => 'cashier', 'width' => 42, 'connection_type' => 'log', 'path' => 'x']);
    $printer->id = 1;

    $ref = new ReflectionClass($svc);
    $buildPayload = $ref->getMethod('buildClosedBillingPayload');
    $buildPayload->setAccessible(true);
    $buildLines = $ref->getMethod('buildClosedBillingSimulationLines');
    $buildLines->setAccessible(true);

    $session->loadMissing('orders.items', 'reservation', 'customer', 'table');
    $payload = $buildPayload->invoke($svc, $billing, $session);

    return $buildLines->invoke($svc, $payload, 42, $printer);
}

it('kalkulator sesi sadar-DP: 5.054.940 - DP 500.000 = 4.554.940', function () {
    [$booking, $session] = fredyFixture(adminUser());

    $calc = app(SessionBillingCalculator::class);
    $totals = $calc->calculate(
        $session->fresh()->load('orders.items.inventoryItem'),
        0,
        0,
        (float) $booking->down_payment_amount,
    );

    expect($totals['orders_total'])->toBe(4140000.0)
        ->and($totals['tax'])->toBe(414000.0)
        ->and($totals['service_charge'])->toBe(500940.0)
        ->and(round($totals['subtotal'] + $totals['tax'] + $totals['service_charge'], 2))->toBe(5054940.0)
        ->and($totals['grand_total'])->toBe(4554940.0);
});

it('tambah order jalur POS setelah DP: grand_total & sisa tetap net-DP (tidak menghidupkan DP)', function () {
    [$booking, $session, $billing] = fredyFixture(adminUser());

    // Simulasi persis urutan PosController::checkout saat tambah order:
    // hitung dengan DP lalu tulis grand_total + recalculatePaymentStatus.
    $calc = app(SessionBillingCalculator::class);
    $totals = $calc->calculate(
        $session->fresh()->load('orders.items.inventoryItem'),
        (float) $billing->discount_amount,
        (float) $billing->minimum_charge,
        (float) ($session->fresh()->reservation?->down_payment_amount ?? 0),
    );
    $billing->update(['grand_total' => (float) $totals['grand_total']]);
    $billing->recalculatePaymentStatus();

    $fresh = $billing->fresh();
    expect((float) $fresh->grand_total)->toBe(4554940.0)
        ->and((float) $fresh->remaining_balance)->toBe(4554940.0)
        ->and((float) $fresh->remaining_balance)->not->toBe(5054940.0);
});

it('tambah order setelah partial paid: sisa = net-DP dikurangi yang sudah dibayar', function () {
    [$booking, $session, $billing] = fredyFixture(adminUser());

    // Billing sudah partial: grand net-DP 4.554.940, dibayar 150.000
    $billing->update(['grand_total' => 4554940, 'paid_amount' => 150000, 'billing_status' => 'partially_paid']);
    $billing->payments()->create(['amount_paid' => 150000, 'payment_method' => 'cash', 'payment_type' => 'initial_partial', 'created_by' => $billing->id, 'paid_at' => now()]);

    // Tamu nambah order -> jalur POS hitung ulang dengan DP
    $calc = app(SessionBillingCalculator::class);
    $totals = $calc->calculate(
        $session->fresh()->load('orders.items.inventoryItem'),
        (float) $billing->discount_amount,
        (float) $billing->minimum_charge,
        (float) ($session->fresh()->reservation?->down_payment_amount ?? 0),
    );
    $billing->update(['grand_total' => (float) $totals['grand_total']]);
    $billing->recalculatePaymentStatus();

    $fresh = $billing->fresh();
    expect((float) $fresh->grand_total)->toBe(4554940.0)
        ->and((float) $fresh->remaining_balance)->toBe(4404940.0); // 4.554.940 - 150.000
});

it('struk partial: Sisa Tagihan = Total Tagihan - Dibayar (DP tidak hilang lagi)', function () {
    [$booking, $session, $billing] = fredyFixture(adminUser());

    // Kondisi rusak seperti produksi: grand sudah net-DP, remaining basi penuh
    $billing->update(['grand_total' => 4554940, 'paid_amount' => 0, 'remaining_balance' => 5054940]);
    $billing->refresh();

    $lines = renderStrukPartial($billing, $session->fresh());
    $sisaLine = collect($lines)->first(fn ($line) => str_contains($line, 'Sisa Tagihan'));

    expect($sisaLine)->toContain('4.554.940')
        ->and($sisaLine)->not->toContain('5.054.940');
});

it('printRunningReceipt menyegarkan remaining_balance yang basi', function () {
    [$booking, $session, $billing] = fredyFixture(adminUser());

    // Kondisi rusak: grand penuh + remaining penuh (warisan jalur buta-DP)
    $billing->update(['grand_total' => 5054940, 'remaining_balance' => 5054940]);

    actingAs(adminUser())->post(route('admin.bookings.printRunningReceipt', $booking));

    $fresh = $billing->fresh();
    expect((float) $fresh->grand_total)->toBe(4554940.0)
        ->and((float) $fresh->remaining_balance)->toBe(4554940.0);
});
