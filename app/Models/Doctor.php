<?php

namespace App\Models;

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
