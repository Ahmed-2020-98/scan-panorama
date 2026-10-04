<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\MedicalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShareLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_cases_get_an_unguessable_share_token_and_expiry(): void
    {
        $case = MedicalCase::factory()->create();

        $this->assertSame(48, strlen($case->share_token));
        $this->assertTrue($case->share_expires_at->isFuture());
        $this->assertTrue($case->isShareActive());
    }

    public function test_share_link_shows_the_case_and_records_the_open(): void
    {
        $case = MedicalCase::factory()->create();

        $this->get($case->shareUrl())
            ->assertOk()
            ->assertSee($case->patient->name)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $case->refresh();
        $this->assertNotNull($case->first_opened_at);
        $this->assertSame(1, $case->open_count);
        $this->assertDatabaseHas('activity_logs', ['action' => 'share.opened', 'medical_case_id' => $case->id, 'user_id' => null]);
    }

    public function test_staff_previewing_the_link_does_not_count_as_opened(): void
    {
        $case = MedicalCase::factory()->create();

        $this->actingAs(User::factory()->reception()->create())->get($case->shareUrl())->assertOk();

        $this->assertNull($case->refresh()->first_opened_at);
    }

    public function test_revoked_expired_and_unknown_links_are_rejected(): void
    {
        $revoked = MedicalCase::factory()->create();
        $revoked->revokeShareLink();

        $expired = MedicalCase::factory()->create();
        $expired->forceFill(['share_expires_at' => now()->subDay()])->save();

        $this->get($revoked->shareUrl())->assertStatus(410);
        $this->get($expired->shareUrl())->assertStatus(410);
        $this->get(route('shared.show', str_repeat('a', 48)))->assertNotFound();
    }

    public function test_regenerating_the_link_invalidates_the_old_one(): void
    {
        $case = MedicalCase::factory()->create();
        $oldUrl = $case->shareUrl();

        $case->regenerateShareLink();

        $this->get($oldUrl)->assertNotFound();
        $this->get($case->shareUrl())->assertOk();
    }

    public function test_files_can_only_be_fetched_through_their_own_case_link(): void
    {
        $case = MedicalCase::factory()->create();
        $other = MedicalCase::factory()->create();

        Storage::disk('local')->put('cases/x/report.pdf', '%PDF-1.4');
        $file = CaseFile::factory()->create(['medical_case_id' => $case->id, 'path' => 'cases/x/report.pdf', 'mime' => 'application/pdf', 'original_name' => 'report.pdf']);

        $this->get(route('shared.file', [$case->share_token, $file, 'view']))->assertOk();
        $this->get(route('shared.file', [$other->share_token, $file, 'view']))->assertNotFound();

        $case->revokeShareLink();
        $this->get(route('shared.file', [$case->share_token, $file, 'view']))->assertNotFound();
    }
}
