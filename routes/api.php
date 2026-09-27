<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\ItemVariantController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Support\Facades\Route;

Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::put('/profile/password', [ProfileController::class, 'changePassword']);
    });

    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::put('/users/{user}', [UserController::class, 'update']);
    });

    Route::get('/rooms', [RoomController::class, 'index'])->name('api.rooms.index');

    Route::get('/items', [ItemController::class, 'index'])->name('api.items.index');
    Route::get('/items/{item}', [ItemController::class, 'show'])->name('api.items.show');
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::post('/items', [ItemController::class, 'store'])->name('api.items.store');
        Route::post('/items/{item}/variants', [ItemVariantController::class, 'store'])->name('api.items.variants.store');
        Route::put('/items/{item}/variant-comparison', [ItemController::class, 'updateVariantComparison'])->name('api.items.variant-comparison.update');
    });
});
