<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Carbon\Carbon;

class BackupSystem extends Command
{
    protected $signature = 'system:backup {--keep=10 : Jumlah backup maksimum yang disimpan}';
    protected $description = 'Backup database + storage/app/files ke satu file ZIP';

    public function handle()
    {
        $disk = Storage::disk('local'); // storage/app
        $backupsDir = 'backups';
        if (!$disk->exists($backupsDir)) {
            $disk->makeDirectory($backupsDir);
        }

        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $tmpDir = storage_path("app/tmp/backup_{$timestamp}");
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        // ===== 1) Dump database =====
        $db = config('database.connections.mysql');
        $dumpPath = "{$tmpDir}/database.sql";

        // Support password with special characters (use --password=... instead of -p)
        $mysqldumpBin = env('MYSQLDUMP_PATH', 'mysqldump');

        $cmd = [
            $mysqldumpBin,
            "-h{$db['host']}",
            "-P{$db['port']}",
            "-u{$db['username']}",
            "--password={$db['password']}",
            $db['database']
        ];

        $process = new Process($cmd);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->error('Gagal melakukan dump database: ' . $process->getErrorOutput());
            throw new ProcessFailedException($process);
        }
        file_put_contents($dumpPath, $process->getOutput());

        // ===== 2) Salin folder files =====
        $sourceFiles = storage_path('app/files'); // sesuaikan dengan folder file kamu
        $copyTo = "{$tmpDir}/files";
        if (is_dir($sourceFiles)) {
            $this->recursiveCopy($sourceFiles, $copyTo);
        }

        // ===== 3) Zip semua =====
        $zipName = "backup_{$timestamp}.zip";
        $zipFullPath = storage_path("app/{$backupsDir}/{$zipName}");

        $zip = new ZipArchive;
        if ($zip->open($zipFullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error('Tidak bisa membuat file zip.');
            return Command::FAILURE;
        }
        $this->folderToZip($tmpDir, $zip, strlen(dirname($tmpDir)) + 1);
        $zip->close();

        // Bersihkan tmp
        $this->rrmdir($tmpDir);

        $this->info("Backup selesai → {$zipFullPath}");

        // ===== 4) Retensi (hapus backup lama) =====
        $keep = (int)$this->option('keep');
        $this->applyRetention($disk, $backupsDir, $keep);

        return Command::SUCCESS;
    }

    private function recursiveCopy($src, $dst)
    {
        $dir = opendir($src);
        @mkdir($dst, 0777, true);
        while(false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir("$src/$file")) {
                    $this->recursiveCopy("$src/$file", "$dst/$file");
                } else {
                    copy("$src/$file", "$dst/$file");
                }
            }
        }
        closedir($dir);
    }

    private function folderToZip($folder, ZipArchive $zipFile, $exclusiveLength)
    {
        $handle = opendir($folder);
        while (false !== $f = readdir($handle)) {
            if ($f != '.' && $f != '..') {
                $filePath = "$folder/$f";
                $localPath = substr($filePath, $exclusiveLength);
                if (is_file($filePath)) {
                    $zipFile->addFile($filePath, $localPath);
                } elseif (is_dir($filePath)) {
                    $zipFile->addEmptyDir($localPath);
                    $this->folderToZip($filePath, $zipFile, $exclusiveLength);
                }
            }
        }
        closedir($handle);
    }

    private function rrmdir($dir)
    {
        if (!is_dir($dir)) return;
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                $path = $dir . DIRECTORY_SEPARATOR . $object;
                if (is_dir($path)) $this->rrmdir($path); else @unlink($path);
            }
        }
        @rmdir($dir);
    }

    private function applyRetention($disk, $dir, $keep)
    {
        $files = collect($disk->files($dir))
            ->filter(fn($f) => str_ends_with($f, '.zip'))
            ->sortDesc(); // terbaru dulu

        if ($files->count() > $keep) {
            $toDelete = $files->slice($keep);
            foreach ($toDelete as $f) $disk->delete($f);
        }
    }
}
