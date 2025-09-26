<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BackupSetting;
use App\Models\BackupSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\DbDumper\Databases\MySql;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\path;
use App\Models\Backup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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

    // public function getSchedule(): JsonResponse
    // {
    //     $schedule = BackupSchedule::first();
    //     return response()->json([
    //         'status' => 'success',
    //         'schedule' => $schedule
    //     ]);
    // }

    // public function updateSchedule(Request $request): JsonResponse
    // {
    //     $request->validate([
    //         'frequency' => 'required|string|in:off,daily,weekly,monthly,yearly',
    //         'time' => 'nullable|string',
    //         'day_of_week' => 'nullable|string',
    //         'day_of_month' => 'nullable|integer|min:1|max:31',
    //     ]);

    //     $schedule = BackupSchedule::first();
    //     if ($schedule) {
    //         $schedule->update($request->all());
    //     } else {
    //         $schedule = BackupSchedule::create($request->all());
    //     }

    //     return response()->json([
    //         'message' => 'Schedule updated successfully',
    //         'schedule' => $schedule
    //     ]);
    // }



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

        // Jika kamu perlu set path ke mysqldump binary (Windows), set ini dari .env
        if (!empty(env('MYSQL_DUMP_PATH'))) {
            // pastikan path berakhir dengan slash
            $dumpPath = rtrim(env('MYSQL_DUMP_PATH'), '/\\') . DIRECTORY_SEPARATOR;
            $dumper->setDumpBinaryPath($dumpPath);
        }

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






    // Jalankan backup manual spatie
//     public function run()
// {
//     $setting = BackupSetting::first();
//     $backupPath = $setting ? str_replace('"', '', $setting->backup_path) : storage_path('app/backups');

//     if (!file_exists($backupPath)) {
//         mkdir($backupPath, 0755, true);
//     }

//     // Override config supaya hasil backup langsung ke folder custom
//     Config::set('backup.backup.name', 'Laravel'); // kosongkan supaya tidak ada folder Laravel/
//     Config::set('backup.backup.destination.disks', ['local']);
//     Config::set('filesystems.disks.local.root', $backupPath);

//     // Jalankan backup (fresh)
//     Artisan::call('backup:run', [
//         '--only-db' => false,
//         '--disable-notifications' => true,
//     ]);

//     // Cari file terbaru (langsung dari $backupPath)
//     $files = Storage::disk('local')->files();
//     $latestFile = collect($files)
//         ->filter(fn($file) => str_ends_with($file, '.zip'))
//         ->sortByDesc(fn($file) => Storage::disk('local')->lastModified($file))
//         ->first();

//     if ($latestFile) {
//         $sourceFile = Storage::disk('local')->path($latestFile);

//         // Rename file jadi format simple
//         $targetFile = rtrim($backupPath, DIRECTORY_SEPARATOR)
//             . DIRECTORY_SEPARATOR
//             . 'backup_' . date('Ymd_His') . '.zip';

//         rename($sourceFile, $targetFile);

//         Backup::create([
//             'filename' => basename($targetFile),
//             'path'     => $targetFile,
//             'schedule' => 'manual',
//             'size'     => filesize($targetFile),
//         ]);

//         return response()->json([
//             'message' => 'Backup berhasil dibuat',
//             'file'    => $targetFile
//         ]);
//     }

//     return response()->json(['message' => 'Tidak ada file backup ditemukan'], 500);
// }




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

// class BackupController extends Controller
// {
//      // Ambil path yang sedang aktif
//     public function getSettings(): JsonResponse
//     {
//         $setting = BackupSetting::first();
//         return response()->json([
//             'status' => 'success',
//             'backup_path' => $setting ? $setting->backup_path : storage_path('app/backups')
//         ]);
//     }

//     // Update path dari frontend
//     public function updateSettings(Request $request): JsonResponse
//     {
//         $request->validate([
//             'backup_path' => 'required|string'
//         ]);

//         $setting = BackupSetting::first();

//         if ($setting) {
//             $setting->update(['backup_path' => $request->backup_path]);
//         } else {
//             $setting = BackupSetting::create(['backup_path' => $request->backup_path]);
//         }

//         config()->set('filesystems.disks.backup_disk.root', $setting->backup_path);

//         return response()->json([
//             'message' => 'Backup path updated successfully',
//             'backup_path' => $setting->backup_path
//         ]);
//     }

//     // Jalankan backup manual
//     public function run()
// {
//     $setting = BackupSetting::first();
//     $backupPath = $setting ? str_replace('"', '', $setting->backup_path) : storage_path('app/backups');

//     if (!file_exists($backupPath)) {
//         mkdir($backupPath, 0755, true);
//     }

//     // Jalankan Spatie backup
//     Artisan::call('backup:run');

//     // Ambil file terbaru dari disk default Spatie
//     $disk = config('backup.backup.destination.disks')[0];
//     $files = Storage::disk($disk)->files(config('backup.backup.name'));

//     $latestFile = collect($files)
//         ->sortByDesc(fn($file) => Storage::disk($disk)->lastModified($file))
//         ->first();

//     if ($latestFile) {
//         $sourceFile = Storage::disk($disk)->path($latestFile);
//         $targetFile = rtrim($backupPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($latestFile);

//         copy($sourceFile, $targetFile);

//         Backup::create([
//             'filename' => basename($latestFile),
//             'path'     => $targetFile,
//             'schedule' => 'manual',
//             'size'     => filesize($targetFile),
//         ]);

//         return response()->json(['message' => 'Backup berhasil dibuat', 'file' => $targetFile]);
//     }

//     return response()->json(['message' => 'Tidak ada file backup ditemukan'], 500);
// }


//     public function index()
//     {
//         return Backup::all();
//     }


//     public function download($id)
//     {
//         $backup = Backup::findOrFail($id);
//         return Storage::download($backup->path, $backup->filename);
//     }

//     public function destroy($id)
//     {
//         $backup = Backup::findOrFail($id);
//         Storage::delete($backup->path);
//         $backup->delete();

//         return response()->json(['message' => 'Backup berhasil dihapus']);
//     }
// }




// use App\Models\Backup;
// use Illuminate\Http\Request;

// use App\Http\Controllers\Controller;
// use Illuminate\Support\Facades\Artisan;
// use Illuminate\Support\Facades\Storage;

// class BackupController extends Controller
// {
//     public function index()
//     {
//         return Backup::orderBy('created_at','desc')->get();
//     }

//     public function store()
//     {
//         Artisan::call('system:backup');
//         return response()->json(['message' => 'Backup berhasil dibuat']);
//     }

//     public function download($filename)
//     {
//         $backup = Backup::where('filename',$filename)->firstOrFail();
//         $path = storage_path("app/{$backup->path}/{$backup->filename}");
//         return response()->download($path);
//     }

//     public function destroy($filename)
//     {
//         $backup = Backup::where('filename',$filename)->firstOrFail();
//         $path = storage_path("app/{$backup->path}/{$backup->filename}");
//         if (file_exists($path)) unlink($path);
//         $backup->delete();

//         return response()->json(['message'=>'Backup dihapus']);
//     }

//     public function updateSettings(Request $request)
//     {
//         $path = $request->input('path','backups');
//         $schedule = $request->input('schedule','none');

//         config(['backup.path' => $path]);

//         return response()->json([
//             'message' => 'Pengaturan backup diperbarui',
//             'path' => $path,
//             'schedule' => $schedule
//         ]);
//     }
// }


