<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\Admin\DivisionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\Admin\DashboardController; 
use App\Models\Role;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Rute Publik (Login)
Route::post('/login', [AuthController::class, 'login']);

// Grup rute yang memerlukan otentikasi (user harus sudah login)
Route::middleware('auth:sanctum')->group(function () {
    
    // Rute umum untuk user yang sudah login
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);


    // --- RUTE UNTUK MANAJEMEN FILE ---
    Route::get('/files', [FileController::class, 'index']);
    Route::post('/files', [FileController::class, 'store']);
    
    // RUTE BARU UNTUK SIDEBAR
    Route::get('/files/recent', [FileController::class, 'recent']);
    Route::get('/files/favorites', [FileController::class, 'favorites']);
    Route::get('/files/trashed', [FileController::class, 'trashed']);

    // Rute per file
    Route::prefix('files/{file}')->group(function() {
        Route::get('/', [FileController::class, 'download']);
        Route::delete('/', [FileController::class, 'destroy']); // Ini akan menjadi soft delete
        
        // RUTE BARU UNTUK AKSI
        Route::post('/favorite', [FileController::class, 'toggleFavorite']);
        Route::post('/restore', [FileController::class, 'restore']);
        Route::delete('/force', [FileController::class, 'forceDelete']);
    });

    
    // --- GRUP RUTE UNTUK ADMIN ---
    Route::prefix('admin')->group(function() {
        
        // Rute yang bisa diakses Super Admin & Admin Devisi
            Route::middleware('check.role:super_admin,admin_devisi')->group(function () {
            Route::get('/users', [UserController::class, 'index']);
            Route::post('/users', [UserController::class, 'store']);
        });

        // Rute yang HANYA bisa diakses Super Admin
            Route::middleware('check.role:super_admin')->group(function () {
            Route::apiResource('/divisions', DivisionController::class);
            Route::get('/dashboard-stats', [DashboardController::class, 'index']);
            Route::get('/roles', function() {return Role::all();
    });
        });
    });

});