<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CaseFile;
use App\Models\DriveConnection;
use App\Models\MedicalCase;
use App\Models\User;
use App\Services\Drive\DriveMigration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DriveMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_files_are_copied_verified_and_the_system_switches_to_the_new_account(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $branch = Branch::factory()->create();
        $case = MedicalCase::factory()->create(['branch_id' => $branch->id]);
        $old = DriveConnection::create(['refresh_token' => 'old-token', 'account_id' => 'old', 'account_email' => 'old@gmail.com']);
        $new = DriveConnection::create(['refresh_token' => 'new-token', 'account_id' => 'new', 'account_email' => 'new@gmail.com', 'role' => 'target']);
        $contents = ['old-a' => 'first file bytes', 'old-b' => 'second file, a bit longer'];
        $ready = [];
        foreach ($contents as $id => $content) {
            $ready[] = CaseFile::factory()->create(['medical_case_id' => $case->id, 'type' => 'report', 'disk' => 'drive', 'storage_status' => 'ready', 'provider_id' => $id, 'size' => strlen($content), 'mime' => 'application/pdf', 'uploaded_by' => $admin->id]);
        }
        $trashed = $ready[1];
        $trashed->delete();
        $pending = CaseFile::factory()->create(['medical_case_id' => $case->id, 'type' => 'report', 'disk' => 'drive', 'storage_status' => 'pending', 'provider_id' => 'old-pending', 'size' => 3, 'uploaded_by' => $admin->id]);

        $next = 0;
        $uploaded = '';
        Http::preventStrayRequests();
        Http::fake(function ($request) use (&$next, &$uploaded, $contents) {
            $url = $request->url();
            if (str_contains($url, 'oauth2.googleapis.com')) {
                return Http::response(['access_token' => str_contains($request->body(), 'new-token') ? 'new-access' : 'old-access']);
            }
            if (str_contains($url, 'alt=media')) {
                $this->assertSame('Bearer old-access', $request->header('Authorization')[0]);
                foreach ($contents as $id => $content) {
                    if (str_contains($url, '/files/'.$id.'?')) {
                        return Http::response($content);
                    }
                }
            }
            $this->assertSame('Bearer new-access', $request->header('Authorization')[0]);
            if (str_contains($url, 'generateIds')) {
                return Http::response(['ids' => ['new-'.++$next]]);
            }
            if ($request->method() === 'PATCH') {
                return Http::response([], 200, ['Location' => 'https://www.googleapis.com/upload/session']);
            }
            if ($request->method() === 'PUT') {
                $uploaded = $request->body();

                return Http::response(['id' => 'uploaded']);
            }
            if ($request->method() === 'GET') {
                return Http::response(['size' => strlen($uploaded), 'md5Checksum' => md5($uploaded)]);
            }

            return Http::response(['id' => 'created']);
        });

        $this->assertTrue(app(DriveMigration::class)->run(60));

        $this->assertSame('active', $new->fresh()->role);
        $this->assertSame('retired', $old->fresh()->role);
        $ids = CaseFile::withTrashed()->whereIn('id', [$ready[0]->id, $trashed->id])->pluck('provider_id')->all();
        $this->assertCount(2, array_filter($ids, fn ($id) => str_starts_with($id, 'new-')));
        $this->assertNull(CaseFile::withTrashed()->whereNotNull('migrated_provider_id')->first());
        $this->assertNull($pending->fresh()->provider_id);
        $this->assertSame([], Storage::disk('local')->allFiles('drive-migration'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'drive.migrated']);
    }

    public function test_cancel_keeps_the_current_account_and_page_shows_progress(): void
    {
        config(['drive.client_id' => 'id', 'drive.client_secret' => 'secret']);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $old = DriveConnection::create(['refresh_token' => 't', 'account_id' => 'old', 'account_email' => 'old@gmail.com']);
        DriveConnection::create(['refresh_token' => 't2', 'account_id' => 'new', 'account_email' => 'new@gmail.com', 'role' => 'target']);
        // One file still to copy, and Google unreachable: the page's copy slice can't finish.
        CaseFile::factory()->create(['disk' => 'drive', 'storage_status' => 'ready', 'provider_id' => 'old-a', 'size' => 3, 'uploaded_by' => $admin->id]);
        Http::fake(fn () => Http::response([], 500));

        Livewire::test('pages::admin.drive')->assertSee('new@gmail.com')->assertSee('نقل الملفات إلى الحساب الجديد')
            ->call('cancel');

        $this->assertNull(DriveConnection::target());
        $this->assertSame('active', $old->fresh()->role);
        Livewire::test('pages::admin.drive')->assertSee('old@gmail.com')->assertSee('نقل الملفات إلى حساب Google آخر');
    }

    public function test_page_poll_copies_a_slice_after_the_response(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $old = DriveConnection::create(['refresh_token' => 't', 'account_id' => 'old', 'account_email' => 'old@gmail.com']);
        $new = DriveConnection::create(['refresh_token' => 't2', 'account_id' => 'new', 'account_email' => 'new@gmail.com', 'role' => 'target']);

        // Nothing left to copy, so the slice run after the poll's response switches accounts.
        Livewire::test('pages::admin.drive')->call('tick');
        app()->terminate();
        $this->assertSame('active', $new->fresh()->role);
        $this->assertSame('retired', $old->fresh()->role);
    }
}
