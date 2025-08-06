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

    Route::get('/files/preview/{path}', [FileController::class, 'preview'])->where('path', '.*');

    Route::get('/file-preview/{folder}/{filename}', function ($folder, $filename) {
    $path = storage_path("app/uploads/{$folder}/{$filename}");

    if (!file_exists($path)) {
        return response()->json(['message' => 'File not found'], 404);
    }

    return response()->file($path);
});



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


    // GANTIKAN DENGAN KODE INI
    // Route untuk mendapatkan file (bisa untuk pratinjau atau unduh)
    Route::get('/files/{file}', [FileController::class, 'download'])->middleware('auth:sanctum');

    // Route untuk menghapus file (soft delete)
    Route::delete('/files/{file}', [FileController::class, 'destroy'])->middleware('auth:sanctum');

    // Route untuk aksi file lainnya
    Route::post('/files/{file}/favorite', [FileController::class, 'toggleFavorite'])->middleware('auth:sanctum');
    Route::post('/files/{fileId}/restore', [FileController::class, 'restore'])->middleware('auth:sanctum');
    Route::delete('/files/{fileId}/force', [FileController::class, 'forceDelete'])->middleware('auth:sanctum');





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
