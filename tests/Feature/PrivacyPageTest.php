<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_policy_is_public_and_explains_drive_scope(): void
    {
        $this->get(route('privacy'))->assertOk()->assertSee('سياسة الخصوصية')->assertSee('drive.file');
    }
}
