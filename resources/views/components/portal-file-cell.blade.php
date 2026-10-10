@props(['files', 'type', 'compact' => false, 'url' => null])

{{-- One cell of the doctor's cases table: open/download the files of one type --}}
@php
    $files = $files->where('type', $type)->values();
    // $url: fn (CaseFile $file, string $mode): string — defaults to the logged-in file route.
    $url ??= fn ($file, $mode) => route('files.show', [$file, $mode]);
    $buttonClass = 'inline-flex items-center gap-1.5 whitespace-nowrap rounded-md bg-brand-50 font-medium text-brand-700 hover:bg-brand-100 '
        .($compact ? 'px-2 py-1 text-xs' : 'px-2.5 py-1 text-sm');
@endphp

@if ($files->isEmpty())
    <span class="text-zinc-300">—</span>
@elseif ($files->count() === 1)
    @php($file = $files->first())
    <a href="{{ $url($file, $file->isViewable() ? 'view' : 'download') }}" @if ($file->isViewable()) target="_blank" @endif class="{{ $buttonClass }}">
        <flux:icon :name="$file->isViewable() ? 'eye' : 'arrow-down-tray'" variant="micro" />
        @if ($type === \App\Enums\CaseFileType::Dicom && ! $compact)
            تحميل DICOM
        @else
            {{ $file->isViewable() ? 'عرض' : 'تحميل' }}
        @endif
    </a>
@else
    <flux:dropdown position="bottom" align="start">
        <button type="button" class="{{ $buttonClass }}">
            <flux:icon.document-duplicate variant="micro" />
            <span><span class="ltr-nums">{{ $files->count() }}</span> ملفات</span>
            @unless ($compact)
                <flux:icon.chevron-down variant="micro" />
            @endunless
        </button>
        <flux:menu>
            @foreach ($files as $file)
                <flux:menu.item :href="$url($file, $file->isViewable() ? 'view' : 'download')" :target="$file->isViewable() ? '_blank' : null" :icon="$file->isViewable() ? 'eye' : 'arrow-down-tray'">
                    <span dir="ltr">{{ $file->original_name }}</span>
                </flux:menu.item>
            @endforeach
        </flux:menu>
    </flux:dropdown>
@endif
