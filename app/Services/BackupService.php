<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class BackupService
{
    public function backupAll()
    {
        // === 1. Backup Database (manual mysqldump) ===
        $db = config('database.connections.mysql');
        $timestamp = now()->format('Ymd_His');
        $backupDir = 'D:/Backup/Database'; // sesuaikan path
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0777, true);
        }

        $dumpPath = $backupDir . "/db_backup_{$timestamp}.sql";
        $mysqldump = 'C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysqldump.exe';

        $cmd = "\"{$mysqldump}\" -u{$db['username']} " .
            (!empty($db['password']) ? "--password=\"{$db['password']}\" " : "") .
            "-h{$db['host']} {$db['database']} > \"{$dumpPath}\"";

        $result = null;
        $output = null;
        exec($cmd, $output, $result);

        if ($result !== 0) {
            Log::error("❌ Backup database gagal.");
            return false;
        }

        // === 2. Backup Files (pakai Spatie) ===
        Artisan::call('backup:run', ['--only-files' => true]);
        $fileBackupOutput = Artisan::output();

        Log::info('✅ FULL Backup success', [
            'db_path' => $dumpPath,
            'files_output' => $fileBackupOutput
        ]);

        return true;
    }
}
