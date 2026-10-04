<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('بيانات الطبيب')] class extends Component {
    public function boot(): void { abort_unless(auth()->user()->isAdmin(),403); }
    public const TITLES = ['د.', 'أ.د.', 'أ.م.د.'];

    public ?Doctor $record = null;

    public string $name = '';

    public string $title = 'د.';

    public string $code = '';

    public string $specialty = '';

    public string $phone = '';

    public string $whatsapp = '';

    public string $email = '';

    public string $password = '';

    public bool $is_active = true;

    /** @var list<string> */
    public array $branch_ids = [];

    public string $notes = '';

    public function mount(?Doctor $doctor = null): void
    {
        if ($doctor?->exists) {
            $this->record = $doctor->load(['user', 'branches']);
            $this->fill([
                'name' => $doctor->user->name,
                'title' => $doctor->title,
                'code' => $doctor->code,
                'specialty' => (string) $doctor->specialty,
                'phone' => (string) $doctor->user->phone,
                'whatsapp' => (string) $doctor->whatsapp,
                'email' => (string) $doctor->user->email,
                'is_active' => $doctor->user->is_active,
                'branch_ids' => $doctor->branches->pluck('id')->map(fn ($id) => (string) $id)->all(),
                'notes' => (string) $doctor->notes,
            ]);
        }
    }

    public function save(): void
    {
        $userId = $this->record?->user_id;

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['required', Rule::in(self::TITLES)],
            'code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('doctors', 'code')->ignore($this->record?->id)],
            'specialty' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'regex:/^\+?\d{8,15}$/', Rule::unique('users', 'phone')->ignore($userId)],
            'whatsapp' => ['nullable', 'regex:/^\+?\d{8,15}$/'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => [$this->record ? 'nullable' : 'required', 'string', Password::defaults(), 'min:8'],
            'is_active' => ['boolean'],
            'branch_ids' => ['array', 'min:1'],
            'branch_ids.*' => [Rule::exists('branches', 'id')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['branch_ids.min' => 'اختر فرعًا واحدًا على الأقل.']);

        DB::transaction(function () use ($validated) {
            $userData = [
                'name' => trim($validated['name']),
                'phone' => $validated['phone'],
                'email' => $validated['email'] ? strtolower($validated['email']) : null,
                'is_active' => $validated['is_active'],
                'role' => Role::Doctor,
            ];

            if ($validated['password']) {
                $userData['password'] = $validated['password'];
            }

            $doctorData = [
                'title' => $validated['title'],
                'code' => trim($validated['code']),
                'specialty' => $validated['specialty'] ?: null,
                'whatsapp' => $validated['whatsapp'] ?: null,
                'notes' => $validated['notes'] ?: null,
            ];

            if ($this->record) {
                $this->record->user->update($userData);
                $this->record->update($doctorData);
                $doctor = $this->record;
            } else {
                $user = User::create($userData);
                $doctor = Doctor::create($doctorData + ['user_id' => $user->id]);
            }

            $doctor->branches()->sync($validated['branch_ids']);
            \App\Support\ActivityLogger::log($this->record ? 'doctor.updated' : 'doctor.created', $doctor, ['name'=>$userData['name'],'branches'=>$validated['branch_ids'],'is_active'=>$validated['is_active']]);
        });

        Flux::toast(variant: 'success', text: $this->record ? 'تم حفظ بيانات الطبيب.' : 'تم إنشاء حساب الطبيب.');
        $this->redirectRoute('doctors.index', navigate: true);
    }
}; ?>

<div class="mx-auto w-full max-w-3xl">
    <x-page-header
        eyebrow="الإدارة"
        :title="$record ? 'تعديل '.$record->display_name : 'إضافة طبيب'"
        subtitle="يدخل الطبيب برقم الهاتف أو البريد الإلكتروني وكلمة المرور ويرى حالاته فقط"
        :back="route('doctors.index')"
    />

    <form wire:submit="save" class="space-y-6">
        <x-panel title="بيانات الطبيب" icon="academic-cap">
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:select wire:model="title" label="اللقب">
                    @foreach ($this::TITLES as $option)
                        <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="name" label="الاسم" required class="sm:col-span-2" />
                <flux:input wire:model="code" label="كود الطبيب" required dir="ltr" placeholder="ma002" description="يظهر في جدول الحالات (Doc. Code)" />
                <flux:input wire:model="specialty" label="التخصص" class="sm:col-span-2" />
            </div>

            <flux:checkbox.group wire:model="branch_ids" label="الفروع" class="mt-5">
                <div class="flex flex-wrap gap-x-6 gap-y-2">
                    @foreach (Branch::orderBy('name')->get() as $branch)
                        <flux:checkbox :value="(string) $branch->id" :label="$branch->name" />
                    @endforeach
                </div>
            </flux:checkbox.group>
        </x-panel>

        <x-panel title="بيانات الدخول والتواصل" icon="key">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="phone" label="رقم الهاتف (للدخول)" type="tel" dir="ltr" required placeholder="01xxxxxxxxx" />
                <flux:input wire:model="whatsapp" label="رقم واتساب" type="tel" dir="ltr" placeholder="اتركه فارغًا لاستخدام رقم الهاتف" />
                <flux:input wire:model="email" label="البريد الإلكتروني (اختياري)" type="email" dir="ltr" />
                <flux:input wire:model="password" :label="$record ? 'كلمة مرور جديدة' : 'كلمة المرور'" type="password" dir="ltr" viewable :required="! $record" :description="$record ? 'اتركها فارغة للإبقاء على كلمة المرور الحالية' : 'أرسلها للطبيب مع رقم الهاتف'" autocomplete="new-password" />
                <flux:switch wire:model="is_active" label="الحساب مفعّل" description="الطبيب المعطّل لا يستطيع الدخول ولا يظهر عند إنشاء حالة جديدة" class="sm:col-span-2" />
                <flux:textarea wire:model="notes" label="ملاحظات" rows="2" class="sm:col-span-2" />
            </div>
        </x-panel>

        <div class="flex items-center justify-end gap-3">
            <flux:button :href="route('doctors.index')" wire:navigate variant="ghost">إلغاء</flux:button>
            <flux:button type="submit" variant="primary">{{ $record ? 'حفظ' : 'إنشاء الحساب' }}</flux:button>
        </div>
    </form>
</div>
