<?php

namespace App\Http\Controllers\Api; 

use App\Http\Controllers\Controller; 
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use App\Models\LoginHistory; 
use Carbon\Carbon; 

class SuperAdminController extends Controller
{
    /**
     * Mengambil semua divisi beserta folder-foldernya.
     */
    public function getDivisionsWithFolders()
    {
        $divisions = \App\Models\Division::with('folders')->get();
        return response()->json($divisions);
    }

    /**
     * Mengambil daftar log aktivitas dengan paginasi dan filter tanggal.
     */
    public function getActivityLogs(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        $query = ActivityLog::with('user:id,name')->latest();

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $logs = $query->paginate(15);
        return response()->json($logs);
    }
    
    /**
     * Mengambil daftar riwayat login dengan paginasi dan filter.
     */
    public function getLoginHistory(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        $query = LoginHistory::with('user:id,name')->latest();

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $history = $query->paginate(15);

        return response()->json($history);
    }

    /**
     * --- FUNGSI BARU UNTUK MEMBERSIHKAN RIWAYAT LOGIN ---
     */
    public function purgeLoginHistory(Request $request)
    {
        // Validasi input, memastikan 'range' ada dan nilainya sesuai
        $validated = $request->validate([
            'range' => 'required|string|in:1-month,6-months,1-year,all',
        ]);

        $range = $validated['range'];
        $query = LoginHistory::query();

        // Tentukan batas tanggal berdasarkan range yang dipilih
        if ($range === '1-month') {
            $query->where('created_at', '<', Carbon::now()->subMonth());
        } elseif ($range === '6-months') {
            $query->where('created_at', '<', Carbon::now()->subMonths(6));
        } elseif ($range === '1-year') {
            $query->where('created_at', '<', Carbon::now()->subYear());
        }

        $count = $query->count();
        
        // Lakukan penghapusan
        $query->delete();

        return response()->json([
            'message' => "Berhasil menghapus {$count} data riwayat login secara permanen."
        ]);
    }
     public function countLoginHistoryForPurge(Request $request)
    {
        $validated = $request->validate([
            'range' => 'required|string|in:1-month,6-months,1-year,all',
        ]);

        $range = $validated['range'];
        $query = LoginHistory::query();

        if ($range === '1-month') {
            $query->where('created_at', '<', Carbon::now()->subMonth());
        } elseif ($range === '6-months') {
            $query->where('created_at', '<', Carbon::now()->subMonths(6));
        } elseif ($range === '1-year') {
            $query->where('created_at', '<', Carbon::now()->subYear());
        }
        // Jika 'all', tidak ada kondisi where

        $count = $query->count();

        return response()->json(['count' => $count]);
    }
}