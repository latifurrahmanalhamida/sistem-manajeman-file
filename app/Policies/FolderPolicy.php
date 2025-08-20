<?php

namespace App\Policies;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FolderPolicy
{
    use HandlesAuthorization;

    private function userHasRole(User $user, string $roleName): bool
    {
        return $user->role && $user->role->name === $roleName;
    }

    public function before(User $user, string $ability): bool|null
    {
        // Super admin boleh semua
        if ($this->userHasRole($user, 'super_admin')) {
            return true;
        }
        return null;
    }

    public function view(User $user, Folder $folder): bool
    {
        return $user->division_id === $folder->division_id;
    }

    public function create(User $user): bool
    {
        // Hanya admin_devisi (akses pembuatan folder via panel admin)
        return $this->userHasRole($user, 'admin_devisi');
    }

    public function update(User $user, Folder $folder): bool
    {
        if ($this->userHasRole($user, 'admin_devisi')) {
            return $user->division_id === $folder->division_id;
        }
        // Opsi: user_devisi boleh update folder yang dia buat di divisinya (jika suatu saat dibuka akses)
        if ($this->userHasRole($user, 'user_devisi')) {
            return $user->division_id === $folder->division_id && $user->id === $folder->user_id;
        }
        return false;
    }

    public function delete(User $user, Folder $folder): bool
    {
        if ($this->userHasRole($user, 'admin_devisi')) {
            return $user->division_id === $folder->division_id;
        }
        if ($this->userHasRole($user, 'user_devisi')) {
            return $user->division_id === $folder->division_id && $user->id === $folder->user_id;
        }
        return false;
    }

    public function restore(User $user, Folder $folder): bool
    {
        if ($this->userHasRole($user, 'admin_devisi')) {
            return $user->division_id === $folder->division_id;
        }
        // Creator boleh memulihkan miliknya
        return $user->id === $folder->user_id;
    }

    public function forceDelete(User $user, Folder $folder): bool
    {
        if ($this->userHasRole($user, 'admin_devisi')) {
            return $user->division_id === $folder->division_id;
        }
        return $user->id === $folder->user_id;
    }
}
