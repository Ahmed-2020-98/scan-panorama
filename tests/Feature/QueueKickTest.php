<?php

namespace Tests\Feature;

use App\Jobs\UploadCaseFileToDrive;
use App\Support\QueueKick;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QueueKickTest extends TestCase
{
    use RefreshDatabase;

    public function test_queued_drive_jobs_are_drained_after_the_response(): void
    {
        config(['queue.default' => 'database']);
        UploadCaseFileToDrive::dispatch(999999);
        $this->assertSame(1, DB::table('jobs')->count());

        $response = QueueKick::afterResponse(response()->json(['done' => true]));
        $this->assertSame((string) strlen((string) $response->getContent()), $response->headers->get('Content-Length'));

        app()->terminate();
        $this->assertSame(0, DB::table('jobs')->count(), 'missing file job should be consumed without error');
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function test_sync_queue_does_not_register_a_drainer(): void
    {
        config(['queue.default' => 'sync']);
        $response = QueueKick::afterResponse(response()->json(['done' => true]));
        $this->assertNull($response->headers->get('Connection') === 'close' ? 'set' : null);
    }
}
