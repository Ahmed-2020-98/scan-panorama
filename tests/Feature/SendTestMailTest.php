<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendTestMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_a_test_email_and_rejects_a_bad_address(): void
    {
        Mail::fake();

        $this->artisan('mail:test', ['to' => 'owner@example.com'])->assertSuccessful();
        $this->artisan('mail:test', ['to' => 'not-an-email'])->assertFailed();
    }
}
