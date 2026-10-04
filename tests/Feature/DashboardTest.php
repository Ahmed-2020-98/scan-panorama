<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_staff_can_visit_the_dashboard(): void
    {
        $this->actingAs(User::factory()->reception()->create());

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_doctors_can_not_visit_the_dashboard(): void
    {
        $this->actingAs(User::factory()->doctor()->create());

        $this->get(route('dashboard'))->assertForbidden();
    }
}
