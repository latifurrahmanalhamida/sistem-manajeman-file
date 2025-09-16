<?php

namespace App\Http\Controllers\Api; 

use App\Http\Controllers\Controller; 
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use App\Models\LoginHistory; 
use Carbon\Carbon; 
use Illuminate\Support\Facades\Log;

class SuperAdminController extends Controller
{
    public function getDivisionsWithStats()
    {
        // Ambil semua divisi dari database
        $divisions = \App\Models\Division::query()
            // Hitung jumlah relasi 'folders' dan simpan sebagai 'folders_count'
            ->withCount('folders')
            // Jumlahkan kolom 'ukuran_file' dari relasi 'files' dan simpan sebagai 'files_sum_ukuran_file'
            ->withSum('files', 'ukuran_file')
            // Urutkan berdasarkan nama divisi
            ->orderBy('name')
            ->get();

        // Ubah nama properti agar lebih rapi saat dikirim sebagai JSON
        $divisions->transform(function ($division) {
            $division->total_storage = $division->files_sum_ukuran_file ?? 0;
            unset($division->files_sum_ukuran_file); // Hapus properti lama
            return $division;
        });

        return response()->json($divisions);
    }
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
public function deleteActivityLogsByRange(Request $request)
{
    $validated = $request->validate([
        'range' => 'required|string',
    ]);

    $range = $validated['range'];
    $query = ActivityLog::query();

    // Normalisasi format singkat -> panjang
    $map = [
        '1d' => '1_day',
        '3d' => '3_days',
        '1w' => '1_week',
        '1m' => '1_month',
        '1y' => '1_year',
    ];
    if (isset($map[$range])) {
        $range = $map[$range];
    }

    // Tentukan filter berdasarkan range
    if ($range === '1_day') {
        $query->where('created_at', '<', Carbon::now()->subDay());
    } elseif ($range === '3_days') {
        $query->where('created_at', '<', Carbon::now()->subDays(3));
    } elseif ($range === '1_week') {
        $query->where('created_at', '<', Carbon::now()->subWeek());
    } elseif ($range === '1_month') {
        $query->where('created_at', '<', Carbon::now()->subMonth());
    } elseif ($range === '1_year') {
        $query->where('created_at', '<', Carbon::now()->subYear());
    } elseif ($range !== 'all') {
        return response()->json(['message' => 'Rentang waktu tidak valid.'], 400);
    }

    try {
        $count = $query->count();
        $query->delete();

        Log::info("Successfully deleted {$count} activity logs.", ['range' => $range]);

        return response()->json([
            'message' => "Berhasil menghapus {$count} data log aktivitas."
        ]);
    } catch (\Exception $e) {
        Log::error('Error deleting activity logs: ' . $e->getMessage());
        return response()->json(['message' => 'Gagal menghapus log aktivitas karena kesalahan server.'], 500);
    }
}


}