<?php

namespace App\Models;

use App\Support\Phone;
use Carbon\CarbonInterface;
use Database\Factories\DoctorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $user_id
 * @property string $code
 * @property string $title
 * @property string|null $whatsapp
 * @property string|null $specialty
 * @property string|null $notes
 * @property string|null $deletion_batch_id
 * @property bool|null $user_was_active
 * @property string|null $link_token
 * @property CarbonInterface|null $deleted_at
 * @property-read User $user
 * @property-read string $display_name
 */
#[Fillable(['user_id', 'code', 'title', 'whatsapp', 'specialty', 'notes'])]
class Doctor extends Model
{
    /** @use HasFactory<DoctorFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Branch, $this>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }

    /**
     * @return HasMany<MedicalCase, $this>
     */
    public function cases(): HasMany
    {
        return $this->hasMany(MedicalCase::class);
    }

    protected function casts(): array
    {
        return ['user_was_active' => 'boolean'];
    }

    /** Last day a deleted doctor can still be restored. */
    public function restorableUntil(): ?CarbonInterface
    {
        return $this->deleted_at?->addDays((int) config('radiology.doctor_restore_days', 30));
    }

    public function isRestorable(): bool
    {
        return $this->deleted_at !== null && $this->restorableUntil()?->isFuture() === true;
    }

    /** The doctor's private cases link; created on first use. */
    public function linkUrl(): string
    {
        if (! $this->link_token) {
            $this->forceFill(['link_token' => MedicalCase::newShareToken()])->save();
        }

        return route('doctor-link.index', $this->link_token);
    }

    public function regenerateLink(): void
    {
        $this->forceFill(['link_token' => MedicalCase::newShareToken()])->save();
    }

    public function revokeLink(): void
    {
        $this->forceFill(['link_token' => null])->save();
    }

    public function linkWhatsappUrl(): string
    {
        $message = implode("\n", [
            'مرحبًا '.$this->display_name.'،',
            'هذا رابطك الخاص لعرض كل حالاتك وملفات الأشعة في '.Setting::get('center_name').':',
            $this->linkUrl(),
            '',
            'احفظ الرابط؛ تظهر فيه الحالات الجديدة تلقائيًا. لا تشاركه مع أحد.',
        ]);

        return Phone::whatsappUrl($this->whatsappNumber(), $message);
    }

    /**
     * Cases reachable from the doctor's link: the same set the doctor sees in the portal.
     *
     * @return Builder<MedicalCase>
     */
    public function linkCases(): Builder
    {
        return MedicalCase::withoutGlobalScope('access')
            ->where('doctor_id', $this->id)
            ->whereIn('branch_id', $this->branches()->pluck('branches.id'))
            ->whereHas('patient', fn ($q) => $q->withoutGlobalScope('access'));
    }

    public function getDisplayNameAttribute(): string
    {
        return trim($this->title.' '.$this->user->name);
    }

    /**
     * Number used for WhatsApp links: explicit WhatsApp number, else the login phone.
     */
    public function whatsappNumber(): ?string
    {
        return $this->whatsapp ?: $this->user->phone;
    }

    /**
     * @param  Builder<Doctor>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereHas('user', fn (Builder $q) => $q->where('is_active', true));
    }

    /**
     * @param  Builder<Doctor>  $query
     */
    public function scopeOrderByName(Builder $query): void
    {
        $query->orderBy(User::select('name')->whereColumn('users.id', 'doctors.user_id'));
    }
}
