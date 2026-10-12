<?php
use App\Jobs\MigrateDriveFiles;
use App\Models\{DriveConnection,CaseFile};
use App\Services\Drive\DriveMigration;
use App\Support\{ActivityLogger,QueueKick};
use Flux\Flux;
use Livewire\Attributes\{Title,Computed};
use Livewire\Component;
new #[Title('Google Drive والملفات')] class extends Component {
    public string $sharedDriveId='';
    public function boot(): void { abort_unless(auth()->user()->isAdmin(),403); }
    public function mount(): void {
        $this->sharedDriveId=(string)DriveConnection::active()?->shared_drive_id;
        if (DriveConnection::target()) { QueueKick::drainAfterResponse(); }
    }
    public function save(): void {
        $this->validate(['sharedDriveId'=>['nullable','regex:/^[A-Za-z0-9_-]+$/','max:200']]);
        $connection=DriveConnection::activeOrFail();
        abort_if($connection->root_folder_id || CaseFile::where('disk','drive')->exists(),422,'لا يمكن تغيير وجهة التخزين بعد بدء رفع الملفات.');
        $connection->update(['shared_drive_id'=>$this->sharedDriveId ?: null]); ActivityLogger::log('drive.destination_updated'); Flux::toast(text:'تم حفظ وجهة Drive.');
    }
    /** Polled while a migration runs: keeps the copy going on hosts without a worker. */
    public function tick(): void {
        unset($this->migration, $this->counts);
        if (DriveConnection::target()) { QueueKick::drainAfterResponse(); }
    }
    public function retry(): void {
        abort_unless(DriveConnection::target(), 404);
        \Illuminate\Support\Facades\Cache::forget('drive-migration-failed');
        MigrateDriveFiles::dispatch(); QueueKick::drainAfterResponse();
        Flux::toast(text:'تمت إعادة محاولة نقل الملفات المتبقية.');
    }
    public function cancel(): void {
        DriveMigration::cancel(); ActivityLogger::log('drive.migration_cancelled');
        Flux::toast(text:'تم إلغاء النقل. النظام يعمل على الحساب الحالي كما كان.');
    }
    #[Computed] public function counts(): array { return CaseFile::where('disk','drive')->selectRaw('storage_status, COUNT(*) as total')->groupBy('storage_status')->pluck('total','storage_status')->all(); }
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
<x-panel title="متابعة نقل الملفات"><div class="grid gap-3 sm:grid-cols-4">@foreach(['pending'=>'بانتظار النقل','uploading'=>'جارٍ النقل','ready'=>'جاهزة','failed'=>'تحتاج إعادة محاولة'] as $state=>$label)<div class="rounded-lg bg-zinc-50 p-3"><div class="text-sm text-zinc-600">{{ $label }}</div><div class="mt-2 text-xl font-semibold">{{ $this->counts[$state] ?? 0 }}</div></div>@endforeach</div><p class="mt-4 text-sm text-zinc-600">الملفات المؤقتة تُحذف بعد نجاح النقل. الملفات التي فشل نقلها تبقى مؤقتًا لمدة 7 أيام لإعادة المحاولة.</p></x-panel>
<x-panel title="تنظيم الملفات"><p class="text-sm text-zinc-700" dir="ltr">Radiology Center / Branch / Year / Patient / Case</p><p class="mt-3 text-sm text-zinc-600">داخل الحالة: الصور، التقارير، DICOM، الفيديوهات، وطلبات الطبيب. الملفات السابقة المحفوظة على الخادم تستمر في الفتح؛ الملفات الجديدة تستخدم مزود التخزين المحدد.</p></x-panel></div>
