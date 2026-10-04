<?php

namespace App\Services;

use App\Enums\Permission;
use App\Models\Branch;
use App\Models\ExamType;
use App\Models\MedicalCase;
use App\Models\Setting;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CasePricing
{
    public static function quote(ExamType $exam, Branch $branch): ?int
    {
        $override = $exam->branches()->where('branches.id', $branch->id)->first();

        return $override?->pivot->base_price_minor ?? $exam->base_price_minor;
    }

    public static function apply(User $actor, MedicalCase $case, int $baseMinor, int $finalMinor, ?string $reason): void
    {
        Gate::forUser($actor)->authorize('update', $case);
        if ($baseMinor < 0 || $finalMinor < 0 || $finalMinor > $baseMinor) {
            throw ValidationException::withMessages(['final_price' => 'السعر النهائي يجب أن يكون بين صفر والسعر الأساسي.']);
        }
        if ($finalMinor < $baseMinor) {
            abort_unless($actor->hasPermission(Permission::ApplyDiscount), 403);
            if (! trim((string) $reason)) {
                throw ValidationException::withMessages(['discount_reason' => 'اذكر سبب الخصم.']);
            }
        }
        DB::transaction(function () use ($actor, $case, $baseMinor, $finalMinor, $reason) {
            $locked = MedicalCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            if ($locked->payments()->sum('amount_minor') > $finalMinor) {
                throw ValidationException::withMessages(['final_price' => 'السعر أقل من المقبوض. سجّل رد المبلغ أولًا من الحسابات.']);
            }
            $expected = $locked->base_price_minor ?? ($locked->examType ? self::quote($locked->examType, $locked->branch) : null);
            if (! $actor->isAdmin() && $expected !== null && $baseMinor !== $expected) {
                abort(403);
            }
            if ($locked->base_price_minor !== null && $locked->base_price_minor !== $baseMinor && ! $actor->isAdmin()) {
                abort(403);
            }
            $currency = Setting::get('currency');
            if (! in_array($currency, ['EGP', 'SAR', 'USD', 'EUR', 'AED'], true)) {
                throw ValidationException::withMessages(['final_price' => 'يحدد المدير العملة من إعدادات المركز أولًا.']);
            }
            $before = $locked->only(['base_price_minor', 'final_price_minor', 'discount_minor', 'discount_reason', 'currency']);
            $locked->fill(['base_price_minor' => $baseMinor, 'final_price_minor' => $finalMinor, 'discount_minor' => $baseMinor - $finalMinor, 'discount_reason' => $finalMinor < $baseMinor ? $reason : null, 'discount_by' => $finalMinor < $baseMinor ? $actor->id : null, 'discount_at' => $finalMinor < $baseMinor ? now() : null, 'currency' => $locked->currency ?? $currency])->save();
            $after = $locked->only(array_keys($before));
            if ($before !== $after) {
                ActivityLogger::log($finalMinor < $baseMinor ? 'price.discounted' : 'price.updated', $locked, ['before' => $before, 'after' => $after]);
            }
            $case->refresh();
        });
    }
}
