<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\MedicalCase;
use App\Models\Patient;
use App\Models\User;
use App\Services\CampaignAudience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CampaignAudienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_landlines_are_listed_as_unreachable_and_shared_mobiles_are_sent_once(): void
    {
        $admin = User::factory()->admin()->create();
        $mobile = Patient::factory()->create(['name' => 'مريض موبايل', 'phone' => '01012345678']);
        Patient::factory()->create(['name' => 'أخوه', 'phone' => '010 1234 5678']);
        $landline = Patient::factory()->create(['name' => 'مريض أرضي', 'phone' => '0502345678']);

        $recipients = CampaignAudience::for($admin, 'patients');
        $this->assertSame(['01012345678'], $recipients->pluck('phone')->map(fn ($p) => preg_replace('/\D/', '', $p))->all(), 'one message per shared mobile');

        $missed = CampaignAudience::unreachable($admin, 'patients');
        $this->assertSame(['مريض أرضي'], $missed->pluck('name')->all());
        $this->assertSame(route('patients.edit', $landline), $missed->first()['edit_url']);
        $this->assertNotContains($mobile->name, $missed->pluck('name'));
    }

    public function test_unreachable_list_follows_the_branch_filter_and_shows_on_the_page(): void
    {
        $admin = User::factory()->admin()->create();
        [$a, $b] = Branch::factory()->count(2)->create();
        $inA = Patient::factory()->create(['name' => 'أرضي فرع أ', 'phone' => '0502345678']);
        $inB = Patient::factory()->create(['name' => 'أرضي فرع ب', 'phone' => '0503456789']);
        MedicalCase::factory()->create(['patient_id' => $inA->id, 'branch_id' => $a->id]);
        MedicalCase::factory()->create(['patient_id' => $inB->id, 'branch_id' => $b->id]);

        $this->assertSame(['أرضي فرع أ'], CampaignAudience::unreachable($admin, 'patients', $a->id)->pluck('name')->all());

        Livewire::actingAs($admin)->test('pages::admin.campaigns')
            ->set('branch', (string) $a->id)
            ->assertSee('مش موبايل صحيح')
            ->assertSee('أرضي فرع أ')
            ->assertDontSee('أرضي فرع ب');
    }
}
