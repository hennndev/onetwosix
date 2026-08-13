<?php

use App\Http\Controllers\PromoController;
use Illuminate\Support\Facades\Route;

Route::resource('promos', PromoController::class)->except(['show', 'create', 'edit']);
Route::patch('promos/{promo}/toggle-status', [PromoController::class, 'toggleStatus'])->name('promos.toggleStatus');
