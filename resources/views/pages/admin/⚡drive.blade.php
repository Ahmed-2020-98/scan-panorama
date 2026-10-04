<?php
use App\Models\{DriveConnection,CaseFile};
use App\Support\ActivityLogger;
use Flux\Flux;
use Livewire\Attributes\{Title,Computed};
use Livewire\Component;
new #[Title('Google Drive والملفات')] class extends Component {
    public string $sharedDriveId='';
    public function boot(): void { abort_unless(auth()->user()->isAdmin(),403); }
    public function mount(): void { $this->sharedDriveId=(string)DriveConnection::first()?->shared_drive_id; }
    public function save(): void {
        $this->validate(['sharedDriveId'=>['nullable','regex:/^[A-Za-z0-9_-]+$/','max:200']]);
        $connection=DriveConnection::firstOrFail();
        abort_if($connection->root_folder_id || CaseFile::where('disk','drive')->exists(),422,'لا يمكن تغيير وجهة التخزين بعد بدء رفع الملفات.');
        $connection->update(['shared_drive_id'=>$this->sharedDriveId ?: null]); ActivityLogger::log('drive.destination_updated'); Flux::toast(text:'تم حفظ وجهة Drive.');
    }
    #[Computed] public function counts(): array { return CaseFile::where('disk','drive')->selectRaw('storage_status, COUNT(*) as total')->groupBy('storage_status')->pluck('total','storage_status')->all(); }
}; ?>
<div class="mx-auto w-full max-w-4xl space-y-5"><x-page-header title="Google Drive والملفات" subtitle="ترفع الملفات من النظام، وينظمها داخل حساب المركز تلقائيًا" />
@if(session('drive_error'))<flux:callout color="red">{{ session('drive_error') }}</flux:callout>@endif
@if(session('drive_success'))<flux:callout color="green">{{ session('drive_success') }}</flux:callout>@endif
<x-panel title="اتصال حساب المركز" icon="cloud"><p class="mb-4 text-sm text-zinc-600">{{ DriveConnection::exists() ? 'تم حفظ تفويض الحساب. نجاح النقل يظهر على كل ملف عند اكتمال الرفع.' : 'Google Drive غير متصل. اربط حساب المركز لتفعيل رفع الملفات الجديدة.' }}</p>
@if(config('drive.client_id') && config('drive.client_secret'))<flux:button variant="primary" :href="route('drive.connect')">{{ DriveConnection::exists() ? 'إعادة تفويض الحساب نفسه':'ربط حساب Google Drive' }}</flux:button>@else<flux:callout icon="information-circle" color="amber"><flux:callout.heading>بيانات تطبيق Google مطلوبة</flux:callout.heading><flux:callout.text>أضف Client ID وClient Secret وعنوان Callback إلى إعدادات الخادم حسب دليل الربط، ثم اربط حساب المركز من هنا.</flux:callout.text></flux:callout>@endif
<p class="mt-4 text-sm text-zinc-600">ربط Google يمنح النظام صلاحية إدارة الملفات التي ينشئها. الملفات تبقى خاصة وتفتح من خلال روابط النظام المصرح بها.</p><div class="mt-3 rounded-lg bg-zinc-50 p-3 text-sm" dir="ltr">{{ config('drive.redirect_uri') ?: route('drive.callback') }}</div>
</x-panel>
@if(DriveConnection::exists())<x-panel title="وجهة التخزين"><form wire:submit="save" class="space-y-3"><flux:input wire:model="sharedDriveId" label="Shared Drive ID (اختياري)" dir="ltr" description="اتركه فارغًا لاستخدام My Drive. يحدد قبل رفع أول ملف، ويحتاج صلاحية كتابة للحساب المتصل." /><flux:button type="submit">حفظ الوجهة</flux:button></form></x-panel>@endif
<x-panel title="متابعة نقل الملفات"><div class="grid gap-3 sm:grid-cols-4">@foreach(['pending'=>'بانتظار النقل','uploading'=>'جارٍ النقل','ready'=>'جاهزة','failed'=>'تحتاج إعادة محاولة'] as $state=>$label)<div class="rounded-lg bg-zinc-50 p-3"><div class="text-sm text-zinc-600">{{ $label }}</div><div class="mt-2 text-xl font-semibold">{{ $this->counts[$state] ?? 0 }}</div></div>@endforeach</div><p class="mt-4 text-sm text-zinc-600">الملفات المؤقتة تُحذف بعد نجاح النقل. الملفات التي فشل نقلها تبقى مؤقتًا لمدة 7 أيام لإعادة المحاولة.</p></x-panel>
<x-panel title="تنظيم الملفات"><p class="text-sm text-zinc-700" dir="ltr">Radiology Center / Branch / Year / Patient / Case</p><p class="mt-3 text-sm text-zinc-600">داخل الحالة: الصور، التقارير، DICOM، الفيديوهات، وطلبات الطبيب. الملفات السابقة المحفوظة على الخادم تستمر في الفتح؛ الملفات الجديدة تستخدم مزود التخزين المحدد.</p></x-panel></div>
