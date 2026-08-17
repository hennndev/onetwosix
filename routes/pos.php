<?php

use App\Http\Controllers\PosController;
use illuminate\Support\Facades\Route;

Route::get('pos', [PosController::class, 'index'])->name('pos.index');
Route::post('pos/select-counter', [PosController::class, 'selectCounter'])->name('pos.select-counter');
Route::post('pos/{productId}/add-to-cart', [PosController::class, 'addToCart'])->name('pos.add-to-cart');
Route::post('pos/{productId}/update-cart', [PosController::class, 'updateCartQuantity'])->name('pos.update-cart');
Route::delete('pos/{productId}/remove-from-cart', [PosController::class, 'removeFromCart'])->name('pos.remove-from-cart');
Route::post('pos/clear-cart', [PosController::class, 'clearCart'])->name('pos.clear-cart');
Route::get('pos/preview-checkout-availability', [PosController::class, 'previewCheckoutAvailability'])->name('pos.preview-checkout-availability');
Route::post('pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
Route::get('pos/recent-orders', [PosController::class, 'recentOrders'])->name('pos.recent-orders');
Route::post('pos/assign-waiter/{booking}', [PosController::class, 'assignWaiterFromPos'])->name('pos.assign-waiter');

Route::get('pos/walk-in/search-customers', [PosController::class, 'walkInSearchCustomers'])->name('pos.walk-in.search-customers');
Route::post('pos/walk-in/create-customer', [PosController::class, 'walkInCreateCustomer'])->name('pos.walk-in.create-customer');

// Receipt preview
Route::get('pos/orders/{order}/receipt', [PosController::class, 'orderReceipt'])->name('pos.order-receipt');

// Printer integration
Route::post('pos/print-receipt/{order?}', [PosController::class, 'printReceipt'])->name('pos.print-receipt');
Route::post('pos/print-walk-in-draft-receipt', [PosController::class, 'printWalkInDraftReceipt'])->name('pos.print-walk-in-draft-receipt');
Route::post('pos/test-print', [PosController::class, 'testPrint'])->name('pos.test-print');
