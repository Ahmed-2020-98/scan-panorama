<?php

namespace Tests\Feature;

use App\Livewire\CaseClinicalPanel;
use App\Models\CaseFile;
use App\Models\Doctor;
use App\Models\MedicalCase;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DiscountAndDoctorFilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_with_the_discount_permission_save_a_percent_discount_with_a_reason(): void
    {
        Setting::put(['currency' => 'EGP']);
        $case = MedicalCase::factory()->create();
        $case->doctor->branches()->syncWithoutDetaching([$case->branch_id]);
        $reception = User::factory()->reception()->create(['branch_id' => $case->branch_id, 'permission_overrides' => ['apply_discount' => true]]);
        $reception->branches()->sync([$case->branch_id]);

        Livewire::actingAs($reception)->test('pages::admin.cases.form', ['case' => $case])
            ->set('base_price', '1000')->set('discount_type', 'percent')->set('discount', '20')->set('discount_reason', 'عرض')
            ->assertSee('800.00')
            ->call('save')->assertHasNoErrors();

        $case->refresh();
        $this->assertSame([100000, 20000, 80000, 'عرض'], [$case->base_price_minor, $case->discount_minor, $case->final_price_minor, $case->discount_reason]);
    }

    public function test_staff_without_the_permission_see_no_discount_fields_and_a_reason_is_required(): void
    {
        Setting::put(['currency' => 'EGP']);
        $case = MedicalCase::factory()->create();
        $case->doctor->branches()->syncWithoutDetaching([$case->branch_id]);
        $reception = User::factory()->reception()->create(['branch_id' => $case->branch_id]);

        Livewire::actingAs($reception)->test('pages::admin.cases.form', ['case' => $case])->assertDontSee('نوع الخصم');

        $admin = User::factory()->admin()->create();
        Livewire::actingAs($admin)->test('pages::admin.cases.form', ['case' => $case])
            ->set('base_price', '500')->set('discount', '100')->call('save')->assertHasErrors('discount_reason');
    }

    public function test_doctor_deletes_only_files_they_uploaded_and_they_go_to_the_recycle_bin(): void
    {
        $doctor = Doctor::factory()->create();
        $case = MedicalCase::factory()->create(['doctor_id' => $doctor->id]);
        $doctor->branches()->attach($case->branch_id);
        $own = CaseFile::factory()->create(['medical_case_id' => $case->id, 'uploaded_by' => $doctor->user_id, 'original_name' => 'mine.jpg']);
        $center = CaseFile::factory()->create(['medical_case_id' => $case->id, 'uploaded_by' => User::factory()->reception()->create()->id, 'original_name' => 'center.jpg']);

        $panel = Livewire::actingAs($doctor->user)->test(CaseClinicalPanel::class, ['medicalCase' => $case]);
        $panel->call('deleteFile', $own->id)->assertHasNoErrors();
        $this->assertSoftDeleted($own);
        $this->assertNotNull($own->fresh()?->deletion_batch_id ?? CaseFile::withTrashed()->find($own->id)->deletion_batch_id);

        $panel->call('deleteFile', $center->id)->assertForbidden();
        $this->assertNotSoftDeleted($center);
    }
}
