<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SystemEnumController;

/**
 * Admin Routes
 */
Route::prefix('admin')->group(function () {
    Route::prefix('user')->group(function () {
        Route::post('/login', [UserController::class, 'adminLogin']);
        Route::post('/register', [UserController::class, 'register']);
    });

    /**
     * Admin API Routes
     * Need Authentication
     */
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::prefix('user')->group(function () {
            Route::get('/profile', [UserController::class, 'adminProfile']);
            Route::get('/list', [UserController::class, 'adminUserList']);

            // Route::post('/generate-dummy', [UserController::class, 'adminGenerateDummyUsers']);
            Route::post('/logout', [UserController::class, 'AdminLogout']);
            Route::post('/change-password', [UserController::class, 'changePassword']);
        });

        Route::prefix('system-enums')->group(function () {
            Route::get('/', [SystemEnumController::class, 'fetchEnum']);
            Route::get('/list', [SystemEnumController::class, 'fetchEnumList']);

            Route::post('/', [SystemEnumController::class, 'createEnum']);
            // Route::post('/generate-dummy', [SystemEnumController::class, 'createDummyEnum']);
            
            Route::put('/{id}', [SystemEnumController::class, 'updateEnum']);
            Route::delete('/{id}', [SystemEnumController::class, 'softDeleteEnum']);
        });
        
    });
});

/**
 * Public API Routes (no authentication required)
 */
Route::post('/send-message', function (Request $request) {
    broadcast(new \App\Events\MessageSent($request->input('text')));
    return ['status' => 'Message Sent!'];
});

/**
 * Authenticated API Routes (requires auth:sanctum)
 */
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

