<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BackupSchedule;
use App\Models\BackupSetting;
use App\Models\Backup;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Spatie\DbDumper\Databases\MySql;
use ZipArchive;

class CheckBackupSchedule extends Command
{
    protected $signature = 'system:backup-check';
    protected $description = 'Cek jadwal backup dari DB, jalankan backup otomatis bila waktunya pas';

    public function handle()
    {
        $schedule = BackupSchedule::first();
        if (!$schedule || $schedule->frequency === 'off') {
            return;
        }

        $now = Carbon::now();
        $currentTime = $now->format('H:i:00');

        $shouldRun = false;

        switch ($schedule->frequency) {
            case 'daily':
                $shouldRun = $currentTime === $schedule->time;
                break;

            case 'weekly':
                $shouldRun = $now->dayOfWeek == $schedule->day_of_week &&
                            $currentTime === $schedule->time;
                break;

            case 'monthly':
                $shouldRun = $now->day == $schedule->day_of_month &&
                            $currentTime === $schedule->time;
                break;

            case 'yearly':
                $shouldRun = $now->day == 1 &&
                            $now->month == 1 &&
                            $currentTime === $schedule->time;
                break;
        }

        if (!$shouldRun) {
            return;
        }

        // === jalankan backup otomatis ===
        try {
            $setting = BackupSetting::first();
            $backupPath = $setting ? trim($setting->backup_path, "\" \t\n\r\0\x0B") : storage_path('app/backups');

            if (!file_exists($backupPath)) {
                mkdir($backupPath, 0755, true);
            }

            $timestamp   = date('Ymd_His');
            $zipFilename = "backup_{$timestamp}.zip";
            $zipFilePath = $backupPath . DIRECTORY_SEPARATOR . $zipFilename;
            $dbDumpFile  = storage_path("app/db-backup-{$timestamp}.sql");

            // --- Dump database ---
            $connection = config('database.default');
            $dbConfig   = config("database.connections.{$connection}");

            $dumper = MySql::create()
                ->setDbName($dbConfig['database'])
                ->setUserName($dbConfig['username'])
                ->setPassword($dbConfig['password'] ?? '')
                ->setHost($dbConfig['host'] ?? '127.0.0.1');

            if (!empty($dbConfig['port'])) {
                $dumper->setPort($dbConfig['port']);
            }

            if (!empty(env('MYSQL_DUMP_PATH'))) {
                $dumpPath = rtrim(env('MYSQL_DUMP_PATH'), '/\\') . DIRECTORY_SEPARATOR;
                $dumper->setDumpBinaryPath($dumpPath);
            }

            $dumper->dumpToFile($dbDumpFile);

            // --- Buat ZIP ---
            $zip = new ZipArchive();
            if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                $this->error("Gagal membuka ZIP: {$zipFilePath}");
                return;
            }

            // masukkan dump DB
            $zip->addFile($dbDumpFile, 'database-dumps/' . basename($dbDumpFile));

            // masukkan storage/app/uploads
            $uploadsPath = storage_path('app/uploads');
            if (is_dir($uploadsPath)) {
                $this->addFolderToZip($uploadsPath, $zip, 'storage/app/uploads');
            }

            $zip->close();

            @unlink($dbDumpFile);

            // simpan metadata ke DB
            Backup::create([
                'filename' => $zipFilename,
                'path'     => $zipFilePath,
                'schedule' => 'auto',
                'size'     => filesize($zipFilePath),
            ]);

            $this->info("Backup otomatis berhasil dibuat: {$zipFilePath}");
        } catch (\Throwable $e) {
            Log::error("Backup otomatis gagal: " . $e->getMessage());
            $this->error("Backup otomatis gagal: " . $e->getMessage());
        }
    }

    // app/Console/Commands/CheckBackupSchedule.php atau BackupSystem.php

private function addFolderToZip(string $folderPath, \ZipArchive $zip, string $zipPath)
{
    // Perbaikan: Gunakan RecursiveDirectoryIterator untuk mendapatkan path dasar
    $directory = new \RecursiveDirectoryIterator($folderPath, \FilesystemIterator::SKIP_DOTS);

    // Gunakan RecursiveIteratorIterator untuk iterasi
    $iterator = new \RecursiveIteratorIterator(
        $directory,
        \RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
        if ($file->isDir()) {
            continue;
        }

        $filePath = $file->getRealPath();

        // SOLUSI: Hitung path relatif dengan memotong path dasar
        $relativePath = substr($filePath, strlen($folderPath) + 1);

        // Pastikan separator path-nya adalah forward slash (/) untuk ZIP
        $relativePath = str_replace('\\', '/', $relativePath);

        // Membangun path lengkap di dalam ZIP
        $zipInnerPath = trim(str_replace(['\\', '/'], '/', $zipPath), '/') . '/' . $relativePath;

        $zip->addFile($filePath, $zipInnerPath);
    }
}
}

// namespace App\Console\Commands;

// use Illuminate\Console\Command;
// use App\Models\BackupSchedule;
// use App\Models\BackupSetting;
// use App\Models\Backup;
// use Illuminate\Support\Facades\Artisan;
// use Illuminate\Support\Facades\Config;
// use Illuminate\Support\Facades\File;
// use Illuminate\Support\Facades\Storage;
// use Carbon\Carbon;

// class CheckBackupSchedule extends Command
// {
//     protected $signature = 'system:backup-check';
//     protected $description = 'Cek jadwal backup dari DB, jalankan Spatie backup bila waktunya pas';

//     public function handle()
//     {
//         $schedule = BackupSchedule::first();
//         if (!$schedule || $schedule->frequency === 'off') {
//             return;
//         }

//         $now = Carbon::now();
//             $currentTime = $now->format('H:i'); // misalnya "14:23"

//             // cek apakah waktunya pas
//             $shouldRun = false;

//             switch ($schedule->frequency) {
//                 case 'daily':
//                     $shouldRun = $currentTime === $schedule->time;
//                     break;

//                 case 'weekly':
//                     $shouldRun = $now->dayOfWeek == $schedule->day_of_week &&
//                                 $currentTime === $schedule->time;
//                     break;

//                 case 'monthly':
//                     $shouldRun = $now->day == $schedule->day_of_month &&
//                                 $currentTime === $schedule->time;
//                     break;

//                 case 'yearly':
//                     $shouldRun = $now->day == 1 &&
//                                 $now->month == 1 &&
//                                 $currentTime === $schedule->time;
//                     break;
//             }

//         if ($shouldRun) {
//             $setting = BackupSetting::first();
//             $backupPath = $setting ? $setting->backup_path : storage_path('app/backups');

//             // Override config Spatie supaya file backup langsung ada di root path (tanpa folder laravel)
//             Config::set('backup.backup.name', '');
//             Config::set('backup.backup.destination.disks', ['local']);
//             Config::set('filesystems.disks.local.root', $backupPath);

//             // Jalankan backup
//             Artisan::call('backup:run');

//             // Cari file backup terbaru
//             $disk = config('backup.backup.destination.disks')[0];
//             $files = Storage::disk($disk)->files();

//             $latestFile = collect($files)
//                 ->sortByDesc(fn($file) => Storage::disk($disk)->lastModified($file))
//                 ->first();

//                 if ($latestFile) {
//                 $sourceFile = Storage::disk($disk)->path($latestFile);

//                 Config::set('backup.backup.name', '');

//                 // Simpan ke tabel backup
//                 Backup::create([
//                     'filename' => basename($latestFile),
//                     'path'     => $sourceFile,
//                     'schedule' => 'auto',
//                     'size'     => filesize($sourceFile),
//                 ]);

//                 $this->info("Backup otomatis berhasil pada {$now}");
//             } else {
//                 $this->error("Tidak ada file backup ditemukan pada {$now}");
//             }
//         }
//     }
// }
