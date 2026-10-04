<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property Role $role
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Doctor|null $doctor
 * @property int|null $branch_id
 * @property array<string, bool>|null $permission_overrides
 */
#[Fillable(['name', 'email', 'phone', 'role', 'is_active', 'password', 'branch_id', 'permission_overrides'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'permission_overrides' => 'array',
        ];
    }

    /**
     * @return HasOne<Doctor, $this>
     */
    public function doctor(): HasOne
    {
        return $this->hasOne(Doctor::class);
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isTechnician(): bool
    {
        return $this->role === Role::Technician;
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function hasPermission(Permission $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($permission === Permission::ViewFinancials && ! $this->isStaff()) {
            return false;
        }
        if ($this->isAdmin()) {
            return true;
        }
        // Role boundaries remain hard constraints even with per-user grants.
        if (in_array($permission, [Permission::ManageUsers, Permission::DeleteCase, Permission::ManageBranches, Permission::ManageExamTypes], true)) {
            return false;
        }
        if (! $this->isStaff() && in_array($permission, [Permission::CreateCase, Permission::EditCase, Permission::ShareCase, Permission::ApplyDiscount, Permission::ManageBranches, Permission::ManageExamTypes, Permission::ManageVisits], true)) {
            return false;
        }
        $override = ($this->permission_overrides ?? [])[$permission->value] ?? null;
        if (is_bool($override)) {
            return $override;
        }
        $defaults = $this->isStaff()
            ? ['view_cases', 'create_case', 'edit_case', 'upload_files', 'share_case', 'view_financials']
            : ['view_cases', 'upload_files'];

        return in_array($permission->value, $defaults, true);
    }

    public function isStaff(): bool
    {
        return $this->hasRole(...Role::staff());
    }

    public function isDoctor(): bool
    {
        return $this->role === Role::Doctor;
    }

    /**
     * Landing page after login, based on the user's role.
     */
    public function homeUrl(): string
    {
        return $this->isDoctor() ? route('portal') : ($this->isTechnician() ? route('technician') : route('dashboard'));
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeStaff(Builder $query): void
    {
        $query->whereIn('role', array_map(fn (Role $role) => $role->value, Role::staff()));
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
