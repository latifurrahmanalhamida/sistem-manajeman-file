<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\BackupScheduleController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\Admin\DivisionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\Admin\DashboardController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use App\Models\Role;
use App\Http\Controllers\Api\SuperAdminController;
use App\Http\Controllers\Api\Admin\FolderController;

// Rute Publik
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// Grup Rute Terotentikasi
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // --- RUTE MANAJEMEN FILE ---
    Route::get('/files', [FileController::class, 'index']);
    Route::post('/files', [FileController::class, 'store']);
    Route::get('/files/recent', [FileController::class, 'recent']);
    Route::get('/files/favorites', [FileController::class, 'favorites']);
    Route::get('/files/trashed', [FileController::class, 'trashed']);
    Route::get('/files/all', [FileController::class, 'allFiles']);

    Route::prefix('files/{fileId}')->group(function () {
        Route::put('/rename', [FileController::class, 'rename']);
        Route::get('/', [FileController::class, 'download']);
        Route::delete('/', [FileController::class, 'destroy']);
        Route::post('/favorite', [FileController::class, 'toggleFavorite']);
        Route::post('/restore', [FileController::class, 'restore']);
        Route::delete('/force', [FileController::class, 'forceDelete']);
    });


Route::prefix('backups')->group(function () {
    // GET /api/backups -> Menampilkan semua backup
    Route::get('/', [BackupController::class, 'index']);
    
    // POST /api/backups/run -> Menjalankan backup manual
    Route::post('/run', [BackupController::class, 'run']);
    
    // Rute untuk settings
    Route::get('/settings', [BackupController::class, 'getSettings']);
    Route::post('/settings', [BackupController::class, 'updateSettings']);
    
    // Rute untuk schedule
    Route::get('/schedule', [BackupController::class, 'getSchedule']);
    Route::post('/schedule', [BackupController::class, 'updateSchedule']);
    
    // GET /api/backups/{backup}/download -> Download backup spesifik
    Route::get('/{backup}/download', [BackupController::class, 'download']);
    
    // DELETE /api/backups/{backup} -> Hapus backup spesifik
    Route::delete('/{backup}', [BackupController::class, 'destroy']);      
});

    // --- GRUP RUTE ADMIN (/api/admin/...) ---
    Route::prefix('admin')->group(function () {

        // Rute yang bisa diakses Super Admin & Admin Devisi
        Route::middleware('check.role:super_admin,admin_devisi')->group(function () {
            Route::get('/users', [UserController::class, 'index']);
            Route::post('/users', [UserController::class, 'store']);
            Route::get('/users/trashed', [UserController::class, 'trashed']);
            Route::get('/users/{user}', [UserController::class, 'show']);
            Route::put('/users/{user}', [UserController::class, 'update']);
            Route::delete('/users/{user}', [UserController::class, 'destroy']);
            Route::put('/users/{user}/restore', [UserController::class, 'restore']);
            Route::delete('/users/{user}/force-delete', [UserController::class, 'forceDelete']);

            Route::get('/folders/trashed', [FolderController::class, 'trashed']);
            Route::post('/folders/{id}/restore', [FolderController::class, 'restore']);
            Route::delete('/folders/{id}/force', [FolderController::class, 'forceDelete']);
            Route::apiResource('/folders', FolderController::class);

            // Route untuk admin devisi melihat log aktivitas divisinya
            Route::get('/activity-logs/division', [FolderController::class, 'getDivisionLogs']);
        });

        // Rute yang HANYA bisa diakses Super Admin
        Route::middleware('check.role:super_admin')->group(function () {
            Route::put('/divisions/{division}/quota', [DivisionController::class, 'updateQuota']);
            Route::get('/divisions-with-stats', [SuperAdminController::class, 'getDivisionsWithStats']);
            Route::apiResource('/divisions', DivisionController::class);
            Route::get('/dashboard-stats', [DashboardController::class, 'index']);
            Route::get('/divisions-with-folders', [SuperAdminController::class, 'getDivisionsWithFolders']);
            Route::get('/roles', fn() => Role::all());
            Route::get('/activity-logs', [SuperAdminController::class, 'getActivityLogs']);
            Route::post('/activity-logs/delete-by-range', [SuperAdminController::class, 'deleteActivityLogsByRange']);
            Route::delete('/activity-logs', [SuperAdminController::class, 'purgeActivityLogs']);

            Route::get('/login-history', [SuperAdminController::class, 'getLoginHistory']);
            Route::delete('/login-history', [SuperAdminController::class, 'purgeLoginHistory']);
            Route::get('/login-history/count-purge', [SuperAdminController::class, 'countLoginHistoryForPurge']);
        });
    });

});
