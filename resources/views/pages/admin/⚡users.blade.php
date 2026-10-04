<?php
use App\Enums\{Role,Permission};
use App\Models\{User,Branch,Doctor};
use App\Support\ActivityLogger;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\{Computed,Locked,Title};
use Livewire\Component;
new #[Title('المستخدمون والصلاحيات')] class extends Component {
    #[Locked] public ?int $editingId=null;
    public string $name=''; public string $email=''; public string $phone=''; public string $role='reception'; public string $password=''; public bool $is_active=true;
    public string $branch_id=''; public array $overrides=[];
    public function boot(): void { abort_unless(auth()->user()->isAdmin(),403); }
    #[Computed] public function users() { return User::with('branch')->orderBy('role')->orderBy('name')->get(); }
    public function create(): void { $this->resetForm(); Flux::modal('user-form')->show(); }
    public function edit(int $id): void { $user=User::findOrFail($id); $this->resetForm(); $this->editingId=$id; foreach(['name','email','phone','branch_id'] as $key){$this->{$key}=(string)$user->{$key};} $this->role=$user->role->value; $this->is_active=$user->is_active; foreach($user->permission_overrides ?? [] as $key=>$value){$this->overrides[$key]=$value ? 'allow' : 'deny';} Flux::modal('user-form')->show(); }
    public function save(): void {
        abort_unless(auth()->user()->isAdmin(),403);
        $data=$this->validate(['name'=>['required','string','max:255'],'email'=>['nullable','required_without:phone','email','max:255',Rule::unique('users','email')->ignore($this->editingId)],'phone'=>['nullable','required_without:email','regex:/^\+?\d{8,15}$/',Rule::unique('users','phone')->ignore($this->editingId)],'role'=>['required',Rule::enum(Role::class)],'password'=>[$this->editingId ? 'nullable':'required','string',Password::defaults(),'min:8'],'is_active'=>['boolean'],'branch_id'=>['nullable','required_unless:role,manager',Rule::exists('branches','id')],'overrides'=>['array'],'overrides.*'=>['in:default,allow,deny']]);
        if(array_diff(array_keys($this->overrides),array_column(Permission::cases(),'value'))){abort(422);}
        if($this->editingId===auth()->id() && (! $data['is_active'] || $data['role'] !== 'manager')){$this->addError('role','لا يمكنك إزالة الإدارة أو تعطيل حسابك.');return;}
        DB::transaction(function() use($data){
            $managers=User::where('role','manager')->where('is_active',true)->lockForUpdate()->get();
            $user=$this->editingId ? User::whereKey($this->editingId)->lockForUpdate()->firstOrFail() : new User;
            if($user->exists && $user->isAdmin() && $user->is_active && $managers->count()<=1 && (!$data['is_active'] || $data['role']!=='manager')){throw \Illuminate\Validation\ValidationException::withMessages(['role'=>'يجب الإبقاء على مدير مفعّل واحد على الأقل.']);}
            $values=['name'=>trim($data['name']),'email'=>$data['email'] ? strtolower($data['email']):null,'phone'=>$data['phone'] ?: null,'role'=>$data['role'],'is_active'=>$data['is_active'],'branch_id'=>$data['branch_id'] ?: null,'permission_overrides'=>[]];
            foreach($this->overrides as $key=>$state){if($state!=='default'){$values['permission_overrides'][$key]=$state==='allow';}}
            $before=$user->only(['name','role','branch_id','is_active','permission_overrides']);
            if($data['password']){$values['password']=$data['password'];}
            $user->fill($values)->save();
            if($user->isDoctor()){
                $doctor=Doctor::firstOrCreate(['user_id'=>$user->id],['code'=>'DR-'.$user->id,'title'=>'د.']);
                $doctor->branches()->syncWithoutDetaching([$user->branch_id]);
            }
            ActivityLogger::log($this->editingId ? 'user.updated':'user.created',$user,['before'=>$before,'after'=>$user->only(array_keys($before))]);
        });
        Flux::modal('user-form')->close(); Flux::toast(text:'تم حفظ المستخدم وصلاحياته.'); $this->resetForm();
    }
    private function resetForm(): void { $this->reset('editingId','name','email','phone','role','password','is_active','branch_id','overrides'); $this->resetValidation(); }
}; ?>
<div class="mx-auto w-full max-w-6xl"><x-page-header title="المستخدمون والصلاحيات" subtitle="أربعة أدوار أساسية، مع تحديد الفرع والصلاحيات لكل مستخدم"><x-slot:actions><flux:button variant="primary" icon="plus" wire:click="create">إضافة مستخدم</flux:button></x-slot:actions></x-page-header>
<x-panel :padded="false"><div class="divide-y">@foreach($this->users as $user)<div class="flex flex-wrap items-center gap-4 p-4"><flux:avatar :name="$user->name" /><div class="min-w-0 flex-1"><div class="font-semibold">{{ $user->name }} @if($user->id===auth()->id())<small class="text-zinc-500">أنت</small>@endif</div><div class="mt-1 text-sm text-zinc-600">{{ $user->branch?->name ?? ($user->isAdmin() ? 'جميع الفروع':'يحتاج تحديد الفرع') }} · <span dir="ltr">{{ $user->phone ?? $user->email }}</span></div></div><flux:badge :color="$user->role->color()">{{ $user->role->label() }}</flux:badge><flux:badge :color="$user->is_active ? 'green':'zinc'">{{ $user->is_active ? 'مفعّل':'معطّل' }}</flux:badge><flux:button size="sm" wire:click="edit({{ $user->id }})" icon="pencil-square">تعديل</flux:button></div>@endforeach</div></x-panel>
<flux:modal name="user-form" class="w-full max-w-2xl"><form wire:submit="save" class="space-y-5"><flux:heading size="lg">{{ $editingId ? 'تعديل المستخدم':'إضافة مستخدم' }}</flux:heading><flux:input wire:model="name" label="الاسم" /><div class="grid gap-4 sm:grid-cols-2"><flux:input wire:model="phone" label="الهاتف" type="tel" dir="ltr" /><flux:input wire:model="email" label="البريد الإلكتروني" type="email" dir="ltr" /><flux:select wire:model.live="role" label="الدور">@foreach(Role::cases() as $r)<flux:select.option :value="$r->value">{{ $r->label() }}</flux:select.option>@endforeach</flux:select><flux:select wire:model="branch_id" label="الفرع"><flux:select.option value="">{{ $role==='manager' ? 'جميع الفروع':'اختر الفرع' }}</flux:select.option>@foreach(Branch::active()->get() as $branch)<flux:select.option :value="(string)$branch->id">{{ $branch->name }}</flux:select.option>@endforeach</flux:select></div><flux:input wire:model="password" :label="$editingId ? 'كلمة مرور جديدة (اختياري)':'كلمة المرور'" type="password" viewable autocomplete="new-password" /><flux:switch wire:model="is_active" label="الحساب مفعّل" />
<details class="rounded-lg border p-4"><summary class="cursor-pointer font-medium">الصلاحيات التفصيلية</summary><p class="mt-2 text-sm text-zinc-600">الصلاحيات تعمل داخل حدود الدور والفرع. الحسابات محجوبة عن الطبيب والفني، والاستقبال مقيد بحسابات اليوم. إدارة المستخدمين والفروع والأسعار العامة والحذف للمدير فقط؛ المدير يملك جميع الصلاحيات.</p><div class="mt-4 grid gap-3 sm:grid-cols-2">@foreach(Permission::cases() as $permission)<flux:select wire:model="overrides.{{ $permission->value }}" :label="$permission->label()" :disabled="$role==='manager' || in_array($permission,[Permission::ManageUsers,Permission::DeleteCase,Permission::ManageBranches,Permission::ManageExamTypes],true) || (in_array($role,['doctor','technician'],true) && !in_array($permission,[Permission::ViewCases,Permission::UploadFiles],true))"><flux:select.option value="default">حسب الدور</flux:select.option><flux:select.option value="allow">سماح</flux:select.option><flux:select.option value="deny">منع</flux:select.option></flux:select>@endforeach</div></details><div class="flex justify-end gap-2"><flux:modal.close><flux:button variant="ghost">إلغاء</flux:button></flux:modal.close><flux:button type="submit" variant="primary">حفظ المستخدم</flux:button></div></form></flux:modal></div>
