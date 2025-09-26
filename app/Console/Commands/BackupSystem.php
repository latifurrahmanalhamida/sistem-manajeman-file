<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use App\Models\Backup;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class BackupSystem extends Command
{
    protected $signature = 'system:backup {--schedule=manual}';
    protected $description = 'Jalankan Spatie Backup dan simpan metadata ke tabel backups';

    public function handle()
    {
        $schedule = $this->option('schedule') ?? 'manual';

        // Jalankan spatie backup run
        Artisan::call('backup:run');

        // Ambil output dari spatie
        $output = Artisan::output();
        $this->info("=== LOG SPATIE BACKUP ===");
        $this->info($output);

        // Tambahkan jeda kecil agar file benar-benar tersimpan
        sleep(2);

        // Ambil file backup terbaru dari storage
        $disk = config('backup.backup.destination.disks')[0] ?? 'local';
        $backupName = config('backup.backup.name') ?? 'Laravel';
        $files = Storage::disk($disk)->files($backupName);

        $latestFile = collect($files)
            ->sortByDesc(fn($file) => Storage::disk($disk)->lastModified($file))
            ->first();

        if ($latestFile) {
            $size = Storage::disk($disk)->size($latestFile);

            Backup::create([
                'filename' => basename($latestFile),
                'path'     => $latestFile,
                'schedule' => $schedule,
                'size'     => $size,
            ]);

            $this->info("Backup ($schedule) berhasil disimpan: " . basename($latestFile));
        } else {
            $this->error("❌ Tidak menemukan file backup. Cek log di atas!");
        }
    }
}



// manually backup system (db + files) into a ZIP file
// use Illuminate\Console\Command;
// use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Storage;
// use ZipArchive;
// use Carbon\Carbon;
// use App\Models\Backup;

// class BackupSystem extends Command
// {
//     protected $signature = 'system:backup';
//     protected $description = 'Backup database dan files ke dalam ZIP';

//     public function handle()
//     {
//         $date = Carbon::now()->format('Y-m-d_H-i-s');
//         $fileName = "backup-{$date}.zip";
//         $backupPath = config('backup.path', 'backups'); // bisa diubah dari config/env/db
//         $storagePath = storage_path("app/{$backupPath}");

//         if (!is_dir($storagePath)) {
//             mkdir($storagePath, 0755, true);
//         }

//         $zip = new ZipArchive;
//         $zipFile = "{$storagePath}/{$fileName}";

//         if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
//             // dump database
//             $dbName = env('DB_DATABASE');
//             $username = env('DB_USERNAME');
//             $password = env('DB_PASSWORD');
//             $host = env('DB_HOST', '127.0.0.1');

//             $dumpFile = "{$storagePath}/db-{$date}.sql";
//             $command = "mysqldump -u{$username} -p{$password} -h{$host} {$dbName} > {$dumpFile}";
//             system($command);

//             $zip->addFile($dumpFile, "database.sql");

//             // add storage files
//             $filesPath = storage_path('app/files');
//             $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($filesPath));
//             foreach ($files as $file) {
//                 if (!$file->isDir()) {
//                     $filePath = $file->getRealPath();
//                     $relativePath = substr($filePath, strlen($filesPath) + 1);
//                     $zip->addFile($filePath, "files/{$relativePath}");
//                 }
//             }

//             $zip->close();
//             unlink($dumpFile);

//             // save record
//             $size = filesize($zipFile) / 1024;
//             Backup::create([
//                 'filename' => $fileName,
//                 'path' => $backupPath,
//                 'size' => (int)$size,
//                 'schedule' => 'none',
//             ]);

//             $this->info("Backup berhasil: {$zipFile}");
//         } else {
//             $this->error("Gagal membuat backup");
//         }
//     }
// }


