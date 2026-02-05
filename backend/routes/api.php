<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::post('/auth/otp/request', [AuthController::class, 'requestOtp']);
Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp']);
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
