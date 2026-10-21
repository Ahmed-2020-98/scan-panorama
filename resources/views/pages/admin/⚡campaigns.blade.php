<?php
use App\Models\{Campaign,CampaignRecipient};
use App\Services\CampaignAudience;
use App\Support\{AccessScope,ActivityLogger};
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\{Computed,Title};
use Livewire\Component;
use Livewire\WithFileUploads;
new #[Title('رسائل العروض')] class extends Component {
    use WithFileUploads;
    public $image = null;
    public string $audience='patients'; public string $branch=''; public string $since=''; public string $title=''; public string $message='';
    public const TEMPLATES = [
        'offer' => ['عرض خصم', "أهلًا {الاسم} 👋\nيسعدنا نبلغك بعرض خاص في مركز الأشعة: خصم __% على __ حتى __.\nللحجز والاستفسار رد على الرسالة دي."],
        'eid' => ['تهنئة العيد', "كل سنة وانت طيب يا {الاسم} 🌙\nأسرة المركز بتتمنى لك ولأسرتك عيد سعيد، وبمناسبة العيد عندنا عرض خاص: __."],
        'mawlid' => ['المولد النبوي', "كل عام وأنتم بخير يا {الاسم} بمناسبة المولد النبوي الشريف 🌟\nبهذه المناسبة: __."],
        'ramadan' => ['رمضان', "رمضان كريم يا {الاسم} 🌙\nمواعيد المركز في رمضان: __.\nوعرض الشهر الكريم: __."],
        'doctors' => ['للأطباء', "د. {الاسم} المحترم،\nتحية طيبة من مركز الأشعة. نود إبلاغكم بـ __.\nشاكرين تعاونكم الدائم."],
    ];
    public function mount(): void { $this->useTemplate('offer'); }
    public function useTemplate(string $key): void { abort_unless(isset(self::TEMPLATES[$key]),404); [$this->title,$this->message]=self::TEMPLATES[$key]; if($key==='doctors'){ $this->audience='doctors'; } }
    #[Computed] public function count(): int { return CampaignAudience::for(auth()->user(),$this->audience,$this->branch ? (int)$this->branch : null,$this->audience==='patients' && $this->since ? $this->since : null)->count(); }
    #[Computed] public function campaigns() { return Campaign::withCount(['recipients','recipients as sent_count'=>fn($q)=>$q->whereNotNull('sent_at')])->with('creator')->when(! auth()->user()->isAdmin(),fn($q)=>$q->where(fn($w)=>$w->where('created_by',auth()->id())->orWhereIn('branch_id',AccessScope::branchIds(auth()->user()))))->latest()->limit(30)->get(); }
    public function start() {
        $data=$this->validate(['audience'=>['required',Rule::in(array_keys(Campaign::AUDIENCES))],'title'=>['required','string','max:150'],'message'=>['required','string','max:2000','not_regex:/__/'],'branch'=>['nullable','integer'],'since'=>['nullable','date'],'image'=>['nullable','image','mimes:png,jpg,jpeg,webp','max:5120']],['message.not_regex'=>'كمّل الأماكن الفاضية (__) في الرسالة قبل الإرسال.']);
        if($this->branch){ abort_unless(AccessScope::branches(auth()->user())->whereKey($this->branch)->exists(),403); }
        $people=CampaignAudience::for(auth()->user(),$this->audience,$this->branch ? (int)$this->branch : null,$this->audience==='patients' && $this->since ? $this->since : null);
        if($people->isEmpty()){ $this->addError('audience','لا يوجد أرقام موبايل صحيحة لهذا الاختيار.'); return; }
        $campaign=DB::transaction(function() use($data,$people){
            $c=Campaign::create(['audience'=>$data['audience'],'title'=>trim($data['title']),'message'=>trim($data['message']),'image_path'=>$this->image?->store('campaigns','public'),'branch_id'=>$this->branch ?: (auth()->user()->isAdmin() ? null : auth()->user()->branch_id),'created_by'=>auth()->id()]);
            foreach($people->chunk(200) as $chunk){ CampaignRecipient::insert($chunk->map(fn($p)=>['campaign_id'=>$c->id,'name'=>mb_substr($p['name'],0,255),'phone'=>$p['phone']])->all()); }
            ActivityLogger::log('campaign.created',$c,['audience'=>$c->audience,'recipients'=>$people->count()]);
            return $c;
        });
        return $this->redirectRoute('campaigns.show',$campaign,navigate:true);
    }
}; ?>
<div class="mx-auto w-full max-w-6xl space-y-5">
    <x-page-header eyebrow="الدعاية" title="رسائل العروض" subtitle="اكتب الرسالة مرة واحدة وابعتها لكل المرضى أو كل الأطباء على واتساب، واحد ورا التاني من موبايلك" />

    <div class="grid gap-5 lg:grid-cols-5">
        <x-panel title="رسالة جديدة" icon="megaphone" class="lg:col-span-3">
            <form wire:submit="start" class="space-y-4">
                <div class="grid grid-cols-2 gap-2">
                    @foreach(Campaign::AUDIENCES as $key=>$label)
                        <button type="button" wire:click="$set('audience','{{ $key }}')" class="flex min-h-14 items-center justify-center gap-2 rounded-xl border-2 text-sm font-semibold transition {{ $audience===$key ? 'border-brand-600 bg-brand-50 text-brand-800' : 'border-zinc-200 text-zinc-600 hover:border-zinc-300' }}">
                            <flux:icon :name="$key==='doctors' ? 'academic-cap' : 'users'" variant="mini" /> كل {{ $label }}
                        </button>
                    @endforeach
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:select wire:model.live="branch" label="الفرع"><flux:select.option value="">{{ auth()->user()->isAdmin() ? 'كل الفروع' : 'فروعي' }}</flux:select.option>@foreach(AccessScope::branches(auth()->user())->get() as $b)<flux:select.option :value="(string)$b->id">{{ $b->name }}</flux:select.option>@endforeach</flux:select>
                    @if($audience==='patients')<flux:input wire:model.live="since" type="date" label="مرضى زاروا المركز من تاريخ (اختياري)" />@endif
                </div>
                <div>
                    <div class="mb-2 text-sm font-medium text-zinc-800">قوالب جاهزة</div>
                    <div class="flex flex-wrap gap-2">@foreach(self::TEMPLATES as $key=>[$label])<flux:button size="sm" wire:click="useTemplate('{{ $key }}')">{{ $label }}</flux:button>@endforeach</div>
                </div>
                <flux:input wire:model="title" label="اسم الحملة (للمتابعة فقط)" />
                <flux:textarea wire:model.live.debounce.400ms="message" label="نص الرسالة" rows="7" description="{الاسم} يتبدل باسم كل شخص. كمّل الأماكن (__) قبل الإرسال." />
                <div>
                    <div class="mb-1 text-sm font-medium text-zinc-800">صورة مع الرسالة (اختياري)</div>
                    <div class="flex flex-wrap items-center gap-3">
                        <label class="inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-lg border border-zinc-300 bg-white px-3 text-sm hover:bg-zinc-50"><flux:icon.photo variant="mini" class="text-brand-600" /> {{ $image ? 'تغيير الصورة' : 'اختر صورة' }}<input type="file" wire:model="image" accept="image/png,image/jpeg,image/webp" class="sr-only"></label>
                        @if($image)<flux:button size="sm" variant="ghost" wire:click="$set('image', null)">إزالة</flux:button>@endif
                        <span wire:loading wire:target="image" class="text-xs text-zinc-500">جارٍ التحميل…</span>
                    </div>
                    <flux:error name="image" />
                </div>
                <flux:error name="audience" />
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-zinc-50 p-3">
                    <span class="text-sm">هتتبعت لـ <strong class="ltr-nums text-brand-700">{{ $this->count }}</strong> رقم موبايل</span>
                    <flux:button type="submit" variant="primary" icon="paper-airplane">تجهيز قائمة الإرسال</flux:button>
                </div>
            </form>
        </x-panel>

        <div class="space-y-5 lg:col-span-2">
            <x-panel title="معاينة" icon="eye">
                <div class="rounded-2xl bg-[#e7ddd3] p-4">
                    @if($image && ! $errors->has('image') && $image->isPreviewable())<div class="mb-2 ms-auto max-w-[85%] rounded-xl rounded-se-none bg-[#d9fdd3] p-1 shadow-sm"><img src="{{ $image->temporaryUrl() }}" alt="" class="max-h-60 w-full rounded-lg object-cover"></div>@endif
                    <div class="ms-auto max-w-[85%] whitespace-pre-line rounded-xl rounded-se-none bg-[#d9fdd3] p-3 text-sm leading-relaxed text-zinc-800 shadow-sm">{{ str_replace('{الاسم}', $audience==='doctors' ? 'أحمد' : 'محمد', $message) }}</div>
                </div>
            </x-panel>
            <x-panel title="أرقام على موبايل المركز" icon="device-phone-mobile">
                <p class="text-sm text-zinc-600">نزّل الأرقام واحفظها في جهات اتصال موبايل المركز، علشان المرضى والأطباء يشوفوا الحالة (Status) بتاعتك على واتساب.</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <flux:button icon="arrow-down-tray" :href="route('contacts.export', ['audience' => 'patients', 'branch' => $branch ?: null])">أرقام المرضى</flux:button>
                    <flux:button icon="arrow-down-tray" :href="route('contacts.export', ['audience' => 'doctors', 'branch' => $branch ?: null])">أرقام الأطباء</flux:button>
                </div>
                <p class="mt-2 text-xs text-zinc-500">على الموبايل: افتح الملف ← استيراد جهات الاتصال.</p>
            </x-panel>
        </div>
    </div>

    <x-panel title="الحملات السابقة" :padded="false">
        <div class="divide-y">
        @forelse($this->campaigns as $c)
            @php($pct = $c->recipients_count ? (int) floor($c->sent_count * 100 / $c->recipients_count) : 0)
            <a href="{{ route('campaigns.show',$c) }}" wire:navigate class="flex flex-wrap items-center gap-4 p-4 hover:bg-zinc-50" wire:key="c-{{ $c->id }}">
                <div class="min-w-0 flex-1"><div class="font-medium">{{ $c->title }}</div><div class="text-sm text-zinc-500">{{ Campaign::AUDIENCES[$c->audience] }} · {{ $c->created_at->format('d/m/Y') }} · {{ $c->creator?->name }}</div></div>
                <div class="w-40"><div class="h-2 rounded-full bg-zinc-100"><div class="h-2 rounded-full bg-brand-600" style="width: {{ $pct }}%"></div></div><div class="mt-1 text-xs text-zinc-500 ltr-nums">{{ $c->sent_count }} / {{ $c->recipients_count }}</div></div>
            </a>
        @empty<p class="p-4 text-sm text-zinc-500">لم تُرسل حملات بعد.</p>@endforelse
        </div>
    </x-panel>
</div>
