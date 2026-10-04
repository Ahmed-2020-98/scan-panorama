<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Patient;
use App\Models\User;
use App\Support\AccessScope;

class PatientPolicy
{
    public function view(User $user, Patient $patient): bool
    {
        return $user->hasPermission(Permission::ViewCases) && AccessScope::patients($user)->whereKey($patient->id)->exists();
    }

    public function update(User $user, Patient $patient): bool
    {
        return $this->view($user, $patient) && $user->hasPermission(Permission::EditCase);
    }

    public function delete(User $user, Patient $patient): bool
    {
        return $user->isAdmin();
    }
}
