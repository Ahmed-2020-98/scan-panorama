<?php

use App\Models\CaseFile;
use App\Models\MedicalCase;
use App\Support\ActivityLogger;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::portal')] class extends Component {
    public MedicalCase $medicalCase;

    public function mount(MedicalCase $case): void
    {
        $this->authorize('view', $case);

        $this->medicalCase = $case;

        $case->recordOpen();
        ActivityLogger::log('portal.opened', $case);
    }

    public function render()
    {
        return $this->view()->title($this->medicalCase->patient->name);
    }
}; ?>

<div>
    <a href="{{ route('portal') }}" wire:navigate class="mb-4 inline-flex items-center gap-1 text-sm text-zinc-500 hover:text-zinc-800">
        <flux:icon.arrow-right variant="micro" />
        كل الحالات
    </a>

    <livewire:case-clinical-panel :medical-case="$medicalCase" :key="'doctor-case-'.$medicalCase->id" />

    @include('partials.case-view', [
        'case' => $medicalCase->load(['patient', 'doctor.user', 'branch', 'examType', 'files' => fn($q)=>$q->where('storage_status','ready')]),
        'fileUrl' => fn (CaseFile $file, string $mode) => route('files.show', [$file, $mode]),
    ])
</div>
