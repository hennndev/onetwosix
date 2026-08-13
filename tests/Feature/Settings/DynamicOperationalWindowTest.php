<?php

use App\Models\RecapHistory;
use Illuminate\Support\Carbon;

test('resolveActiveWindow falls back to default anchor when no history exists', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-17 08:00:00', 'Asia/Jakarta'));

    [$start, $end] = RecapHistory::resolveActiveWindow();

    expect($start->toDateTimeString())->toBe('2026-07-16 09:00:00')
        ->and($end->toDateTimeString())->toBe('2026-07-17 08:59:59');

    Carbon::setTestNow(Carbon::parse('2026-07-17 10:00:00', 'Asia/Jakarta'));

    [$start, $end] = RecapHistory::resolveActiveWindow();

    expect($start->toDateTimeString())->toBe('2026-07-17 09:00:00')
        ->and($end->toDateTimeString())->toBe('2026-07-18 08:59:59');

    Carbon::setTestNow(); // Reset time mock
});

test('resolveActiveWindow starts at previous closing time when yesterday recap exists', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-17 04:30:00', 'Asia/Jakarta'));

    // Create a mock recap closed early at 04:00 AM on 2026-07-17 (marking 2026-07-16 closed)
    Illuminate\Support\Facades\DB::table('recap_history')->insert([
        'end_day' => '2026-07-16',
        'created_at' => '2026-07-17 04:00:00',
        'total_amount' => 1000,
        'total_transactions' => 1,
    ]);

    // Current time is 04:30 AM on 2026-07-17 (less than 9 AM)
    // Since 2026-07-16 is already closed, the active window should resolve to today (2026-07-17)
    // and start from yesterday's close (04:00 AM)
    [$start, $end] = RecapHistory::resolveActiveWindow();

    expect($start->toDateTimeString())->toBe('2026-07-17 04:00:00')
        ->and($end->toDateTimeString())->toBe('2026-07-18 08:59:59');

    Carbon::setTestNow();
});

test('resolveWindowForDate dynamically shifts start and end times based on history', function () {
    // 1. Setup previous day's recap history (2026-07-16 closed early at 05:00 AM on 2026-07-17)
    Illuminate\Support\Facades\DB::table('recap_history')->insert([
        'end_day' => '2026-07-16',
        'created_at' => '2026-07-17 05:00:00',
        'total_amount' => 1000,
        'total_transactions' => 1,
    ]);

    // 2. Setup current day's recap history (2026-07-17 closed early at 04:30 AM on 2026-07-18)
    Illuminate\Support\Facades\DB::table('recap_history')->insert([
        'end_day' => '2026-07-17',
        'created_at' => '2026-07-18 04:30:00',
        'total_amount' => 2000,
        'total_transactions' => 2,
    ]);

    // 3. Verify target date 2026-07-17
    [$start, $end] = RecapHistory::resolveWindowForDate(Carbon::parse('2026-07-17', 'Asia/Jakarta'));

    expect($start->toDateTimeString())->toBe('2026-07-17 05:00:00')
        ->and($end->toDateTimeString())->toBe('2026-07-18 04:30:00');
});

test('resolveActiveWindow extends end time when outlet reopens after multi-day holiday', function () {
    // Scenario: outlet closed at 5 AM Monday (end_day = Sunday), then holiday Tue-Thu
    Illuminate\Support\Facades\DB::table('recap_history')->insert([
        'end_day' => '2026-07-13', // Sunday
        'created_at' => '2026-07-14 05:00:00', // Monday 5 AM
        'total_amount' => 5000,
        'total_transactions' => 10,
    ]);

    // Friday 10:00 AM — outlet reopens after 3-day holiday
    Carbon::setTestNow(Carbon::parse('2026-07-18 10:00:00', 'Asia/Jakarta'));

    [$start, $end] = RecapHistory::resolveActiveWindow();

    // Start should still be Monday 05:00 (when the last recap was closed)
    expect($start->toDateTimeString())->toBe('2026-07-14 05:00:00');

    // End should extend to cover today (Friday), NOT be stuck at Tuesday 08:59:59
    // Since now (10:00) is past 09:00 anchor, end = Saturday 08:59:59
    expect($end->toDateTimeString())->toBe('2026-07-19 08:59:59');

    // Verify: before 9 AM on the reopening day
    Carbon::setTestNow(Carbon::parse('2026-07-18 07:00:00', 'Asia/Jakarta'));

    [$start2, $end2] = RecapHistory::resolveActiveWindow();

    expect($start2->toDateTimeString())->toBe('2026-07-14 05:00:00')
        ->and($end2->toDateTimeString())->toBe('2026-07-18 08:59:59');

    Carbon::setTestNow();
});

test('resolveNextEndDay resolves sequentially based on previous history even if closed late', function () {
    // 1. When no history exists, falls back to time-based fallback
    Carbon::setTestNow(Carbon::parse('2026-07-17 08:00:00', 'Asia/Jakarta'));
    expect(RecapHistory::resolveNextEndDay())->toBe('2026-07-16');

    Carbon::setTestNow(Carbon::parse('2026-07-17 10:00:00', 'Asia/Jakarta'));
    expect(RecapHistory::resolveNextEndDay())->toBe('2026-07-17');

    // 2. When history exists, even if we close late (e.g. at 10:00 AM on Tuesday 2026-07-18 for the Monday 2026-07-17 business day)
    // Mock last closed recap is Sunday 2026-07-12
    Illuminate\Support\Facades\DB::table('recap_history')->insert([
        'end_day' => '2026-07-12',
        'created_at' => '2026-07-13 05:00:00',
        'total_amount' => 1000,
        'total_transactions' => 1,
    ]);

    // Now it is Tuesday 2026-07-14 10:00:00 (admin woke up late to close Monday 2026-07-13)
    Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00', 'Asia/Jakarta'));

    // Even though hour is 10 (> 9), it should sequential-resolve to the next day: Sunday (2026-07-12) + 1 day = Monday (2026-07-13)
    expect(RecapHistory::resolveNextEndDay())->toBe('2026-07-13');

    Carbon::setTestNow();
});

test('resolveNextEndDay stays anchored after early-morning close; kitchen/bar must resolve to today', function () {
    // Close T (2026-07-16) at 5 AM on 2026-07-17
    Illuminate\Support\Facades\DB::table('recap_history')->insert([
        'end_day' => '2026-07-16',
        'created_at' => '2026-07-17 05:00:00',
        'total_amount' => 1000,
        'total_transactions' => 1,
    ]);

    // At 7 AM, resolveNextEndDay still clamps to yesterday (already closed) — this is the
    // dead-zone bug source. The window for that (stale) endDay excludes the 7 AM order.
    Carbon::setTestNow(Carbon::parse('2026-07-17 07:00:00', 'Asia/Jakarta'));

    expect(RecapHistory::resolveNextEndDay())->toBe('2026-07-16');

    // resolveWindowForDate(yesterday) window ends at the close time (05:00) — 7 AM order falls out
    [$start, $end] = RecapHistory::resolveWindowForDate(Carbon::parse('2026-07-16', 'Asia/Jakarta'));
    $sevenAmOrder = Carbon::parse('2026-07-17 07:00:00', 'Asia/Jakarta');
    expect($start->toDateTimeString())->toBe('2026-07-16 09:00:00')
        ->and($end->toDateTimeString())->toBe('2026-07-17 05:00:00')
        ->and($sevenAmOrder->between($start, $end))->toBeFalse();

    // But the today window (start = close time 05:00) DOES include the 7 AM order.
    [$todayStart, $todayEnd] = RecapHistory::resolveWindowForDate(Carbon::parse('2026-07-17', 'Asia/Jakarta'));
    expect($todayStart->toDateTimeString())->toBe('2026-07-17 05:00:00')
        ->and($sevenAmOrder->between($todayStart, $todayEnd))->toBeTrue();

    Carbon::setTestNow();
});

test('kitchen/bar resolveEndDayRange resolves to today after early-morning close', function () {
    // Close T (2026-07-16) at 5 AM on 2026-07-17
    Illuminate\Support\Facades\DB::table('recap_history')->insert([
        'end_day' => '2026-07-16',
        'created_at' => '2026-07-17 05:00:00',
        'total_amount' => 1000,
        'total_transactions' => 1,
    ]);

    // 7 AM — kitchen/bar endDay must be today (T+1), not the clamped yesterday.
    Carbon::setTestNow(Carbon::parse('2026-07-17 07:00:00', 'Asia/Jakarta'));

    expect(RecapHistory::resolveNextEndDayForEarlyClose())->toBe('2026-07-17');

    Carbon::setTestNow();
});

test('resolveEndDayWindowForToday collapses holiday gap to active day with extended window', function () {
    // Last close before a multi-day holiday: end_day 2026-07-13 (Sunday), closed 05:00 Monday
    Illuminate\Support\Facades\DB::table('recap_history')->insert([
        'end_day' => '2026-07-13',
        'created_at' => '2026-07-14 05:00:00',
        'total_amount' => 1000,
        'total_transactions' => 1,
    ]);

    // Reopen Friday 2026-07-18 10:00 (after Mon-Thu holiday)
    Carbon::setTestNow(Carbon::parse('2026-07-18 10:00:00', 'Asia/Jakarta'));

    [$endDay, $startAt, $endAt] = RecapHistory::resolveEndDayWindowForToday();

    expect($endDay)->toBe('2026-07-18')
        ->and($startAt->toDateTimeString())->toBe('2026-07-14 05:00:00')
        ->and($endAt->toDateTimeString())->toBe('2026-07-19 08:59:59');

    $order = Carbon::parse('2026-07-18 10:30:00', 'Asia/Jakarta');
    expect($order->between($startAt, $endAt))->toBeTrue();

    Carbon::setTestNow();
});

test('resolveEndDayWindowForToday keeps normal daily behavior (no gap)', function () {
    // Close T (2026-07-16) at 5 AM on 2026-07-17 — no gap
    Illuminate\Support\Facades\DB::table('recap_history')->insert([
        'end_day' => '2026-07-16',
        'created_at' => '2026-07-17 05:00:00',
        'total_amount' => 1000,
        'total_transactions' => 1,
    ]);

    // 7 AM — early-close fix preserved: endDay = today, start = close time
    Carbon::setTestNow(Carbon::parse('2026-07-17 07:00:00', 'Asia/Jakarta'));

    [$endDay, $startAt, $endAt] = RecapHistory::resolveEndDayWindowForToday();

    expect($endDay)->toBe('2026-07-17')
        ->and($startAt->toDateTimeString())->toBe('2026-07-17 05:00:00');

    Carbon::setTestNow();
});

test('resolveEndDayWindowForToday with no main recap falls back to existing behavior', function () {
    // No main recap — kitchen/bar resolves normally
    Carbon::setTestNow(Carbon::parse('2026-07-17 16:00:00', 'Asia/Jakarta'));

    [$endDay, $startAt, $endAt] = RecapHistory::resolveEndDayWindowForToday();

    expect($endDay)->toBe('2026-07-17')
        ->and($startAt->toDateTimeString())->toBe('2026-07-17 09:00:00')
        ->and($endAt->toDateTimeString())->toBe('2026-07-18 08:59:59');

    Carbon::setTestNow();
});

test('resolveWindowForDate prefers stored opened_at over previous created_at', function () {
    // 2026-07-16 opened at anchor 09:00, closed at 05:00 on 07-17 → window 07-17 opened at 05:00
    Illuminate\Support\Facades\DB::table('recap_history')->insert([
        'end_day' => '2026-07-16',
        'opened_at' => '2026-07-16 09:00:00',
        'created_at' => '2026-07-17 05:00:00',
        'total_amount' => 1000,
        'total_transactions' => 1,
    ]);

    // Window 07-17 (no current recap yet) → start = stored opened_at (05:00), bukan fallback prev created_at yang sama
    [$start, $end] = RecapHistory::resolveWindowForDate(Carbon::parse('2026-07-17', 'Asia/Jakarta'));

    expect($start->toDateTimeString())->toBe('2026-07-17 05:00:00');

    // Window 07-16 (current recap exists) → start = current row's own opened_at (09:00), end = its created_at (05:00 07-17)
    [$dayStart, $dayEnd] = RecapHistory::resolveWindowForDate(Carbon::parse('2026-07-16', 'Asia/Jakarta'));

    expect($dayStart->toDateTimeString())->toBe('2026-07-16 09:00:00')
        ->and($dayEnd->toDateTimeString())->toBe('2026-07-17 05:00:00');
});

test('closeDay stores opened_at as previous recap close time', function () {
    // Seed T (2026-07-16) closed normally on 2026-07-16
    Illuminate\Support\Facades\DB::table('recap_history')->insert([
        'end_day' => '2026-07-16',
        'opened_at' => '2026-07-16 09:00:00',
        'created_at' => '2026-07-16 22:00:00',
        'total_amount' => 1000,
        'total_transactions' => 1,
    ]);

    App\Models\Dashboard::query()->updateOrCreate(['id' => 1], [
        'total_amount' => 2000,
        'total_transactions' => 2,
    ]);

    // Close 07-17 at 23:00 (past anchor 09:00) → new window 07-17, opened_at = prev close 07-16 22:00
    Carbon::setTestNow(Carbon::parse('2026-07-17 23:00:00', 'Asia/Jakarta'));

    $result = (new App\Services\RecapClosingService)->closeDay();

    expect($result['status'])->toBe('closed')
        ->and($result['recap_history']->opened_at?->toDateTimeString())->toBe('2026-07-16 22:00:00');

    Carbon::setTestNow();
});

test('closeDay first recap uses operational anchor as opened_at', function () {
    App\Models\Dashboard::query()->updateOrCreate(['id' => 1], [
        'total_amount' => 3000,
        'total_transactions' => 3,
    ]);

    Carbon::setTestNow(Carbon::parse('2026-07-17 10:00:00', 'Asia/Jakarta'));

    $result = (new App\Services\RecapClosingService)->closeDay();

    expect($result['status'])->toBe('closed')
        ->and($result['recap_history']->opened_at?->toDateTimeString())->toBe('2026-07-17 09:00:00');

    Carbon::setTestNow();
});

test('operational anchor respects configured GeneralSetting time instead of hardcoded 09:00', function () {
    \App\Models\GeneralSetting::instance()->update(['operational_anchor_time' => '10:00']);

    // Before 10:00 anchor => still previous operational day
    Carbon::setTestNow(Carbon::parse('2026-07-17 09:30:00', 'Asia/Jakarta'));
    expect(RecapHistory::resolveNextEndDay())->toBe('2026-07-16');

    // After 10:00 anchor => today
    Carbon::setTestNow(Carbon::parse('2026-07-17 10:30:00', 'Asia/Jakarta'));
    expect(RecapHistory::resolveNextEndDay())->toBe('2026-07-17');

    // resolveActiveWindow fallback shifts to 10:00
    Carbon::setTestNow(Carbon::parse('2026-07-17 09:30:00', 'Asia/Jakarta'));
    [$start, $end] = RecapHistory::resolveActiveWindow();
    expect($start->toDateTimeString())->toBe('2026-07-16 10:00:00')
        ->and($end->toDateTimeString())->toBe('2026-07-17 09:59:59');

    // resolveWindowForDate fallback uses 10:00
    [$start2, $end2] = RecapHistory::resolveWindowForDate(Carbon::parse('2026-07-17', 'Asia/Jakarta'));
    expect($start2->toDateTimeString())->toBe('2026-07-17 10:00:00')
        ->and($end2->toDateTimeString())->toBe('2026-07-18 09:59:59');

    \App\Models\GeneralSetting::instance()->update(['operational_anchor_time' => '09:00']);

    Carbon::setTestNow();
});
