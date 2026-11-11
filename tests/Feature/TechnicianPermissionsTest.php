<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\MedicalCase;
use App\Models\User;
use App\Support\AccessScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TechnicianPermissionsTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, bool> $overrides */
    private function technician(MedicalCase $case, array $overrides = []): User
    {
        $tech = User::factory()->create(['role' => Role::Technician, 'branch_id' => $case->branch_id, 'permission_overrides' => $overrides]);
        $tech->branches()->sync([$case->branch_id]);

        return $tech;
    }

    public function test_a_plain_technician_stays_on_assigned_cases_only(): void
    {
        $mine = MedicalCase::factory()->create();
        $other = MedicalCase::factory()->create(['branch_id' => $mine->branch_id]);
        $tech = $this->technician($mine);
        $mine->update(['technician_id' => $tech->id]);

        $this->assertFalse($tech->isStaff());
        $this->assertSame(route('technician'), $tech->homeUrl());
        $this->assertFalse($tech->hasPermission(Permission::ViewFinancials));
        $this->assertTrue(AccessScope::allowsCase($tech, $mine));
        $this->assertFalse(AccessScope::allowsCase($tech, $other));
        $this->actingAs($tech)->get(route('dashboard'))->assertForbidden();
        $this->get(route('cases.show', $mine))->assertForbidden();
        $this->get(route('accounts.index'))->assertForbidden();
        $this->get(route('technician'))->assertOk();
    }

    public function test_admin_can_grant_a_technician_any_permission_and_the_staff_screens_open(): void
    {
        $mine = MedicalCase::factory()->create();
        $other = MedicalCase::factory()->create(['branch_id' => $mine->branch_id]);
        $tech = $this->technician($mine, ['view_financials' => true, 'create_case' => true, 'manage_doctors' => true]);
        $mine->update(['technician_id' => $tech->id]);

        $this->assertTrue($tech->isStaff());
        $this->assertSame(route('dashboard'), $tech->homeUrl());
        $this->assertTrue($tech->can('clinicalUpdate', $mine), 'still updates the exam on assigned cases without edit_case');
        $this->assertFalse($tech->can('update', $mine));
        $this->actingAs($tech)->get(route('dashboard'))->assertOk();
        $this->get(route('accounts.index'))->assertOk();
        $this->get(route('cases.create'))->assertOk();
        $this->get(route('doctors.index'))->assertOk();
        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('technician'))->assertOk();
        // Without «كل حالات فروعه» the rest of the branch stays hidden.
        $this->get(route('cases.show', $mine))->assertOk();
        $this->get(route('cases.show', $other))->assertNotFound();
    }

    public function test_view_all_cases_opens_the_rest_of_the_branch_but_not_other_branches(): void
    {
        $mine = MedicalCase::factory()->create();
        $other = MedicalCase::factory()->create(['branch_id' => $mine->branch_id]);
        $elsewhere = MedicalCase::factory()->create();
        $tech = $this->technician($mine, ['view_all_cases' => true]);
        $mine->update(['technician_id' => $tech->id]);

        $this->assertTrue(AccessScope::allowsCase($tech, $other));
        $this->assertFalse(AccessScope::allowsCase($tech, $elsewhere));
        $this->actingAs($tech)->get(route('cases.show', $other))->assertOk();
        // «الحالات المسندة إليّ» keeps listing only the assigned ones.
        $this->get(route('technician'))->assertOk()->assertSee($mine->case_code)->assertDontSee($other->case_code);
    }

    public function test_admin_sets_technician_permissions_from_the_users_page(): void
    {
        $case = MedicalCase::factory()->create();
        $tech = $this->technician($case);
        $admin = User::factory()->create(['role' => Role::Admin]);

        Livewire::actingAs($admin)->test('pages::admin.users')->call('edit', $tech->id)
            ->set('overrides.view_financials', 'allow')->set('overrides.view_all_cases', 'allow')
            ->call('save')->assertHasNoErrors();

        $tech->refresh();
        $this->assertTrue($tech->hasPermission(Permission::ViewFinancials));
        $this->assertTrue($tech->hasPermission(Permission::ViewAllCases));
        $this->assertFalse($tech->hasPermission(Permission::ManageUsers));
    }
}
