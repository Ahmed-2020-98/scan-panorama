@props(['case'])

{{-- Compact per-type indicators used in case tables --}}
<div class="flex items-center gap-1.5">
    @foreach (\App\Enums\CaseFileType::cases() as $type)
        @php($count = $case->fileCount($type))
        <span title="{{ $type->label() }}: {{ $count }}" @class([
            'inline-flex h-6 items-center gap-1 rounded-md px-1.5 text-xs font-medium',
            'bg-emerald-50 text-emerald-700' => $count > 0,
            'bg-zinc-100 text-zinc-400' => $count === 0,
        ])>
            <flux:icon :name="$type->icon()" variant="micro" />
            <span class="ltr-nums">{{ $count }}</span>
        </span>
    @endforeach
</div>
