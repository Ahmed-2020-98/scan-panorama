<?php

namespace Tests\Feature;

use App\Livewire\DoctorLinkPanel;
use App\Models\Branch;
use App\Models\CaseFile;
use App\Models\Doctor;
use App\Models\MedicalCase;
use App\Models\User;
use App\Services\RecordRecovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DoctorLinkTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Doctor, MedicalCase, CaseFile, MedicalCase} */
    private function scenario(): array
    {
        $branch = Branch::factory()->create();
        $doctor = Doctor::factory()->create();
        $doctor->branches()->attach($branch);
        $case = MedicalCase::factory()->create(['doctor_id' => $doctor->id, 'branch_id' => $branch->id]);
        Storage::disk('local')->put('cases/d/report.pdf', '%PDF-1.4');
        $file = CaseFile::factory()->create(['medical_case_id' => $case->id, 'path' => 'cases/d/report.pdf', 'mime' => 'application/pdf', 'original_name' => 'report.pdf']);
        $other = MedicalCase::factory()->create();

        return [$doctor, $case, $file, $other];
    }

    public function test_link_lists_only_the_doctors_cases_and_opens_them_without_login(): void
    {
        [$doctor, $case, $file, $other] = $this->scenario();
        $url = $doctor->linkUrl();

        $this->get($url)->assertOk()->assertSee($case->patient->name)->assertSee($case->case_code)->assertDontSee($other->case_code);
        $this->get(route('doctor-link.case', [$doctor->link_token, $case->id]))->assertOk()->assertSee('report.pdf');
        $this->get(route('doctor-link.file', [$doctor->link_token, $file, 'view']))->assertOk();
        $this->assertGreaterThanOrEqual(1, $case->fresh()->open_count, 'opening counts as the doctor opening the case');

        $this->get(route('doctor-link.case', [$doctor->link_token, $other->id]))->assertNotFound();
        $otherFile = CaseFile::factory()->create(['medical_case_id' => $other->id]);
        $this->get(route('doctor-link.file', [$doctor->link_token, $otherFile, 'view']))->assertNotFound();
        $this->get($url.'?q='.urlencode($case->case_code))->assertOk()->assertSee($case->case_code);
    }

    public function test_regenerate_revoke_disable_and_delete_stop_the_link(): void
    {
        [$doctor] = $this->scenario();
        $old = $doctor->linkUrl();

        $doctor->regenerateLink();
        $this->get($old)->assertNotFound();
        $this->get($doctor->fresh()->linkUrl())->assertOk();

        $doctor->user->update(['is_active' => false]);
        $this->get($doctor->fresh()->linkUrl())->assertNotFound();
        $doctor->user->update(['is_active' => true]);

        $current = $doctor->fresh()->linkUrl();
        RecordRecovery::delete(User::factory()->admin()->create(), $doctor->fresh());
        $this->get($current)->assertNotFound();
    }

    public function test_manager_panel_creates_link_and_whatsapp_targets_the_doctor(): void
    {
        [$doctor] = $this->scenario();
        $doctor->update(['whatsapp' => '01011112222']);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(DoctorLinkPanel::class, ['doctor' => $doctor])->assertSee('إنشاء الرابط')->call('create')->assertSee('إرسال للطبيب واتساب');
        $doctor = $doctor->fresh();
        $this->assertNotNull($doctor->link_token);
        $this->assertStringStartsWith('https://wa.me/201011112222?text=', $doctor->linkWhatsappUrl());
        $this->assertStringContainsString(rawurlencode($doctor->linkUrl()), $doctor->linkWhatsappUrl());

        Livewire::test(DoctorLinkPanel::class, ['doctor' => $doctor])->call('revoke');
        $this->assertNull($doctor->fresh()->link_token);
    }

    public function test_reception_cannot_manage_doctor_links(): void
    {
        [$doctor] = $this->scenario();
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(DoctorLinkPanel::class, ['doctor' => $doctor])->assertForbidden();
    }
}
