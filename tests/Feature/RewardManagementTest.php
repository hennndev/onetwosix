<?php

use App\Models\Reward;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Regresi bug produksi: "The PUT method is not supported for route admin/rewards.
 * Supported methods: GET, HEAD, POST." — muncul saat menambah reward karena
 * input hidden _method=PUT dulu diletakkan di dalam <div x-show="isEdit">.
 * x-show hanya menyembunyikan (display:none), elemen tetap di DOM dan tetap
 * ikut ter-submit, sehingga form "Tambah Reward" mengirim POST /admin/rewards
 * + _method=PUT → di-spoof jadi PUT ke route koleksi yang tidak ada.
 */
test('halaman rewards render dan method-spoof PUT hanya di dalam template x-if', function () {
    $admin = adminUser();

    $response = actingAs($admin)->get(route('admin.rewards.index'));

    $response->assertSuccessful();
    $html = $response->getContent();

    expect($html)->toContain('x-if="isEdit"');
    expect($html)->not->toContain('<div x-show="isEdit">');
});

test('store reward berhasil lewat POST tanpa method spoof (Tambah Reward)', function () {
    $admin = adminUser();

    $response = actingAs($admin)->post(route('admin.rewards.store'), [
        'name' => 'Free Cocktail',
        'category' => 'drink',
        'description' => 'Reward uji',
        'points_required' => 50,
        'stock' => 10,
    ]);

    $response->assertRedirect(route('admin.rewards.index'));
    $this->assertDatabaseHas('rewards', ['name' => 'Free Cocktail', 'points_required' => 50]);
});

test('POST /admin/rewards dengan _method=PUT ditolak (membuktikan akar bug)', function () {
    $admin = adminUser();

    $response = actingAs($admin)->post(route('admin.rewards.store'), [
        '_method' => 'PUT',
        'name' => 'X',
        'category' => 'drink',
        'points_required' => 10,
        'stock' => 5,
    ]);

    expect($response->status())->toBe(405);
});

test('update reward berhasil lewat PUT ke route dengan id', function () {
    $admin = adminUser();
    $reward = Reward::create([
        'name' => 'Voucher',
        'category' => 'voucher',
        'description' => 'awal',
        'points_required' => 100,
        'stock' => 5,
    ]);

    $response = actingAs($admin)->put(route('admin.rewards.update', $reward), [
        'name' => 'Voucher Update',
        'category' => 'voucher',
        'description' => 'diubah',
        'points_required' => 120,
        'stock' => 8,
    ]);

    $response->assertRedirect(route('admin.rewards.index'));
    $this->assertDatabaseHas('rewards', ['id' => $reward->id, 'name' => 'Voucher Update', 'points_required' => 120]);
});
