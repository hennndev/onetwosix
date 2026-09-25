<?php

use App\Models\Area;
use App\Models\Billing;
use App\Models\GeneralSetting;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RecapHistory;
use App\Models\Tabel;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

/**
 * Regresi kasus produksi 25/09: setelah end-day per-area, kartu Dashboard
 * "Semua Area" masih menampilkan 109 transaksi / 593 item — karena kartu
 * live memakai window timeline recap GLOBAL yang tidak pernah maju lagi
 * (end-day kini hanya per-area), menghisap semua transaksi yang sudah
 * ter-seal ke rekap area.
 */
function dashAreaFixture(User $admin, string $name): array
{
    GeneralSetting::instance()->update(['operational_anchor_time' => '09:00']);
    $area = Area::create(['code' => strtoupper(substr($name, 0, 3)).uniqid(), 'name' => $name, 'is_active' => true, 'sort_order' => random_int(1, 99)]);
    $table = Tabel::create(['area_id' => $area->id, 'table_number' => 'T-'.uniqid(), 'qr_code' => 'QR-'.uniqid(), 'capacity' => 4, 'minimum_charge' => 0, 'status' => 'available', 'is_active' => true]);
    $inv = InventoryItem::create(['code' => 'INV-'.uniqid(), 'accurate_id' => random_int(100000, 999999), 'name' => 'Item '.$name, 'category_type' => 'beverage', 'price' => 100000, 'stock_quantity' => 99, 'threshold' => 5, 'unit' => 'x', 'is_active' => true]);

    return [$area, $table, $inv];
}

function dashPaidBilling(User $admin, Area $area, Tabel $table, InventoryItem $inv, string $paidAt, float $amount = 100000): void
{
    $customer = User::factory()->create();
    $booking = \App\Models\TableReservation::create([
        'booking_code' => random_int(100000, 999999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => substr($paidAt, 0, 10),
        'reservation_time' => '12:00',
        'status' => 'completed',
    ]);
    $session = TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'session_code' => 'S-'.uniqid(),
        'checked_in_at' => $paidAt,
        'status' => 'completed',
    ]);
    $order = Order::create(['table_session_id' => $session->id, 'created_by' => $admin->id, 'area_id' => $area->id, 'order_number' => 'ORD-'.uniqid(), 'status' => 'completed', 'items_total' => $amount, 'discount_amount' => 0, 'total' => $amount, 'ordered_at' => $paidAt]);
    Order::where('id', $order->id)->update(['created_at' => $paidAt, 'updated_at' => $paidAt]);
    OrderItem::create(['order_id' => $order->id, 'inventory_item_id' => $inv->id, 'item_name' => 'Item', 'item_code' => 'INV', 'quantity' => 2, 'price' => $amount / 2, 'subtotal' => $amount, 'discount_amount' => 0, 'preparation_location' => 'bar', 'status' => 'served']);

    // Kartu "item terjual" dashboard dihitung dari BarOrderItem — buat tiket bar-nya
    $barOrder = \App\Models\BarOrder::create(['order_id' => $order->id, 'area_id' => $area->id, 'table_id' => $table->id, 'order_number' => $order->order_number, 'status' => 'selesai', 'total_amount' => $amount, 'payment_method' => 'cash']);
    \App\Models\BarOrder::where('id', $barOrder->id)->update(['created_at' => $paidAt, 'updated_at' => $paidAt]);
    \App\Models\BarOrderItem::create(['bar_order_id' => $barOrder->id, 'inventory_item_id' => $inv->id, 'quantity' => 2, 'price' => $amount / 2, 'is_completed' => true]);

    Billing::create([
        'table_session_id' => $session->id,
        'area_id' => $area->id,
        'is_booking' => true,
        'billing_status' => 'paid',
        'grand_total' => $amount,
        'paid_amount' => $amount,
        'remaining_balance' => 0,
        'paid_at' => $paidAt,
        'payment_method' => 'cash',
    ]);
}

function seedAreaClose(Area $area, string $createdAt): void
{
    $recap = RecapHistory::create(['area_id' => $area->id, 'end_day' => Carbon::parse($createdAt, 'Asia/Jakarta')->subDay()->toDateString(), 'total_amount' => 0]);
    RecapHistory::where('id', $recap->id)->update(['created_at' => $createdAt, 'updated_at' => $createdAt]);
}

it('kartu Semua Area tidak menghisap transaksi yang sudah ter-seal per-area', function () {
    $admin = adminUser();
    [$lounge, $loungeTable, $loungeInv] = dashAreaFixture($admin, 'LOUNGE');
    [$room, $roomTable, $roomInv] = dashAreaFixture($admin, 'ROOM');

    // Timeline global MACET: recap global terakhir dibuat 22 Sep 04:56
    $global = RecapHistory::create(['area_id' => null, 'end_day' => '2026-09-21', 'total_amount' => 0]);
    RecapHistory::where('id', $global->id)->update(['created_at' => '2026-09-22 04:56:00', 'updated_at' => '2026-09-22 04:56:00']);

    // Transaksi TER-SEAL: paid 23 Sep (sudah masuk rekap per-area yang ditutup 24 Sep 16:45)
    dashPaidBilling($admin, $lounge, $loungeTable, $loungeInv, '2026-09-23 22:00:00', 500000);
    dashPaidBilling($admin, $room, $roomTable, $roomInv, '2026-09-24 02:00:00', 200000);
    seedAreaClose($lounge, '2026-09-24 16:45:00');
    seedAreaClose($room, '2026-09-24 16:45:00');

    // Transaksi BARU: paid setelah close (25 Sep)
    dashPaidBilling($admin, $lounge, $loungeTable, $loungeInv, '2026-09-25 03:00:00', 100000);

    Carbon::setTestNow(Carbon::parse('2026-09-25 08:00:00', 'Asia/Jakarta'));

    actingAs($admin)->get(route('admin.dashboard', ['area_id' => 'all']))
        ->assertViewHas('transactionsToday', 1)   // dulu: 3 (menghisap ter-seal)
        ->assertViewHas('revenueToday', 100000.0)
        ->assertViewHas('itemsSoldToday', 2);

    // Per-area juga benar
    actingAs($admin)->get(route('admin.dashboard', ['area_id' => $lounge->id]))
        ->assertViewHas('transactionsToday', 1);

    Carbon::setTestNow();
});

it('kartu Semua Area = union per-area: tx ROOM pasca-close ikut walau LOUNGE close lebih baru', function () {
    $admin = adminUser();
    [$lounge, $loungeTable, $loungeInv] = dashAreaFixture($admin, 'LOUNGE');
    [$room, $roomTable, $roomInv] = dashAreaFixture($admin, 'ROOM');

    // ROOM close jam 10:00, LOUNGE close jam 16:45 (beda jam)
    seedAreaClose($room, '2026-09-24 10:00:00');
    seedAreaClose($lounge, '2026-09-24 16:45:00');

    // Tx ROOM paid 12:00 (setelah close ROOM, sebelum close LOUNGE)
    dashPaidBilling($admin, $room, $roomTable, $roomInv, '2026-09-24 12:00:00', 300000);

    Carbon::setTestNow(Carbon::parse('2026-09-24 17:00:00', 'Asia/Jakarta'));

    // Union: tx ROOM itu "hari ini" bagi ROOM, jadi harus terhitung di Semua Area.
    // Dulu (pin global/area): window global 10:00 atau area 16:45 membuatnya hilang/ganda.
    actingAs($admin)->get(route('admin.dashboard', ['area_id' => 'all']))
        ->assertViewHas('transactionsToday', 1)
        ->assertViewHas('revenueToday', 300000.0);

    Carbon::setTestNow();
});
