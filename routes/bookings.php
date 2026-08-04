<?php

use App\Http\Controllers\TableReservationController;
use Illuminate\Support\Facades\Route;

Route::resource('bookings', TableReservationController::class)->except(['show', 'create', 'edit']);
Route::patch('bookings/{booking}/status', [TableReservationController::class, 'updateStatus'])->name('bookings.updateStatus');
Route::post('bookings/{booking}/move-table', [TableReservationController::class, 'requestTableMove'])->name('bookings.moveTable');
Route::post('bookings/{booking}/move-order', [TableReservationController::class, 'moveOrder'])->name('bookings.moveOrder');
Route::post('bookings/{booking}/cancel-order', [TableReservationController::class, 'cancelOrder'])->name('bookings.cancelOrder');
Route::post('bookings/{booking}/delete-order-item', [TableReservationController::class, 'deleteOrderItem'])->name('bookings.deleteOrderItem');
Route::post('bookings/{booking}/print-running-receipt', [TableReservationController::class, 'printRunningReceipt'])->name('bookings.printRunningReceipt');
Route::post('bookings/{booking}/reprint-receipt', [TableReservationController::class, 'reprintReceipt'])->name('bookings.reprintReceipt');
Route::post('bookings/{booking}/re-sync-accurate', [TableReservationController::class, 'reSyncAccurate'])->name('bookings.reSyncAccurate');
Route::get('bookings/{booking}/discount-items', [TableReservationController::class, 'discountItems'])->name('bookings.discountItems');
Route::post('bookings/{booking}/close-billing', [TableReservationController::class, 'closeBilling'])->name('bookings.closeBilling');
Route::post('bookings/{booking}/settle-payment', [TableReservationController::class, 'settlePayment'])->name('bookings.settlePayment');
Route::patch('bookings/{booking}/history-payment', [TableReservationController::class, 'updateHistoryPayment'])->name('bookings.updateHistoryPayment');
Route::post('bookings/{booking}/assign-waiter', [TableReservationController::class, 'assignWaiter'])->name('bookings.assignWaiter');
Route::get('bookings/{booking}/receipt', [TableReservationController::class, 'receipt'])->name('bookings.receipt');
