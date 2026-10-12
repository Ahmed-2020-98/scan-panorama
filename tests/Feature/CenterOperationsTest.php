<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\BookletRequest;
use App\Models\Branch;
use App\Models\Campaign;
use App\Models\Doctor;
use App\Models\DoctorVisit;
use App\Models\Expense;
use App\Models\MedicalCase;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Services\CasePricing;
use App\Services\CenterAlerts;
use App\Services\DoctorPerformance;
use App\Support\Barcode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CenterOperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = Branch::factory()->create();
        $this->admin = User::factory()->admin()->create();
        Setting::put(['currency' => 'EGP']);
        $this->actingAs($this->admin);
    }

    private function doctor(): Doctor
    {
        $doctor = Doctor::factory()->create();
        $doctor->branches()->attach($this->branch);

        return $doctor;
    }

    private function caseFor(Doctor $doctor, string $date): MedicalCase
    {
        return MedicalCase::factory()->create(['doctor_id' => $doctor->id, 'branch_id' => $this->branch->id, 'exam_date' => $date]);
    }

    public function test_marketing_role_defaults_and_admin_can_grant_any_permission_to_staff(): void
    {
        $marketing = User::factory()->create(['role' => Role::Marketing, 'branch_id' => $this->branch->id]);
        $this->assertTrue($marketing->hasPermission(Permission::ManageVisits));
        $this->assertTrue($marketing->hasPermission(Permission::SendCampaigns));
        $this->assertFalse($marketing->hasPermission(Permission::ViewFinancials));

        $reception = User::factory()->reception()->create(['branch_id' => $this->branch->id, 'permission_overrides' => ['manage_branches' => true, 'manage_expenses' => true]]);
        $this->actingAs($reception);
        $this->get(route('branches.index'))->assertOk();
        $this->get(route('expenses.index'))->assertOk();
        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('drive.settings'))->assertForbidden();

        // Doctors stay limited even if someone tries to grant them more.
        $doctorUser = User::factory()->create(['role' => Role::Doctor, 'permission_overrides' => ['manage_branches' => true]]);
        $this->assertFalse($doctorUser->hasPermission(Permission::ManageBranches));
    }

    public function test_staff_with_manage_users_cannot_create_or_touch_admins(): void
    {
        $reception = User::factory()->reception()->create(['branch_id' => $this->branch->id, 'permission_overrides' => ['manage_users' => true]]);
        $this->actingAs($reception);
        Livewire::test('pages::admin.users')
            ->set('name', 'X')->set('phone', '01000000009')->set('role', 'manager')->set('password', 'Password-123!')->set('branch_id', (string) $this->branch->id)
            ->call('save')->assertForbidden();
        Livewire::test('pages::admin.users')->call('edit', $this->admin->id)->assertForbidden();
    }

    public function test_user_can_be_deleted_with_history_kept_and_shift_rate_saved(): void
    {
        $component = Livewire::test('pages::admin.users')
            ->set('name', 'فني أشعة')->set('phone', '01011112222')->set('role', 'reception')->set('password', 'Password-123!')
            ->set('branch_id', (string) $this->branch->id)->set('shift_rate', '250')
            ->call('save')->assertHasNoErrors();
        $user = User::where('name', 'فني أشعة')->sole();
        $this->assertSame(25000, $user->shift_rate_minor);

        $component->call('delete', $user->id);
        $this->assertSoftDeleted($user);
        $this->assertNull($user->fresh()->phone);
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.deleted']);
        Livewire::test('pages::admin.users')->call('delete', $this->admin->id);
        $this->assertNotSoftDeleted($this->admin);
    }

    public function test_salary_is_recorded_from_shifts_and_expenses_reduce_net(): void
    {
        $employee = User::factory()->reception()->create(['branch_id' => $this->branch->id, 'shift_rate_minor' => 30000]);
        Livewire::test('pages::admin.expenses')
            ->set('shifts.'.$employee->id, '20')->call('paySalary', $employee->id)
            ->call('create')->set('form.category', 'rent')->set('form.amount', '5000')->set('form.branch_id', (string) $this->branch->id)->call('save')->assertHasNoErrors();

        $salary = Expense::where('category', 'salaries')->sole();
        $this->assertSame(600000, $salary->amount_minor);
        $this->assertSame(20, $salary->shifts);
        $this->assertSame(1100000, (int) Expense::sum('amount_minor'));

        $case = MedicalCase::factory()->create(['branch_id' => $this->branch->id, 'exam_date' => today()]);
        CasePricing::apply($this->admin, $case, 2000000, 2000000, null);
        Payment::create(['medical_case_id' => $case->id, 'branch_id' => $this->branch->id, 'amount_minor' => 1500000, 'currency' => 'EGP', 'method' => 'cash', 'collected_by' => $this->admin->id, 'received_at' => now(), 'request_id' => 'r1']);
        Livewire::test('pages::admin.accounts')->call('applyPreset', 'month')
            ->assertSee('الصافي')->assertSee('4,000.00')->assertSee('5,000.00');
    }

    public function test_doctor_stop_and_drop_are_detected_and_alert_admin_and_marketing(): void
    {
        $steady = $this->doctor();
        $stopped = $this->doctor();
        foreach (range(20, 70, 5) as $daysAgo) {
            $this->caseFor($stopped, today()->subDays($daysAgo)->toDateString());
        }
        foreach (range(1, 55, 6) as $daysAgo) {
            $this->caseFor($steady, today()->subDays($daysAgo)->toDateString());
        }
        $marketing = User::factory()->create(['role' => Role::Marketing, 'branch_id' => $this->branch->id]);

        $rows = DoctorPerformance::rows($this->admin);
        $this->assertSame('stopped', $rows[$stopped->id]['status']);
        $this->assertNotSame('stopped', $rows[$steady->id]['status']);

        $this->assertGreaterThan(0, CenterAlerts::run());
        $this->assertSame(1, $marketing->notifications()->where('data->key', 'doctor-stopped-'.$stopped->id)->count());
        $this->assertSame(1, $this->admin->notifications()->where('data->key', 'doctor-stopped-'.$stopped->id)->count());
        CenterAlerts::run();
        $this->assertSame(1, $this->admin->notifications()->where('data->key', 'doctor-stopped-'.$stopped->id)->count(), 'no duplicate within a week');

        $this->get(route('notifications.index'))->assertOk()->assertSee('توقف عن الإرسال');
    }

    public function test_visit_before_after_and_visits_page(): void
    {
        $doctor = $this->doctor();
        $this->caseFor($doctor, today()->subDays(8)->toDateString()); // inside the 6-day window before the visit
        $this->caseFor($doctor, today()->subDays(40)->toDateString()); // outside it
        $this->caseFor($doctor, today()->subDays(3)->toDateString());
        $this->caseFor($doctor, today()->subDays(2)->toDateString());
        DoctorVisit::create(['doctor_id' => $doctor->id, 'branch_id' => $this->branch->id, 'responsible_user_id' => $this->admin->id, 'visited_at' => today()->subDays(5), 'next_visit_at' => today()->subDay()]);

        $row = DoctorPerformance::rows($this->admin)[$doctor->id];
        $this->assertSame(1, $row['before_visit']);
        $this->assertSame(2, $row['after_visit']);
        $this->assertTrue($row['next_visit']->isYesterday());

        Livewire::test('pages::admin.visits')->assertSee($doctor->display_name)->assertSee('الزيارة رفعت الحالات')
            ->call('create', $doctor->id)->assertSet('form.doctor_id', (string) $doctor->id)
            ->call('save')->assertHasNoErrors();
        $this->assertSame(2, DoctorVisit::count());
    }

    public function test_doctor_requests_booklet_from_private_link_and_staff_marks_it_delivered(): void
    {
        $doctor = $this->doctor();
        $url = $doctor->linkUrl();
        $token = basename(parse_url($url, PHP_URL_PATH));
        auth()->logout();

        $this->get($url)->assertOk()->assertSee('طلب دفتر');
        $this->post(route('doctor-link.booklet', $token), ['quantity' => 2, 'note' => 'بعد الساعة 5'])->assertRedirect();
        $this->post(route('doctor-link.booklet', $token), ['quantity' => 1])->assertRedirect();
        $this->assertSame(1, BookletRequest::count(), 'one open request at a time');
        $this->get($url)->assertSee('تم استلام طلب دفتر التحويل');
        $this->assertSame(1, $this->admin->notifications()->count());

        $this->actingAs($this->admin);
        Livewire::test('pages::admin.visits')->assertSee('طلبات دفاتر التحويل')->call('deliverBooklet', BookletRequest::sole()->id);
        $this->assertSame('delivered', BookletRequest::sole()->status);
    }

    public function test_campaign_lists_unique_mobile_numbers_and_tracks_sending(): void
    {
        $patients = Patient::factory()->count(3)->sequence(['phone' => '01012345678'], ['phone' => '01012345678'], ['phone' => '123'])->create();
        foreach ($patients as $p) {
            MedicalCase::factory()->create(['patient_id' => $p->id, 'branch_id' => $this->branch->id]);
        }
        Patient::factory()->create(['phone' => '01198765432']);

        $component = Livewire::test('pages::admin.campaigns')->assertSet('count', 2)
            ->set('message', 'عرض __')->call('start')->assertHasErrors('message')
            ->set('message', 'أهلًا {الاسم} خصم 20%')->call('start');
        $campaign = Campaign::sole();
        $component->assertRedirect(route('campaigns.show', $campaign));
        $this->assertSame(2, $campaign->recipients()->count());

        $first = $campaign->recipients()->first();
        Livewire::test('pages::admin.campaign', ['campaign' => $campaign])
            ->assertSee('إرسال والتالي')->assertSee(rawurlencode('أهلًا '.$first->name))
            ->call('markSent', $first->id);
        $this->assertNotNull($first->fresh()->sent_at);

        $vcf = $this->get(route('contacts.export', 'patients'))->assertOk()->streamedContent();
        $this->assertSame(2, substr_count($vcf, 'BEGIN:VCARD'));
        $this->assertStringContainsString('TEL;TYPE=CELL:+201012345678', $vcf);
    }

    public function test_print_pages_and_patient_phone_from_case_form(): void
    {
        $doctor = $this->doctor();
        $patient = Patient::factory()->create(['phone' => null]);
        $case = MedicalCase::factory()->create(['patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'branch_id' => $this->branch->id, 'exam_date' => today()]);

        $this->get(route('cases.print', $case))->assertOk()->assertSee($case->case_code)->assertSee('window.print')->assertSee('class="barcode"', false)->assertSee('امسح لفتح الحالة');
        $this->get(route('cases.receipt', $case))->assertOk()->assertSee($patient->name)->assertSee('class="barcode"', false)
            ->assertDontSee('المتبقي')->assertDontSee('قيمة الفحص');
        $this->get(route('accounts.print'))->assertOk()->assertSee('تقرير الحسابات');

        Livewire::test('pages::admin.cases.form', ['case' => $case])
            ->assertSet('doctor_id', (string) $doctor->id)
            ->set('patientPhone', '01055556666')->call('save')->assertHasNoErrors();
        $this->assertSame('01055556666', $patient->fresh()->phone);
    }

    public function test_case_barcode_is_valid_code128(): void
    {
        $modules = Barcode::modules('2026-00042');
        // start + 10 chars + checksum = 12 symbols of 11 modules, stop adds 13.
        $this->assertSame(12 * 11 + 13, array_sum(str_split($modules)));
        $this->assertStringStartsWith('211214', $modules);
        $this->assertStringEndsWith('2331112', $modules);

        // Each data symbol matches that character encoded alone (start, char, checksum, stop).
        $symbols = str_split(substr($modules, 0, -7), 6);
        foreach (str_split('2026-00042') as $i => $char) {
            $this->assertSame(str_split(Barcode::modules($char), 6)[1], $symbols[$i + 1]);
        }
        // '2026-00042' → (104 + Σ value × position) mod 103 = 27, symbol 27 = '312212'.
        $this->assertSame('312212', $symbols[11]);
        $this->assertStringContainsString('<svg', Barcode::svg('2026-00042'));
    }

    public function test_doctor_clinic_details_are_saved(): void
    {
        $doctor = $this->doctor();
        Livewire::test('pages::admin.doctors.form', ['doctor' => $doctor])
            ->set('clinic_address', 'شارع التحرير، الدقي')
            ->set('location_url', 'https://maps.app.goo.gl/abc123')
            ->set('working_hours', 'السبت-الخميس 5م-10م')
            ->call('save')->assertHasNoErrors();
        $doctor->refresh();
        $this->assertSame('شارع التحرير، الدقي', $doctor->clinic_address);
        $this->assertSame('https://maps.app.goo.gl/abc123', $doctor->location_url);

        Livewire::test('pages::admin.doctors.form', ['doctor' => $doctor])->set('location_url', 'javascript:alert(1)')->call('save')->assertHasErrors('location_url');
    }

    public function test_dashboard_and_management_pages_render(): void
    {
        $this->caseFor($this->doctor(), today()->toDateString());
        foreach (['dashboard', 'accounts.index', 'visits.index', 'expenses.index', 'campaigns.index', 'users.index', 'notifications.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }
}
