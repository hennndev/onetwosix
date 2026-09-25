<?php

use App\Models\Order;

use function Pest\Laravel\actingAs;

function makePaginationPollOrder(int $createdById, int $sequence): Order
{
    return Order::create([
        'table_session_id' => null,
        'customer_user_id' => null,
        'created_by' => $createdById,
        'order_number' => 'TH-PAGE-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
        'status' => 'pending',
        'items_total' => 50000,
        'discount_amount' => 0,
        'total' => 50000,
        'ordered_at' => now()->subMinutes($sequence),
    ]);
}

test('realtime poll url keeps the viewed page so page 3 does not snap back to page 1', function () {
    $admin = adminUser();

    foreach (range(1, 25) as $sequence) {
        makePaginationPollOrder($admin->id, $sequence);
    }

    $response = actingAs($admin)
        ->get(route('admin.transaction-history.index', ['per_page' => 10, 'page' => 3]))
        ->assertSuccessful();

    $content = $response->getContent();

    expect($content)->toContain('transaction-history/refresh');
    // The poll must re-fetch the same page the user is viewing; without page=3
    // the tbody would be overwritten with page 1 rows on every tick.
    expect($content)->toMatch('#transaction-history/refresh\?[^"\']*page=3#');
});

test('refresh endpoint returns the requested page rather than defaulting to page 1', function () {
    $admin = adminUser();

    foreach (range(1, 25) as $sequence) {
        makePaginationPollOrder($admin->id, $sequence);
    }

    $firstPage = actingAs($admin)
        ->getJson(route('admin.transaction-history.refresh', ['per_page' => 10, 'page' => 1]))
        ->assertSuccessful()
        ->json('listHtml');

    $thirdPage = actingAs($admin)
        ->getJson(route('admin.transaction-history.refresh', ['per_page' => 10, 'page' => 3]))
        ->assertSuccessful()
        ->json('listHtml');

    // Page 1 shows the newest orders (TH-PAGE-0001..0010); page 3 shows the tail.
    expect($firstPage)->toContain('TH-PAGE-0001')
        ->and($firstPage)->not->toContain('TH-PAGE-0021')
        ->and($thirdPage)->toContain('TH-PAGE-0021')
        ->and($thirdPage)->not->toContain('TH-PAGE-0001');
});
