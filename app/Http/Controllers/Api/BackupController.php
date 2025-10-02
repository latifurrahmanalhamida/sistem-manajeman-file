<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BackupSetting;
use App\Models\BackupSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\DbDumper\Databases\MySql;
use Illuminate\Support\Facades\Log;
use App\Models\Backup;
use ZipArchive;


class BackupController extends Controller
{
    public function getSchedule(): JsonResponse
    {
        $schedule = BackupSchedule::first();
        return response()->json([
            'status' => 'success',
            'schedule' => $schedule
        ]);
    }

    public function updateSchedule(Request $request): JsonResponse
    {
        $request->validate([
            'frequency'   => 'required|string|in:off,daily,weekly,monthly,yearly',
            'time'        => 'nullable|string',
            'day_of_week' => 'nullable|string',
            'day_of_month'=> 'nullable|integer|min:1|max:31',
            'month'       => 'nullable|integer|min:1|max:12',
        ]);

        $data = $request->only([
            'frequency', 'time', 'day_of_week', 'day_of_month', 'month'
        ]);

        $schedule = BackupSchedule::first();
        if ($schedule) {
            $schedule->update($data);
        } else {
            $schedule = BackupSchedule::create($data);
        }

        return response()->json([
            'message' => 'Schedule updated successfully',
            'schedule' => $schedule
        ]);
    }

    // Ambil path backup yang sedang aktif
    public function getSettings(): JsonResponse
    {
        $setting = BackupSetting::first();
        return response()->json([
            'status' => 'success',
            'backup_path' => $setting ? $setting->backup_path : storage_path('app/backups')
        ]);
    }

    // Update path dari frontend
    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'backup_path' => 'required|string'
        ]);

        $path = str_replace('"', '', $request->backup_path); // bersihkan tanda kutip

        $setting = BackupSetting::first();

        if ($setting) {
            $setting->update(['backup_path' => $path]);
        } else {
            $setting = BackupSetting::create(['backup_path' => $path]);
        }

        // Set disk backup_disk root ke folder user
        config()->set('filesystems.disks.backup_disk.root', $path);

        return response()->json([
            'message' => 'Backup path updated successfully',
            'backup_path' => $setting->backup_path
        ]);
    }



public function run(Request $request)
{
    // ambil path target dari DB (dinamis dari frontend)
    $setting = BackupSetting::first();
    $backupPath = $setting ? trim($setting->backup_path, "\" \t\n\r\0\x0B") : storage_path('app/backups');

    if (!file_exists($backupPath)) {
        if (!mkdir($backupPath, 0755, true) && !is_dir($backupPath)) {
            return response()->json(['message' => 'Gagal membuat folder backup: ' . $backupPath], 500);
        }
    }

    // Siapkan nama file zip dan temp file SQL
    $timestamp = date('Ymd_His');
    $zipFilename = 'backup_' . $timestamp . '.zip';
    $zipFilePath = rtrim($backupPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $zipFilename;
    $dbDumpFile  = storage_path('app/db-backup-' . $timestamp . '.sql');

    // --- 1) Ambil konfigurasi DB secara aman ---
    $connection = config('database.default');
    $dbConfig = config("database.connections.{$connection}");

    if (empty($dbConfig['database']) || empty($dbConfig['username'])) {
        return response()->json(['message' => 'Konfigurasi database tidak lengkap. Periksa DB_DATABASE & DB_USERNAME di .env'], 500);
    }

    // --- 2) Dump DB pakai Spatie DbDumper ---
    try {
        $dumper = MySql::create()
            ->setDbName($dbConfig['database'])
            ->setUserName($dbConfig['username'])
            ->setPassword($dbConfig['password'] ?? '');

        // host/port
        if (!empty($dbConfig['host'])) {
            $dumper->setHost($dbConfig['host']);
        }
        if (!empty($dbConfig['port'])) {
            // Spatie DbDumper biasanya autodetect port via --port flag; but setHost already supports host:port not needed
            $dumper->setPort($dbConfig['port']);
        }
        if (!empty(env('MYSQL_DUMP_PATH'))) {
            // pastikan path berakhir dengan slash
            $dumpPath = rtrim(env('MYSQL_DUMP_PATH'), '/\\') . DIRECTORY_SEPARATOR;
            $dumper->setDumpBinaryPath($dumpPath);
        }
        // if (!empty(env('DB_DUMP_PATH'))) {
        //     $dumpPath = rtrim(env('DB_DUMP_PATH'), '/\\') . DIRECTORY_SEPARATOR;
        //     $dumper->setDumpBinaryPath($dumpPath);
        // }

        $dumper->dumpToFile($dbDumpFile);

        if (!file_exists($dbDumpFile)) {
            Log::error("DB dump tidak menghasilkan file: {$dbDumpFile}");
            return response()->json(['message' => 'Gagal membuat dump database. Periksa log.'], 500);
        }
    } catch (\Throwable $e) {
        Log::error('DB dump error: '.$e->getMessage());
        return response()->json(['message' => 'Gagal dump database', 'error' => $e->getMessage()], 500);
    }

    // --- 3) Buat ZIP dan masukkan dump + folder uploads ---
    $zip = new ZipArchive();
    if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return response()->json(['message' => 'Tidak bisa membuat file ZIP di path: '.$zipFilePath], 500);
    }

    // masukkan dump db ke folder database-dumps/
    $zip->addFile($dbDumpFile, 'database-dumps/' . basename($dbDumpFile));

    // masukkan folder storage/app/uploads sebagai storage/app/uploads
    $uploadsPath = storage_path('app/uploads');
    if (is_dir($uploadsPath)) {
        $this->addFolderToZip($uploadsPath, $zip, 'storage/app/uploads');
    }

    // jika ingin tambahkan folder lain, cek dan tambahkan di sini
    // ex: $this->addFolderToZip(public_path('uploads'), $zip, 'public/uploads');

    $zip->close();

    // hapus file sql sementara
    if (file_exists($dbDumpFile)) {
        @unlink($dbDumpFile);
    }

    // simpan metadata ke DB
    Backup::create([
        'filename' => $zipFilename,
        'path'     => $zipFilePath,
        'schedule' => 'manual',
        'size'     => filesize($zipFilePath),
    ]);

    return response()->json([
        'message' => 'Backup berhasil dibuat',
        'file' => $zipFilePath,
    ]);
}

/**
 * Rekursif menambahkan folder ke dalam zip
 * $zipPath = path yang diinginkan di dalam zip, mis: 'storage/app/uploads'
 */
private function addFolderToZip(string $folderPath, \ZipArchive $zip, string $zipPath)
{
    // Gunakan RecursiveDirectoryIterator untuk mendapatkan path dasar
    $directory = new \RecursiveDirectoryIterator($folderPath, \FilesystemIterator::SKIP_DOTS);

    // Gunakan RecursiveIteratorIterator untuk iterasi
    $iterator = new \RecursiveIteratorIterator(
        $directory,
        \RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
        // Pastikan kita hanya memproses file, bukan direktori
        if ($file->isDir()) {
            continue;
        }

        $filePath = $file->getRealPath();

        // GANTI BARIS ERROR INI: Ambil path relatif dari base folder
        // Kita menggunakan substr untuk memotong path dasar ($folderPath)
        $relativePath = substr($filePath, strlen($folderPath) + 1);

        // Perbaiki separator direktori (opsional, tapi disarankan untuk ZIP)
        $relativePath = str_replace('\\', '/', $relativePath);

        // Membangun path lengkap di dalam ZIP
        $zipInnerPath = trim(str_replace(['\\', '/'], '/', $zipPath), '/') . '/' . $relativePath;

        $zip->addFile($filePath, $zipInnerPath);
    }
}

    // List semua backup
    public function index()
    {
        return Backup::all();
    }




    //Download backup berdasarkan ID
    public function download($id)
{
    $backup = Backup::findOrFail($id);

    if (!file_exists($backup->path)) {
        return response()->json(['error' => 'File tidak ditemukan'], 404);
    }

    return response()->streamDownload(function () use ($backup) {
        readfile($backup->path);
    }, $backup->filename, [
        'Content-Type'        => 'application/zip',
        'Content-Disposition' => 'attachment; filename="'.$backup->filename.'"',
    ]);
}


    // Hapus backup berdasarkan ID
    public function destroy($id)
    {
        $backup = Backup::findOrFail($id);
        if (file_exists($backup->path)) {
            unlink($backup->path);
        }
        $backup->delete();

        return response()->json(['message' => 'Backup berhasil dihapus']);
    }
}