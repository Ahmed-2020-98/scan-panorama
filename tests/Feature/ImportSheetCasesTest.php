<?php

namespace Tests\Feature;

use App\Enums\WorkflowStatus;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\ExamType;
use App\Models\MedicalCase;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportSheetCasesTest extends TestCase
{
    use RefreshDatabase;

    private string $csv;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->admin()->create();
        Setting::put(['currency' => 'EGP']);
        Branch::factory()->create(['name' => 'الجماليه ']);
        ExamType::factory()->create(['name' => '2D - panorama']);
        ExamType::factory()->create(['name' => '3D CBCT - Quadrant']);
        $doctor = Doctor::factory()->create();
        $doctor->user->update(['name' => 'سامي فؤاد الريس']);

        $this->csv = tempnam(sys_get_temp_dir(), 'cases');
        file_put_contents($this->csv, implode("\n", [
            ',اسم الحاله,السن,تاريخ,رقم الحاله,,اسم الدكتور ,اسم الفحص,السعر,المتبقي ,حاله التسليم,Documents,Referral,Dicom,المصروفات ',
            ',,,,,,,,,,,,,,',
            'PG-21,منى عادل حسن ,30,2025-09-27,1060000001,📱 واتساب,سامي فؤاد,PANORAMA,150,50,تم,,,,',
            'PG-22,كريم سعيد,,29/6/2026,,📱 واتساب,طبيب غير مسجل,CBCT LR,م,,لم يتم,,,,',
            'PG-23,منى عادل حسن,31,30-10 - 2025,1060000001,📱 واتساب,,cbct,450,,,,,,شاي 20',
            'PG-24,YY,6,2026-06-20,Y,,,,,,,,,,',
            'PG-25,هالة ممدوح,,غير معروف,,,,,,,,,,,',
        ]));
    }

    protected function tearDown(): void
    {
        @unlink($this->csv);
        parent::tearDown();
    }

    public function test_imports_sheet_rows_with_doctors_exams_and_payments(): void
    {
        $this->artisan('cases:import-sheet', ['csv' => $this->csv, '--branch' => 'الجماليه'])->assertSuccessful();

        $this->assertSame(3, MedicalCase::count());
        $this->assertSame(2, Patient::count());

        $first = MedicalCase::where('case_code', 'PG-21')->firstOrFail();
        $this->assertSame('2025-09-27', $first->exam_date->toDateString());
        $this->assertSame('سامي فؤاد الريس', $first->doctor->user->name);
        $this->assertSame('2D - panorama', $first->examType->name);
        $this->assertSame(15000, $first->final_price_minor);
        $this->assertSame(WorkflowStatus::Completed, $first->workflow_status);
        $this->assertSame('01060000001', $first->patient->phone);
        $this->assertSame(1995, $first->patient->birth_date->year);
        $this->assertSame(10000, (int) Payment::where('medical_case_id', $first->id)->sum('amount_minor'));

        $second = MedicalCase::where('case_code', 'PG-22')->firstOrFail();
        $this->assertNull($second->doctor_id);
        $this->assertNull($second->final_price_minor);
        $this->assertSame('3D CBCT - Quadrant', $second->examType->name);
        $this->assertSame(WorkflowStatus::New, $second->workflow_status);
        $this->assertStringContainsString('الطبيب: طبيب غير مسجل', $second->notes_internal);
        $this->assertStringContainsString('السعر: م', $second->notes_internal);

        $third = MedicalCase::where('case_code', 'PG-23')->firstOrFail();
        $this->assertSame('2025-10-30', $third->exam_date->toDateString());
        $this->assertNull($third->exam_type_id);
        $this->assertSame($first->patient_id, $third->patient_id);
        $this->assertStringContainsString('المصروفات: شاي 20', $third->notes_internal);
    }

    public function test_dry_run_writes_nothing_and_wipe_replaces_existing_cases(): void
    {
        $old = MedicalCase::factory()->create();
        Payment::create(['medical_case_id' => $old->id, 'branch_id' => $old->branch_id, 'amount_minor' => 100, 'currency' => 'EGP', 'method' => 'cash', 'collected_by' => User::first()->id, 'received_at' => now(), 'request_id' => 'old']);

        $this->artisan('cases:import-sheet', ['csv' => $this->csv, '--branch' => 'الجماليه', '--wipe' => true, '--dry-run' => true])->assertSuccessful();
        $this->assertSame(1, MedicalCase::count());

        $this->artisan('cases:import-sheet', ['csv' => $this->csv, '--branch' => 'الجماليه', '--wipe' => true, '--code-prefix' => 'MS'])->assertSuccessful();
        $this->assertSame(3, MedicalCase::count());
        $this->assertFalse(MedicalCase::whereKey($old->id)->exists());
        $this->assertFalse(Patient::whereKey($old->patient_id)->exists());
        $this->assertSame(['MS-00001', 'MS-00002', 'MS-00003'], MedicalCase::orderBy('case_code')->pluck('case_code')->all());
        $this->assertSame(2, Payment::count());
    }
}
