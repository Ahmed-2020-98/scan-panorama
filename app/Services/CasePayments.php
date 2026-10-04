<?php

namespace App\Services;

use App\Enums\Permission;
use App\Models\MedicalCase;
use App\Models\Payment;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CasePayments
{
    public static function receive(User $actor, MedicalCase $case, int $amountMinor, string $method, string $requestId): Payment
    {
        Gate::forUser($actor)->authorize('view', $case);
        abort_unless($actor->hasPermission(Permission::ViewFinancials) && $actor->isStaff(), 403);
        if (! $actor->isAdmin() && ! $case->exam_date->isToday()) {
            abort(403);
        }

        return DB::transaction(function () use ($actor, $case, $amountMinor, $method, $requestId) {
            $locked = MedicalCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            if ($existing = Payment::where('request_id', $requestId)->first()) {
                abort_unless($existing->medical_case_id === $case->id && $existing->collected_by === $actor->id && $existing->amount_minor === $amountMinor && $existing->method === $method, 409);

                return $existing;
            }
            if ($amountMinor <= 0 || ! in_array($method, ['cash', 'card', 'transfer'], true) || $locked->final_price_minor === null || $amountMinor + $locked->payments()->sum('amount_minor') > $locked->final_price_minor) {
                throw ValidationException::withMessages(['amount' => 'المبلغ غير صالح أو يتجاوز المتبقي. تأكد من تسعير الحالة أولًا.']);
            }
            $payment = $locked->payments()->create(['branch_id' => $locked->branch_id, 'amount_minor' => $amountMinor, 'currency' => $locked->currency, 'method' => $method, 'collected_by' => $actor->id, 'received_at' => now(), 'request_id' => $requestId]);
            ActivityLogger::log('payment.received', $locked, ['payment_id' => $payment->id, 'amount_minor' => $amountMinor, 'currency' => $payment->currency]);

            return $payment;
        });
    }

    public static function refund(User $actor, Payment $payment, int $amountMinor, string $reason, string $requestId): Payment
    {
        abort_unless($actor->isAdmin(), 403);

        return DB::transaction(function () use ($actor, $payment, $amountMinor, $reason, $requestId) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($existing = Payment::where('request_id', $requestId)->first()) {
                abort_unless($existing->adjustment_of_id === $payment->id && $existing->collected_by === $actor->id && $existing->amount_minor === -$amountMinor, 409);

                return $existing;
            }
            $refunded = -Payment::where('adjustment_of_id', $payment->id)->sum('amount_minor');
            if ($payment->amount_minor <= 0 || $amountMinor <= 0 || $amountMinor + $refunded > $payment->amount_minor || ! trim($reason)) {
                throw ValidationException::withMessages(['refundAmount' => 'تحقق من مبلغ الرد وسببه.']);
            }
            $result = Payment::create(['medical_case_id' => $payment->medical_case_id, 'branch_id' => $payment->branch_id, 'amount_minor' => -$amountMinor, 'currency' => $payment->currency, 'method' => $payment->method, 'collected_by' => $actor->id, 'received_at' => now(), 'request_id' => $requestId, 'adjustment_of_id' => $payment->id, 'reason' => $reason]);
            ActivityLogger::log('payment.refunded', MedicalCase::withoutGlobalScope('access')->withTrashed()->findOrFail($payment->medical_case_id), ['payment_id' => $result->id, 'original_id' => $payment->id, 'amount_minor' => $amountMinor, 'reason' => $reason]);

            return $result;
        });
    }
}
