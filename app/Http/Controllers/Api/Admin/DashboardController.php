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
        // 1. Ambil data summary cards
        $totalFiles = File::count();
        $totalUsers = User::count();
        $totalDivisions = Division::count();
        $storageUsedBytes = File::sum('ukuran_file'); // Dalam bytes

        // 2. Data untuk grafik upload harian (7 hari terakhir)
        // Ambil data dari DB dan kelompokkan berdasarkan tanggal
        $uploadsData = File::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('count(*) as count')
        )
        ->where('created_at', '>=', now()->subDays(6)) // 7 hari termasuk hari ini
        ->groupBy('date')
        ->orderBy('date', 'asc')
        ->get()
        ->keyBy('date'); // Jadikan tanggal sebagai key untuk pencarian mudah

        // Buat koleksi 7 tanggal terakhir untuk memastikan tidak ada hari yang terlewat
        $dailyUploads = collect();
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dailyUploads->push([
                'date' => $date,
                'count' => $uploadsData->get($date)->count ?? 0, // Ambil count, atau 0 jika tidak ada
            ]);
        }

        // 3. Data untuk grafik distribusi file per divisi
        $filesPerDivision = Division::withCount('files')
            ->get()
            ->map(fn ($division) => [
                'name' => $division->name,
                'count' => $division->files_count,
            ]);

        // 4. Data untuk tabel aktivitas terbaru (5 file terakhir)
        $recentUploads = File::with('uploader:id,name', 'division:id,name')
            ->latest()
            ->take(5)
            ->get();

        // Gabungkan semua data menjadi satu respons
        return response()->json([
            'totalFiles' => $totalFiles,
            'totalUsers' => $totalUsers,
            'totalDivisions' => $totalDivisions,
            'storageUsed' => $this->formatBytes($storageUsedBytes),
            'storageUsedBytes' => (int) $storageUsedBytes,
            'dailyUploads' => $dailyUploads,
            'filesPerDivision' => $filesPerDivision,
            'recentUploads' => $recentUploads,
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