<?php

use App\Models\CustomerUser;
use App\Models\User;
use App\Services\AccurateService;
use Mockery\MockInterface;

use function Pest\Laravel\actingAs;

test('admin can store new customer instantly in local database', function () {
    $admin = adminUser();

    $response = actingAs($admin)->post(route('admin.customers.store'), [
        'name' => 'Fast Test Customer',
        'email' => 'fastcustomer@test.com',
        'password' => 'password123',
        'phone' => '08123456789',
        'address' => 'Jl. Test 123',
        'birth_date' => '1995-05-15',
    ]);

    $response->assertRedirect(route('admin.customers.index'))
        ->assertSessionHas('success', 'Customer berhasil ditambahkan');

    $user = User::where('email', 'fastcustomer@test.com')->first();
    expect($user)->not->toBeNull();

    $customerUser = CustomerUser::where('user_id', $user->id)->first();
    expect($customerUser)->not->toBeNull()
        ->and($customerUser->customer_code)->toBeNull()
        ->and($customerUser->accurate_id)->toBeNull();
});

test('admin can update customer successfully', function () {
    $admin = adminUser();

    $user = User::factory()->create(['name' => 'Original Name', 'email' => 'original@test.com']);
    $profile = \App\Models\UserProfile::create(['user_id' => $user->id, 'phone' => '0811111111']);
    $customerUser = CustomerUser::create([
        'user_id' => $user->id,
        'user_profile_id' => $profile->id,
        'customer_code' => 'CUST-00050',
        'total_visits' => 2,
        'lifetime_spending' => 50000,
    ]);

    $response = actingAs($admin)->put(route('admin.customers.update', $customerUser->id), [
        'name' => 'Updated Name',
        'email' => 'original@test.com',
        'phone' => '0822222222',
        'total_visits' => 5,
        'lifetime_spending' => 150000,
    ]);

    $response->assertRedirect(route('admin.customers.index'))
        ->assertSessionHas('success', 'Customer berhasil diupdate');

    $user->refresh();
    $profile->refresh();
    $customerUser->refresh();

    expect($user->name)->toBe('Updated Name')
        ->and($profile->phone)->toBe('0822222222')
        ->and($customerUser->total_visits)->toBe(5)
        ->and((float) $customerUser->lifetime_spending)->toBe(150000.0);
});

test('customer list spending and visits match their editable lifetime values', function () {
    $admin = adminUser();

    $user = User::factory()->create([
        'name' => 'Customer Spending Test',
        'email' => 'spending@test.com',
    ]);
    $profile = \App\Models\UserProfile::create(['user_id' => $user->id]);
    CustomerUser::create([
        'user_id' => $user->id,
        'user_profile_id' => $profile->id,
        'customer_code' => 'CUST-SPENDING',
        'total_visits' => 17,
        'lifetime_spending' => 987654,
    ]);

    actingAs($admin)
        ->get(route('admin.customers.index'))
        ->assertOk()
        ->assertSee('Rp 987.654')
        ->assertSee('"total_visits":17', false)
        ->assertSee('"lifetime_spending":"987654.00"', false);
});

test('admin can sync customer to accurate using sync route', function () {
    $admin = adminUser();

    $user = User::factory()->create(['name' => 'John Sync', 'email' => 'johnsync@test.com']);
    $profile = \App\Models\UserProfile::create(['user_id' => $user->id]);
    $customerUser = CustomerUser::create([
        'user_id' => $user->id,
        'user_profile_id' => $profile->id,
        'customer_code' => 'CUST-00099',
    ]);

    $this->mock(AccurateService::class, function (MockInterface $mock) {
        $mock->shouldReceive('saveCustomer')
            ->once()
            ->andReturn([
                'r' => [
                    'id' => 998877,
                    'customerNo' => 'ACC-CUST-1001',
                ],
            ]);
    });

    $response = actingAs($admin)->post(route('admin.customers.sync-accurate', $customerUser->id));

    $response->assertRedirect()
        ->assertSessionHas('success');

    $customerUser->refresh();
    expect($customerUser->accurate_id)->toBe(998877)
        ->and($customerUser->customer_code)->toBe('ACC-CUST-1001');
});
