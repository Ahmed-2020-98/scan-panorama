<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\MedicalCase;
use App\Models\User;
use App\Support\AccessScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DoctorDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_doctor_keeps_cases_disables_login_and_restores(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $case = MedicalCase::factory()->create();
        $doctor = $case->doctor;
        $name = $doctor->display_name;

        Livewire::test('pages::admin.doctors.index')->call('delete', $doctor->id)->assertHasNoErrors();

        $this->assertSoftDeleted($doctor);
        $this->assertFalse($doctor->user->fresh()->is_active);
        $this->assertNotSoftDeleted($case);
        $this->assertSame($name, $case->fresh()->doctor->display_name);
        $this->assertFalse(AccessScope::doctors($admin)->whereKey($doctor->id)->exists());
        $this->get(route('cases.show', $case))->assertOk()->assertSee($name);
        $this->get(route('doctors.index'))->assertOk()->assertDontSee($doctor->code);

        $this->get(route('recycle-bin'))->assertOk()->assertSee($name);
        Livewire::test('pages::admin.recycle-bin')->call('restore', 'doctor', $doctor->id);

        $this->assertNotSoftDeleted($doctor->fresh());
        $this->assertTrue($doctor->user->fresh()->is_active);
    }

    public function test_deleted_doctor_cannot_log_in(): void
    {
        $doctor = Doctor::factory()->create();
        $doctor->user->update(['phone' => '01055555555']);
        $this->actingAs(User::factory()->admin()->create());
        Livewire::test('pages::admin.doctors.index')->call('delete', $doctor->id);
        auth()->logout();

        $this->post(route('login.store'), ['email' => '01055555555', 'password' => 'password']);
        $this->assertGuest();
    }
}
