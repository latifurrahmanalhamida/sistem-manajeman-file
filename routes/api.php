<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\Admin\DivisionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\BackupController;
use illuminate\Support\Facades\Routes;
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

    // --- DIKEMBALIKAN KE STRUKTUR LAMA YANG LEBIH STABIL ---
    Route::prefix('files/{fileId}')->group(function() {
    Route::put('/rename', [FileController::class, 'rename']);
    Route::get('/', [FileController::class, 'download']);
    Route::delete('/', [FileController::class, 'destroy']);
    Route::post('/favorite', [FileController::class, 'toggleFavorite']);
    Route::post('/restore', [FileController::class, 'restore']);
    Route::delete('/force', [FileController::class, 'forceDelete']);
    });

    Route::prefix('backup')->group(function () {
    Route::post('/backup', [BackupController::class, 'backupAll']);           // Full backup
    Route::post('/database', [BackupController::class, 'backupDatabase']); // Database only
    Route::post('/storage', [BackupController::class, 'backupStorage']);   // Files only
    Route::get('/list', [BackupController::class, 'listBackups']);         // List backups
    Route::delete('/delete/{filename}', [BackupController::class, 'deleteBackup']);
    Route::get('/download/{filename}', [BackupController::class, 'downloadBackup']); // Download

     Route::post('/users', [BackupController::class, 'backupUsersTable']);
    });

//     Route::post('/backup/{type}', function ($type, Request $request) {
//     try {
//         if ($type === 'database') {
//             Artisan::call('backup:run --only-db');
//         } elseif ($type === 'storage') {
//             Artisan::call('backup:run --only-files');
//         } else {
//             Artisan::call('backup:run');
//         }

//         return response()->json([
//             'message' => 'Backup ' . $type . ' berhasil!',
//             'output' => Artisan::output(),
//         ]);
//     } catch (\Exception $e) {
//         return response()->json([
//             'message' => 'Backup gagal!',
//             'error' => $e->getMessage(),
//         ], 500);
//     }
// });

//     Route::post('/backup/{type}', function ($type) {
//     try {
//         if ($type === 'database') {
//             Artisan::call('backup:run --only-db');
//         } elseif ($type === 'storage') {
//             Artisan::call('backup:run --only-files');
//         } else {
//             return response()->json(['message' => 'Jenis backup tidak dikenal'], 400);
//         }

//         return response()->json(['message' => 'Backup berhasil dijalankan']);
//     } catch (\Exception $e) {
//         return response()->json(['message' => 'Backup gagal: '.$e->getMessage()], 500);
//     }


// });

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
            Route::get('/folders/trashed', [FolderController::class, 'trashed']);
            Route::post('/folders/{id}/restore', [FolderController::class, 'restore']);
            Route::delete('/folders/{id}/force', [FolderController::class, 'forceDelete']);
             Route::apiResource('/folders', FolderController::class);
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
            Route::post('/activity-logs/delete-by-range', [SuperAdminController::class, 'deleteActivityLogsByRange']); // Rute baru
            Route::delete('/activity-logs', [SuperAdminController::class, 'purgeActivityLogs']);

            Route::get('/login-history', [SuperAdminController::class, 'getLoginHistory']);
            Route::delete('/login-history', [SuperAdminController::class, 'purgeLoginHistory']);
            Route::get('/login-history/count-purge', [SuperAdminController::class, 'countLoginHistoryForPurge']);
        });
    });

});
