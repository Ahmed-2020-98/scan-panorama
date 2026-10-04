<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\MedicalCase;
use App\Models\User;
use App\Support\AccessScope;

class MedicalCasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewCases);
    }

    public function view(User $user, MedicalCase $case): bool
    {
        return $this->viewAny($user) && AccessScope::allowsCase($user, $case);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CreateCase) && ($user->isAdmin() || count(AccessScope::branchIds($user)) > 0);
    }

    public function update(User $user, MedicalCase $case): bool
    {
        return $this->view($user, $case) && $user->hasPermission(Permission::EditCase);
    }

    public function uploadFiles(User $user, MedicalCase $case): bool
    {
        return $this->view($user, $case) && $user->hasPermission(Permission::UploadFiles) && ! $case->archived_at;
    }

    public function share(User $user, MedicalCase $case): bool
    {
        return $this->view($user, $case) && $user->hasPermission(Permission::ShareCase);
    }

    public function clinicalUpdate(User $user, MedicalCase $case): bool
    {
        return $this->view($user, $case) && (! $user->isStaff() || $user->hasPermission(Permission::EditCase));
    }

    public function delete(User $user, MedicalCase $case): bool
    {
        return $user->isAdmin() && $this->view($user, $case);
    }
}
