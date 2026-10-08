<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CaseFile;
use App\Models\MedicalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BranchDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_deletes_branch_with_its_cases_and_restores_them_together(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $branch = Branch::factory()->create(['name' => 'فرع تجريبي']);
        $keep = Branch::factory()->create();
        $cases = MedicalCase::factory()->count(2)->create(['branch_id' => $branch->id]);
        $file = CaseFile::factory()->create(['medical_case_id' => $cases[0]->id]);
        $other = MedicalCase::factory()->create(['branch_id' => $keep->id]);

        Livewire::test('pages::admin.branches')->call('delete', $branch->id)->assertHasNoErrors();

        $this->assertSoftDeleted($branch);
        $cases->each(fn ($case) => $this->assertSoftDeleted($case));
        $this->assertSoftDeleted($file);
        $this->assertNotSoftDeleted($other);
        $this->assertNotNull(MedicalCase::withTrashed()->find($cases[0]->id)->share_revoked_at);
        $this->get(route('cases.index'))->assertOk()->assertDontSee($cases[0]->case_code)->assertSee($other->case_code);
        $this->assertSame([$keep->id], Branch::pluck('id')->all());
        $this->assertSame('فرع تجريبي', MedicalCase::withTrashed()->find($cases[0]->id)->branch->name);

        $this->get(route('recycle-bin'))->assertOk()->assertSee('فرع تجريبي');
        Livewire::test('pages::admin.recycle-bin')->call('restore', 'branch', $branch->id);

        $this->assertNotSoftDeleted($branch->fresh());
        $cases->each(fn ($case) => $this->assertNotSoftDeleted($case->fresh()));
        $this->assertNotSoftDeleted($file->fresh());
    }

    public function test_branch_with_assigned_staff_is_not_deleted(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $branch = Branch::factory()->create();
        User::factory()->reception()->create(['branch_id' => $branch->id]);

        Livewire::test('pages::admin.branches')->call('delete', $branch->id);

        $this->assertNotSoftDeleted($branch);
    }

    public function test_reception_cannot_delete_branches(): void
    {
        $branch = Branch::factory()->create();
        $this->actingAs(User::factory()->reception()->create(['branch_id' => $branch->id]));

        $this->get(route('branches.index'))->assertForbidden();
        $this->assertNotSoftDeleted($branch);
    }
}
