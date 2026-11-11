<?php
use App\Models\{DriveConnection,CaseFile};
use App\Services\Drive\DriveMigration;
use App\Jobs\UploadCaseFileToDrive;
use App\Support\{ActivityLogger,QueueKick};
use Illuminate\Support\Facades\Storage;
use Flux\Flux;
use Livewire\Attributes\{Title,Computed};
use Livewire\Component;
new #[Title('Google Drive والملفات')] class extends Component {
    public string $sharedDriveId='';
    public function boot(): void { abort_unless(auth()->user()->isAdmin(),403); }
    public function mount(): void {
        $this->sharedDriveId=(string)DriveConnection::active()?->shared_drive_id;
        if (DriveConnection::target()) { DriveMigration::runAfterResponse(); }
    }
    public function save(): void {
        $this->validate(['sharedDriveId'=>['nullable','regex:/^[A-Za-z0-9_-]+$/','max:200']]);
        $connection=DriveConnection::activeOrFail();
        abort_if($connection->root_folder_id || CaseFile::where('disk','drive')->exists(),422,'لا يمكن تغيير وجهة التخزين بعد بدء رفع الملفات.');
        $connection->update(['shared_drive_id'=>$this->sharedDriveId ?: null]); ActivityLogger::log('drive.destination_updated'); Flux::toast(text:'تم حفظ وجهة Drive.');
    }
    /** Polled while a migration runs: each poll copies a short slice (hosts kill long processes). */
    public function tick(): void {
        unset($this->migration, $this->counts);
        if (DriveConnection::target()) { DriveMigration::runAfterResponse(); }
    }
    public function retry(): void {
        abort_unless(DriveConnection::target(), 404);
        \Illuminate\Support\Facades\Cache::forget('drive-migration-failed');
        DriveMigration::runAfterResponse();
        Flux::toast(text:'تمت إعادة محاولة نقل الملفات المتبقية.');
    }
    public function cancel(): void {
        DriveMigration::cancel(); ActivityLogger::log('drive.migration_cancelled');
        Flux::toast(text:'تم إلغاء النقل. النظام يعمل على الحساب الحالي كما كان.');
    }
    #[Computed] public function counts(): array { return CaseFile::where('disk','drive')->selectRaw('storage_status, COUNT(*) as total')->groupBy('storage_status')->pluck('total','storage_status')->all(); }
    /** Newest failed transfers; retryableIds says which still have their temporary copy to retry from. */
    #[Computed] public function failedFiles() {
        return CaseFile::where('disk','drive')->where('storage_status','failed')->with('medicalCase.patient')->latest()->limit(30)->get();
    }
    /** @return list<int> */
    #[Computed] public function retryableIds(): array {
        return $this->failedFiles->filter(fn (CaseFile $f) => $f->staging_path && Storage::disk('local')->exists($f->staging_path))->modelKeys();
    }
    public function retryFailed(): void {
        $ids=CaseFile::where('disk','drive')->where('storage_status','failed')->whereNotNull('staging_path')->get()
            ->filter(fn (CaseFile $f) => Storage::disk('local')->exists($f->staging_path))->modelKeys();
        CaseFile::whereKey($ids)->update(['storage_status'=>'pending','storage_error'=>null]);
        foreach($ids as $id){ UploadCaseFileToDrive::dispatch($id); }
        if($ids){ QueueKick::drainAfterResponse(); }
        unset($this->counts, $this->failedFiles, $this->retryableIds);
        Flux::toast(text: $ids ? 'بدأت إعادة نقل '.count($ids).' ملف. حدّث الصفحة بعد دقيقة.' : 'مفيش ملفات ينفع تتنقل تاني. ارفعها من جهازك من صفحة الحالة.');
    }
    #[Computed] public function migration(): ?array { return DriveConnection::target() ? DriveMigration::progress() + ['email'=>DriveConnection::target()?->account_email] : null; }
}; ?>
<div class="mx-auto w-full max-w-4xl space-y-5" @if($this->migration) wire:poll.8s="tick" @endif>
<x-page-header title="Google Drive والملفات" subtitle="ترفع الملفات من النظام، وينظمها داخل حساب المركز تلقائيًا" />
@if(session('drive_error'))<flux:callout color="red" icon="exclamation-triangle">{{ session('drive_error') }}</flux:callout>@endif
@if(session('drive_success'))<flux:callout color="green" icon="check-circle">{{ session('drive_success') }}</flux:callout>@endif

<x-panel title="اتصال حساب المركز" icon="cloud">
    @php($active = DriveConnection::active())
    @if($active)
        <div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg bg-zinc-50 p-3">
            <flux:badge color="green" size="sm">متصل</flux:badge>
            <span class="text-sm text-zinc-700">الحساب الحالي:</span>
            <span class="font-medium" dir="ltr">{{ $active->account_email ?? 'حساب المركز' }}</span>
        </div>
    @else
        <p class="mb-4 text-sm text-zinc-600">Google Drive غير متصل. اربط حساب المركز لتفعيل رفع الملفات الجديدة.</p>
    @endif
    @if(config('drive.client_id') && config('drive.client_secret'))
        <div class="flex flex-wrap gap-2">
            <flux:button :variant="$active ? 'ghost' : 'primary'" :href="route('drive.connect')">{{ $active ? 'إعادة تفويض الحساب نفسه' : 'ربط حساب Google Drive' }}</flux:button>
            @if($active && ! $this->migration)
                <flux:button variant="primary" icon="arrows-right-left" :href="route('drive.connect', ['migrate' => 1])">نقل الملفات إلى حساب Google آخر</flux:button>
            @endif
        </div>
    @else
        <flux:callout icon="information-circle" color="amber"><flux:callout.heading>بيانات تطبيق Google مطلوبة</flux:callout.heading><flux:callout.text>أضف Client ID وClient Secret وعنوان Callback إلى إعدادات الخادم حسب دليل الربط، ثم اربط حساب المركز من هنا.</flux:callout.text></flux:callout>
    @endif
    <p class="mt-4 text-sm text-zinc-600">ربط Google يمنح النظام صلاحية إدارة الملفات التي ينشئها. الملفات تبقى خاصة وتفتح من خلال روابط النظام المصرح بها.</p>
</x-panel>

@if($m = $this->migration)
    @php($pct = $m['total'] ? (int) floor($m['copied'] * 100 / $m['total']) : 100)
    <x-panel title="نقل الملفات إلى الحساب الجديد" icon="arrows-right-left">
        <p class="text-sm text-zinc-700">من <span dir="ltr" class="font-medium">{{ $active?->account_email ?? 'الحساب الحالي' }}</span> إلى <span dir="ltr" class="font-medium">{{ $m['email'] ?? 'الحساب الجديد' }}</span></p>
        <div class="mt-4 h-3 w-full overflow-hidden rounded-full bg-zinc-100" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"><div class="h-full rounded-full bg-brand-600 transition-all duration-500" style="width: {{ $pct }}%"></div></div>
        <div class="mt-2 flex flex-wrap justify-between gap-2 text-sm"><span class="ltr-nums font-medium">{{ $m['copied'] }} / {{ $m['total'] }} ملف</span><span class="text-zinc-500">{{ $pct }}%</span></div>
        @if($m['failed'] > 0)
            <flux:callout class="mt-4" color="amber" icon="exclamation-triangle"><flux:callout.text>تعذر نقل {{ $m['failed'] }} ملف في المحاولة الأخيرة. تأكد من وجود مساحة كافية في الحساب الجديد ثم أعد المحاولة.</flux:callout.text></flux:callout>
            <flux:button class="mt-3" wire:click="retry" icon="arrow-path">إعادة المحاولة</flux:button>
        @endif
        <ul class="mt-4 space-y-1 text-sm text-zinc-600">
            <li>• النظام يعمل طبيعيًا أثناء النقل، والملفات الجديدة تُنقل أيضًا.</li>
            <li>• عند اكتمال النقل يتحول النظام للحساب الجديد تلقائيًا، ويبقى الحساب القديم كما هو كنسخة احتياطية.</li>
            <li>• اترك هذه الصفحة مفتوحة حتى يكتمل النقل.</li>
        </ul>
        <div class="mt-4"><flux:button size="sm" variant="ghost" class="text-red-600!" wire:click="cancel" wire:confirm="إلغاء النقل؟ يبقى النظام على الحساب الحالي، ويمكن البدء من جديد لاحقًا.">إلغاء النقل</flux:button></div>
    </x-panel>
@endif

@if($active && ! $active->root_folder_id && ! CaseFile::where('disk','drive')->exists())<x-panel title="وجهة التخزين"><form wire:submit="save" class="space-y-3"><flux:input wire:model="sharedDriveId" label="Shared Drive ID (اختياري)" dir="ltr" description="اتركه فارغًا لاستخدام My Drive. يحدد قبل رفع أول ملف، ويحتاج صلاحية كتابة للحساب المتصل." /><flux:button type="submit">حفظ الوجهة</flux:button></form></x-panel>@endif
<x-panel title="حالة رفع الملفات على Google Drive" icon="cloud-arrow-up">
    <p class="text-sm text-zinc-600">أي ملف بيترفع على الموقع الأول، وبعدها الموقع بينقله لوحده لحساب Drive بتاع المركز. الأرقام دي بتقولك الملفات وصلت لفين.</p>
    @php($failedCount = $this->counts['failed'] ?? 0)
    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-test="drive-counts">
        @foreach([
            'ready' => ['محفوظة على Drive', 'اتنقلت وبتفتح عادي'],
            'pending' => ['مستنية دورها', 'هتتنقل لوحدها خلال دقايق'],
            'uploading' => ['بتتنقل دلوقتي', 'جاري نقلها لـ Drive'],
            'failed' => ['فشل نقلها', $failedCount ? 'محتاجة منك تصرف، شوف تحت' : 'مفيش ملفات واقفة'],
        ] as $state => [$label, $hint])
            <div @class(['rounded-lg p-3', 'bg-red-50 ring-1 ring-red-200' => $state==='failed' && $failedCount, 'bg-zinc-50' => ! ($state==='failed' && $failedCount)])>
                <div class="text-sm font-medium text-zinc-700">{{ $label }}</div>
                <div @class(['mt-1 text-2xl font-semibold ltr-nums', 'text-red-700' => $state==='failed' && $failedCount])>{{ $this->counts[$state] ?? 0 }}</div>
                <div class="mt-1 text-xs text-zinc-500">{{ $hint }}</div>
            </div>
        @endforeach
    </div>
    @if($failedCount)
        <div class="mt-5 rounded-lg border border-red-200 p-4" data-test="drive-failed">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="font-medium">{{ $failedCount }} ملف مانتقلش لـ Drive</div>
                    <p class="mt-1 text-sm text-zinc-600">الملفات دي مش ظاهرة للطبيب لحد ما تتنقل. اضغط «حاول تاني»، ولو مكتوب إن النسخة المؤقتة اتمسحت يبقى لازم ترفع الملف من جهازك تاني من صفحة الحالة.</p>
                </div>
                @if($this->retryableIds)
                    <flux:button variant="primary" icon="arrow-path" wire:click="retryFailed">حاول تاني في الكل</flux:button>
                @endif
            </div>
            <div class="mt-3 divide-y text-sm">
                @foreach($this->failedFiles as $file)
                    <div class="flex flex-wrap items-center justify-between gap-3 py-2.5">
                        <div class="min-w-0">
                            <div class="break-all font-medium">{{ $file->original_name }}</div>
                            <div class="text-xs text-zinc-500">
                                @if($file->medicalCase)الحالة <span class="ltr-nums">{{ $file->medicalCase->case_code }}</span> · {{ $file->medicalCase->patient?->name }} · @endif
                                اترفع {{ $file->created_at->translatedFormat('j F Y') }}
                            </div>
                            <div class="mt-0.5 text-xs text-red-700">{{ in_array($file->id, $this->retryableIds, true) ? ($file->storage_error ?: 'النقل وقف قبل ما يكمل.') : 'النسخة المؤقتة اتمسحت. ارفع الملف من جهازك تاني.' }}</div>
                        </div>
                        @if($file->medicalCase)<flux:button size="sm" :href="route('cases.show', $file->medicalCase)" wire:navigate>افتح الحالة</flux:button>@endif
                    </div>
                @endforeach
            </div>
            @if($failedCount > $this->failedFiles->count())<p class="mt-2 text-xs text-zinc-500">ظاهر أحدث {{ $this->failedFiles->count() }} ملف بس.</p>@endif
        </div>
    @endif
    <p class="mt-4 text-sm text-zinc-600">الملف اللي بيفشل نقله بيفضل محفوظ على الموقع {{ (int) config('drive.staging_days', 7) }} أيام عشان تحاول تاني. بعد المدة دي بيتمسح، ولازم يترفع من الجهاز من جديد.</p>
</x-panel>
<x-panel title="تنظيم الملفات"><p class="text-sm text-zinc-700" dir="ltr">Radiology Center / Branch / Year / Patient / Case</p><p class="mt-3 text-sm text-zinc-600">داخل الحالة: الصور، التقارير، DICOM، الفيديوهات، وطلبات الطبيب. الملفات السابقة المحفوظة على الخادم تستمر في الفتح؛ الملفات الجديدة تستخدم مزود التخزين المحدد.</p></x-panel></div>
