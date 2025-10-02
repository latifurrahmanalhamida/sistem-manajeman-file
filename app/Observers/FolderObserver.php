<?php

namespace App\Observers;

use App\Models\Folder;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class FolderObserver
{
    /**
     * Menjalankan event observer setelah semua transaksi database selesai.
     * @var bool
     */
    // public $afterCommit = true;

    public function created(Folder $folder): void
    {
        ActivityLog::create([
            'user_id'     => Auth::id(),
            'division_id' => $folder->division_id,
            'action'      => 'Membuat Folder',
            'target_type' => get_class($folder),
            'target_id'   => $folder->id,
            'details'     => ['info' => "Folder '{$folder->name}' berhasil dibuat."],
            'status'      => 'Berhasil',
        ]);
    }

public function updated(Folder $folder): void
{
    if ($folder->wasChanged('name')) {
        $oldName = $folder->getOriginal('name'); // harus nama lama
        $newName = $folder->name; // nama baru

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'division_id' => $folder->division_id,
            'action'      => 'Mengubah Nama Folder',
            'target_type' => get_class($folder),
            'target_id'   => $folder->id,
            'details'     => [
                'info' => "Nama folder diubah dari '{$oldName}' menjadi '{$newName}'."
            ],
            'status'      => 'Berhasil',
        ]);
    }
}

    public function deleted(Folder $folder): void
    {
        if (method_exists($folder, 'isForceDeleting') && $folder->isForceDeleting()) {
            return; // Force delete ditangani di event terpisah
        }

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'division_id' => $folder->division_id,
            'action'      => 'Menghapus Folder',
            'target_type' => get_class($folder),
            'target_id'   => $folder->id,
            'details'     => ['info' => "Folder '{$folder->name}' telah dipindah ke sampah."],
            'status'      => 'Berhasil',
        ]);
    }

    public function restored(Folder $folder): void
    {
        ActivityLog::create([
            'user_id'     => Auth::id(),
            'division_id' => $folder->division_id,
            'action'      => 'Memulihkan Folder',
            'target_type' => get_class($folder),
            'target_id'   => $folder->id,
            'details'     => ['info' => "Folder '{$folder->name}' telah dipulihkan dari sampah."],
            'status'      => 'Berhasil',
        ]);
    }

    public function forceDeleted(Folder $folder): void
    {
        ActivityLog::create([
            'user_id'     => Auth::id(),
            'division_id' => $folder->division_id,
            'action'      => 'Menghapus Folder Permanen',
            'target_type' => get_class($folder),
            'target_id'   => $folder->id,
            'details'     => ['info' => "Folder '{$folder->name}' telah dihapus secara permanen."],
            'status'      => 'Berhasil',
        ]);
    }
}