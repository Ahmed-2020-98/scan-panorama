<x-layouts::public :title="$case->patient->name">
    @auth
        @if (auth()->user()->isStaff())
            <flux:callout icon="information-circle" color="sky" class="mb-6">
                <flux:callout.text>أنت تشاهد رابط المشاركة كما يراه الطبيب. زيارتك لا تُحتسب كفتح من الطبيب.</flux:callout.text>
            </flux:callout>
        @endif
    @endauth

    @include('partials.case-view', [
        'case' => $case,
        'fileUrl' => fn (\App\Models\CaseFile $file, string $mode) => route('shared.file', [$case->share_token, $file, $mode]),
    ])

    <p class="mt-6 text-center text-xs text-zinc-400">
        هذا رابط خاص بالطبيب المعالج. لا تشاركه مع أي شخص آخر.
        @if ($case->share_expires_at)
            صالح حتى <span class="ltr-nums">{{ $case->share_expires_at->format('d/m/Y') }}</span>.
        @endif
    </p>
</x-layouts::public>
