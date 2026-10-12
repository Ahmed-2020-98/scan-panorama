<?php

namespace App\Services;

use App\Models\User;
use App\Support\AccessScope;
use App\Support\Phone;
use Illuminate\Support\Collection;

/**
 * The people a campaign can reach: patients or doctors in the actor's
 * branches with a WhatsApp-capable number, one row per number.
 */
class CampaignAudience
{
    /**
     * @return Collection<int, array<string, string>>
     */
    public static function for(User $actor, string $audience, ?int $branchId = null, ?string $since = null): Collection
    {
        return collect(self::list($actor, $audience, $branchId, $since));
    }

    /** @return array<int, array<string, string>> */
    private static function list(User $actor, string $audience, ?int $branchId, ?string $since): array
    {
        if ($audience === 'doctors') {
            $rows = AccessScope::doctors($actor)->active()->with('user')
                ->when($branchId, fn ($q) => $q->whereHas('branches', fn ($b) => $b->where('branches.id', $branchId)))
                ->orderByName()->get()
                ->map(fn ($d) => ['name' => $d->display_name, 'phone' => (string) $d->whatsappNumber()]);
        } else {
            $rows = AccessScope::patients($actor)->whereNotNull('phone')->where('phone', '!=', '')
                ->when($branchId, fn ($q) => $q->whereHas('cases', fn ($c) => $c->where('branch_id', $branchId)))
                ->when($since, fn ($q) => $q->whereHas('cases', fn ($c) => $c->whereDate('exam_date', '>=', $since)))
                ->orderBy('name')->get(['name', 'phone'])
                ->map(fn ($p) => ['name' => (string) ($p->name ?: 'عميلنا العزيز'), 'phone' => (string) $p->phone]);
        }

        return $rows->filter(fn ($r) => Phone::isMobile($r['phone']))
            ->unique(fn ($r) => Phone::toInternational($r['phone']))
            ->values()->all();
    }
}
