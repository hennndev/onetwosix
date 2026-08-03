<?php

use App\Http\Controllers\SongRequestController;
use Illuminate\Support\Facades\Route;

Route::get('song-requests/search-api', [SongRequestController::class, 'searchApi'])->name('song-requests.searchApi');
Route::resource('song-requests', SongRequestController::class)->except(['show', 'create', 'edit']);
Route::patch('song-requests/{songRequest}/status', [SongRequestController::class, 'updateStatus'])->name('song-requests.updateStatus');
