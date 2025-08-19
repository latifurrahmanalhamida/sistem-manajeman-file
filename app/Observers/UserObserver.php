<?php

namespace App\Observers;

use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class UserObserver
{
    /**
     * Menjalankan event observer setelah semua transaksi database selesai.
     * @var bool
     */
    public $afterCommit = true;

    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        ActivityLog::create([
            'user_id'     => Auth::id() ?? 0,
            'action'      => 'Membuat Pengguna',
            'target_type' => get_class($user),
            'target_id'   => $user->id,
            'details'     => [
                'info' => "Pengguna baru '{$user->name}' dengan peran '{$user->role->name}' berhasil dibuat."
            ],
            'status'      => 'Berhasil',
        ]);
    }

    /**
     * Handle the User "updated" event.
     * --- FINAL FIX: skip log update jika ada perubahan deleted_at ---
     */
    public function updated(User $user): void
    {
        $changes = array_keys($user->getChanges());

        // Jika perubahan menyentuh deleted_at (restore/soft delete),
        // jangan buat log update supaya tidak ganda.
        if (in_array('deleted_at', $changes)) {
            return;
        }

        ActivityLog::create([
            'user_id'     => Auth::id() ?? 0,
            'action'      => 'Mengubah Data Pengguna',
            'target_type' => get_class($user),
            'target_id'   => $user->id,
            'details'     => [
                'info' => "Data untuk pengguna '{$user->name}' telah diperbarui."
            ],
            'status'      => 'Berhasil',
        ]);
    }

    /**
     * Handle the User "deleted" event (Soft Delete).
     */
    public function deleted(User $user): void
    {
        if ($user->isForceDeleting()) {
            return;
        }

        ActivityLog::create([
            'user_id'     => Auth::id() ?? 0,
            'action'      => 'Menghapus Pengguna',
            'target_type' => get_class($user),
            'target_id'   => $user->id,
            'details'     => [
                'info' => "Pengguna '{$user->name}' telah dipindah ke sampah."
            ],
            'status'      => 'Berhasil',
        ]);
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        ActivityLog::create([
            'user_id'     => Auth::id() ?? 0,
            'action'      => 'Memulihkan Pengguna',
            'target_type' => get_class($user),
            'target_id'   => $user->id,
            'details'     => [
                'info' => "Pengguna '{$user->name}' telah dipulihkan dari sampah."
            ],
            'status'      => 'Berhasil',
        ]);
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        ActivityLog::create([
            'user_id'     => Auth::id() ?? 0,
            'action'      => 'Menghapus Pengguna Permanen',
            'target_type' => get_class($user),
            'target_id'   => $user->id,
            'details'     => [
                'info' => "Pengguna '{$user->name}' telah dihapus secara permanen."
            ],
            'status'      => 'Berhasil',
        ]);
    }
}
