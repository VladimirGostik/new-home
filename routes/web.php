<?php

declare(strict_types=1);

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemVariantController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\NewPasswordController;
use App\Http\Controllers\PasswordResetLinkController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\TemporaryUploadController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/language/{locale}', [LanguageController::class, 'switch'])->name('language.switch');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');

    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
        Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
    });
});

// API docs — registered here (not via Scribe's add_routes) so that the web middleware group
// (sessions, cookies) is active. Without it, auth middleware cannot read the session.
Route::middleware(['auth', 'permission:view api docs'])->group(function (): void {
    Route::view('/docs', 'scribe.index')->name('scribe');

    Route::get('/docs.postman', function (): JsonResponse {
        return new JsonResponse(Storage::disk('local')->get('scribe/collection.json'), json: true);
    })->name('scribe.postman');

    Route::get('/docs.openapi', function () {
        return response()->file(Storage::disk('local')->path('scribe/openapi.yaml'));
    })->name('scribe.openapi');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'changePassword'])->name('profile.password');
    });

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/audit-logs/{activity}', [AuditLogController::class, 'show'])->name('audit-logs.show');

    Route::get('/media', [MediaController::class, 'index'])->name('media.index');
    Route::get('/media/{media}', [MediaController::class, 'show'])->name('media.show');

    Route::post('/uploads', [TemporaryUploadController::class, 'store'])->name('uploads.store');
    Route::delete('/uploads/{uuid}', [TemporaryUploadController::class, 'destroy'])->name('uploads.destroy');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/autocomplete', [UserController::class, 'autocomplete'])->name('users.autocomplete');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    });

    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    });

    Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
    Route::get('/rooms/create', [RoomController::class, 'create'])->name('rooms.create');
    Route::get('/rooms/{room}', [RoomController::class, 'show'])->name('rooms.show');
    Route::get('/rooms/{room}/edit', [RoomController::class, 'edit'])->name('rooms.edit');
    Route::put('/rooms/reorder', [RoomController::class, 'reorder'])->name('rooms.reorder');
    Route::delete('/rooms/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy');
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
        Route::put('/rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
    });

    Route::get('/items', [ItemController::class, 'index'])->name('items.index');
    Route::get('/items/create', [ItemController::class, 'create'])->name('items.create');
    Route::get('/my-items', [ItemController::class, 'mine'])->name('items.mine');
    Route::get('/items/{item}/edit', [ItemController::class, 'edit'])->name('items.edit');
    Route::get('/items/{item}', [ItemController::class, 'show'])->name('items.show');
    Route::delete('/items/{item}/variants/{variant}', [ItemVariantController::class, 'destroy'])->name('items.variants.destroy')->scopeBindings();
    Route::post('/items/{item}/variants/{variant}/select', [ItemVariantController::class, 'select'])->name('items.variants.select')->scopeBindings();
    Route::delete('/items/{item}/selected-variant', [ItemVariantController::class, 'unselect'])->name('items.variants.unselect');
    Route::post('/items/{item}/variants/{variant}/vote', [ItemVariantController::class, 'vote'])->name('items.variants.vote')->scopeBindings();
    Route::delete('/items/{item}/vote', [ItemVariantController::class, 'retractVote'])->name('items.vote.destroy');
    Route::patch('/items/{item}/status', [ItemController::class, 'toggleStatus'])->name('items.toggle-status');
    Route::delete('/items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::post('/items', [ItemController::class, 'store'])->name('items.store');
        Route::put('/items/{item}', [ItemController::class, 'update'])->name('items.update');
        Route::post('/items/{item}/variants', [ItemVariantController::class, 'store'])->name('items.variants.store');
        Route::put('/items/{item}/variants/{variant}', [ItemVariantController::class, 'update'])->name('items.variants.update')->scopeBindings();
    });
});
