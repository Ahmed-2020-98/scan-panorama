<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\DoctorVisit;
use App\Models\User;
use App\Notifications\CenterAlert;
use App\Support\AccessScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Daily reminders for the admin and the marketing officers: doctors who
 * suddenly stopped or dropped their referrals, and visits that are due.
 */
class CenterAlerts
{
    /** Run at most once a day, from the scheduler or lazily from a page load. */
    public static function runDaily(): void
    {
        if (Cache::add('center-alerts-'.today()->toDateString(), true, now()->addDay())) {
            self::run();
        }
    }

    public static function run(): int
    {
        $sent = 0;
        foreach (self::recipients() as $user) {
            $rows = DoctorPerformance::rows($user);
            foreach ($rows->whereIn('status', ['stopped', 'declining']) as $row) {
                $doctor = $row['doctor'];
                $body = $row['status'] === 'stopped'
                    ? 'لم يرسل أي حالة منذ '.$row['quiet_days'].' يومًا (آخر حالة '.$row['last_case']?->format('d/m/Y').').'
                    : 'حالات آخر 30 يومًا: '.$row['last30'].' مقابل '.$row['prev30'].' في الشهر السابق.';
                $sent += self::notify($user, 'doctor-'.$row['status'].'-'.$doctor->id, $row['status'] === 'stopped' ? 'stopped' : 'declining',
                    ($row['status'] === 'stopped' ? 'توقف عن الإرسال: ' : 'تراجع في الحالات: ').$doctor->display_name, $body, route('visits.index', ['doctor' => $doctor->id]));
            }
            $due = DoctorVisit::with('doctor.user')->pendingFollowup()->whereDate('next_visit_at', '<=', today())
                ->when(! $user->isAdmin(), fn ($q) => $q->whereIn('branch_id', AccessScope::branchIds($user)))->get();
            foreach ($due as $visit) {
                $late = (int) $visit->next_visit_at->diffInDays(today());
                $sent += self::notify($user, 'visit-due-'.$visit->id, 'visit',
                    'زيارة مستحقة: '.($visit->doctor->display_name ?? 'طبيب'),
                    $late === 0 ? 'موعد الزيارة اليوم.' : 'متأخرة '.$late.' يوم عن موعدها '.$visit->next_visit_at->format('d/m/Y').'.', route('visits.index'));
            }
        }

        return $sent;
    }

    /** Notify the admins and the marketing officers (for their branch) about something. */
    public static function broadcast(string $key, string $kind, string $title, string $body, ?string $url = null, ?int $branchId = null): void
    {
        foreach (self::recipients() as $user) {
            if ($branchId && ! $user->isAdmin() && ! in_array($branchId, AccessScope::branchIds($user), true)) {
                continue;
            }
            self::notify($user, $key, $kind, $title, $body, $url, 0);
        }
    }

    /** @return Collection<int, User> */
    public static function recipients(): Collection
    {
        return User::where('is_active', true)->whereIn('role', [Role::Admin->value, Role::Marketing->value])->get();
    }

    private static function notify(User $user, string $key, string $kind, string $title, string $body, ?string $url, int $quietDays = 7): int
    {
        if ($quietDays > 0 && $user->notifications()->where('data->key', $key)->where('created_at', '>=', now()->subDays($quietDays))->exists()) {
            return 0;
        }
        $user->notify(new CenterAlert($key, $kind, $title, $body, $url));

        return 1;
    }
}
