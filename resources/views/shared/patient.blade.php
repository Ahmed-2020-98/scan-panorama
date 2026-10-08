<x-layouts::public :title="$case->patient->name">
    @auth
        @if (auth()->user()->isStaff())
            <flux:callout icon="information-circle" color="sky" class="mb-6">
                <flux:callout.text>أنت تشاهد رابط المريض كما يراه المريض: بدون ملاحظات الطبيب أو الملاحظات الطبية.</flux:callout.text>
            </flux:callout>
        @endif
    @endauth

    @include('partials.case-view', [
        'case' => $case,
        'forPatient' => true,
        'fileUrl' => fn (\App\Models\CaseFile $file, string $mode) => route('patient-share.file', [$case->patient_share_token, $file, $mode]),
    ])

    <p class="mt-6 text-center text-xs text-zinc-400">
        هذا رابط خاص بك لعرض وتحميل نتائج الأشعة. لا تشاركه إلا مع طبيبك.
        @if ($case->share_expires_at)
            صالح حتى <span class="ltr-nums">{{ $case->share_expires_at->format('d/m/Y') }}</span>.
        @endif
    </p>
</x-layouts::public>
