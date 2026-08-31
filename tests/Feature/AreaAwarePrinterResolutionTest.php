<?php

use App\Models\Area;
use App\Models\GeneralSetting;
use App\Models\InternalUser;
use App\Models\Printer;
use App\Models\UserProfile;

use function Pest\Laravel\actingAs;

/**
 * Repo ini menggabungkan beberapa area (lounge + room) dalam satu instance, jadi
 * setiap resolusi printer WAJIB menyaring area. Tanpa itu printer ber-id terkecil
 * selalu menang dan struk tercetak di gedung yang salah.
 */
function areaPair(): array
{
    return [
        Area::create(['code' => 'LNG', 'name' => 'Lounge', 'capacity' => 20, 'is_active' => true, 'sort_order' => 1]),
        Area::create(['code' => 'ROOM', 'name' => 'Room', 'capacity' => 10, 'is_active' => true, 'sort_order' => 2]),
    ];
}

function cashierPrinter(string $name, ?int $areaId): Printer
{
    return Printer::create([
        'name' => $name,
        'location' => 'cashier',
        'area_id' => $areaId,
        'printer_type' => 'cashier',
        'connection_type' => 'log',
        'port' => 9100,
        'timeout' => 30,
        'is_active' => true,
    ]);
}

test('getForService picks the printer of the requested area, not the lowest id', function () {
    [$lounge, $room] = areaPair();

    $loungeCashier = cashierPrinter('Kasir Lounge', $lounge->id);
    $roomCashier = cashierPrinter('Kasir Room', $room->id);

    expect(Printer::getForService('cashier', $room->id)->id)->toBe($roomCashier->id);
    expect(Printer::getForService('cashier', $lounge->id)->id)->toBe($loungeCashier->id);
});

test('getForService falls back to an area-less printer when the area has none', function () {
    [$lounge, $room] = areaPair();

    cashierPrinter('Kasir Lounge', $lounge->id);
    $global = cashierPrinter('Kasir Global', null);

    // Room punya nol printer cashier → printer tanpa area jadi fallback,
    // BUKAN printer milik Lounge.
    expect(Printer::getForService('cashier', $room->id)->id)->toBe($global->id);
});

test('getDefault prefers the requested area over an is_default printer elsewhere', function () {
    [$lounge, $room] = areaPair();

    $loungeDefault = cashierPrinter('Kasir Lounge', $lounge->id);
    $loungeDefault->update(['is_default' => true]);
    $roomCashier = cashierPrinter('Kasir Room', $room->id);

    expect(Printer::getDefault($room->id)->id)->toBe($roomCashier->id);
    expect(Printer::getDefault($lounge->id)->id)->toBe($loungeDefault->id);
});

test('walk-in receipt printer follows the active area of a multi-area user', function () {
    [$lounge, $room] = areaPair();

    cashierPrinter('Kasir Lounge', $lounge->id);
    $roomCashier = cashierPrinter('Kasir Room', $room->id);

    GeneralSetting::instance()->update([
        'area_printer_settings' => [
            $room->id => ['walk_in' => (string) $roomCashier->id],
        ],
    ]);

    // Admin tanpa assigned area: area ditentukan session, bukan getAssignedArea().
    $admin = adminUser();
    actingAs($admin);
    session(['active_area_id' => $room->id]);

    $controller = app(\App\Http\Controllers\PosController::class);
    $method = (new ReflectionClass($controller))->getMethod('resolveReceiptPrinter');
    $method->setAccessible(true);

    expect($method->invoke($controller, 'walk_in')->id)->toBe($roomCashier->id);
});

test('walk-in receipt printer follows the assigned area of a single-area cashier', function () {
    [$lounge, $room] = areaPair();

    $loungeCashier = cashierPrinter('Kasir Lounge', $lounge->id);
    $roomCashier = cashierPrinter('Kasir Room', $room->id);

    GeneralSetting::instance()->update([
        'area_printer_settings' => [
            $lounge->id => ['walk_in' => (string) $loungeCashier->id],
            $room->id => ['walk_in' => (string) $roomCashier->id],
        ],
    ]);

    $cashier = adminUser();
    $profile = UserProfile::create(['user_id' => $cashier->id, 'full_name' => 'Kasir Room', 'is_active' => true]);
    InternalUser::updateOrCreate(
        ['user_id' => $cashier->id],
        ['user_profile_id' => $profile->id, 'area_id' => $room->id, 'is_active' => true]
    );

    actingAs($cashier);

    $controller = app(\App\Http\Controllers\PosController::class);
    $method = (new ReflectionClass($controller))->getMethod('resolveReceiptPrinter');
    $method->setAccessible(true);

    expect($method->invoke($controller, 'walk_in')->id)->toBe($roomCashier->id);
});

test('closed billing receipt printer follows the table area even when the user is elsewhere', function () {
    [$lounge, $room] = areaPair();

    $loungeCashier = cashierPrinter('Kasir Lounge', $lounge->id);
    $roomCashier = cashierPrinter('Kasir Room', $room->id);

    GeneralSetting::instance()->update([
        'area_printer_settings' => [
            $lounge->id => ['closed_billing' => (string) $loungeCashier->id],
            $room->id => ['closed_billing' => (string) $roomCashier->id],
        ],
    ]);

    // User sedang aktif di Lounge, tapi meja yang ditutup ada di Room.
    $admin = adminUser();
    actingAs($admin);
    session(['active_area_id' => $lounge->id]);

    $controller = app(\App\Http\Controllers\TableReservationController::class);
    $method = (new ReflectionClass($controller))->getMethod('resolveClosedBillingReceiptPrinter');
    $method->setAccessible(true);

    expect($method->invoke($controller, $room->id)->id)->toBe($roomCashier->id);
});

test('closed billing falls back to the active area when the table has no area', function () {
    [$lounge, $room] = areaPair();

    cashierPrinter('Kasir Lounge', $lounge->id);
    $roomCashier = cashierPrinter('Kasir Room', $room->id);

    $admin = adminUser();
    actingAs($admin);
    session(['active_area_id' => $room->id]);

    $controller = app(\App\Http\Controllers\TableReservationController::class);
    $method = (new ReflectionClass($controller))->getMethod('resolveClosedBillingReceiptPrinter');
    $method->setAccessible(true);

    // areaId null (meja tanpa area) → jangan jatuh ke printer Lounge ber-id kecil.
    expect($method->invoke($controller, null)->id)->toBe($roomCashier->id);
});
