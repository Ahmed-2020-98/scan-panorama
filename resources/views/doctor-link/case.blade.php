<x-layouts::public :title="$case->patient->name">
    <a href="{{ route('doctor-link.index', $token) }}" class="mb-4 inline-flex items-center gap-1 text-sm text-zinc-500 hover:text-zinc-800">
        <flux:icon.arrow-right variant="micro" />
        كل الحالات
    </a>

    @include('partials.case-view', [
        'case' => $case,
        'fileUrl' => fn (\App\Models\CaseFile $file, string $mode) => route('doctor-link.file', [$token, $file, $mode]),
    ])

    <p class="mt-6 text-center text-xs text-zinc-400">هذا رابط خاص بالطبيب. لا تشاركه مع أي شخص آخر.</p>
</x-layouts::public>
