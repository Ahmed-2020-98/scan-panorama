<?php

namespace App\Services;

use App\Enums\WorkflowStatus;
use App\Models\MedicalCase;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CaseClinicalUpdates
{
    /** @param array<string, mixed> $data */
    public static function update(User $actor, MedicalCase $case, array $data): void
    {
        Gate::forUser($actor)->authorize('clinicalUpdate', $case);
        $rules = $actor->isDoctor() ? ['medical_notes' => ['nullable', 'string', 'max:10000']] : [
            'technical_notes' => ['nullable', 'string', 'max:10000'],
            'workflow_status' => ['required', Rule::enum(WorkflowStatus::class)],
        ];
        abort_if(array_diff(array_keys($data), array_keys($rules)) !== [], 403);
        $validated = Validator::make($data, $rules)->validate();
        DB::transaction(function () use ($case, $validated) {
            $before = $case->only(array_keys($validated));
            $case->fill($validated)->save();
            ActivityLogger::log('case.clinical_updated', $case, ['before' => $before, 'after' => $validated]);
        });
    }
}
