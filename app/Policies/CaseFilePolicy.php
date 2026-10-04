<?php

namespace App\Policies;

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
        return $user->isAdmin();
    }
}
