<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\MedicalCase;
use App\Models\User;
use App\Services\CaseRegistration;
use App\Support\AccessScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CaseExtraDoctorsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{MedicalCase, Doctor, Doctor, User} */
    private function setUpCase(): array
    {
        $case = MedicalCase::factory()->create();
        $case->doctor->branches()->syncWithoutDetaching([$case->branch_id]);
        $extra = Doctor::factory()->create();
        $extra->branches()->attach($case->branch_id);
        $reception = User::factory()->reception()->create(['branch_id' => $case->branch_id]);
        $reception->branches()->sync([$case->branch_id]);

        return [$case, $case->doctor, $extra, $reception];
    }

    public function test_staff_add_an_extra_doctor_from_the_case_form_and_the_main_one_is_never_duplicated(): void
    {
        [$case, $main, $extra, $reception] = $this->setUpCase();

        Livewire::actingAs($reception)->test('pages::admin.cases.form', ['case' => $case])
            ->set('extra_pick', (string) $extra->id)
            ->set('extra_pick', (string) $main->id) // main doctor is not offered as an extra
            ->assertSet('extra_doctors', [(string) $extra->id])
            ->call('save')->assertHasNoErrors();

        $this->assertSame([$extra->id], $case->fresh()->extraDoctors()->pluck('doctors.id')->all());
        $this->assertSame($main->id, $case->fresh()->doctor_id);

        Livewire::actingAs($reception)->test('pages::admin.cases.form', ['case' => $case->fresh()])
            ->assertSet('extra_doctors', [(string) $extra->id])
            ->call('removeExtraDoctor', (string) $extra->id)->call('save')->assertHasNoErrors();
        $this->assertCount(0, $case->fresh()->extraDoctors);
    }

    public function test_extra_doctor_sees_the_case_in_portal_and_link_and_can_upload_but_others_cannot(): void
    {
        [$case, $main, $extra] = $this->setUpCase();
        $case->extraDoctors()->attach($extra->id);
        $stranger = Doctor::factory()->create();
        $stranger->branches()->attach($case->branch_id);

        $this->assertTrue($extra->linkCases()->whereKey($case->id)->exists());
        $this->assertTrue($main->linkCases()->whereKey($case->id)->exists());
        $this->assertFalse($stranger->linkCases()->whereKey($case->id)->exists());

        $this->assertTrue(AccessScope::allowsCase($extra->user, $case));
        $this->assertTrue($extra->user->can('view', $case));
        $this->assertTrue($extra->user->can('uploadFiles', $case));
        $this->assertFalse($stranger->user->can('view', $case));

        $this->actingAs($extra->user)->get(route('portal'))->assertOk()->assertSee($case->case_code);
        $this->actingAs($extra->user)->get(route('portal.cases.show', $case))->assertOk();
        $this->actingAs($stranger->user)->get(route('portal'))->assertOk()->assertDontSee($case->case_code);
    }

    public function test_extra_doctor_outside_the_case_branch_is_rejected(): void
    {
        [$case, , , $reception] = $this->setUpCase();
        $elsewhere = Doctor::factory()->create();

        $this->expectException(HttpException::class);
        CaseRegistration::save($reception, ['branch_id' => $case->branch_id, 'doctor_id' => $case->doctor_id, 'extra_doctor_ids' => [$elsewhere->id], 'exam_date' => $case->exam_date->toDateString()], $case);
    }

    public function test_saving_without_the_extra_doctors_key_leaves_them_alone(): void
    {
        [$case, , $extra, $reception] = $this->setUpCase();
        $case->extraDoctors()->attach($extra->id);

        CaseRegistration::save($reception, ['branch_id' => $case->branch_id, 'doctor_id' => $case->doctor_id, 'exam_date' => $case->exam_date->toDateString()], $case);

        $this->assertSame([$extra->id], $case->fresh()->extraDoctors()->pluck('doctors.id')->all());
    }
}
