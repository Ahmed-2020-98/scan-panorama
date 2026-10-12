<div class="space-y-5" wire:poll.10s>
<x-panel :title="$case->case_code.' — '.$case->patient->name" icon="clipboard-document-list"><form wire:submit="save" class="space-y-4">
@if(!auth()->user()->isDoctor())<flux:select wire:model="workflow" label="سير الفحص">@foreach(\App\Enums\WorkflowStatus::cases() as $state)<flux:select.option :value="$state->value">{{ $state->label() }}</flux:select.option>@endforeach</flux:select>@endif
<flux:textarea wire:model="notes" :label="auth()->user()->isDoctor() ? 'الملاحظات الطبية / التقرير':'ملاحظات فنية'" rows="4" /><flux:button type="submit" variant="primary">حفظ بيانات الفحص</flux:button></form></x-panel>
@can('uploadFiles',$case)<x-panel title="رفع ملفات الحالة" icon="cloud-arrow-up"><flux:select wire:model.live="uploadType" label="تصنيف الملف"><flux:select.option value="report">التقارير</flux:select.option><flux:select.option value="image">الصور</flux:select.option>@unless(auth()->user()->isDoctor())<flux:select.option value="referral">طلب الطبيب</flux:select.option><flux:select.option value="dicom">DICOM / ZIP</flux:select.option><flux:select.option value="video">الفيديو</flux:select.option>@endunless</flux:select>
@php($type=\App\Enums\CaseFileType::tryFrom($uploadType))
@if($type && (!auth()->user()->isDoctor() || in_array($uploadType,['report','image'],true)))<x-chunk-uploader :case="$case" :type="$type" class="mt-4" wire:key="clinical-uploader-{{ $uploadType }}" />@endif</x-panel>@endcan
@php($sliderImages = $files->filter(fn ($f) => $f->storage_status === 'ready' && $f->isImage())->values())
<x-panel title="ملفات الحالة">
    @if($sliderImages->isNotEmpty())
        <div class="mb-4 flex gap-2 overflow-x-auto pb-1" x-data>
            @foreach($sliderImages as $idx => $img)
                <button type="button" x-on:click="$dispatch('open-slider-staff-{{ $case->id }}', { index: {{ $idx }} })" class="film relative h-24 w-32 shrink-0 cursor-zoom-in overflow-hidden rounded-lg border border-zinc-200 transition hover:border-brand-400" aria-label="عرض {{ $img->original_name }}">
                    <img src="{{ route('files.show',[$img,'view']) }}" alt="" loading="lazy" class="size-full object-contain p-1">
                </button>
            @endforeach
            <flux:button size="sm" icon="photo" class="self-center" x-on:click="$dispatch('open-slider-staff-{{ $case->id }}', { index: 0 })">عرض الصور ({{ $sliderImages->count() }})</flux:button>
        </div>
        <x-image-slider :name="'staff-'.$case->id" :images="$sliderImages->map(fn ($f) => [route('files.show',[$f,'view']), $f->original_name, route('files.show',[$f,'download'])])->all()" />
    @endif
<div class="divide-y">@forelse($files as $file)<div class="flex flex-wrap items-center justify-between gap-3 py-3"><div class="min-w-0"><div class="break-all text-sm">{{ $file->original_name }}</div><small class="text-zinc-500">{{ $file->type->shortLabel() }} · {{ $file->humanSize() }}</small></div>@if($file->storage_status==='ready')<div class="flex gap-2">@if($file->isViewable())<flux:button size="sm" :href="route('files.show',[$file,'view'])" target="_blank">عرض</flux:button>@endif<flux:button size="sm" :href="route('files.show',[$file,'download'])">تحميل</flux:button></div>@else<div class="text-sm text-amber-800">{{ $file->storage_error ?? 'جارٍ النقل إلى Drive' }}@if($file->storage_status==='failed')<flux:button size="sm" wire:click="retryFile({{ $file->id }})">إعادة المحاولة</flux:button>@endif</div>@endif</div>@empty<p class="text-sm text-zinc-500">لم ترفع ملفات بعد.</p>@endforelse</div></x-panel>
</div>
