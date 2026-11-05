<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\CaseFile;
use App\Models\User;

class CaseFilePolicy
{
    public function view(User $user, CaseFile $file): bool
    {
        return ! $file->trashed() && $file->storage_status === 'ready' && $file->medicalCase !== null && $user->can('view', $file->medicalCase);
    }

    public function delete(User $user, CaseFile $file): bool
    {
        if ($file->medicalCase === null || ! $user->can('view', $file->medicalCase)) {
            return false;
        }

        // Doctors may remove only what they uploaded themselves; it still goes to the recycle bin.
        return $user->hasPermission(Permission::DeleteCase) || ($user->isDoctor() && $file->uploaded_by === $user->id);
    }
}
