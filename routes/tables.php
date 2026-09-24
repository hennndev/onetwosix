<?php

use App\Http\Controllers\TableController;
use App\Http\Controllers\TableLayoutController;
use Illuminate\Support\Facades\Route;

Route::get('tables/layout', [TableLayoutController::class, 'edit'])->name('tables.layout');
Route::post('tables/layout/positions', [TableLayoutController::class, 'update'])->name('tables.layout.update');
Route::resource('tables', TableController::class)->except(['show', 'create', 'edit']);
Route::get('active-tables', [TableController::class, 'activeTables'])->name('active-tables.index');
Route::get('active-tables/readonly', [TableController::class, 'activeTablesReadonly'])->name('active-tables.readonly');
Route::patch('active-tables/{session}/pax', [TableController::class, 'updatePax'])->name('active-tables.updatePax');
Route::get('table-scanner', [TableController::class, 'scanner'])->name('table-scanner.index');
Route::post('table-scanner/scan', [TableController::class, 'scanQR'])->name('table-scanner.scan');
Route::post('table-scanner/generate-checkin-qr', [TableController::class, 'generateCheckInQR'])->name('table-scanner.generate-checkin-qr');
Route::post('table-scanner/process-checkin', [TableController::class, 'processCheckIn'])->name('table-scanner.process-checkin');
