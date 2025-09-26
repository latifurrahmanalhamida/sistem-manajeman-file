<?php

// namespace App\Services;

// use App\Models\Backup;
// use Illuminate\Support\Facades\Storage;
// use ZipArchive;
// use Carbon\Carbon;
// use Symfony\Component\Process\Process;

// class BackupService
// {
//     public function createBackup($path = null, $schedule = 'manual')
//     {
//         $backupPath = $path ?? storage_path('app/backups');
//         if (!file_exists($backupPath)) {
//             mkdir($backupPath, 0777, true);
//         }

//         $date = Carbon::now()->format('Y-m-d_H-i-s');
//         $filename = "backup_{$date}.zip";
//         $fullPath = $backupPath . '/' . $filename;

//         // 1. Dump database
//         $dbName = env('DB_DATABASE');
//         $dbUser = env('DB_USERNAME');
//         $dbPass = env('DB_PASSWORD');
//         $dbHost = env('DB_HOST', '127.0.0.1');

//         $sqlFile = "{$backupPath}/db_{$date}.sql";
//         $process = Process::fromShellCommandline(
//             "mysqldump -h {$dbHost} -u {$dbUser} -p{$dbPass} {$dbName} > {$sqlFile}"
//         );
//         $process->run();

//         // 2. Buat ZIP (isi: DB + files)
//         $zip = new ZipArchive();
//         if ($zip->open($fullPath, ZipArchive::CREATE) === TRUE) {
//             $zip->addFile($sqlFile, "database.sql");

//             // Tambahkan folder files (contoh: storage/app/files)
//             $filesPath = storage_path('app/files');
//             if (file_exists($filesPath)) {
//                 $this->addFolderToZip($filesPath, $zip, "files");
//             }

//             $zip->close();
//         }

//         // Hapus file sql sementara
//         unlink($sqlFile);

//         // Simpan metadata di DB
//         return Backup::create([
//             'filename' => $filename,
//             'path'     => $backupPath,
//             'size'     => filesize($fullPath),
//             'schedule' => $schedule,
//         ]);
//     }

//     private function addFolderToZip($folder, &$zipFile, $parentFolder)
//     {
//         $files = scandir($folder);
//         foreach ($files as $file) {
//             if ($file === '.' || $file === '..') continue;
//             $filePath = $folder . '/' . $file;
//             if (is_dir($filePath)) {
//                 $this->addFolderToZip($filePath, $zipFile, $parentFolder . '/' . $file);
//             } else {
//                 $zipFile->addFile($filePath, $parentFolder . '/' . $file);
//             }
//         }
//     }
// }
