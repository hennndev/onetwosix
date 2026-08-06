<?php

use App\Http\Controllers\TableController;
use App\Http\Controllers\TableReservationController;
use App\Http\Controllers\Waiter\WaiterController;
use App\Http\Controllers\Waiter\WaiterPosController;
use Illuminate\Support\Facades\Route;

Route::get('/', [WaiterController::class, 'index'])->name('index');
Route::get('/pos', [WaiterController::class, 'pos'])->name('pos');
Route::get('/pos/live', [WaiterController::class, 'posLive'])->name('pos.live');
Route::post('/pos/select-session', [WaiterPosController::class, 'selectSession'])->name('pos.select-session');
Route::post('/pos/{productId}/add-to-cart', [WaiterPosController::class, 'addToCart'])->name('pos.add-to-cart');
Route::post('/pos/{productId}/update-cart', [WaiterPosController::class, 'updateCart'])->name('pos.update-cart');
Route::delete('/pos/{productId}/remove-from-cart', [WaiterPosController::class, 'removeFromCart'])->name('pos.remove-from-cart');
Route::post('/pos/checkout', [WaiterPosController::class, 'checkout'])->name('pos.checkout');
Route::get('/active-tables', [WaiterController::class, 'activeTables'])->name('active-tables');
Route::patch('/active-tables/{session}/pax', [WaiterController::class, 'updatePax'])->name('active-tables.updatePax');
Route::get('/transactions', [WaiterController::class, 'transactions'])->name('transactions');
Route::get('/transaction-checker', [WaiterController::class, 'transactionChecker'])->name('transaction-checker');
Route::patch('/transaction-checker/items/{item}/check', [WaiterController::class, 'transactionCheckerCheckItem'])->name('transaction-checker.checkItem');
Route::patch('/transaction-checker/orders/{order}/check-all', [WaiterController::class, 'transactionCheckerCheckAll'])->name('transaction-checker.checkAll');
Route::get('/scanner', [WaiterController::class, 'scanner'])->name('scanner');
Route::post('/table-scanner/scan', [TableController::class, 'scanQR'])->name('table-scanner.scan');
Route::post('/table-scanner/generate-checkin-qr', [TableController::class, 'generateCheckInQR'])->name('table-scanner.generate-checkin-qr');
Route::post('/table-scanner/process-checkin', [TableController::class, 'processCheckIn'])->name('table-scanner.process-checkin');
Route::get('/bookings', [TableReservationController::class, 'index'])->name('bookings.index');
Route::get('/notifications', [WaiterController::class, 'notifications'])->name('notifications');
Route::get('/settings', [WaiterController::class, 'settings'])->name('settings');
Route::get('/display-messages', [WaiterController::class, 'displayMessages'])->name('display-messages.index');
Route::post('/display-messages', [WaiterController::class, 'storeDisplayMessage'])->name('display-messages.store');
