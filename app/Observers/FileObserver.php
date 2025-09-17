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
            'division_id' => $file->division_id,
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
        // Cek apakah kolom 'nama_file_asli' yang berubah.
        // Ini untuk memastikan kita hanya mencatat log saat ada perubahan nama.
        if ($file->isDirty('nama_file_asli')) {
            // Ambil nama file sebelum diubah
            $originalName = $file->getOriginal('nama_file_asli');
            
            ActivityLog::create([
                'user_id'     => Auth::id(),
                'division_id' => $file->division_id,
                'action'      => 'Mengubah Nama File',
                'target_type' => get_class($file),
                'target_id'   => $file->id,
                'details'     => ['info' => "Mengubah nama file dari '{$originalName}' menjadi '{$file->nama_file_asli}'."],
                'status'      => 'Berhasil',
            ]);
        }
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
            'division_id' => $file->division_id,
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
            'division_id' => $file->division_id,
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
            'division_id' => $file->division_id,
            'action'      => 'Menghapus File Permanen',
            'target_type' => get_class($file),
            'target_id'   => $file->id,
            'details'     => ['info' => "File '{$file->nama_file_asli}' telah dihapus secara permanen."],
            'status'      => 'Berhasil',
        ]);
    }
}