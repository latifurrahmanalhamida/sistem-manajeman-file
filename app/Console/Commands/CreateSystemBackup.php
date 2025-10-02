<?php

// namespace App\Console\Commands;

// use Illuminate\Support\Facades\Storage;
// use Illuminate\Support\Facades\File;
// use Illuminate\Console\Command;
// use Symfony\Component\Process\Process;
// use Symfony\Component\Process\Exception\ProcessFailedException;
// use Carbon\Carbon;
// use ZipArchive;

// class CreateSystemBackup extends Command
// {
//     protected $signature = 'system:backup {--keep=10 : Jumlah backup maksimum yang disimpan}';
//     protected $description = 'Backup database + storage/app/files ke satu file ZIP';

//     public function handle()
//     {
//         $disk = Storage::disk('local');
//         $backupsDir = 'backups';
//         if (!$disk->exists($backupsDir)) {
//             $disk->makeDirectory($backupsDir);
//         }

//         $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
//         $tmpDir = storage_path("app/tmp/backup_{$timestamp}");
//         File::makeDirectory($tmpDir, 0777, true, true);

//         // ===== 1) Dump database =====
//         $db = config('database.connections.mysql');
//         $dumpPath = "{$tmpDir}/database.sql";
//         $mysqldumpBin = env('MYSQLDUMP_PATH', 'mysqldump');
//         $cmd = [ $mysqldumpBin, "-h{$db['host']}", "-P{$db['port']}", "-u{$db['username']}", "--password={$db['password']}", $db['database'] ];

//         $process = new Process($cmd);
//         $process->run();

//         if (!$process->isSuccessful()) {
//             $this->error('Gagal melakukan dump database: ' . $process->getErrorOutput());
//             File::deleteDirectory($tmpDir);
//             throw new ProcessFailedException($process);
//         }
//         file_put_contents($dumpPath, $process->getOutput());

//         // ===== 2) Salin folder files =====
//         $sourceFiles = storage_path('app/files');
//         $copyTo = "{$tmpDir}/files";
//         if (File::exists($sourceFiles)) {
//             File::copyDirectory($sourceFiles, $copyTo);
//         }

//         // ===== 3) Zip semua =====
//         $zipName = "backup_{$timestamp}.zip";
//         $zipFullPath = storage_path("app/{$backupsDir}/{$zipName}");

//         $zip = new ZipArchive;
//         if ($zip->open($zipFullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
//             $this->error('Tidak bisa membuat file zip.');
//             return Command::FAILURE;
//         }
//         $this->folderToZip($tmpDir, $zip, strlen(dirname($tmpDir)) + 1);
//         $zip->close();

//         File::deleteDirectory($tmpDir);
//         $this->info("Backup selesai → {$zipFullPath}");

//         // ===== 4) Retensi (hapus backup lama) =====
//         $keep = (int)$this->option('keep');
//         $this->applyRetention($disk, $backupsDir, $keep);

//         return Command::SUCCESS;
//     }

//     private function folderToZip($folder, ZipArchive $zipFile, $exclusiveLength)
//     {
//         $handle = opendir($folder);
//         while (false !== $f = readdir($handle)) {
//             if ($f != '.' && $f != '..') {
//                 $filePath = "$folder/$f";
//                 $localPath = substr($filePath, $exclusiveLength);
//                 if (is_file($filePath)) {
//                     $zipFile->addFile($filePath, $localPath);
//                 } elseif (is_dir($filePath)) {
//                     $zipFile->addEmptyDir($localPath);
//                     $this->folderToZip($filePath, $zipFile, $exclusiveLength);
//                 }
//             }
//         }
//         closedir($handle);
//     }

//     private function applyRetention($disk, $dir, $keep)
//     {
//         $files = collect($disk->files($dir))
//             ->filter(fn($f) => str_ends_with($f, '.zip'))
//             ->sortByDesc(fn($f) => $disk->lastModified($f));

//         if ($files->count() > $keep) {
//             $toDelete = $files->slice($keep);
//             foreach ($toDelete as $f) $disk->delete($f);
//         }
//     }
// }