<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A WhatsApp message prepared for many patients or doctors; staff send it
 * one by one from their own WhatsApp (no paid API).
 *
 * @property int $id
 * @property string $audience
 * @property string $title
 * @property string $message
 * @property string|null $image_path
 * @property int|null $branch_id
 * @property int|null $created_by
 * @property CarbonInterface $created_at
 * @property-read Branch|null $branch
 * @property-read User|null $creator
 */
#[Fillable(['audience', 'title', 'message', 'image_path', 'branch_id', 'created_by'])]
class Campaign extends Model
{
    public const AUDIENCES = ['patients' => 'المرضى', 'doctors' => 'الأطباء'];

    /** @return HasMany<CampaignRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /** Public URL of the picture sent with the message, if any. */
    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    /** The message with the recipient's name filled in. */
    public function messageFor(string $name): string
    {
        return str_replace('{الاسم}', $name, $this->message);
    }
}
