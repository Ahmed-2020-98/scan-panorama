<?php

namespace App\Livewire;

use App\Models\MedicalCase;
use App\Support\ActivityLogger;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CaseSharePanel extends Component
{
    #[Locked]
    public MedicalCase $medicalCase;

    /** @var list<string> */
    public array $selected = [];

    public function mount(MedicalCase $medicalCase): void
    {
        $this->medicalCase = $medicalCase;
        $this->authorize('share', $medicalCase);
        $this->selected = array_values($medicalCase->files()->where('storage_status', 'ready')->where('is_shared', true)->pluck('id')->map(fn ($id) => (string) $id)->all());
    }

    public function saveSelection(): void
    {
        $this->authorize('share', $this->medicalCase);
        $this->validate(['selected' => ['array'], 'selected.*' => ['integer', 'distinct']]);
        $files = $this->medicalCase->files()->where('storage_status', 'ready');
        abort_unless((clone $files)->whereIn('id', $this->selected)->count() === count($this->selected), 422);
        DB::transaction(function () use ($files) {
            $before = (clone $files)->where('is_shared', true)->pluck('id')->all();
            (clone $files)->update(['is_shared' => false]);
            (clone $files)->whereIn('id', $this->selected)->update(['is_shared' => true]);
            ActivityLogger::log('share.files_updated', $this->medicalCase, ['before' => $before, 'after' => $this->selected]);
        });
        Flux::toast(variant: 'success', text: 'تم تحديث الملفات داخل الرابط.');
    }

    public function markCopied(): void
    {
        $this->authorize('share', $this->medicalCase);
        $this->medicalCase->markShared();
        ActivityLogger::log('share.copied', $this->medicalCase);
        Flux::toast(text: 'تم نسخ الرابط.');
    }

    public function markWhatsapp(): void
    {
        $this->authorize('share', $this->medicalCase);
        $this->medicalCase->markShared();
        ActivityLogger::log('share.whatsapp', $this->medicalCase);
    }

    public function regenerateLink(): void
    {
        $this->authorize('share', $this->medicalCase);
        $this->medicalCase->regenerateShareLink();
        ActivityLogger::log('share.regenerated', $this->medicalCase);
        Flux::toast(text: 'تم إنشاء رابط جديد.');
    }

    public function revokeLink(): void
    {
        $this->authorize('share', $this->medicalCase);
        $this->medicalCase->revokeShareLink();
        ActivityLogger::log('share.revoked', $this->medicalCase);
    }

    public function render(): View
    {
        $this->authorize('share', $this->medicalCase);

        return view('livewire.case-share-panel', ['case' => $this->medicalCase->fresh(), 'files' => $this->medicalCase->files()->where('storage_status', 'ready')->get()]);
    }
}
