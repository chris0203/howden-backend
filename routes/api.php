<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\UserController;

/**
 * Admin Routes
 * Need Authentication
 */
Route::prefix('admin')->group(function () {
    Route::prefix('user')->group(function () {
        Route::post('/login', [UserController::class, 'adminLogin']);
        Route::post('/register', [UserController::class, 'register']);
    });
    /**
     * API Routes
     * Need Authentication
     */
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::prefix('user')->group(function () {
            Route::get('/profile', [UserController::class, 'adminProfile']);
            Route::post('/logout', [UserController::class, 'AdminLogout']);
            Route::post('/change-password', [UserController::class, 'changePassword']);
        });
        
    });
});
/**
 * API Routes
 * Need Authentication
 */
Route::middleware(['auth:sanctum'])->group(function () {

});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/send-message', function (Request $request) {
    broadcast(new \App\Events\MessageSent($request->input('text')));
    return ['status' => 'Message Sent!'];
});
