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
     * @return Collection<int, array{name: string, phone: string}>
     */
    public static function for(User $actor, string $audience, ?int $branchId = null, ?string $since = null): Collection
    {
        return self::people($actor, $audience, $branchId, $since)
            ->filter(fn ($r) => Phone::isMobile($r['phone']))
            ->unique(fn ($r) => Phone::toInternational($r['phone']))
            ->values()
            ->map(fn ($r) => ['name' => $r['name'], 'phone' => $r['phone']]);
    }

    /**
     * Same selection, but the people the campaign will miss because their number
     * is not a mobile (landline, a digit short or extra), so staff can fix them.
     *
     * @return Collection<int, array{name: string, phone: string, edit_url: string}>
     */
    public static function unreachable(User $actor, string $audience, ?int $branchId = null, ?string $since = null): Collection
    {
        return self::people($actor, $audience, $branchId, $since)
            ->reject(fn ($r) => Phone::isMobile($r['phone']))
            ->values();
    }

    /** @return Collection<int, array{name: string, phone: string, edit_url: string}> */
    private static function people(User $actor, string $audience, ?int $branchId, ?string $since): Collection
    {
        if ($audience === 'doctors') {
            return AccessScope::doctors($actor)->active()->with('user')
                ->when($branchId, fn ($q) => $q->whereHas('branches', fn ($b) => $b->where('branches.id', $branchId)))
                ->orderByName()->get()
                ->map(fn ($d) => self::row((string) $d->display_name, (string) $d->whatsappNumber(), route('doctors.edit', $d)));
        }

        return AccessScope::patients($actor)->whereNotNull('phone')->where('phone', '!=', '')
            ->when($branchId, fn ($q) => $q->whereHas('cases', fn ($c) => $c->where('branch_id', $branchId)))
            ->when($since, fn ($q) => $q->whereHas('cases', fn ($c) => $c->whereDate('exam_date', '>=', $since)))
            ->orderBy('name')->get(['id', 'name', 'phone'])
            ->map(fn ($p) => self::row((string) ($p->name ?: 'عميلنا العزيز'), (string) $p->phone, route('patients.edit', $p)));
    }

    /** @return array{name: string, phone: string, edit_url: string} */
    private static function row(string $name, string $phone, string $editUrl): array
    {
        return ['name' => $name, 'phone' => $phone, 'edit_url' => $editUrl];
    }
}
