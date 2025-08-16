<?php

namespace App\Policies;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FolderPolicy
{
    use HandlesAuthorization;

    /**
     * Checks the user's role name via relationship.
     * Assumes User->role->name exists.
     */
    private function userHasRole(User $user, string $roleName): bool
    {
        // Check if the role relationship exists and the name matches.
        return $user->role && $user->role->name === $roleName;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Folder $folder): bool
    {
        // A super_admin can restore any folder.
        if ($this->userHasRole($user, 'super_admin')) {
            return true;
        }

        // An admin_devisi can restore any folder within their division.
        if ($this->userHasRole($user, 'admin_devisi') && $user->division_id === $folder->division_id) {
            return true;
        }

        // The original creator can restore their own folder.
        return $user->id === $folder->user_id;
    }
}
