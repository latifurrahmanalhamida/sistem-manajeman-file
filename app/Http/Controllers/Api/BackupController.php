<?php

    namespace App\Http\Controllers\Api;

    use App\Http\Controllers\Controller;
    use Illuminate\Support\Facades\Artisan;
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Facades\Log;
    use Illuminate\Http\JsonResponse;
    use Illuminate\Http\Request;
    use ZipArchive;

    class BackupController extends Controller
    {

       /**
         * Backup seluruh data (database + files)
         */
        public function backupAll()
{
    try {
        Log::info('Starting FULL backup (database + files)');

        // === 1. Backup Database (manual mysqldump) ===
        $db = config('database.connections.mysql');
        $timestamp = now()->format('Ymd_His');
        $backupDir = 'D:/Backup/Database'; // folder DB
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
            return response()->json([
                'status' => 'error',
                'message' => "❌ Backup database gagal. Cek konfigurasi MySQL & permission folder."
            ], 500);
        }

        // === 2. Backup Files/Storage (pakai Spatie) ===
        Artisan::call('backup:run', ['--only-files' => true]);
        $fileBackupOutput = Artisan::output();

        Log::info('FULL backup completed', [
            'db_path' => $dumpPath,
            'files_output' => $fileBackupOutput
        ]);

        return response()->json([
            'status' => 'success',
            'message' => '✅ Backup database & storage berhasil.',
            'database_path' => $dumpPath,
            'files_output' => $fileBackupOutput
        ]);

    } catch (\Exception $e) {
        Log::error('Full backup failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'status' => 'error',
            'message' => 'Backup gagal: ' . $e->getMessage(),
            'trace' => config('app.debug') ? $e->getTraceAsString() : null,
        ], 500);
    }
}


        /**
         * Backup database saja (custom mysqldump)
         */
        public function backupDatabase()
    {
        try {
            $db = config('database.connections.mysql');
            $timestamp = now()->format('Ymd_His');
            $backupDir = 'D:/Backup/Database'; // samakan dengan path Spatie
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0777, true);
            }

            // nama file hasil backup
            $dumpPath = $backupDir . "/db_backup_{$timestamp}.sql";

            // path ke mysqldump.exe (ubah sesuai instalasi MySQL Anda)
            $mysqldump = 'C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysqldump.exe';

            // buat perintah mysqldump (seluruh database)
            $cmd = "\"{$mysqldump}\" -u{$db['username']} " .
                (!empty($db['password']) ? "--password=\"{$db['password']}\" " : "") .
                "-h{$db['host']} {$db['database']} > \"{$dumpPath}\"";

            $result = null;
            $output = null;
            exec($cmd, $output, $result);

            if ($result === 0) {
                return response()->json([
                    'status' => 'success',
                    'message' => "✅ Backup database selesai: {$dumpPath}"
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => "❌ Backup database gagal. Cek konfigurasi MySQL & permission folder."
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Backup database gagal: ' . $e->getMessage()
            ], 500);
        }
    }

        /**
         * Backup storage/files saja
         */
        public function backupStorage()
        {
            try {
                Log::info('Starting storage backup');

                Artisan::call('backup:run', ['--only-files' => true]);
                $output = Artisan::output();

                Log::info('Storage backup completed', ['output' => $output]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Backup storage berhasil dibuat.',
                    'output' => $output
                ]);

            } catch (\Exception $e) {
                Log::error('Storage backup failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Backup storage gagal: ' . $e->getMessage(),
                ], 500);
            }
        }

     public function listBackups()
    {
        try {
            $backups = [];

            // Ambil file database (.sql)
            foreach (glob('D:/Backup/Database/*.sql') as $f) {
                $backups[] = [
                    'name' => basename($f),
                    'size' => filesize($f),
                    'modified' => filemtime($f),
                    'path' => $f,
                ];
            }

            // Ambil file storage (.zip)
            foreach (glob('D:/Backup/Files/laravel/*.zip') as $f) {
                $backups[] = [
                    'name' => basename($f),
                    'size' => filesize($f),
                    'modified' => filemtime($f),
                    'path' => $f,
                ];
            }

            // Urutkan terbaru
            usort($backups, fn($a, $b) => $b['modified'] <=> $a['modified']);

            return response()->json([
                'status' => 'success',
                'files' => $backups
            ]);
        } catch (\Exception $e) {
            Log::error('List backups failed', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil daftar backup: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function downloadBackup($filename)
    {
        try {
            $pathDb = 'D:/Backup/Database/' . $filename;
            $pathLaravel = 'D:/Backup/Files/laravel/' . $filename;

            $fullPath = null;
            if (file_exists($pathDb)) {
                $fullPath = $pathDb;
            } elseif (file_exists($pathLaravel)) {
                $fullPath = $pathLaravel;
            }

            if (!$fullPath) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'File backup tidak ditemukan'
                ], 404);
            }

            return response()->download($fullPath);
        } catch (\Exception $e) {
            Log::error('Download backup failed', [
                'file' => $filename,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal download backup: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteBackup($filename)
    {
        try {
            $pathDb = 'D:/Backup/Database/' . $filename;
            $pathLaravel = 'D:/Backup/Files/laravel/' . $filename;

            if (file_exists($pathDb)) {
                unlink($pathDb);
            } elseif (file_exists($pathLaravel)) {
                unlink($pathLaravel);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'File backup tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Backup berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            Log::error('Delete backup failed', [
                'file' => $filename,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus backup: ' . $e->getMessage()
            ], 500);
        }
    }
}
