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
        if (app()->runningInConsole()) {
            return;
        }

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'division_id' => $user->division_id,
            'action'      => 'Membuat Pengguna',
            'target_type' => get_class($user),
            'target_id'   => $user->id,
            'details'     => [
                'info' => "Pengguna baru '{$user->name}' dengan peran '{$user->role->name}' berhasil dibuat."
            ],
            'status'      => 'Berhasil',
        ]);
    }

        public function updated(User $user): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        // Ambil semua perubahan
        $changes = $user->getChanges();

        // Abaikan jika hanya 'updated_at' atau 'deleted_at' yang berubah
        unset($changes['updated_at']);
        if (empty($changes) || array_key_exists('deleted_at', $changes)) {
            return;
        }

        $details = [];
        // Peta untuk menerjemahkan nama kolom menjadi lebih ramah pengguna
        $fieldMap = [
            'name' => 'Nama',
            'email' => 'Email',
            'nipp' => 'NIPP',
            'role_id' => 'Peran',
        ];

        foreach ($changes as $field => $newValue) {
            $oldValue = $user->getOriginal($field);
            $fieldName = $fieldMap[$field] ?? ucfirst($field);

            // Perlakuan khusus untuk password agar tidak terekspos
            if ($field === 'password') {
                $details[] = "Password telah diubah";
                continue;
            }
            
            // Perlakuan khusus untuk 'role_id' untuk menampilkan nama peran
            if ($field === 'role_id') {
                $oldRole = \App\Models\Role::find($oldValue)->name ?? 'N/A';
                $newRole = \App\Models\Role::find($newValue)->name ?? 'N/A';
                $details[] = "Peran diubah dari '{$oldRole}' menjadi '{$newRole}'";
                continue;
            }

            $details[] = "{$fieldName} diubah dari '{$oldValue}' menjadi '{$newValue}'";
        }
        
        // Hanya buat log jika ada detail perubahan yang valid
        if (empty($details)) {
            return;
        }

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'division_id' => $user->division_id,
            'action'      => 'Mengubah Data Pengguna',
            'target_type' => get_class($user),
            'target_id'   => $user->id,
            'details'     => ['info' => implode('. ', $details) . '.'],
            'status'      => 'Berhasil',
        ]);
    }

    /**
     * Handle the User "deleted" event (Soft Delete).
     */
        public function deleted(User $user): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        if ($user->isForceDeleting()) {
            return;
        }

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'division_id' => $user->division_id,
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
        if (app()->runningInConsole()) {
            return;
        }

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'division_id' => $user->division_id,
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
        if (app()->runningInConsole()) {
            return;
        }

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'division_id' => $user->division_id,
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