<?php

use App\Models\Setting;
use App\Models\ViewerLink;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('إعدادات المركز')] class extends Component {
    public function boot(): void { abort_unless(auth()->user()->isAdmin(),403); }
    use WithFileUploads;

    public string $currency = '';
    public int $stale_visit_days = 90;

    public string $center_name = '';

    public string $center_tagline = '';

    public string $contact_phone = '';

    public string $support_whatsapp = '';

    public string $tutorial_url = '';

    public int $share_link_days = 30;

    public $logo = null;

    public ?int $editingLinkId = null;

    /** @var array{name: string, platform: string, url: string} */
    public array $link = ['name' => '', 'platform' => 'windows', 'url' => ''];

    public function mount(): void
    {
        foreach (['center_name', 'center_tagline', 'contact_phone', 'support_whatsapp', 'tutorial_url'] as $key) {
            $this->{$key} = (string) Setting::get($key);
        }

        $this->share_link_days = (int) Setting::get('share_link_days');
        $this->currency = (string) Setting::get('currency');
        $this->stale_visit_days = (int) Setting::get('stale_visit_days',90);
    }

    #[Computed]
    public function viewerLinks()
    {
        return ViewerLink::orderBy('platform')->orderBy('sort')->get();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'currency' => ['nullable','in:EGP,SAR,USD,EUR,AED'],
            'stale_visit_days' => ['required','integer','min:1','max:3650'],
            'center_name' => ['required', 'string', 'max:100'],
            'center_tagline' => ['nullable', 'string', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'support_whatsapp' => ['nullable', 'regex:/^\+?\d{8,15}$/'],
            'tutorial_url' => ['nullable', 'url', 'max:500'],
            'share_link_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
        ]);

        unset($validated['logo']);

        if ($this->logo) {
            $old = Setting::get('logo_path');
            $validated['logo_path'] = $this->logo->store('branding', 'public');

            if ($old) {
                Storage::disk('public')->delete($old);
            }

            $this->logo = null;
        }

        if (Setting::get('currency') && Setting::get('currency') !== $validated['currency'] && (\App\Models\ExamType::whereNotNull('base_price_minor')->exists() || \Illuminate\Support\Facades\DB::table('branch_exam_type')->whereNotNull('base_price_minor')->exists())) { $this->addError('currency','تغيير العملة بعد التسعير يحتاج مراجعة وتحويل أسعار الفحوصات أولًا.'); return; }
        Setting::put($validated);
        \App\Support\ActivityLogger::log('settings.updated', null, ['fields'=>array_keys($validated)]);

        Flux::toast(variant: 'success', text: 'تم حفظ إعدادات المركز.');
    }

    public function removeLogo(): void
    {
        if ($path = Setting::get('logo_path')) {
            Storage::disk('public')->delete($path);
        }

        Setting::put(['logo_path' => null]);
        Flux::toast(text: 'تم حذف الشعار.');
    }

    public function createLink(): void
    {
        $this->reset('editingLinkId', 'link');
        $this->resetValidation();
        Flux::modal('link-form')->show();
    }

    public function editLink(int $id): void
    {
        $link = ViewerLink::findOrFail($id);
        $this->resetValidation();
        $this->editingLinkId = $link->id;
        $this->link = $link->only('name', 'platform', 'url');
        Flux::modal('link-form')->show();
    }

    public function saveLink(): void
    {
        $this->validate([
            'link.name' => ['required', 'string', 'max:100'],
            'link.platform' => ['required', Rule::in(array_keys(ViewerLink::PLATFORMS))],
            'link.url' => ['required', 'url', 'max:2048'],
        ]);

        ViewerLink::updateOrCreate(
            ['id' => $this->editingLinkId],
            $this->link + ['sort' => $this->editingLinkId ? ViewerLink::find($this->editingLinkId)->sort : (int) ViewerLink::max('sort') + 1],
        );

        Flux::modal('link-form')->close();
        Flux::toast(variant: 'success', text: 'تم حفظ الرابط.');
        $this->reset('editingLinkId', 'link');
    }

    public function deleteLink(int $id): void
    {
        ViewerLink::whereKey($id)->delete();
        Flux::toast(text: 'تم حذف الرابط.');
    }
}; ?>

<div class="mx-auto w-full max-w-4xl">
    <x-page-header eyebrow="الإدارة" title="إعدادات المركز" subtitle="الاسم والشعار وبيانات التواصل وروابط برامج DICOM التي تظهر للأطباء" />

    <form wire:submit="save" class="space-y-6">
        <x-panel title="هوية المركز" icon="building-office-2">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="center_name" label="اسم المركز" required />
                <flux:input wire:model="center_tagline" label="وصف مختصر" />
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-4">
                @php($logoUrl = Setting::logoUrl())
                <div class="flex size-20 items-center justify-center overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50">
                    @if ($logo)
                        <img src="{{ $logo->temporaryUrl() }}" class="size-full object-contain p-1" alt="">
                    @elseif ($logoUrl)
                        <img src="{{ $logoUrl }}" class="size-full object-contain p-1" alt="">
                    @else
                        <img src="{{ asset('images/brand/logo.png') }}" class="size-full object-contain p-1" alt="">
                    @endif
                </div>
                <div class="space-y-2">
                    <flux:input type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp" label="الشعار" description="PNG أو JPG أو WEBP حتى 1 ميجابايت" />
                    @if ($logoUrl && ! $logo)
                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeLogo" wire:confirm="حذف الشعار؟">حذف الشعار</flux:button>
                    @endif
                </div>
            </div>
        </x-panel>

        <x-panel title="إعدادات التشغيل"><div class="grid gap-4 sm:grid-cols-2"><flux:select wire:model="currency" label="عملة الأسعار والحسابات"><flux:select.option value="">حدد العملة قبل التسعير</flux:select.option><flux:select.option value="EGP">جنيه مصري (EGP)</flux:select.option><flux:select.option value="SAR">ريال سعودي (SAR)</flux:select.option><flux:select.option value="USD">دولار (USD)</flux:select.option><flux:select.option value="EUR">يورو (EUR)</flux:select.option><flux:select.option value="AED">درهم إماراتي (AED)</flux:select.option></flux:select><flux:input wire:model="stale_visit_days" label="مدة تأخر متابعة الطبيب (بالأيام)" type="number" min="1" /></div></x-panel>
        <x-panel title="التواصل والمشاركة" icon="phone">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="contact_phone" label="رقم التواصل" dir="ltr" description="يظهر في صفحة الطبيب ورابط المشاركة" />
                <flux:input wire:model="support_whatsapp" label="واتساب الدعم الفني" dir="ltr" placeholder="01xxxxxxxxx" />
                <flux:input wire:model="tutorial_url" label="رابط شرح طريقة الاستخدام" dir="ltr" placeholder="https://youtube.com/..." description="فيديو أو صفحة تشرح للطبيب طريقة استخدام صفحته" />
                <flux:input wire:model="share_link_days" type="number" min="0" label="مدة صلاحية رابط المشاركة (بالأيام)" description="0 = الرابط لا ينتهي. تطبَّق على الروابط الجديدة" />
            </div>
        </x-panel>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">حفظ الإعدادات</flux:button>
        </div>
    </form>

    <x-panel title="برامج عرض DICOM" subtitle="تظهر في صفحة الطبيب لتحميل البرنامج المناسب" icon="computer-desktop" :padded="false" class="mt-6">
        <x-slot:actions>
            <flux:button size="sm" icon="plus" wire:click="createLink">إضافة برنامج</flux:button>
        </x-slot:actions>

        <ul class="divide-y divide-zinc-100">
            @forelse ($this->viewerLinks as $viewer)
                <li class="flex flex-wrap items-center gap-3 px-5 py-3" wire:key="viewer-{{ $viewer->id }}">
                    <flux:icon :name="$viewer->platform === 'mobile' ? 'device-phone-mobile' : 'computer-desktop'" class="text-zinc-400" />
                    <div class="min-w-0 flex-1">
                        <div class="font-medium text-zinc-900" dir="ltr" style="text-align: right">{{ $viewer->name }}</div>
                        <div class="truncate text-xs text-zinc-500" dir="ltr" style="text-align: right">{{ $viewer->url }}</div>
                    </div>
                    <flux:badge size="sm" color="zinc">{{ ViewerLink::PLATFORMS[$viewer->platform] ?? $viewer->platform }}</flux:badge>
                    <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="editLink({{ $viewer->id }})" aria-label="تعديل" />
                    <flux:button size="sm" variant="ghost" icon="trash" class="text-red-600!" wire:click="deleteLink({{ $viewer->id }})" wire:confirm="حذف {{ $viewer->name }}؟" aria-label="حذف" />
                </li>
            @empty
                <li class="px-5 py-6 text-sm text-zinc-500">لا توجد روابط.</li>
            @endforelse
        </ul>
    </x-panel>

    <flux:modal name="link-form" class="w-full max-w-md">
        <form wire:submit="saveLink" class="space-y-5">
            <flux:heading size="lg">{{ $editingLinkId ? 'تعديل البرنامج' : 'إضافة برنامج عرض' }}</flux:heading>
            <flux:input wire:model="link.name" label="اسم البرنامج" dir="ltr" required />
            <flux:select wire:model="link.platform" label="المنصة">
                @foreach (ViewerLink::PLATFORMS as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="link.url" label="رابط التحميل" dir="ltr" type="url" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">إلغاء</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">حفظ</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
