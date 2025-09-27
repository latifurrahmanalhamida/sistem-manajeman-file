<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\File;
use App\Models\User;
use App\Models\ActivityLog; // <-- [DITAMBAHKAN] Sertakan model ActivityLog
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
public function index()
{
    // 1. Data untuk kartu metrik utama di bagian atas
    $summary = [
        'totalUsers' => User::count(),
        'totalDivisions' => Division::count(),
        'totalFiles' => File::count(),
        'storageUsed' => $this->formatBytes(File::sum('ukuran_file')),
    ];

    // 2. Data untuk grafik upload harian
    $uploadsData = File::select(
        DB::raw('DATE(created_at) as date'),
        DB::raw('count(*) as count')
    )
    ->where('created_at', '>=', now()->subDays(6))
    ->groupBy('date')
    ->orderBy('date', 'asc')
    ->get()
    ->keyBy('date');

    $dailyUploads = collect();
    for ($i = 6; $i >= 0; $i--) {
        $date = now()->subDays($i)->format('Y-m-d');
        $dailyUploads->push([
            'date' => $date,
            'count' => $uploadsData->get($date)->count ?? 0,
        ]);
    }

    // 3. Data Penggunaan Kuota per Divisi
    $quotaPerDivision = Division::all()->map(function ($division) {
        $usedBytes = $division->files()->sum('ukuran_file');
        $quotaBytes = $division->storage_quota ?? 0;

        return [
            'name' => $division->name,
            'used' => $this->formatBytes($usedBytes),
            'quota' => $this->formatBytes($quotaBytes),
            'percentage' => $quotaBytes > 0 ? round(($usedBytes / $quotaBytes) * 100, 2) : 0,
        ];
    });

    // 4. Data Aktivitas Sistem Terbaru
    $recentActivities = ActivityLog::with('user:id,name')
        ->latest()
        ->take(5)
        ->get()
        ->map(function ($log) {
            // [INILAH PERBAIKANNYA]
            // Kita tidak perlu lagi json_decode, karena $log->details sudah otomatis menjadi array
            // berkat properti $casts di model ActivityLog. Langsung saja kita akses.
            $info = $log->details['info'] ?? 'Detail tidak tersedia';

            return [
                'id' => $log->id,
                'actor' => $log->user->name ?? 'Sistem',
                'action' => $log->action,
                'details' => $info, // 'details' ini sekarang hanya berisi string 'info'
                'time' => $log->created_at->diffForHumans(),
            ];
        });

    // 5. Gabungkan semua data menjadi satu respons
    return response()->json([
        'summary' => $summary,
        'dailyUploads' => $dailyUploads,
        'quotaPerDivision' => $quotaPerDivision,
        'recentActivities' => $recentActivities,
    ]);
}


    private function formatBytes($bytes, $precision = 2)
    {
        if ($bytes == 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}