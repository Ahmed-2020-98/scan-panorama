<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Doctor;
use App\Models\ExamType;
use App\Models\MedicalCase;
use App\Models\Patient;
use App\Models\User;
use App\Support\AccessScope;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CaseRegistration
{
    /** @param array<string, mixed> $data */
    public static function save(User $actor, array $data, ?MedicalCase $case = null): MedicalCase
    {
        Gate::forUser($actor)->authorize($case ? 'update' : 'create', $case ?? MedicalCase::class);
        $data += array_fill_keys(['branch_id', 'exam_date', 'doctor_id', 'technician_id', 'exam_type_id', 'notes_internal', 'notes_for_doctor'], null);
        $data['branch_id'] = $actor->isAdmin() ? ($data['branch_id'] ?: Branch::active()->value('id')) : $actor->branch_id;
        $data['exam_date'] = $data['exam_date'] ?: today()->toDateString();
        $data = Validator::make($data, [
            'patient_id' => ['nullable', 'integer'], 'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'], 'gender' => ['nullable', Rule::in(array_keys(Patient::GENDERS))],
            'birth_year' => ['nullable', 'integer', 'min:'.(now()->year - 130), 'max:'.now()->year],
            'branch_id' => ['required', Rule::exists('branches', 'id')],
            'doctor_id' => ['nullable', Rule::exists('doctors', 'id')],
            'technician_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'technician')->where('is_active', true)],
            'exam_type_id' => ['nullable', Rule::exists('exam_types', 'id')],
            'exam_date' => ['required', 'date', 'before_or_equal:'.today()->addDay()->toDateString()],
            'notes_internal' => ['nullable', 'string', 'max:5000'], 'notes_for_doctor' => ['nullable', 'string', 'max:5000'],
            'case_code' => ['nullable', 'string', 'max:30', Rule::unique('medical_cases', 'case_code')->ignore($case?->id)],
        ])->validate();
        $branch = AccessScope::branches($actor)->active()->whereKey($data['branch_id'])->firstOrFail();
        if (! empty($data['doctor_id'])) {
            $doctor = Doctor::active()->whereKey($data['doctor_id'])->firstOrFail();
            abort_unless($doctor->branches()->whereKey($branch->id)->exists(), 422, 'الطبيب غير مرتبط بالفرع المختار.');
        }
        if (! empty($data['technician_id'])) {
            abort_unless(User::whereKey($data['technician_id'])->where('branch_id', $branch->id)->exists(), 422, 'الفني غير مرتبط بالفرع المختار.');
        }
        if (! empty($data['exam_type_id'])) {
            $exam = ExamType::active()->whereKey($data['exam_type_id'])->firstOrFail();
            $override = $exam->branches()->whereKey($branch->id)->first();
            abort_if($override && ! $override->pivot->getAttribute('is_active'), 422, 'الفحص غير متاح في هذا الفرع.');
        }

        return DB::transaction(function () use ($actor, $data, $case, $branch) {
            $patient = $case ? Patient::whereKey($case->patient_id)->firstOrFail() : (! empty($data['patient_id']) ? AccessScope::patients($actor)->whereKey($data['patient_id'])->firstOrFail() : null);
            if (! $patient) {
                $name = trim((string) ($data['name'] ?? ''));
                $patient = Patient::create(['name' => $name ?: 'بيانات المريض غير مكتملة', 'identity_incomplete' => $name === '', 'phone' => ($data['phone'] ?? '') ?: null, 'gender' => ($data['gender'] ?? '') ?: null, 'birth_date' => empty($data['birth_year']) ? null : $data['birth_year'].'-01-01', 'birth_year_only' => ! empty($data['birth_year']), 'created_by' => $actor->id]);
                $patient->branches()->syncWithoutDetaching([$branch->id]);
                ActivityLogger::log('patient.created', $patient);
            }
            $attributes = ['patient_id' => $patient->id, 'branch_id' => $branch->id, 'doctor_id' => $data['doctor_id'] ?: null, 'technician_id' => $data['technician_id'] ?: null, 'exam_type_id' => $data['exam_type_id'] ?: null, 'exam_date' => $data['exam_date'], 'notes_internal' => $data['notes_internal'] ?: null, 'notes_for_doctor' => $data['notes_for_doctor'] ?: null];
            if ($case) {
                $case = MedicalCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
                if ($case->payments()->exists() && $case->branch_id !== $branch->id) {
                    abort(422, 'لا يمكن نقل حالة بها تحصيل إلى فرع آخر.');
                }
                if ($actor->isAdmin() && ! empty($data['case_code'])) {
                    $attributes['case_code'] = $data['case_code'];
                }
                $before = $case->only(array_keys($attributes));
                $case->fill($attributes)->save();
                ActivityLogger::log('case.updated', $case, ['before' => $before, 'after' => $case->only(array_keys($attributes))]);
            } else {
                $case = MedicalCase::create($attributes + ['created_by' => $actor->id]);
                ActivityLogger::log('case.created', $case);
            }
            $patient->branches()->syncWithoutDetaching([$branch->id]);

            return $case;
        });
    }
}
