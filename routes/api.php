<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CustomerKeepController;
use App\Http\Controllers\Api\DisplayMessageController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\FirebaseNotificationTestController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\MembershipController;
use App\Http\Controllers\Api\MusicSearchController;
use App\Http\Controllers\Api\PaymentInfoController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PromoController;
use App\Http\Controllers\Api\RewardController;
use App\Http\Controllers\Api\SongRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Mobile App (v1)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // === Auth (Public) ===
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // === Public ===
    Route::get('/events', [EventController::class, 'index']);
    Route::get('/events/{event}', [EventController::class, 'show']);
    Route::get('/promos', [PromoController::class, 'index']);
    Route::get('/promos/{promo}', [PromoController::class, 'show']);
    Route::get('/leaderboard', [LeaderboardController::class, 'index']);
    Route::get('/tiers', [MembershipController::class, 'index']);
    Route::get('/payment-info', [PaymentInfoController::class, 'index']);

    // === Authenticated ===
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/notifications/test', FirebaseNotificationTestController::class);

        // Profile
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::post('/profile', [ProfileController::class, 'update']);

        // Bookings
        Route::get('/bookings', [BookingController::class, 'index']);
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::get('/bookings/available-tables', [BookingController::class, 'availableTables']);
        Route::get('/bookings/{booking}', [BookingController::class, 'show']);
        Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);

        // Song Requests
        Route::get('/song-requests', [SongRequestController::class, 'index']);
        Route::post('/song-requests', [SongRequestController::class, 'store']);
        Route::get('/song-requests/{songRequest}', [SongRequestController::class, 'show']);

        // Music Search (YouTube Music)
        Route::get('/music/search', [MusicSearchController::class, 'search']);

        // Display Messages
        Route::get('/display-messages', [DisplayMessageController::class, 'index']);
        Route::post('/display-messages', [DisplayMessageController::class, 'store']);
        Route::get('/display-messages/{displayMessage}', [DisplayMessageController::class, 'show']);

        // My Bottles (Customer Keep)
        Route::get('/bottles', [CustomerKeepController::class, 'index']);
        Route::get('/bottles/{customerKeep}', [CustomerKeepController::class, 'show']);

        // Leaderboard (my rank)
        Route::get('/leaderboard/my-rank', [LeaderboardController::class, 'myRank']);

        // Rewards
        Route::get('/rewards', [RewardController::class, 'index']);
        Route::get('/rewards/my-redemptions', [RewardController::class, 'myRedemptions']);
        Route::post('/rewards/redeem', [RewardController::class, 'redeem']);
        Route::get('/rewards/{reward}', [RewardController::class, 'show']);

        // Membership / QR
        Route::get('/membership', [MembershipController::class, 'myMembership']);
        Route::get('/membership/qr', [MembershipController::class, 'qrCode']);
    });
});
