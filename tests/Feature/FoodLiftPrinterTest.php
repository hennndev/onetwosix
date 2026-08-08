<?php

use App\Models\Area;
use App\Models\GeneralSetting;
use App\Models\Printer;

use function Pest\Laravel\actingAs;

function createFoodLiftFixture(): array
{
    $admin = adminUser();

    $area = Area::create([
        'code' => 'FL-'.uniqid(),
        'name' => 'Food Lift Area '.uniqid(),
        'capacity' => 10,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $liftPrinter = Printer::create([
        'name' => 'Food Lift Printer',
        'location' => 'food_lift',
        'printer_type' => 'food_lift',
        'connection_type' => 'log',
        'port' => 9100,
        'timeout' => 30,
        'header' => '126 Club',
        'footer' => 'Thank you',
        'width' => 42,
        'is_default' => false,
        'is_active' => true,
    ]);

    return [
        'admin' => $admin,
        'area' => $area,
        'liftPrinter' => $liftPrinter,
    ];
}

test('printer can be created with food_lift printer type', function () {
    $admin = adminUser();

    actingAs($admin)
        ->post(route('admin.printers.store'), [
            'name' => 'Lift Printer Test',
            'location' => 'food_lift',
            'printer_type' => 'food_lift',
            'connection_type' => 'log',
            'width' => 42,
            'is_active' => true,
        ])
        ->assertRedirect();

    expect(Printer::where('printer_type', 'food_lift')->exists())->toBeTrue();
});

test('general settings can map food lift printer per area', function () {
    $fixture = createFoodLiftFixture();

    actingAs($fixture['admin'])
        ->put(route('admin.settings.general.update'), [
            'tax_percentage' => 10,
            'service_charge_percentage' => 5,
            'can_choose_checker' => true,
            'mail_provider' => 'resend',
            'auth_code_delivery_channel' => 'both',
            'area_printer_settings' => [
                $fixture['area']->id => [
                    'food_lift' => $fixture['liftPrinter']->id,
                ],
            ],
        ])
        ->assertRedirect(route('admin.settings.general.index'));

    $settings = GeneralSetting::instance();

    expect((int) $settings->getPrinterIdForArea($fixture['area']->id, 'food_lift'))->toBe((int) $fixture['liftPrinter']->id)
        ->and($settings->getPrinterIdForArea($fixture['area']->id, 'closed_billing'))->toBeNull();
});
