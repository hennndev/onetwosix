<?php

use App\Models\CustomerUser;
use App\Models\GeneralSetting;
use App\Models\InventoryItem;
use App\Models\PosCategorySetting;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\AccurateService;
use Mockery\MockInterface;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

function coaSetting(): void
{
    PosCategorySetting::clearCache();
    PosCategorySetting::firstOrCreate(
        ['category_type' => 'beverage'],
        ['show_in_pos' => true, 'is_menu' => false, 'is_item_group' => false, 'preparation_location' => 'bar', 'source' => 'inventory'],
    );
    PosCategorySetting::clearCache();
}

function coaCustomer(): User
{
    $customer = User::factory()->create();
    UserProfile::create(['user_id' => $customer->id]);
    CustomerUser::create([
        'user_id' => $customer->id,
        'user_profile_id' => UserProfile::where('user_id', $customer->id)->first()->id,
        'total_visits' => 0,
        'lifetime_spending' => 0,
    ]);

    return $customer;
}

function coaItem(string $categoryMain): InventoryItem
{
    coaSetting();

    return InventoryItem::create([
        'code' => 'COA-'.uniqid(),
        'accurate_id' => random_int(100000, 999999),
        'name' => 'COA Item '.uniqid(),
        'category_type' => 'beverage',
        'category_main' => $categoryMain,
        'price' => 25000,
        'stock_quantity' => 50,
        'unit' => 'glass',
        'is_active' => true,
        'is_visible_in_pos' => true,
    ]);
}

function coaCheckout(User $customer, InventoryItem $item, array $settings): void
{
    GeneralSetting::instance()->update(array_merge([
        'tax_percentage' => 0,
        'service_charge_percentage' => 0,
    ], $settings));

    actingAs(adminUser())
        ->withSession([
            'pos_cart' => [
                'item_'.$item->id => [
                    'id' => 'item_'.$item->id,
                    'name' => $item->name,
                    'price' => (float) $item->price,
                    'quantity' => 1,
                    'preparation_location' => 'bar',
                ],
            ],
        ])
        ->postJson(route('admin.pos.checkout'), [
            'customer_type' => 'walk-in',
            'walk_in_customer_id' => $customer->id,
            'payment_mode' => 'normal',
            'payment_method' => 'cash',
            'auto_print_receipt' => false,
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);
}

function coaMockService(array &$payloads): void
{
    mock(AccurateService::class, function (MockInterface $mock) use (&$payloads): void {
        $mock->shouldReceive('saveCustomer')->andReturn([
            'r' => ['id' => 42424, 'customerNo' => 'CUST-COA'],
        ]);
        $mock->shouldReceive('saveSalesOrder')->once()->withArgs(function (array $payload) use (&$payloads): bool {
            $payloads['sales_order'] = $payload;

            return true;
        })->andReturnUsing(fn (array $payload): array => ['r' => ['number' => $payload['number']]]);
        $mock->shouldReceive('saveSalesInvoice')->once()->withArgs(function (array $payload) use (&$payloads): bool {
            $payloads['sales_invoice'] = $payload;

            return true;
        })->andReturn(['r' => ['number' => 'INV-COA']]);
        $mock->shouldReceive('saveSalesReceipt')->zeroOrMoreTimes();
    });
}

test('food item line carries food sales account number', function () {
    $admin = adminUser();
    $customer = coaCustomer();
    $item = coaItem('food');
    $payloads = [];

    coaMockService($payloads);
    coaCheckout($customer, $item, [
        'accurate_food_sales_account_no' => '410101_FOOD',
    ]);

    foreach (['sales_order', 'sales_invoice'] as $document) {
        expect($payloads[$document]['detailItem'][0]['accountNo'])->toBe('410101_FOOD');
    }
});

test('cigarette and breakage item lines carry their own category accounts', function (string $categoryMain, string $settingField, string $accountNo) {
    $admin = adminUser();
    $customer = coaCustomer();
    $item = coaItem($categoryMain);
    $payloads = [];

    coaMockService($payloads);
    coaCheckout($customer, $item, [
        $settingField => $accountNo,
    ]);

    foreach (['sales_order', 'sales_invoice'] as $document) {
        $line = collect($payloads[$document]['detailItem'])->firstWhere('itemNo', $item->code);

        expect($line['accountNo'])->toBe($accountNo);
    }
})->with([
    'cigarette' => ['cigarette', 'accurate_cigarette_sales_account_no', '410103_ROKOK'],
    'breakage' => ['breakage', 'accurate_breakage_account_no', '410104_BREAK'],
    'beverage' => ['beverage', 'accurate_beverage_sales_account_no', '410102_BEV'],
]);

test('items without a configured category coa carry no account number', function () {
    $admin = adminUser();
    $customer = coaCustomer();

    // category_main 'room' tidak punya field COA; dan COA beverage tidak diisi.
    $item = coaItem('room');
    $payloads = [];

    coaMockService($payloads);
    coaCheckout($customer, $item, []);

    foreach (['sales_order', 'sales_invoice'] as $document) {
        expect($payloads[$document]['detailItem'][0])->not->toHaveKey('accountNo');
    }
});

test('category_main with dirty spacing still resolves to its coa', function () {
    $admin = adminUser();
    $customer = coaCustomer();
    $item = coaItem('Cigarette ');
    $payloads = [];

    coaMockService($payloads);
    coaCheckout($customer, $item, [
        'accurate_cigarette_sales_account_no' => '410103_ROKOK',
    ]);

    foreach (['sales_order', 'sales_invoice'] as $document) {
        expect($payloads[$document]['detailItem'][0]['accountNo'])->toBe('410103_ROKOK');
    }
});
