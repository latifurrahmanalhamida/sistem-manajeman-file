<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\File;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
public function index()
{
    // 1. Ambil data summary cards (Kode Anda yang sudah ada)
    $totalFiles = File::count();
    $totalUsers = User::count();
    $totalDivisions = Division::count();
    $storageUsedBytes = File::sum('ukuran_file');

    // --- BAGIAN BARU: MENGAMBIL STATISTIK SERVER ---
    // a. Statistik Disk / Penyimpanan Server
    $diskTotalSpace = disk_total_space("/"); // Total space in bytes
    $diskFreeSpace = disk_free_space("/");   // Free space in bytes
    $diskUsedSpace = $diskTotalSpace - $diskFreeSpace;
    $diskUsedPercentage = ($diskTotalSpace > 0) ? ($diskUsedSpace / $diskTotalSpace) * 100 : 0;

    // b. Statistik RAM & CPU (Perintah ini umumnya untuk server Linux)
    // PENTING: shell_exec() bisa dinonaktifkan oleh hosting karena alasan keamanan.
    // Pastikan ini diizinkan di server kantor Anda.
    $ramUsagePercentage = shell_exec("free | grep Mem | awk '{print $3/$2 * 100.0}'");
    $cpuUsagePercentage = shell_exec("top -bn1 | grep 'Cpu(s)' | sed 's/.*, *\\([0-9.]*\\)%* id.*/\\1/' | awk '{print 100 - $1}'");
    // --------------------------------------------------

    // 2. Data untuk grafik upload harian (Kode Anda yang sudah ada)
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

    // 3. Data untuk grafik distribusi file per divisi (Kode Anda yang sudah ada)
    $filesPerDivision = Division::withCount('files')
        ->get()
        ->map(fn ($division) => [
            'name' => $division->name,
            'count' => $division->files_count,
        ]);

    // 4. Data untuk tabel aktivitas terbaru (Kode Anda yang sudah ada)
    $recentUploads = File::with('uploader:id,name', 'division:id,name')
        ->latest()
        ->take(5)
        ->get();

    // Gabungkan semua data menjadi satu respons, TERMASUK DATA SERVER
    return response()->json([
        // Data Aplikasi
        'totalFiles' => $totalFiles,
        'totalUsers' => $totalUsers,
        'totalDivisions' => $totalDivisions,
        'storageUsed' => $this->formatBytes($storageUsedBytes),
        'storageUsedBytes' => (int) $storageUsedBytes,
        'dailyUploads' => $dailyUploads,
        'filesPerDivision' => $filesPerDivision,
        'recentUploads' => $recentUploads,

        // Data Server Baru
        'disk' => [
            'total' => $this->formatBytes($diskTotalSpace),
            'free' => $this->formatBytes($diskFreeSpace),
            'used' => $this->formatBytes($diskUsedSpace),
            'percentage' => round($diskUsedPercentage, 2)
        ],
        'ram' => [
            'percentage' => round((float) $ramUsagePercentage, 2)
        ],
        'cpu' => [
            'percentage' => round((float) $cpuUsagePercentage, 2)
        ],
    ]);
}

    /**
     * Mengubah ukuran byte menjadi format yang mudah dibaca (KB, MB, GB, ...).
     */
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