<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AppearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_choose_an_interface_font_and_it_is_applied(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('appearance.edit'))->assertOk()->assertSee('IBM Plex Sans Arabic');

        Livewire::test('pages::settings.appearance')->assertSet('font', 'tajawal')->set('font', 'cairo')->assertHasNoErrors();
        $this->assertSame('cairo', $user->fresh()->font);
        $this->get(route('profile.edit'))->assertSee("--font-sans: 'Cairo'", false);

        Livewire::test('pages::settings.appearance')->set('font', 'tajawal');
        $this->assertNull($user->fresh()->font);
        $this->get(route('profile.edit'))->assertDontSee('data-user-font', false);
    }

    public function test_unknown_font_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('pages::settings.appearance')->set('font', 'comic-sans')->assertHasErrors('font');
        $this->assertNull($user->fresh()->font);
    }
}
