<?php
use App\Enums\{Role,Permission};
use App\Models\{User,Branch,Doctor};
use App\Support\{ActivityLogger,Money};
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\{Computed,Locked,Title};
use Livewire\Component;
new #[Title('المستخدمون والصلاحيات')] class extends Component {
    #[Locked] public ?int $editingId=null;
    public string $name=''; public string $email=''; public string $phone=''; public string $role='reception'; public string $password=''; public bool $is_active=true;
    public string $branch_id=''; public string $shift_rate=''; public array $overrides=[];
    public function boot(): void { abort_unless(auth()->user()->hasPermission(Permission::ManageUsers),403); }
    #[Computed] public function users() { return User::with('branch')->orderBy('role')->orderBy('name')->get(); }
    /** Roles this actor may assign: only the admin creates or edits admins. */
    #[Computed] public function roles(): array { return array_values(array_filter(Role::cases(), fn (Role $r) => auth()->user()->isAdmin() || $r !== Role::Admin)); }
    private function guardTarget(User $user): void { abort_if($user->isAdmin() && ! auth()->user()->isAdmin(), 403); }
    public function create(): void { $this->resetForm(); Flux::modal('user-form')->show(); }
    public function edit(int $id): void {
        $user=User::findOrFail($id); $this->guardTarget($user); $this->resetForm(); $this->editingId=$id;
        foreach(['name','email','phone','branch_id'] as $key){$this->{$key}=(string)$user->{$key};}
        $this->role=$user->role->value; $this->is_active=$user->is_active; $this->shift_rate=Money::decimal($user->shift_rate_minor);
        foreach($user->permission_overrides ?? [] as $key=>$value){$this->overrides[$key]=$value ? 'allow' : 'deny';}
        Flux::modal('user-form')->show();
    }
    public function save(): void {
        $data=$this->validate(['name'=>['required','string','max:255'],'email'=>['nullable','required_without:phone','email','max:255',Rule::unique('users','email')->ignore($this->editingId)],'phone'=>['nullable','required_without:email','regex:/^\+?\d{8,15}$/',Rule::unique('users','phone')->ignore($this->editingId)],'role'=>['required',Rule::enum(Role::class)],'password'=>[$this->editingId ? 'nullable':'required','string',Password::defaults(),'min:8'],'is_active'=>['boolean'],'branch_id'=>['nullable','required_unless:role,manager',Rule::exists('branches','id')],'shift_rate'=>['nullable','numeric','min:0','max:1000000'],'overrides'=>['array'],'overrides.*'=>['in:default,allow,deny']]);
        if(array_diff(array_keys($this->overrides),array_column(Permission::cases(),'value'))){abort(422);}
        abort_if($data['role']==='manager' && ! auth()->user()->isAdmin(),403);
        if($this->editingId===auth()->id() && (! $data['is_active'] || $data['role'] !== auth()->user()->role->value)){$this->addError('role','لا يمكنك تغيير دورك أو تعطيل حسابك.');return;}
        DB::transaction(function() use($data){
            $managers=User::where('role','manager')->where('is_active',true)->lockForUpdate()->get();
            $user=$this->editingId ? User::whereKey($this->editingId)->lockForUpdate()->firstOrFail() : new User;
            if($user->exists){ $this->guardTarget($user); }
            if($user->exists && $user->isAdmin() && $user->is_active && $managers->count()<=1 && (!$data['is_active'] || $data['role']!=='manager')){throw \Illuminate\Validation\ValidationException::withMessages(['role'=>'يجب الإبقاء على مدير مفعّل واحد على الأقل.']);}
            $values=['name'=>trim($data['name']),'email'=>$data['email'] ? strtolower($data['email']):null,'phone'=>$data['phone'] ?: null,'role'=>$data['role'],'is_active'=>$data['is_active'],'branch_id'=>$data['branch_id'] ?: null,'shift_rate_minor'=>$data['shift_rate']!=='' && $data['shift_rate']!==null ? Money::minor((string)$data['shift_rate']) : null,'permission_overrides'=>[]];
            foreach($this->overrides as $key=>$state){if($state!=='default'){$values['permission_overrides'][$key]=$state==='allow';}}
            $before=$user->only(['name','role','branch_id','is_active','permission_overrides','shift_rate_minor']);
            if($data['password']){$values['password']=$data['password'];}
            $user->fill($values)->save();
            if($user->isDoctor()){
                $doctor=Doctor::firstOrCreate(['user_id'=>$user->id],['code'=>'DR-'.$user->id,'title'=>'د.']);
                $doctor->branches()->syncWithoutDetaching([$user->branch_id]);
            }
            ActivityLogger::log($this->editingId ? 'user.updated':'user.created',$user,['before'=>$before,'after'=>$user->only(array_keys($before))]);
        });
        Flux::modal('user-form')->close(); Flux::toast(variant:'success',text:'تم حفظ المستخدم وصلاحياته.'); $this->resetForm(); unset($this->users);
    }
    public function delete(int $id): void {
        $user=User::findOrFail($id); $this->guardTarget($user);
        if($user->id===auth()->id()){ Flux::toast(variant:'danger',text:'لا يمكنك حذف حسابك.'); return; }
        if($user->isDoctor()){ Flux::toast(variant:'danger',text:'احذف الطبيب من صفحة «الأطباء» ليمكن استرجاعه خلال 30 يومًا.'); return; }
        if($user->isAdmin() && User::where('role','manager')->where('is_active',true)->count()<=1){ Flux::toast(variant:'danger',text:'يجب الإبقاء على مدير مفعّل واحد على الأقل.'); return; }
        DB::transaction(function() use($user){
            ActivityLogger::log('user.deleted',$user,['name'=>$user->name,'role'=>$user->role->value]);
            // Free the login identifiers so they can be reused; history keeps the name.
            $user->forceFill(['is_active'=>false,'email'=>null,'phone'=>null,'remember_token'=>null])->save();
            $user->delete();
            DB::table('sessions')->where('user_id',$user->id)->delete();
        });
        unset($this->users); Flux::toast(variant:'success',text:'تم حذف المستخدم '.$user->name.'. سجله السابق يبقى محفوظًا في الحالات والحسابات.');
    }
    private function resetForm(): void { $this->reset('editingId','name','email','phone','role','password','is_active','branch_id','shift_rate','overrides'); $this->resetValidation(); }
}; ?>
<div class="mx-auto w-full max-w-6xl">
<x-page-header title="المستخدمون والصلاحيات" subtitle="الأدوار، الفرع، أجر الشيفت، وصلاحيات كل مستخدم"><x-slot:actions><flux:button variant="primary" icon="plus" wire:click="create">إضافة مستخدم</flux:button></x-slot:actions></x-page-header>
<x-panel :padded="false"><div class="divide-y">@foreach($this->users as $user)
<div class="flex flex-wrap items-center gap-3 p-4" wire:key="u-{{ $user->id }}">
    <flux:avatar :name="$user->name" />
    <div class="min-w-0 flex-1">
        <div class="font-semibold">{{ $user->name }} @if($user->id===auth()->id())<small class="text-zinc-500">أنت</small>@endif</div>
        <div class="mt-1 text-sm text-zinc-600">{{ $user->branch?->name ?? ($user->isAdmin() ? 'جميع الفروع':'يحتاج تحديد الفرع') }} · <span dir="ltr">{{ $user->phone ?? $user->email }}</span>@if($user->shift_rate_minor) · الشيفت <span class="ltr-nums">{{ Money::display($user->shift_rate_minor) }}</span>@endif</div>
    </div>
    <flux:badge :color="$user->role->color()">{{ $user->role->label() }}</flux:badge>
    <flux:badge :color="$user->is_active ? 'green':'zinc'">{{ $user->is_active ? 'مفعّل':'معطّل' }}</flux:badge>
    @if(auth()->user()->isAdmin() || ! $user->isAdmin())
        <flux:button size="sm" wire:click="edit({{ $user->id }})" icon="pencil-square">تعديل</flux:button>
        @if($user->id!==auth()->id() && ! $user->isDoctor())
            <flux:button size="sm" variant="ghost" icon="trash" class="text-red-600!" wire:click="delete({{ $user->id }})" wire:confirm="حذف المستخدم {{ $user->name }}؟ لن يستطيع الدخول، ويبقى اسمه في سجل الحالات والحسابات.">حذف</flux:button>
        @endif
    @endif
</div>@endforeach</div></x-panel>

<flux:modal name="user-form" class="w-full max-w-3xl"><form wire:submit="save" class="space-y-5">
    <flux:heading size="lg">{{ $editingId ? 'تعديل المستخدم':'إضافة مستخدم' }}</flux:heading>
    <flux:input wire:model="name" label="الاسم" />
    <div class="grid gap-4 sm:grid-cols-2">
        <flux:input wire:model="phone" label="الهاتف" type="tel" dir="ltr" />
        <flux:input wire:model="email" label="البريد الإلكتروني" type="email" dir="ltr" />
        <flux:select wire:model.live="role" label="الدور">@foreach($this->roles as $r)<flux:select.option :value="$r->value">{{ $r->label() }}</flux:select.option>@endforeach</flux:select>
        <flux:select wire:model="branch_id" label="الفرع"><flux:select.option value="">{{ $role==='manager' ? 'جميع الفروع':'اختر الفرع' }}</flux:select.option>@foreach(Branch::active()->get() as $branch)<flux:select.option :value="(string)$branch->id">{{ $branch->name }}</flux:select.option>@endforeach</flux:select>
        <flux:input wire:model="shift_rate" label="أجر الشيفت" inputmode="decimal" description="يُستخدم لحساب الراتب من «المصروفات والرواتب»" />
        <flux:input wire:model="password" :label="$editingId ? 'كلمة مرور جديدة (اختياري)':'كلمة المرور'" type="password" viewable autocomplete="new-password" />
    </div>
    <flux:switch wire:model="is_active" label="الحساب مفعّل" />
    @php($limited = in_array($role,['doctor','technician'],true))
    <details class="rounded-lg border p-4" @if($editingId) open @endif>
        <summary class="cursor-pointer font-medium">الصلاحيات</summary>
        <p class="mt-2 text-sm text-zinc-600">
            @if($role==='manager') المدير يملك جميع الصلاحيات.
            @elseif($limited) الطبيب والفني يعرضان الحالات ويرفعان الملفات فقط.
            @else «حسب الدور» يعطي الصلاحيات المعتادة لهذا الدور، ويمكنك السماح بأي صلاحية أو منعها. البيانات تبقى داخل فرع المستخدم.
            @endif
        </p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            @foreach(Permission::grantable() as $permission)
                @php($locked = $role==='manager' || ($limited && ! in_array($permission,[Permission::ViewCases,Permission::UploadFiles],true)))
                <flux:select wire:model="overrides.{{ $permission->value }}" :label="$permission->label().(! $locked && in_array($permission->value, Role::from($role)->defaultPermissions(), true) ? ' ✓' : '')" :disabled="$locked">
                    <flux:select.option value="default">حسب الدور</flux:select.option><flux:select.option value="allow">سماح</flux:select.option><flux:select.option value="deny">منع</flux:select.option>
                </flux:select>
            @endforeach
        </div>
        @if(! $limited && $role!=='manager')<p class="mt-3 text-xs text-zinc-500">✓ = مسموح افتراضيًا لهذا الدور.</p>@endif
    </details>
    <div class="flex justify-end gap-2"><flux:modal.close><flux:button variant="ghost">إلغاء</flux:button></flux:modal.close><flux:button type="submit" variant="primary">حفظ المستخدم</flux:button></div>
</form></flux:modal>
</div>
