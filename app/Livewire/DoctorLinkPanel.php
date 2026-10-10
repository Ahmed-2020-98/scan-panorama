<?php

namespace App\Livewire;

use App\Models\Doctor;
use App\Support\ActivityLogger;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Manager controls for a doctor's private cases link. */
class DoctorLinkPanel extends Component
{
    #[Locked]
    public Doctor $doctor;

    public function boot(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function create(): void
    {
        $this->doctor->linkUrl();
        ActivityLogger::log('doctor_link.created', $this->doctor);
        Flux::toast(variant: 'success', text: 'تم إنشاء رابط حالات الطبيب.');
    }

    public function regenerate(): void
    {
        $this->doctor->regenerateLink();
        ActivityLogger::log('doctor_link.regenerated', $this->doctor);
        Flux::toast(text: 'تم إنشاء رابط جديد. الرابط القديم لم يعد يعمل.');
    }

    public function revoke(): void
    {
        $this->doctor->revokeLink();
        ActivityLogger::log('doctor_link.revoked', $this->doctor);
        Flux::toast(text: 'تم إلغاء رابط الطبيب.');
    }

    public function markSent(string $via): void
    {
        ActivityLogger::log('doctor_link.'.($via === 'whatsapp' ? 'whatsapp' : 'copied'), $this->doctor);
        if ($via !== 'whatsapp') {
            Flux::toast(text: 'تم نسخ الرابط.');
        }
    }

    public function render(): View
    {
        return view('livewire.doctor-link-panel', ['doctor' => $this->doctor->fresh(['user'])]);
    }
}
