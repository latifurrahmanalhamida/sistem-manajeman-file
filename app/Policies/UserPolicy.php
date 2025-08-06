<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Berikan semua izin ke super_admin.
     */
    public function before(User $admin, string $ability): bool|null
    {
        if ($admin->role->name === 'super_admin') {
            return true;
        }
        return null;
    }

    /**
     * Tentukan apakah admin bisa memulihkan user target.
     */
    public function restore(User $admin, User $userTarget): bool
    {
        // Izin diberikan jika admin adalah admin_devisi dan user target 
        // berada di divisinya. (super_admin sudah diizinkan oleh method 'before').
        if ($admin->role->name === 'admin_devisi') {
            return $admin->division_id === $userTarget->division_id;
        }

        return false;
    }

    /**
     * Tentukan apakah admin bisa menghapus permanen user target.
     */
    public function forceDelete(User $admin, User $userTarget): bool
    {
        // User tidak bisa menghapus permanen dirinya sendiri
        if ($admin->id === $userTarget->id) {
            return false;
        }
        
        // Untuk izin lainnya, gunakan logika yang sama dengan 'restore'.
        // (super_admin sudah diizinkan oleh method 'before').
        if ($admin->role->name === 'admin_devisi') {
            return $admin->division_id === $userTarget->division_id;
        }

        return false;
    }
}