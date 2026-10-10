<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\WorkflowStatus;
use App\Livewire\CaseSharePanel;
use App\Models\Branch;
use App\Models\CaseFile;
use App\Models\Doctor;
use App\Models\DoctorVisit;
use App\Models\DriveConnection;
use App\Models\DriveFolder;
use App\Models\ExamType;
use App\Models\MedicalCase;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Services\CaseClinicalUpdates;
use App\Services\CasePayments;
use App\Services\CasePricing;
use App\Services\CaseRegistration;
use App\Services\Drive\DriveCaseStorage;
use App\Services\FinancialReport;
use App\Services\RecordRecovery;
use App\Support\Money;
use App\Support\PatientAge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RadiologyMvpTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = Branch::factory()->create();
        $this->manager = User::factory()->admin()->create();
        $this->actingAs($this->manager);
        Setting::put(['currency' => 'EGP']);
        config(['radiology.upload_provider' => 'local']);
    }

    private function draft(array $extra = []): MedicalCase
    {
        return CaseRegistration::save(auth()->user(), $extra + ['branch_id' => $this->branch->id, 'doctor_id' => null, 'technician_id' => null, 'exam_type_id' => null, 'exam_date' => today()->toDateString(), 'name' => '', 'phone' => '', 'gender' => '', 'birth_year' => null, 'notes_internal' => '', 'notes_for_doctor' => '']);
    }

    public function test_unified_list_shows_patient_details_and_filters_by_patient(): void
    {
        $a = MedicalCase::factory()->create(['branch_id' => $this->branch->id]);
        $a->patient->update(['phone' => '01099887766']);
        $second = MedicalCase::factory()->create(['branch_id' => $this->branch->id, 'patient_id' => $a->patient_id]);
        $other = MedicalCase::factory()->create(['branch_id' => $this->branch->id]);

        $this->get(route('cases.index'))->assertOk()->assertSee('01099887766')->assertSee($a->patient->file_number)->assertSee('2 حالات');
        $this->get(route('cases.index', ['q' => '01099887766']))->assertSee($a->case_code)->assertDontSee($other->case_code);
        $this->get(route('cases.index', ['patient' => $a->patient_id]))->assertSee($second->case_code)->assertDontSee($other->case_code);
    }

    public function test_branches_and_exam_types_with_empty_optional_fields_can_be_edited(): void
    {
        $branch = Branch::factory()->create(['address' => null, 'phone' => null]);
        Livewire::test('pages::admin.branches')->call('edit', $branch->id)->assertSet('address', '')
            ->set('address', 'شارع 9')->call('save')->assertHasNoErrors();
        $this->assertSame('شارع 9', $branch->fresh()->address);

        $exam = ExamType::factory()->create(['description' => null]);
        Livewire::test('pages::admin.exam-types')->call('edit', $exam->id)->assertSet('description', '')->assertSet('name', $exam->name);
    }

    public function test_anonymous_drafts_are_distinct_and_all_pages_open(): void
    {
        $a = $this->draft();
        $b = $this->draft();
        $this->assertNotSame($a->patient_id, $b->patient_id);
        $this->assertTrue($a->patient->identity_incomplete);
        $this->assertNull($a->doctor_id);
        $this->assertNull($a->final_price_minor);
        foreach (['dashboard', 'cases.index', 'accounts.index', 'visits.index', 'users.index', 'drive.settings', 'recycle-bin', 'exam-types.index', 'branches.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('patients.index'))->assertRedirect('/cases');
        $this->get(route('cases.show', $a))->assertOk();
        $this->get(route('patients.show', $a->patient))->assertOk();
        $this->get($a->shareUrl())->assertOk();
        $this->get(route('cases.edit', $a))->assertOk();
        $this->get(route('patients.edit', $a->patient))->assertOk();
        $this->get(route('branches.show', $this->branch))->assertOk();
    }

    public function test_branch_scopes_reject_routes_and_livewire_tampering(): void
    {
        $foreign = $this->draft();
        $other = Branch::factory()->create();
        $reception = User::factory()->reception()->create(['branch_id' => $other->id]);
        $this->actingAs($reception);
        $this->assertSame(0, MedicalCase::count());
        $this->assertSame(0, Patient::count());
        $this->get(route('cases.show', $foreign))->assertNotFound();
        Livewire::test('pages::admin.cases.form')->call('selectPatient', $foreign->patient_id)->assertNotFound();
        $own = $this->draft(['branch_id' => $this->branch->id, 'name' => 'مريض فرعي']);
        $this->assertSame($other->id, $own->branch_id);
        $reception->update(['branch_id' => null]);
        $this->assertSame(0, MedicalCase::count());
        $this->get(route('cases.create'))->assertForbidden();
    }

    public function test_age_year_and_money_are_exact_and_preserve_untouched_date(): void
    {
        $this->assertSame(1956, PatientAge::yearFromAge(70, 2026));
        $this->assertSame(76, PatientAge::ageFromYear(1950, 2026));
        $this->assertSame(100001, Money::minor('1000.01'));
        $case = $this->draft(['name' => 'مريض', 'birth_year' => 1950]);
        $this->assertSame(now()->year - 1950, $case->patient->age);
        $case->patient->update(['birth_date' => '1950-08-20', 'birth_year_only' => false]);
        Livewire::test('pages::admin.patients.form', ['patient' => $case->patient])->set('notes', 'ملاحظة')->call('save')->assertHasNoErrors();
        $this->assertSame('1950-08-20', $case->patient->fresh()->birth_date->toDateString());
    }

    public function test_prices_discounts_and_payments_keep_history_and_are_idempotent(): void
    {
        $exam = ExamType::factory()->create(['base_price_minor' => 100000]);
        $exam->branches()->attach($this->branch, ['base_price_minor' => 120000]);
        $this->assertSame(120000, CasePricing::quote($exam, $this->branch));
        $case = $this->draft(['exam_type_id' => $exam->id]);
        CasePricing::apply($this->manager, $case, 100000, 50000, 'خصم خاص للدكتور حسب الاتفاق.');
        $this->assertSame(50000, $case->fresh()->discount_minor);
        $this->assertSame($this->manager->id, $case->fresh()->discount_by);
        $id = (string) Str::uuid();
        $payment = CasePayments::receive($this->manager, $case, 20000, 'cash', $id);
        $again = CasePayments::receive($this->manager, $case, 20000, 'cash', $id);
        $this->assertSame($payment->id, $again->id);
        $this->assertSame(1, Payment::count());
        $exam->update(['base_price_minor' => 900000]);
        $this->assertSame(50000, $case->fresh()->final_price_minor);
        CasePayments::refund($this->manager, $payment, 10000, 'رد جزء من الدفعة', (string) Str::uuid());
        $this->assertEquals(10000, $case->payments()->sum('amount_minor'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'price.discounted', 'medical_case_id' => $case->id]);
    }

    public function test_accounts_show_collected_outstanding_and_exam_value_without_discounts(): void
    {
        $case = $this->draft();
        CasePricing::apply($this->manager, $case, 100000, 100000, null);
        CasePayments::receive($this->manager, $case, 30000, 'cash', (string) Str::uuid());
        $row = FinancialReport::summarize($this->manager, ['from' => today()->toDateString(), 'to' => today()->toDateString()])['currencies']['EGP'];
        $this->assertSame([100000, 30000, 70000], [(int) $row['billed'], (int) $row['collected'], $row['outstanding']]);
        $this->get(route('accounts.index'))->assertOk()->assertSee('المتبقي')->assertDontSee('الخصومات');
    }

    public function test_reception_daily_finance_and_clinical_roles_never_see_finance(): void
    {
        $case = $this->draft();
        $reception = User::factory()->reception()->create(['branch_id' => $this->branch->id]);
        $this->actingAs($reception);
        $this->get(route('accounts.index'))->assertOk();
        $this->get(route('accounts.index', ['from' => today()->subDay()->toDateString()]))->assertForbidden();
        foreach ([Role::Doctor, Role::Technician] as $role) {
            $user = User::factory()->create(['role' => $role, 'branch_id' => $this->branch->id, 'permission_overrides' => ['view_financials' => true]]);
            $this->assertFalse($user->hasPermission(Permission::ViewFinancials));
            $this->actingAs($user);
            $this->get(route('accounts.index'))->assertForbidden();
        }
    }

    public function test_assigned_technician_and_doctor_clinical_updates_are_scoped(): void
    {
        $tech = User::factory()->create(['role' => Role::Technician, 'branch_id' => $this->branch->id]);
        $doctor = Doctor::factory()->create();
        $doctor->user->update(['branch_id' => $this->branch->id]);
        $doctor->branches()->attach($this->branch);
        $case = $this->draft(['technician_id' => $tech->id, 'doctor_id' => $doctor->id]);
        $this->actingAs($tech);
        $this->get(route('technician'))->assertOk();
        CaseClinicalUpdates::update($tech, $case, ['workflow_status' => 'completed', 'technical_notes' => 'تم الفحص']);
        $this->assertSame(WorkflowStatus::Completed, $case->fresh()->workflow_status);
        $this->assertNotSame('ready', $case->fresh()->status->value);
        $this->actingAs($doctor->user);
        $this->get(route('portal.cases.show', $case))->assertOk();
        CaseClinicalUpdates::update($doctor->user, $case, ['medical_notes' => 'تقرير طبي']);
        $this->assertSame('تقرير طبي', $case->fresh()->medical_notes);
    }

    public function test_recovery_retains_bytes_and_independently_deleted_children(): void
    {
        Storage::fake('local');
        $case = $this->draft();
        $file = CaseFile::factory()->create(['medical_case_id' => $case->id, 'path' => 'x.pdf', 'disk' => 'local']);
        Storage::disk('local')->put('x.pdf', 'test');
        RecordRecovery::delete($this->manager, $file);
        RecordRecovery::delete($this->manager, $case->patient);
        $this->get($case->shareUrl())->assertNotFound();
        Storage::disk('local')->assertExists('x.pdf');
        RecordRecovery::restore($this->manager, Patient::withTrashed()->findOrFail($case->patient_id));
        $this->assertNotNull(MedicalCase::find($case->id));
        $this->assertTrue(CaseFile::withTrashed()->find($file->id)->trashed());
        $this->assertFalse($case->fresh()->isShareActive());
    }

    public function test_share_selection_and_pending_files_are_not_downloadable(): void
    {
        $case = $this->draft();
        $file = CaseFile::factory()->create(['medical_case_id' => $case->id, 'storage_status' => 'pending']);
        $this->get(route('shared.file', [$case->share_token, $file, 'download']))->assertNotFound();
        $file->update(['storage_status' => 'ready']);
        Livewire::test(CaseSharePanel::class, ['medicalCase' => $case])->set('selected', [])->call('saveSelection')->assertHasNoErrors();
        $this->get(route('shared.file', [$case->share_token, $file, 'download']))->assertNotFound();
        $foreign = $this->draft();
        $other = CaseFile::factory()->create(['medical_case_id' => $foreign->id]);
        Livewire::test(CaseSharePanel::class, ['medicalCase' => $case])->set('selected', [(string) $other->id])->call('saveSelection')->assertStatus(422);
    }

    public function test_chunk_upload_finalization_is_idempotent_and_session_is_case_bound(): void
    {
        Storage::fake('local');
        $case = $this->draft();
        $content = "%PDF-1.4\ntest\n%%EOF";
        $id = (string) Str::uuid();
        $payload = ['type' => 'report', 'upload_id' => $id, 'chunk_index' => 0, 'total_chunks' => 1, 'file_name' => 'test.pdf', 'file_size' => strlen($content), 'chunk' => UploadedFile::fake()->createWithContent('chunk', $content)];
        $this->postJson(route('cases.uploads.store', $case), $payload)->assertOk()->assertJsonPath('done', true);
        $payload['chunk'] = UploadedFile::fake()->createWithContent('chunk', $content);
        $this->postJson(route('cases.uploads.store', $case), $payload)->assertOk();
        $this->assertSame(1, CaseFile::count());
        $other = $this->draft();
        $payload['chunk'] = UploadedFile::fake()->createWithContent('chunk', $content);
        $this->postJson(route('cases.uploads.store', $other), $payload)->assertUnprocessable();
    }

    public function test_drive_transfer_uses_stable_ids_and_verifies_content(): void
    {
        Storage::fake('local');
        $case = $this->draft();
        $content = "%PDF-1.4\ntest\n%%EOF";
        Storage::disk('local')->put('drive-staging/x.pdf', $content);
        $file = CaseFile::factory()->create(['medical_case_id' => $case->id, 'type' => 'report', 'path' => 'drive-staging/x.pdf', 'staging_path' => 'drive-staging/x.pdf', 'disk' => 'drive', 'storage_status' => 'pending', 'size' => strlen($content), 'mime' => 'application/pdf', 'uploaded_by' => $this->manager->id]);
        DriveConnection::create(['refresh_token' => 'secret', 'account_id' => 'account']);
        $this->assertNotSame('secret', DB::table('drive_connections')->value('refresh_token'));
        $next = 0;
        Http::preventStrayRequests();
        Http::fake(function ($request) use (&$next, $content) {
            $url = $request->url();
            if (str_contains($url, 'oauth2.googleapis.com')) {
                return Http::response(['access_token' => 'access']);
            }if (str_contains($url, 'generateIds')) {
                return Http::response(['ids' => ['id-'.++$next]]);
            }if ($request->method() === 'PATCH') {
                return Http::response([], 200, ['Location' => 'https://www.googleapis.com/upload/session']);
            }if ($request->method() === 'PUT') {
                return Http::response(['id' => 'uploaded']);
            }if ($request->method() === 'GET') {
                return Http::response(['size' => strlen($content), 'md5Checksum' => md5($content)]);
            }

            return Http::response(['id' => 'created']);
        });
        $storage = app(DriveCaseStorage::class);
        $storage->store($file);
        $this->assertSame('ready', $file->fresh()->storage_status);
        $this->assertSame('id-1', $file->fresh()->provider_id);
        Storage::disk('local')->assertMissing('drive-staging/x.pdf');
        $this->assertSame(6, DriveFolder::count());
    }

    public function test_visit_agreements_followup_and_branch_permissions(): void
    {
        $doctor = Doctor::factory()->create();
        $doctor->branches()->attach($this->branch);
        $component = Livewire::test('pages::admin.visits')->call('create')
            ->set('form.doctor_id', (string) $doctor->id)
            ->set('form.visited_at', today()->toDateString())
            ->set('form.next_visit_at', today()->addDays(7)->toDateString())
            ->set('form.agreement', 'متابعة التعاون')
            ->call('save')->assertHasNoErrors();
        $this->assertSame(1, $component->instance()->summary()['visits']);
        $this->assertSame(1, DoctorVisit::pendingFollowup()->count());
        $this->assertDatabaseHas('activity_logs', ['action' => 'visit.saved', 'user_id' => $this->manager->id]);
        $row = DoctorVisit::sole();
        $row->update(['next_visit_at' => today()->subDay(), 'visited_at' => today()->subDays(3)]);
        $this->assertSame(1, DoctorVisit::pendingFollowup()->count());
        DoctorVisit::create(['doctor_id' => $doctor->id, 'branch_id' => $this->branch->id, 'responsible_user_id' => $this->manager->id, 'visited_at' => today()]);
        $this->assertSame(0, DoctorVisit::pendingFollowup()->count());
        $other = Branch::factory()->create();
        $reception = User::factory()->reception()->create(['branch_id' => $other->id, 'permission_overrides' => ['manage_visits' => true]]);
        $this->actingAs($reception);
        $this->get(route('visits.index'))->assertOk()->assertDontSee('متابعة التعاون');
        Livewire::test('pages::admin.visits')->call('edit', $row->id)->assertNotFound();
    }

    public function test_users_support_four_roles_and_protect_current_manager(): void
    {
        Livewire::test('pages::admin.users')->call('create')->set('name', 'فني تجريبي')
            ->set('phone', '01099998887')->set('password', 'example-password')->set('role', 'technician')
            ->set('branch_id', (string) $this->branch->id)->call('save')->assertHasNoErrors();
        $tech = User::where('phone', '01099998887')->sole();
        $this->assertTrue($tech->isTechnician());
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.created', 'subject_id' => $tech->id]);
        Livewire::test('pages::admin.users')->call('edit', $this->manager->id)->set('role', 'reception')
            ->set('branch_id', (string) $this->branch->id)->call('save')->assertHasErrors('role');
        $this->assertTrue($this->manager->fresh()->isAdmin());
    }

    public function test_video_upload_validation_and_range_download(): void
    {
        Storage::fake('local');
        $case = $this->draft();
        $content = pack('N', 24).'ftypisom'.pack('N', 0).'isommp42';
        $payload = ['type' => 'video', 'upload_id' => (string) Str::uuid(), 'chunk_index' => 0, 'total_chunks' => 1, 'file_name' => 'test.mp4', 'file_size' => strlen($content), 'chunk' => UploadedFile::fake()->createWithContent('chunk', $content)];
        $this->postJson(route('cases.uploads.store', $case), $payload)->assertOk();
        $file = $case->files()->sole();
        $this->assertTrue($file->isVideo());
        $this->withHeader('Range', 'bytes=0-9')->get(route('files.show', [$file, 'view']))
            ->assertStatus(206)->assertHeader('Content-Range', 'bytes 0-9/24');
        $payload['upload_id'] = (string) Str::uuid();
        $payload['file_size'] = 4;
        $payload['chunk'] = UploadedFile::fake()->createWithContent('chunk', 'fake');
        $this->postJson(route('cases.uploads.store', $case), $payload)->assertUnprocessable();
    }

    public function test_invalid_oauth_state_never_exchanges_credentials(): void
    {
        Http::preventStrayRequests();
        $this->get(route('drive.callback', ['state' => 'foreign', 'code' => 'sample']))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_reception_cannot_bypass_discount_permission(): void
    {
        $exam = ExamType::factory()->create(['base_price_minor' => 100000]);
        $case = $this->draft(['exam_type_id' => $exam->id]);
        $reception = User::factory()->reception()->create(['branch_id' => $this->branch->id]);
        $this->actingAs($reception);
        $this->expectException(HttpException::class);
        CasePricing::apply($reception, $case, 100000, 50000, 'خصم غير مصرح');
    }
}
