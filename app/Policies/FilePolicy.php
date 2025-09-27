<?php

namespace App\Policies;

use App\Models\File;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FilePolicy
{
    public function before(User $user, string $ability): bool|null
    {
        if ($user->role->name === 'super_admin') {
            return true;
        }
        return null;
    }

    public function view(User $user, File $file): bool
    {
        return $user->division_id === $file->division_id;
    }

    public function update(User $user, File $file): bool
    {
        // Izinkan jika user adalah Admin Devisi di divisi yang sama
        if ($user->role->name === 'admin_devisi') {
            return $user->division_id === $file->division_id;
        }

        // Izinkan jika user adalah User Devisi, di divisi yang sama, DAN dia pengunggahnya
        if ($user->role->name === 'user_devisi') {
            return $user->id === $file->uploader_id && $user->division_id === $file->division_id;
        }

        return false;
    }

    
public function delete(User $user, File $file): bool
{
    // Izinkan jika user adalah Admin Devisi di divisi yang sama
    if ($user->role->name === 'admin_devisi') {
        return $user->division_id === $file->division_id;
    }

    // Izinkan jika user adalah User Devisi, di divisi yang sama, DAN dia pengunggahnya
    if ($user->role->name === 'user_devisi') {
        return $user->id === $file->uploader_id && $user->division_id === $file->division_id;
    }

    return false;
}

    public function restore(User $user, File $file): bool
{
    // Izinkan jika user adalah Admin Devisi di divisi yang sama
    if ($user->role->name === 'admin_devisi') {
        return $user->division_id === $file->division_id;
    }

    // Izinkan jika user adalah User Devisi DAN dia pengunggahnya
    return $user->id === $file->uploader_id;
}

    public function forceDelete(User $user, File $file): bool
{
    // Izinkan jika user adalah Admin Devisi di divisi yang sama
    if ($user->role->name === 'admin_devisi') {
        return $user->division_id === $file->division_id;
    }

    // Izinkan jika user adalah User Devisi DAN dia pengunggahnya
    return $user->id === $file->uploader_id;
}
}