<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class SuperAdminController extends Controller
{
    /**
     * Mengambil daftar log aktivitas dengan paginasi dan filter tanggal.
     */
    public function getActivityLogs(Request $request)
    {
        // Validasi input tanggal (opsional tapi direkomendasikan)
        $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        // Mulai query
        $query = ActivityLog::with('user:id,name')->latest();

        // --- BAGIAN BARU UNTUK FILTER TANGGAL ---

        // Jika ada parameter 'start_date' di URL
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        // Jika ada parameter 'end_date' di URL
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // -----------------------------------------

        // Lakukan paginasi setelah semua filter diterapkan
        $logs = $query->paginate(15)->withQueryString();

        return response()->json($logs);
    }
}
