<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\MedicalCase;
use App\Models\User;
use App\Services\CaseRegistration;
use App\Support\AccessScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StaffBranchesTest extends TestCase
{
    use RefreshDatabase;

    private function register(User $actor, Branch $branch): MedicalCase
    {
        return CaseRegistration::save($actor, ['branch_id' => $branch->id, 'doctor_id' => null, 'technician_id' => null, 'exam_type_id' => null, 'exam_date' => today()->toDateString(), 'name' => 'مريض تجريبي', 'phone' => '', 'gender' => '', 'birth_year' => null, 'notes_internal' => '', 'notes_for_doctor' => '']);
    }

    public function test_staff_in_two_branches_see_both_and_register_in_the_one_they_pick(): void
    {
        [$a, $b, $c] = Branch::factory()->count(3)->create();
        $reception = User::factory()->reception()->create(['branch_id' => $a->id]);
        $reception->branches()->sync([$a->id, $b->id]);
        $caseA = MedicalCase::factory()->create(['branch_id' => $a->id]);
        $caseB = MedicalCase::factory()->create(['branch_id' => $b->id]);
        $caseC = MedicalCase::factory()->create(['branch_id' => $c->id]);

        $this->assertEqualsCanonicalizing([$a->id, $b->id], AccessScope::branchIds($reception));
        $this->actingAs($reception)->get(route('cases.show', $caseA))->assertOk();
        $this->get(route('cases.show', $caseB))->assertOk();
        $this->get(route('cases.show', $caseC))->assertNotFound();

        $inB = $this->register($reception, $b);
        $this->assertSame($b->id, $inB->branch_id);
        $this->assertSame($b->id, $reception->fresh()->branch_id, 'the branch just used becomes the default');

        $notMine = $this->register($reception->fresh(), $c);
        $this->assertSame($b->id, $notMine->branch_id, 'a branch outside theirs falls back to their default');
    }

    public function test_manager_assigns_several_branches_and_a_schedule_from_the_users_page(): void
    {
        [$a, $b] = Branch::factory()->count(2)->create();
        $manager = User::factory()->admin()->create();

        Livewire::actingAs($manager)->test('pages::admin.users')
            ->call('create')
            ->set('name', 'فني تجريبي')->set('phone', '01000000077')->set('role', 'technician')->set('password', 'Password-123!')
            ->set('branch_ids', [(string) $a->id, (string) $b->id])
            ->set('work_days', ['1', '4'])->set('work_from', '09:00')->set('work_until', '17:00')
            ->call('save')->assertHasNoErrors();

        $tech = User::where('phone', '01000000077')->firstOrFail();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $tech->branches->pluck('id')->all());
        $this->assertSame($a->id, $tech->branch_id);
        $this->assertSame([1, 4], $tech->work_days);
        $this->assertSame('الإثنين والخميس من 9:00 ص لـ 5:00 م', $tech->workScheduleLabel());

        Livewire::actingAs($manager)->test('pages::admin.users')
            ->call('edit', $tech->id)->set('work_from', '17:00')->set('work_until', '09:00')->call('save')->assertHasErrors('work_until');
    }
}
