<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::post('/auth/otp/request', [AuthController::class, 'requestOtp']);
Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('moderators', \App\Http\Controllers\ModeratorController::class);
    Route::apiResource('users', \App\Http\Controllers\UserController::class);
    Route::post('/users/{id}/toggle-accreditation', [\App\Http\Controllers\UserController::class, 'toggleAccreditation']);
    Route::apiResource('auctions', \App\Http\Controllers\AuctionController::class);

    // Auction participants
    Route::get('/auctions/{id}/participants', [\App\Http\Controllers\AuctionController::class, 'participants']);
    Route::post('/auctions/{id}/participants', [\App\Http\Controllers\AuctionController::class, 'syncParticipants']);
    Route::post('/auctions/{id}/send-invitations', [\App\Http\Controllers\AuctionController::class, 'sendInvitations']);
    Route::post('/auctions/{id}/transition-gpb', [\App\Http\Controllers\AuctionController::class, 'transitionToGpb']);

    // Initial offers
    Route::get('/auctions/{id}/initial-offers', [\App\Http\Controllers\InitialOfferController::class, 'index']);
    Route::post('/auctions/{id}/initial-offers', [\App\Http\Controllers\InitialOfferController::class, 'store']);

    // Bids (auction trading)
    Route::get('/auctions/{id}/bids', [\App\Http\Controllers\BidController::class, 'index']);

    // Organizations list (for participant selection)
    Route::get('/participants-list', [\App\Http\Controllers\AuctionController::class, 'participantsList']);
    
    // Dashboard
    Route::get('/dashboard/stats', [\App\Http\Controllers\DashboardController::class, 'stats']);

    // Activity logs (journal)
    Route::get('/activity-logs', [\App\Http\Controllers\ActivityLogController::class, 'index']);

    // Admin notifications
    Route::prefix('admin')->group(function () {
        Route::get('/notifications', [\App\Http\Controllers\AdminNotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [\App\Http\Controllers\AdminNotificationController::class, 'unreadCount']);
        Route::post('/notifications/{id}/read', [\App\Http\Controllers\AdminNotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [\App\Http\Controllers\AdminNotificationController::class, 'markAllAsRead']);
    });

    // Client auction cabinet
    Route::prefix('client')->group(function () {
        Route::get('/me', function (Request $request) {
            return response()->json($request->user());
        });
        Route::get('/auctions', [\App\Http\Controllers\ClientAuctionController::class, 'myAuctions']);
        Route::get('/auctions/{id}', [\App\Http\Controllers\ClientAuctionController::class, 'show']);
        Route::post('/auctions/{id}/offer', [\App\Http\Controllers\ClientAuctionController::class, 'submitOffer']);
        Route::post('/auctions/{id}/bid', [\App\Http\Controllers\ClientAuctionController::class, 'placeBid']);
        Route::get('/stats', [\App\Http\Controllers\ClientAuctionController::class, 'myStats']);
        Route::post('/avatar', [\App\Http\Controllers\ClientAuctionController::class, 'uploadAvatar']);

        // Notifications
        Route::get('/notifications', [\App\Http\Controllers\ClientNotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [\App\Http\Controllers\ClientNotificationController::class, 'unreadCount']);
        Route::post('/notifications/{id}/read', [\App\Http\Controllers\ClientNotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [\App\Http\Controllers\ClientNotificationController::class, 'markAllAsRead']);
    });
});
