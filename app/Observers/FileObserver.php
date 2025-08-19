<?php

namespace App\Observers;

use App\Models\File;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class FileObserver
{
    /**
     * Menjalankan event observer setelah semua transaksi database selesai.
     * @var bool
     */
    public $afterCommit = true;

    /**
     * Handle the File "created" event.
     */
    public function created(File $file): void
    {
        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'Mengunggah File',
            'target_type' => get_class($file),
            'target_id'   => $file->id,
            'details'     => ['info' => "File '{$file->nama_file_asli}' berhasil diunggah."],
            'status'      => 'Berhasil',
        ]);
    }

    /**
     * Handle the File "updated" event.
     * --- FUNGSI INI DIPERBAIKI ---
     */
    public function updated(File $file): void
    {
        // Pengecekan 'isRestoring' yang salah sudah dihapus.
        // Untuk File, kita tidak perlu logika ini karena pemulihan tidak memicu 'updated'.
    }

    /**
     * Handle the File "deleted" event (Soft Delete).
     */
    public function deleted(File $file): void
    {
        if ($file->isForceDeleting()) {
            return; 
        }

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'Menghapus File',
            'target_type' => get_class($file),
            'target_id'   => $file->id,
            'details'     => ['info' => "File '{$file->nama_file_asli}' telah dipindah ke sampah."],
            'status'      => 'Berhasil',
        ]);
    }

    /**
     * Handle the File "restored" event.
     */
    public function restored(File $file): void
    {
        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'Memulihkan File',
            'target_type' => get_class($file),
            'target_id'   => $file->id,
            'details'     => ['info' => "File '{$file->nama_file_asli}' telah dipulihkan dari sampah."],
            'status'      => 'Berhasil',
        ]);
    }

    /**
     * Handle the File "force deleted" event.
     */
    public function forceDeleted(File $file): void
    {
        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'Menghapus File Permanen',
            'target_type' => get_class($file),
            'target_id'   => $file->id,
            'details'     => ['info' => "File '{$file->nama_file_asli}' telah dihapus secara permanen."],
            'status'      => 'Berhasil',
        ]);
    }
}