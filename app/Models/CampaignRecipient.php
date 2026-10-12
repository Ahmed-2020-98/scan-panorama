<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $campaign_id
 * @property string $name
 * @property string $phone
 * @property CarbonInterface|null $sent_at
 * @property int|null $sent_by
 * @property-read Campaign $campaign
 */
#[Fillable(['campaign_id', 'name', 'phone', 'sent_at', 'sent_by'])]
class CampaignRecipient extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
