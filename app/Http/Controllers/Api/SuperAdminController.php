<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog; // <-- Impor model ActivityLog
use Illuminate\Http\Request;

class SuperAdminController extends Controller
{
    /**
     * Mengambil daftar log aktivitas dengan paginasi.
     */
    public function getActivityLogs(Request $request)
    {
        // Ambil data log, urutkan dari yang terbaru,
        // sertakan data 'user' yang berelasi, dan batasi 15 data per halaman.
        $logs = ActivityLog::with('user:id,name')
                            ->latest()
                            ->paginate(15);

        return response()->json($logs);
    }
}