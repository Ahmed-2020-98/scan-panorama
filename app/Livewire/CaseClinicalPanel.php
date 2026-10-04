<?php

namespace App\Livewire;

use App\Jobs\UploadCaseFileToDrive;
use App\Models\MedicalCase;
use App\Services\CaseClinicalUpdates;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CaseClinicalPanel extends Component
{
    #[Locked]
    public MedicalCase $medicalCase;

    public string $notes = '';

    public string $workflow = 'new';

    public string $uploadType = 'report';

    public function mount(MedicalCase $medicalCase): void
    {
        $this->medicalCase = $medicalCase;
        $this->authorize('view', $medicalCase);
        $this->notes = (string) (auth()->user()->isDoctor() ? $medicalCase->medical_notes : $medicalCase->technical_notes);
        $this->workflow = $medicalCase->workflow_status->value;
    }

    public function save(): void
    {
        $this->authorize('clinicalUpdate', $this->medicalCase);
        CaseClinicalUpdates::update(auth()->user(), $this->medicalCase, auth()->user()->isDoctor() ? ['medical_notes' => $this->notes] : ['technical_notes' => $this->notes, 'workflow_status' => $this->workflow]);
        Flux::toast(text: 'تم حفظ بيانات الفحص.');
    }

    public function retryFile(int $id): void
    {
        $this->authorize('uploadFiles', $this->medicalCase);
        $file = $this->medicalCase->files()->where('disk', 'drive')->where('storage_status', 'failed')->findOrFail($id);
        abort_unless($file->staging_path && Storage::disk('local')->exists($file->staging_path), 422, 'انتهت صلاحية الملف المؤقت. أعد رفعه.');
        $file->update(['storage_status' => 'pending', 'storage_error' => null]);
        UploadCaseFileToDrive::dispatch($file->id);
    }

    public function render(): View
    {
        $this->authorize('view', $this->medicalCase);

        return view('livewire.case-clinical-panel', ['case' => $this->medicalCase, 'files' => $this->medicalCase->files()->get()]);
    }
}
