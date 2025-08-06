<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\Admin\DivisionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Models\Role;

// Rute Publik
Route::post('/login', [AuthController::class, 'login']);

// Grup Rute Terotentikasi
Route::middleware('auth:sanctum')->group(function () {
    
    // Rute User Umum
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Rute Manajemen File
    Route::get('/files', [FileController::class, 'index']);
    Route::post('/files', [FileController::class, 'store']);
    Route::get('/files/recent', [FileController::class, 'recent']);
    Route::get('/files/favorites', [FileController::class, 'favorites']);
    Route::get('/files/trashed', [FileController::class, 'trashed']);

    // Rute Aksi per File
    Route::prefix('files/{file}')->group(function() {
        Route::get('/', [FileController::class, 'download']);
        Route::delete('/', [FileController::class, 'destroy']);
        Route::post('/favorite', [FileController::class, 'toggleFavorite']);
        Route::post('/restore', [FileController::class, 'restore']);
        Route::delete('/force', [FileController::class, 'forceDelete']);
    });
    
    // --- GRUP RUTE ADMIN (/api/admin/...) ---
    Route::prefix('admin')->group(function() {

        // Rute yang bisa diakses Super Admin & Admin Devisi
        Route::middleware('check.role:super_admin,admin_devisi')->group(function () {
            Route::get('/users', [UserController::class, 'index']);
            Route::post('/users', [UserController::class, 'store']);
            Route::get('/users/trashed', [UserController::class, 'trashed']);
            Route::get('/users/{user}', [UserController::class, 'show'])->withTrashed();
            Route::put('/users/{user}', [UserController::class, 'update'])->withTrashed();
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->withTrashed();
            Route::put('/users/{user}/restore', [UserController::class, 'restore'])->withTrashed();
            Route::delete('/users/{user}/force-delete', [UserController::class, 'forceDelete'])->withTrashed();
        });

        // Rute yang HANYA bisa diakses Super Admin
        Route::middleware('check.role:super_admin')->group(function () {
            Route::apiResource('/divisions', DivisionController::class);
            Route::get('/dashboard-stats', [DashboardController::class, 'index']);
            Route::get('/roles', fn() => Role::all());
        });
    });
});
