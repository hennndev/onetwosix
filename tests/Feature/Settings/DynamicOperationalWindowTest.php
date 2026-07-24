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
