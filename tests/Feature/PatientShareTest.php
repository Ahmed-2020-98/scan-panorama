<?php

namespace Tests\Feature;

use App\Livewire\CaseSharePanel;
use App\Models\CaseFile;
use App\Models\MedicalCase;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PatientShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_link_shows_ready_files_without_doctor_notes(): void
    {
        $case = MedicalCase::factory()->create(['notes_for_doctor' => 'ملاحظة سرية للطبيب', 'medical_notes' => 'تشخيص داخلي']);
        $case->patient->update(['phone' => '01012345678']);
        Storage::disk('local')->put('cases/p/report.pdf', '%PDF-1.4');
        $file = CaseFile::factory()->create(['medical_case_id' => $case->id, 'path' => 'cases/p/report.pdf', 'mime' => 'application/pdf', 'original_name' => 'report-ready.pdf']);
        $other = MedicalCase::factory()->create();

        $url = $case->patientShareUrl();
        $this->assertNotSame($case->fresh()->share_token, $case->fresh()->patient_share_token);

        $this->get($url)->assertOk()->assertSee('report-ready.pdf')->assertSee($case->patient->name)
            ->assertDontSee('ملاحظة سرية للطبيب')->assertDontSee('تشخيص داخلي')->assertDontSee('كود الطبيب');
        $this->get(route('patient-share.file', [$case->fresh()->patient_share_token, $file, 'download']))->assertOk();
        $this->get(route('patient-share.file', [$other->patientShareUrl() ? $other->fresh()->patient_share_token : '', $file, 'download']))->assertNotFound();
        $this->get(route('shared.file', [$case->fresh()->patient_share_token, $file, 'view']))->assertNotFound();
        $this->assertSame(0, $case->fresh()->open_count, 'patient visits must not count as the doctor opening the case');
    }

    public function test_whatsapp_link_targets_the_patient_number_with_the_patient_link(): void
    {
        $case = MedicalCase::factory()->create();
        $case->patient->update(['phone' => '01012345678']);

        $url = (string) $case->fresh()->patientWhatsappUrl();
        $this->assertStringStartsWith('https://wa.me/201012345678?text=', $url);
        $this->assertStringContainsString(rawurlencode($case->fresh()->patientShareUrl()), $url);
    }

    public function test_invalid_or_missing_phone_has_no_whatsapp_link(): void
    {
        $case = MedicalCase::factory()->create();
        foreach ([null, '0101', '010123', '01012345678999'] as $phone) {
            $case->patient->update(['phone' => $phone]);
            $this->assertNull($case->fresh()->patientWhatsappUrl(), (string) $phone);
        }
        $this->assertTrue(Phone::isMobile('+966 50 123 4567'));
    }

    public function test_revoking_or_regenerating_the_case_link_kills_the_patient_link(): void
    {
        $case = MedicalCase::factory()->create();
        $old = $case->patientShareUrl();

        $case->revokeShareLink();
        $this->get($old)->assertStatus(410)->assertDontSee('دخول الأطباء');

        $case->regenerateShareLink();
        $this->get($old)->assertNotFound();
        $this->get($case->fresh()->patientShareUrl())->assertOk();
    }

    public function test_share_panel_shows_patient_whatsapp_button(): void
    {
        $case = MedicalCase::factory()->create();
        $case->patient->update(['phone' => '01012345678']);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CaseSharePanel::class, ['medicalCase' => $case])->assertSee('واتساب المريض')->call('markPatientWhatsapp');
        $this->assertDatabaseHas('activity_logs', ['action' => 'share.patient_whatsapp']);
    }
}
