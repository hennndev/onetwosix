<?php

use App\Models\Area;
use App\Models\Billing;
use App\Models\Dashboard;
use App\Models\GeneralSetting;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Printer;
use App\Models\RecapHistory;
use App\Models\RecapHistoryBar;
use App\Models\Tabel;
use App\Models\User;
use App\Services\RecapClosingService;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

/**
 * Regresi kasus produksi 2026-09-24: rekap global macet di end_day 21
 * (terakhir ditutup 22 Sep dini hari) sementara rekap per-area sudah di 22.
 * Mesin label lama hanya membaca timeline global sehingga end-day semua area
 * selalu `already_closed` — tidak ada history baru, dashboard tidak ke-cut.
 */
function seedStaleGlobalTimeline(): array
{
    GeneralSetting::instance()->update(['operational_anchor_time' => '09:00']);

    $area1 = Area::create(['code' => 'LNG', 'name' => 'LOUNGE', 'is_active' => true, 'sort_order' => 1]);
    $area2 = Area::create(['code' => 'RM', 'name' => 'ROOM', 'is_active' => true, 'sort_order' => 2]);

    // Global macet di end_day 21 (ditutup 22 Sep 04:56 dini hari)
    $global = RecapHistory::create(['area_id' => null, 'end_day' => '2026-09-21', 'total_amount' => 0]);
    RecapHistory::where('id', $global->id)->update(['created_at' => '2026-09-22 04:56:21', 'updated_at' => '2026-09-22 04:56:21']);

    // Rekap per-area sudah maju ke end_day 22 (ditutup 23 Sep pagi)
    foreach ([$area1, $area2] as $area) {
        $row = RecapHistory::create(['area_id' => $area->id, 'end_day' => '2026-09-22', 'total_amount' => 0]);
        RecapHistory::where('id', $row->id)->update(['created_at' => '2026-09-23 11:10:48', 'updated_at' => '2026-09-23 11:10:48']);
    }

    return [$area1, $area2];
}

function seedDashboardWithTotals(Area $area): Dashboard
{
    return Dashboard::create([
        'area_id' => $area->id,
        'total_amount' => 500000,
        'total_cash' => 500000,
        'total_transactions' => 3,
        'last_synced_at' => now('Asia/Jakarta'),
    ]);
}

it('label end_day area maju dari rekap areanya sendiri meski rekap global telat', function () {
    [$area1, $area2] = seedStaleGlobalTimeline();

    Carbon::setTestNow(Carbon::parse('2026-09-24 15:00:00', 'Asia/Jakarta'));

    // Area yang sudah punya rekap 22 -> label berikutnya 23 (dulu: 22, beku)
    expect(RecapHistory::resolveNextEndDay($area1->id))->toBe('2026-09-23');
    expect(RecapHistory::resolveNextEndDay($area2->id))->toBe('2026-09-23');

    // Area tanpa rekap sendiri fallback ke timeline global (21 -> 22)
    $area3 = Area::create(['code' => 'DBG', 'name' => 'DBG', 'is_active' => true, 'sort_order' => 3]);
    expect(RecapHistory::resolveNextEndDay($area3->id))->toBe('2026-09-22');

    // Scope global tetap mengikuti timeline global saja
    expect(RecapHistory::resolveNextEndDay(null))->toBe('2026-09-22');

    Carbon::setTestNow();
});

it('closeDay area men-segel hari terlupa meski rekap global macet', function () {
    [$area1] = seedStaleGlobalTimeline();
    seedDashboardWithTotals($area1);

    Carbon::setTestNow(Carbon::parse('2026-09-24 15:00:00', 'Asia/Jakarta'));

    $service = app(RecapClosingService::class);
    $result = $service->closeDay(null, $area1->id);

    expect($result['status'])->toBe('closed');
    expect($result['end_day'])->toBe('2026-09-23');
    expect((float) $result['recap_history']->total_amount)->toBe(500000.0);

    // Dashboard ke-cut (di-nol-kan)
    $dashboardRow = Dashboard::where('area_id', $area1->id)->first();
    expect((float) ($dashboardRow?->total_amount ?? 0))->toBe(0.0);

    // Close kedua tetap ditolak sebagai already_closed
    $second = $service->closeDay(null, $area1->id);
    expect($second['status'])->toBe('already_closed');

    Carbon::setTestNow();
});

it('close global lalu close area pada hari yang sama tidak saling blokir', function () {
    [$area1, $area2] = seedStaleGlobalTimeline();
    seedDashboardWithTotals($area1);
    seedDashboardWithTotals($area2);

    // Baris dashboard GLOBAL = hasil merge baris area (angka riil prod)
    Dashboard::create([
        'area_id' => null,
        'total_amount' => 555000,
        'total_cash' => 555000,
        'total_transactions' => 13,
        'last_synced_at' => now('Asia/Jakarta'),
    ]);

    Carbon::setTestNow(Carbon::parse('2026-09-24 15:00:00', 'Asia/Jakarta'));

    $service = app(RecapClosingService::class);

    // Langkah pemulihan: "Semua Area" dulu (label 22), lalu per-area (label 23)
    $globalResult = $service->closeDay(null, null);
    expect($globalResult['status'])->toBe('closed');
    expect($globalResult['end_day'])->toBe('2026-09-22');

    $areaResult = $service->closeDay(null, $area1->id);
    expect($areaResult['status'])->toBe('closed');
    expect($areaResult['end_day'])->toBe('2026-09-23');

    $area2Result = $service->closeDay(null, $area2->id);
    expect($area2Result['status'])->toBe('closed');
    expect($area2Result['end_day'])->toBe('2026-09-23');

    Carbon::setTestNow();
});

function seedBarFixtures(): array
{
    GeneralSetting::instance()->update(['operational_anchor_time' => '09:00']);

    $admin = adminUser();
    $area = Area::create(['code' => 'LNG', 'name' => 'LOUNGE', 'is_active' => true, 'sort_order' => 1]);
    Tabel::create(['area_id' => $area->id, 'table_number' => 'L1', 'qr_code' => 'QR-'.uniqid(), 'capacity' => 4, 'minimum_charge' => 0, 'status' => 'occupied', 'is_active' => true]);
    $printer = Printer::create(['name' => 'Bar', 'location' => 'bar', 'printer_type' => 'bar', 'area_id' => $area->id, 'connection_type' => 'log', 'path' => 'p.log', 'width' => 42, 'is_active' => true]);
    $inv = InventoryItem::create(['code' => 'BIR', 'accurate_id' => random_int(100000, 999999), 'name' => 'Bir', 'category_type' => 'beverage', 'price' => 50000, 'stock_quantity' => 999, 'threshold' => 5, 'unit' => 'btl', 'is_active' => true]);
    $inv->printers()->attach($printer->id);

    return [$admin, $area, $inv];
}

function seedBarOrder(User $admin, Area $area, InventoryItem $inv, string $createdAt, int $qty = 2): void
{
    $order = Order::create(['table_session_id' => null, 'created_by' => $admin->id, 'area_id' => $area->id, 'order_number' => 'ORD-'.uniqid(), 'status' => 'completed', 'items_total' => 50000 * $qty, 'discount_amount' => 0, 'total' => 50000 * $qty, 'ordered_at' => $createdAt]);
    Order::where('id', $order->id)->update(['created_at' => $createdAt, 'updated_at' => $createdAt]);
    OrderItem::create(['order_id' => $order->id, 'inventory_item_id' => $inv->id, 'item_name' => 'Bir', 'item_code' => 'BIR', 'quantity' => $qty, 'price' => 50000, 'subtotal' => 50000 * $qty, 'discount_amount' => 0, 'preparation_location' => 'bar', 'status' => 'served']);
}

it('end-day bar setelah close global di hari yang sama tidak diblokir guard', function () {
    [$admin, $area, $inv] = seedBarFixtures();

    // Rekap main: global ditutup HARI INI jam 10:00 (post-anchor, alur pemulihan),
    // rekap area-1 kemarin. Tanpa fix, guard menolak end-day bar hari ini.
    $global = RecapHistory::create(['area_id' => null, 'end_day' => '2026-09-22', 'total_amount' => 0]);
    RecapHistory::where('id', $global->id)->update(['created_at' => '2026-09-24 10:00:00', 'updated_at' => '2026-09-24 10:00:00']);
    $own = RecapHistory::create(['area_id' => $area->id, 'end_day' => '2026-09-22', 'total_amount' => 0]);
    RecapHistory::where('id', $own->id)->update(['created_at' => '2026-09-23 11:10:48', 'updated_at' => '2026-09-23 11:10:48']);

    Carbon::setTestNow(Carbon::parse('2026-09-24 11:00:00', 'Asia/Jakarta'));
    seedBarOrder($admin, $area, $inv, '2026-09-24 10:30:00');

    actingAs($admin)->post(route('admin.bar.end-day', ['area_id' => $area->id]));

    expect(session('success'))->toContain('berhasil disimpan');
    expect(RecapHistoryBar::where('area_id', $area->id)->whereDate('end_day', '2026-09-24')->exists())->toBeTrue();

    Carbon::setTestNow();
});

it('close dini hari pre-anchor tetap mengunci end-day bar sebelum jam operasional', function () {
    [$admin, $area, $inv] = seedBarFixtures();

    // Close dini hari (03:00, pre-anchor) untuk end_day 23
    $global = RecapHistory::create(['area_id' => null, 'end_day' => '2026-09-23', 'total_amount' => 0]);
    RecapHistory::where('id', $global->id)->update(['created_at' => '2026-09-24 03:00:00', 'updated_at' => '2026-09-24 03:00:00']);

    Carbon::setTestNow(Carbon::parse('2026-09-24 05:00:00', 'Asia/Jakarta'));
    seedBarOrder($admin, $area, $inv, '2026-09-24 04:00:00');

    actingAs($admin)->post(route('admin.bar.end-day', ['area_id' => $area->id]));

    expect(session('error'))->toContain('sudah ditutup lebih awal');
    expect(RecapHistoryBar::count())->toBe(0);

    Carbon::setTestNow();
});

it('siklus normal: buka 20:00 lalu end-day 04:00 dini hari ter-seal utuh sebagai end_day 24', function () {
    GeneralSetting::instance()->update(['operational_anchor_time' => '09:00']);

    $admin = adminUser();
    $area = Area::create(['code' => 'LNG', 'name' => 'LOUNGE', 'is_active' => true, 'sort_order' => 1]);
    Tabel::create(['area_id' => $area->id, 'table_number' => 'L1', 'qr_code' => 'QR-'.uniqid(), 'capacity' => 4, 'minimum_charge' => 0, 'status' => 'occupied', 'is_active' => true]);
    $printer = Printer::create(['name' => 'Bar', 'location' => 'bar', 'printer_type' => 'bar', 'area_id' => $area->id, 'connection_type' => 'log', 'path' => 'p.log', 'width' => 42, 'is_active' => true]);
    $inv = InventoryItem::create(['code' => 'BIR', 'accurate_id' => random_int(100000, 999999), 'name' => 'Bir', 'category_type' => 'beverage', 'price' => 50000, 'stock_quantity' => 999, 'threshold' => 5, 'unit' => 'btl', 'is_active' => true]);
    $inv->printers()->attach($printer->id);

    // State pasca-pemulihan 24 Sep sore: global 22, area 23 (rekap dibuat ~16:00)
    $global = RecapHistory::create(['area_id' => null, 'end_day' => '2026-09-22', 'total_amount' => 0]);
    RecapHistory::where('id', $global->id)->update(['created_at' => '2026-09-24 16:00:00', 'updated_at' => '2026-09-24 16:00:00']);
    $own = RecapHistory::create(['area_id' => $area->id, 'end_day' => '2026-09-23', 'total_amount' => 0]);
    RecapHistory::where('id', $own->id)->update(['created_at' => '2026-09-24 16:00:00', 'updated_at' => '2026-09-24 16:00:00']);

    // Outlet buka jam 20:00 — transaksi malam berjalan
    Carbon::setTestNow(Carbon::parse('2026-09-24 21:00:00', 'Asia/Jakarta'));
    seedBarOrder($admin, $area, $inv, '2026-09-24 20:30:00', 4);
    seedBarOrder($admin, $area, $inv, '2026-09-25 02:00:00', 2);

    // End-day dini hari 25 Sep 04:00 — URUTAN AMAN: bar dulu, main per-area, "Semua Area" terakhir
    Carbon::setTestNow(Carbon::parse('2026-09-25 04:00:00', 'Asia/Jakarta'));

    actingAs($admin)->post(route('admin.bar.end-day', ['area_id' => $area->id]));
    expect(session('success'))->toContain('berhasil disimpan');
    $barRecap = RecapHistoryBar::where('area_id', $area->id)->latest('id')->first();
    expect($barRecap->end_day->toDateString())->toBe('2026-09-24');
    expect((int) $barRecap->total_items)->toBe(6); // 4 + 2, semua transaksi malam ikut

    // Main recap per-area
    Dashboard::create(['area_id' => $area->id, 'total_amount' => 750000, 'total_cash' => 750000, 'total_transactions' => 2, 'last_synced_at' => now('Asia/Jakarta')]);
    $service = app(RecapClosingService::class);
    $areaResult = $service->closeDay(null, $area->id);
    expect($areaResult['status'])->toBe('closed');
    expect($areaResult['end_day'])->toBe('2026-09-24');

    // "Semua Area" terakhir (label global memang tertinggal satu: 23 — warisan divergensi lama)
    Dashboard::create(['area_id' => null, 'total_amount' => 750000, 'total_cash' => 750000, 'total_transactions' => 2, 'last_synced_at' => now('Asia/Jakarta')]);
    $globalResult = $service->closeDay(null, null);
    expect($globalResult['status'])->toBe('closed');
    expect($globalResult['end_day'])->toBe('2026-09-23');

    Carbon::setTestNow();
});

it('preview Semua Area = gabungan view area: DP yang sudah ter-seal tidak dihidupkan ulang', function () {
    GeneralSetting::instance()->update(['operational_anchor_time' => '09:00']);

    $admin = adminUser();
    $area = Area::create(['code' => 'LNG', 'name' => 'LOUNGE', 'is_active' => true, 'sort_order' => 1]);
    $table = Tabel::create(['area_id' => $area->id, 'table_number' => 'L1', 'qr_code' => 'QR-'.uniqid(), 'capacity' => 4, 'minimum_charge' => 0, 'status' => 'available', 'is_active' => true]);
    $customer = User::factory()->create();

    // Booking ber-DP yang billing-nya sudah paid SEBELUM close (sudah ter-seal)
    $booking = \App\Models\TableReservation::create([
        'booking_code' => random_int(100000, 999999),
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'reservation_date' => '2026-09-24',
        'reservation_time' => '20:00',
        'status' => 'completed',
        'down_payment_amount' => 9100000,
    ]);
    $session = \App\Models\TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $table->id,
        'customer_id' => $customer->id,
        'session_code' => 'S-'.uniqid(),
        'checked_in_at' => '2026-09-24 09:30:00',
        'checked_out_at' => '2026-09-24 14:00:00',
        'status' => 'completed',
    ]);
    Billing::create([
        'table_session_id' => $session->id,
        'area_id' => $area->id,
        'is_booking' => true,
        'billing_status' => 'paid',
        'grand_total' => 3000000,
        'paid_amount' => 3000000,
        'remaining_balance' => 0,
        'paid_at' => '2026-09-24 14:00:00',
        'payment_method' => 'cash',
    ]);

    // End-day area tadi sore: rekap 23 (dibuat 16:45) + dashboard area nol
    $recap = RecapHistory::create(['area_id' => $area->id, 'end_day' => '2026-09-23', 'total_dp' => 9100000, 'total_amount' => 0]);
    RecapHistory::where('id', $recap->id)->update(['created_at' => '2026-09-24 16:45:01', 'updated_at' => '2026-09-24 16:45:01']);
    Dashboard::create(['area_id' => $area->id, 'total_amount' => 0, 'total_dp' => 0, 'last_synced_at' => now('Asia/Jakarta')]);
    Dashboard::create(['area_id' => null, 'total_amount' => 0, 'total_dp' => 0, 'last_synced_at' => now('Asia/Jakarta')]);

    Carbon::setTestNow(Carbon::parse('2026-09-24 17:00:00', 'Asia/Jakarta'));

    // Preview "Semua Area" (scope null): DP 9,1 jt TIDAK boleh muncul lagi
    actingAs($admin)->get(route('admin.recap.index', ['area_id' => 'all']))
        ->assertViewHas('totalDownPayment', 0.0)
        ->assertViewHas('cashierRevenue', 0.0);

    // Preview per-area juga nol
    actingAs($admin)->get(route('admin.recap.index', ['area_id' => $area->id]))
        ->assertViewHas('totalDownPayment', 0.0);

    Carbon::setTestNow();
});

it('transaction recap hari ini sadar-area: transaksi ROOM belum ter-seal tetap tampil walau LOUNGE sudah close', function () {
    GeneralSetting::instance()->update(['operational_anchor_time' => '09:00']);

    $admin = adminUser();
    $lounge = Area::create(['code' => 'LNG', 'name' => 'LOUNGE', 'is_active' => true, 'sort_order' => 1]);
    $room = Area::create(['code' => 'RM', 'name' => 'ROOM', 'is_active' => true, 'sort_order' => 2]);
    $roomTable = Tabel::create(['area_id' => $room->id, 'table_number' => 'R1', 'qr_code' => 'QR-'.uniqid(), 'capacity' => 4, 'minimum_charge' => 0, 'status' => 'occupied', 'is_active' => true]);
    $customer = User::factory()->create();

    // LOUNGE close 16:45 (paling baru), ROOM close 10:00
    $loungeRecap = RecapHistory::create(['area_id' => $lounge->id, 'end_day' => '2026-09-23', 'total_amount' => 0]);
    RecapHistory::where('id', $loungeRecap->id)->update(['created_at' => '2026-09-24 16:45:00', 'updated_at' => '2026-09-24 16:45:00']);
    $roomRecap = RecapHistory::create(['area_id' => $room->id, 'end_day' => '2026-09-23', 'total_amount' => 0]);
    RecapHistory::where('id', $roomRecap->id)->update(['created_at' => '2026-09-24 10:00:00', 'updated_at' => '2026-09-24 10:00:00']);

    // Billing ROOM paid 12:00 — sesudah close ROOM (10:00) → BELUM ter-seal.
    // Perilaku lama: window "hari ini" pakai close terakhir APAPUN (16:45) → transaksi ini hilang.
    $booking = \App\Models\TableReservation::create([
        'booking_code' => random_int(100000, 999999),
        'table_id' => $roomTable->id,
        'customer_id' => $customer->id,
        'reservation_date' => '2026-09-24',
        'reservation_time' => '11:00',
        'status' => 'completed',
    ]);
    $session = \App\Models\TableSession::create([
        'table_reservation_id' => $booking->id,
        'table_id' => $roomTable->id,
        'customer_id' => $customer->id,
        'session_code' => 'S-'.uniqid(),
        'checked_in_at' => '2026-09-24 11:00:00',
        'status' => 'completed',
    ]);
    $billing = Billing::create([
        'table_session_id' => $session->id,
        'area_id' => $room->id,
        'is_booking' => true,
        'billing_status' => 'paid',
        'grand_total' => 250000,
        'paid_amount' => 250000,
        'remaining_balance' => 0,
        'paid_at' => '2026-09-24 12:00:00',
        'payment_method' => 'cash',
    ]);

    Carbon::setTestNow(Carbon::parse('2026-09-24 17:00:00', 'Asia/Jakarta'));

    actingAs($admin)->get(route('admin.recap.index', ['area_id' => 'all']))
        ->assertViewHas('todayBillingTransactions', function ($list) use ($billing): bool {
            return $list->contains(fn ($row): bool => $row['billing_id'] === (int) $billing->id);
        });

    Carbon::setTestNow();
});
