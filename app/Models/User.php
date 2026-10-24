<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Role;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
 * @property string|null $font
 * @property int|null $shift_rate_minor
 * @property array<int, int>|null $work_days
 * @property string|null $work_from
 * @property string|null $work_until
 * @property Carbon|null $open_until
 * @property-read Collection<int, Branch> $branches
 */
#[Fillable(['name', 'email', 'phone', 'role', 'is_active', 'password', 'branch_id', 'permission_overrides', 'font', 'shift_rate_minor', 'work_days', 'work_from', 'work_until', 'open_until'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, \Illuminate\Database\Eloquent\SoftDeletes, Notifiable, TwoFactorAuthenticatable;

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
            'shift_rate_minor' => 'integer',
            'permission_overrides' => 'array',
            'work_days' => 'array',
            'open_until' => 'datetime',
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
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    /**
     * Branches a staff member works in (branch_id is the last one used).
     *
     * @return BelongsToMany<Branch, $this>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }

    /**
     * Users working in a branch, through their branches or their single/last-used branch.
     *
     * @param  Builder<User>  $query
     */
    public function scopeInBranch(Builder $query, int $branchId): void
    {
        $query->where(fn (Builder $q) => $q->where('branch_id', $branchId)->orWhereHas('branches', fn (Builder $b) => $b->whereKey($branchId)));
    }

    /** Day names by Carbon dayOfWeek, and the order the week is shown in (Saturday first). */
    public const WEEK_DAYS = [0 => 'الأحد', 1 => 'الإثنين', 2 => 'الثلاثاء', 3 => 'الأربعاء', 4 => 'الخميس', 5 => 'الجمعة', 6 => 'السبت'];

    public const WEEK_ORDER = [6, 0, 1, 2, 3, 4, 5];

    /** Reception, marketing and technicians can be limited to set days and hours; managers and doctors never are. */
    public function hasWorkHours(): bool
    {
        return $this->hasRole(Role::Reception, Role::Marketing, Role::Technician);
    }

    public function canWorkNow(?CarbonInterface $at = null): bool
    {
        if (! $this->hasWorkHours()) {
            return true;
        }
        $at = ($at ?? now())->copy()->setTimezone((string) config('app.timezone'));
        if ($this->open_until && $this->open_until->greaterThan($at)) {
            return true;
        }
        if ($this->work_days && ! in_array($at->dayOfWeek, array_map('intval', $this->work_days), true)) {
            return false;
        }
        $time = $at->format('H:i');

        return ! ($this->work_from && $time < substr($this->work_from, 0, 5))
            && ! ($this->work_until && $time >= substr($this->work_until, 0, 5));
    }

    /** E.g. «الإثنين والخميس من 9:00 ص لـ 5:00 م», or null when the account is always open. */
    public function workScheduleLabel(): ?string
    {
        if (! $this->hasWorkHours() || (! $this->work_days && ! $this->work_from && ! $this->work_until)) {
            return null;
        }
        $days = $this->work_days
            ? collect(self::WEEK_ORDER)->intersect(array_map('intval', $this->work_days))->map(fn ($d) => self::WEEK_DAYS[$d])->join('، ', ' و')
            : 'كل الأيام';
        $clock = function (string $time): string {
            [$h, $m] = array_map('intval', explode(':', $time));

            return (($h % 12) ?: 12).':'.str_pad((string) $m, 2, '0', STR_PAD_LEFT).($h < 12 ? ' ص' : ' م');
        };
        $hours = match (true) {
            $this->work_from && $this->work_until => ' من '.$clock($this->work_from).' لـ '.$clock($this->work_until),
            (bool) $this->work_from => ' من '.$clock($this->work_from),
            (bool) $this->work_until => ' لحد '.$clock($this->work_until),
            default => '',
        };

        return $days.$hours;
    }

    public function hasPermission(Permission $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->isAdmin()) {
            return true;
        }
        // Doctors and technicians are outside the center's operations: they only ever see and upload.
        if (! $this->isStaff() && ! in_array($permission, [Permission::ViewCases, Permission::UploadFiles], true)) {
            return false;
        }
        if ($permission === Permission::ApplyDiscount) {
            return false;
        }
        $override = ($this->permission_overrides ?? [])[$permission->value] ?? null;
        if (is_bool($override)) {
            return $override;
        }

        return in_array($permission->value, $this->role->defaultPermissions(), true);
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
     * The user's interface font key, falling back to the center default.
     */
    public function fontKey(): string
    {
        $fonts = (array) config('radiology.fonts');

        return $this->font && isset($fonts[$this->font]) ? $this->font : (string) config('radiology.default_font');
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
