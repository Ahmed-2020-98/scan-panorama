<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Doctor;
use App\Models\ExamType;
use App\Models\MedicalCase;
use App\Models\Patient;
use App\Models\User;
use App\Support\CodeGenerator;
use App\Support\Phone;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CaseManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
    }

    public function test_reception_can_create_a_case_with_a_new_patient(): void
    {
        $this->actingAs($user = User::factory()->reception()->create(['branch_id' => Branch::first()->id]));
        $doctor = Doctor::factory()->create();
        $branch = Branch::first();
        $doctor->branches()->attach($branch);

        Livewire::test('pages::admin.cases.form')
            ->set('patientMode', 'new')
            ->set('newPatient.name', 'مريض تجريبي')
            ->set('newPatient.phone', '01012345678')
            ->set('doctor_id', (string) $doctor->id)
            ->assertSet('branch_id', (string) $branch->id)
            ->set('exam_type_id', (string) ExamType::first()->id)
            ->set('exam_date', today()->toDateString())
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $case = MedicalCase::sole();
        $this->assertSame('مريض تجريبي', $case->patient->name);
        $this->assertSame($user->id, $case->created_by);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{5}$/', $case->case_code);
        $this->assertStringStartsWith('P-', $case->patient->file_number);
        $this->assertDatabaseHas('activity_logs', ['action' => 'case.created', 'medical_case_id' => $case->id]);
    }

    public function test_optional_demographics_create_an_incomplete_patient(): void
    {
        $this->actingAs(User::factory()->reception()->create(['branch_id' => Branch::first()->id]));
        Livewire::test('pages::admin.cases.form')->call('save')->assertHasNoErrors()->assertRedirect();
        $this->assertTrue(MedicalCase::sole()->patient->identity_incomplete);
    }

    public function test_only_admins_can_change_the_case_code(): void
    {
        $case = MedicalCase::factory()->create();
        $case->doctor->branches()->syncWithoutDetaching([$case->branch_id]);

        $this->actingAs(User::factory()->reception()->create(['branch_id' => $case->branch_id]));
        Livewire::test('pages::admin.cases.form', ['case' => $case])
            ->set('case_code', 'HACKED')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertNotSame('HACKED', $case->refresh()->case_code);

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test('pages::admin.cases.form', ['case' => $case])
            ->set('case_code', 'LEGACY-001')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('LEGACY-001', $case->refresh()->case_code);
    }

    public function test_codes_are_sequential_and_ignore_manual_codes(): void
    {
        $year = now()->format('Y');

        $first = MedicalCase::factory()->create();
        MedicalCase::factory()->create(['case_code' => "{$year}-LEGACY"]);

        $this->assertSame("{$year}-00001", $first->case_code);
        $this->assertSame("{$year}-00002", CodeGenerator::caseCode());

        $this->assertSame('P-'.str_pad((string) (Patient::count() + 1), 5, '0', STR_PAD_LEFT), CodeGenerator::patientFileNumber());
    }

    public function test_sharing_actions_update_the_case(): void
    {
        $case = MedicalCase::factory()->create();
        $this->actingAs(User::factory()->reception()->create(['branch_id' => $case->branch_id]));
        $token = $case->share_token;

        Livewire::test('pages::admin.cases.show', ['case' => $case])
            ->call('markCopied')
            ->call('revokeLink');

        $case->refresh();
        $this->assertNotNull($case->shared_at);
        $this->assertFalse($case->isShareActive());

        Livewire::test('pages::admin.cases.show', ['case' => $case])->call('regenerateLink');

        $case->refresh();
        $this->assertTrue($case->isShareActive());
        $this->assertNotSame($token, $case->share_token);
    }

    public function test_whatsapp_numbers_are_normalized_for_egypt(): void
    {
        $this->assertSame('201012345678', Phone::toInternational('010 1234 5678'));
        $this->assertSame('201012345678', Phone::toInternational('00201012345678'));
        $this->assertSame('201012345678', Phone::toInternational('+201012345678'));
        $this->assertStringStartsWith('https://wa.me/201012345678?text=', Phone::whatsappUrl('01012345678', 'مرحبا'));
    }

    public function test_admin_can_create_a_doctor_account_that_can_log_in_by_phone(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test('pages::admin.doctors.form')
            ->set('name', 'سامي علي')
            ->set('title', 'أ.د.')
            ->set('code', 'sa010')
            ->set('phone', '01099998888')
            ->set('password', 'secret-pass-1')
            ->set('branch_ids', [(string) Branch::first()->id])
            ->call('save')
            ->assertHasNoErrors();

        $doctor = Doctor::where('code', 'sa010')->sole();
        $this->assertTrue($doctor->user->isDoctor());
        $this->assertSame('أ.د. سامي علي', $doctor->display_name);

        auth()->logout();
        $this->post(route('login.store'), ['email' => '01099998888', 'password' => 'secret-pass-1']);
        $this->assertAuthenticatedAs($doctor->user);
    }
}
